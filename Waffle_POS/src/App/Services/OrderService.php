<?php
namespace App\Services;

use PDOException;
use App\Core\Database;
use App\Core\DatabaseException;
use App\Core\OrderException;
use App\Algorithms\Searching;
use App\Payments\PaymentFactory;

// The sales logic: checkout, void, receipt lookup and the cashier's daily tally
class OrderService {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::connect();
    }

    // Saves a sale: the order, its items and its discount.
    // Both happen inside one transaction: all of it succeeds, or none of it does.
    public function checkout(array $items, $customer, $orderType, $paymentName, $reference, $cashierId, $discountId = 1, $discountNumber = '', $amountReceived = null) {
        // 1. clean and check the input
        $selected = [];
        foreach ($items as $productId => $qty) {
            $qty = (int)$qty;
            if ($qty > 0) {
                $selected[(int)$productId] = $qty;
            }
        }
        if (empty($selected)) {
            throw new OrderException("Please enter a quantity for at least one item before checking out.", "Empty Cart");
        }

        $customer = trim($customer);
        $length = function_exists('mb_strlen') ? mb_strlen($customer) : strlen($customer);
        if ($customer === '' || $length > 100) {
            throw new OrderException("Please type the customer's name (up to 100 characters) before checkout.", "Customer Name Required");
        }

        $orderType = ($orderType === 'Take Out') ? 'Take Out' : 'Dine In';

        // polymorphism: the payment object decides what it needs
        $payment = PaymentFactory::create($paymentName);
        $reference = trim($reference);
        if ($payment->requiresReference()) {
            $payment->validate($reference);
        } else {
            $reference = null;
        }

        // the discount: its rate comes from the Discount table, and it needs an ID / card number
        $discountService = new DiscountService();
        $discount = $discountService->find((int)$discountId);
        if (!$discount) {
            throw new OrderException("That discount does not exist.", "Invalid Discount");
        }
        $discountNumber = trim($discountNumber);
        if ($discount->isDiscount()) {
            if (!preg_match('/^[A-Za-z0-9\- ]{3,50}$/', $discountNumber)) {
                throw new OrderException(
                    "Type the Senior Citizen / PWD ID number or the Exclusive Card number (3 to 50 letters, numbers, spaces or dashes).",
                    "ID / Card Number Required"
                );
            }
        } else {
            $discountNumber = null;
        }

        // 2. save everything together
        $this->pdo->beginTransaction();
        try {
            $priceStmt = $this->pdo->prepare("SELECT ProductName, Price FROM Product WHERE ProductID = ? AND IsAvailable = TRUE");

            $lines = [];
            $grandTotal = 0;
            $totalItems = 0;
            foreach ($selected as $productId => $qty) {
                $priceStmt->execute([$productId]);
                $product = $priceStmt->fetch();
                if (!$product) {
                    throw new OrderException("An item in this order is no longer available. Please refresh the page.", "Item Unavailable");
                }
                $lines[$productId] = [
                    'name'  => $product['ProductName'],
                    'qty'   => $qty,
                    'price' => $product['Price'],
                    'line'  => $product['Price'] * $qty
                ];
                $grandTotal += $product['Price'] * $qty;
                $totalItems += $qty;
            }

            // subtotal - discount = total (each amount rounded to centavos)
            $subtotal = round($grandTotal, 2);
            $discountAmount = $discount->calculateAmount($subtotal);
            $total = round($subtotal - $discountAmount, 2);

            // polymorphism again: cash checks the amount received and works out the change, online payment has none
            $change = null;
            if ($payment->requiresAmountReceived()) {
                $change = $payment->calculateChange($total, $amountReceived);
                $amountReceived = round((float)$amountReceived, 2);
            } else {
                $amountReceived = null;
            }

            $this->pdo->prepare(
                "INSERT INTO CustomerOrder (CustomerName, OrderType, SubtotalAmount, DiscountID, DiscountIDNumber, DiscountAmount,
                                            TotalAmount, PaymentMethod, ReferenceNumber, AmountReceived, Status, CashierID)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Completed', ?)"
            )->execute([$customer, $orderType, $subtotal, $discount->getDiscountId(), $discountNumber, $discountAmount,
                        $total, $payment->getName(), $reference, $amountReceived, $cashierId ?: null]);
            $orderId = $this->pdo->lastInsertId();

            $itemStmt = $this->pdo->prepare("INSERT INTO OrderItem (OrderID, ProductID, Quantity, UnitPrice) VALUES (?, ?, ?, ?)");
            foreach ($lines as $productId => $line) {
                $itemStmt->execute([$orderId, $productId, $line['qty'], $line['price']]);
            }

            $this->pdo->commit();

            return [
                'id' => $orderId, 'customer' => $customer, 'type' => $orderType,
                'payment' => $payment->getName(), 'reference' => $reference,
                'lines' => array_values($lines), 'items' => $totalItems,
                'subtotal' => $subtotal, 'discountName' => $discount->getName(), 'discountRate' => $discount->getRate(),
                'discountNumber' => $discountNumber, 'discountAmount' => $discountAmount, 'total' => $total,
                'amountReceived' => $amountReceived, 'change' => $change
            ];
        } catch (OrderException $exception) {
            $this->pdo->rollBack();
            throw $exception;
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            // 1062 = duplicate entry: this reference number was already used
            if (($exception->errorInfo[1] ?? 0) == 1062 && strpos($exception->getMessage(), 'ReferenceNumber') !== false) {
                throw new OrderException("That reference number is already recorded on another order. Please check it.", "Reference Already Used");
            }
            throw new DatabaseException("The order could not be saved. Please try again.", "Checkout Failed");
        }
    }

    // Voids a sale. A supervisor (Owner/Admin) must approve it with their own login.
    // The void is recorded in the OrderVoid table: who processed it, who approved it, when and why.
    public function voidOrder($orderId, $reason, $cashierId, $approverUsername, $approverPassword) {
        $reason = trim($reason);
        if ($reason === '') {
            throw new OrderException("Please type the reason for this void.", "Reason Required");
        }

        // approval first
        $auth = new AuthService();
        $approver = $auth->verifyApprover($approverUsername, $approverPassword);
        if (!$approver) {
            throw new OrderException(
                "The supervisor username or password is wrong, or that account is not an Owner/Admin. The order was NOT voided.",
                "Approval Denied"
            );
        }

        $stmt = $this->pdo->prepare("SELECT TotalAmount, Status FROM CustomerOrder WHERE OrderID = ?");
        $stmt->execute([(int)$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new OrderException("No transaction matches Receipt ID #" . \App\Core\Site::receiptNo($orderId) . " inside database logs.", "Order Not Found");
        }

        $this->pdo->beginTransaction();
        try {
            // only succeeds if the order is still Completed (protects against a double void)
            $update = $this->pdo->prepare("UPDATE CustomerOrder SET Status = 'Voided' WHERE OrderID = ? AND Status = 'Completed'");
            $update->execute([(int)$orderId]);

            if ($update->rowCount() === 0) {
                $this->pdo->rollBack();
                throw new OrderException(
                    "Invoice reference ticket #" . \App\Core\Site::receiptNo($orderId) . " has already been voided and reversed.",
                    "Already Refunded"
                );
            }

            $this->pdo->prepare(
                "INSERT INTO OrderVoid (OrderID, VoidedBy, ApprovedBy, VoidedAt, VoidReason)
                 VALUES (?, ?, ?, NOW(), ?)"
            )->execute([(int)$orderId, $cashierId, $approver['StaffID'], $reason]);

            $this->pdo->commit();
            return ['total' => $order['TotalAmount'], 'approvedBy' => $approver['FullName']];
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new DatabaseException("The order could not be voided. Please try again.", "Void Failed");
        }
    }

    // Looks up a receipt number with BINARY SEARCH.
    // The receipts are read in OrderID order (smallest first), so the list is sorted
    // and each step can throw away half of it: O(log n) instead of O(n).
    public function findReceipt($orderId) {
        $receipts = $this->pdo->query(
            "SELECT OrderID, CustomerName, OrderType, TotalAmount, PaymentMethod, Status
             FROM CustomerOrder
             ORDER BY OrderID"
        )->fetchAll();

        $steps = 0;
        $position = Searching::binarySearch($receipts, (int)$orderId, function ($receipt) {
            return (int)$receipt['OrderID'];
        }, $steps);

        return [
            'found' => $position !== -1,
            'order' => $position !== -1 ? $receipts[$position] : null,
            'steps' => $steps,
            'total' => count($receipts)
        ];
    }

    // The receipt number the next sale will get (shown on the Order Summary before checkout)
    public function nextReceiptNumber() {
        $row = $this->pdo->query("SELECT COALESCE(MAX(OrderID), 0) + 1 AS NextID FROM CustomerOrder")->fetch();
        return (int)$row['NextID'];
    }

    // Every order of today (all cashiers), so the cashier can verify a receipt number before a void
    public function todayOrders() {
        return $this->pdo->query(
            "SELECT o.OrderID, o.OrderDate, o.CustomerName, o.OrderType, o.PaymentMethod, o.TotalAmount, o.Status,
                    s.FullName AS CashierName, COALESCE(SUM(i.Quantity), 0) AS ItemCount
             FROM CustomerOrder o
             LEFT JOIN Staff s ON s.StaffID = o.CashierID
             LEFT JOIN OrderItem i ON i.OrderID = o.OrderID
             WHERE DATE(o.OrderDate) = CURRENT_DATE()
             GROUP BY o.OrderID, o.OrderDate, o.CustomerName, o.OrderType, o.PaymentMethod, o.TotalAmount, o.Status, s.FullName
             ORDER BY o.OrderID DESC"
        )->fetchAll();
    }

    // Today's completed orders and sales of each cashier (shown beside the total for the day)
    public function todayByCashier() {
        return $this->pdo->query(
            "SELECT s.FullName AS CashierName, COUNT(*) AS OrderCount, SUM(o.TotalAmount) AS Total
             FROM CustomerOrder o
             JOIN Staff s ON s.StaffID = o.CashierID
             WHERE DATE(o.OrderDate) = CURRENT_DATE() AND o.Status = 'Completed'
             GROUP BY s.StaffID, s.FullName
             ORDER BY s.FullName"
        )->fetchAll();
    }

    // Every order voided today: who voided it, who approved it and why (information for the cashier, no action here)
    public function todayVoids() {
        return $this->pdo->query(
            "SELECT o.OrderID, o.CustomerName, o.TotalAmount, v.VoidedAt, v.VoidReason,
                    cashier.FullName AS VoidedByName, approver.FullName AS ApprovedByName
             FROM OrderVoid v
             JOIN CustomerOrder o ON o.OrderID = v.OrderID
             JOIN Staff cashier ON cashier.StaffID = v.VoidedBy
             JOIN Staff approver ON approver.StaffID = v.ApprovedBy
             WHERE DATE(v.VoidedAt) = CURRENT_DATE()
             ORDER BY v.VoidedAt DESC"
        )->fetchAll();
    }

    // Today's completed orders and sales (the cashier's Register Report)
    public function todaySummary() {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) AS OrderCount, SUM(TotalAmount) AS Total
             FROM CustomerOrder
             WHERE DATE(OrderDate) = CURRENT_DATE() AND Status = 'Completed'"
        );
        $row = $stmt->fetch();
        return ['count' => (int)$row['OrderCount'], 'total' => $row['Total'] ?? 0];
    }
}
