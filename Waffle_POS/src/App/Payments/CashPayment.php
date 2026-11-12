<?php
namespace App\Payments;

use App\Core\OrderException;
use App\Interfaces\PaymentMethod;

class CashPayment implements PaymentMethod {
    public function getName() {
        return "Cash";
    }

    public function requiresReference() {
        return false;
    }

    public function validate($reference) {
        // cash needs nothing to check
    }

    // Cash is handed over by the customer, so the cashier must type the amount received
    public function requiresAmountReceived() {
        return true;
    }

    // change = amount received - total (it must cover the total)
    public function calculateChange(float $total, $amountReceived) {
        if (!is_numeric($amountReceived) || (float)$amountReceived < $total) {
            throw new OrderException("The amount received must be at least " . number_format($total, 2) . ". Please check the cash handed over by the customer.", "Amount Received Too Low");
        }
        if ((float)$amountReceived > 99999.99) {
            throw new OrderException("The amount received is too large. Please check the amount typed.", "Amount Received Invalid");
        }
        return round((float)$amountReceived - $total, 2);
    }

    public function describe(float $amount) {
        return "Paid " . number_format($amount, 2) . " in cash.";
    }
}
