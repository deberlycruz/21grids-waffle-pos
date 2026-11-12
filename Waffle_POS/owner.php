<?php
// owner.php
session_start();
if (($_SESSION['role'] ?? '') !== 'Owner') { header("Location: login.php"); exit(); }
require_once 'db.php';

use App\Core\Site;
use App\Services\ReportService;

$report = new ReportService();
$totals     = $report->getTotals();        // revenue, completed, voided
$dailySales = $report->getDailySales();    // every date that has sales
$ledger     = $report->getLedger();        // every order with its items, cashier and approver
$topSellers = $report->getTopSellers(5);   // PRIORITY QUEUE (min-heap): the 5 best-selling products

// Ledger tools: find a customer (LINEAR SEARCH) and sort the result (MERGE SORT)
$sortKey = in_array($_GET['sort'] ?? '', ['date', 'amount', 'customer', 'cashier', 'receipt'], true) ? $_GET['sort'] : 'date';
$sortDir = (($_GET['dir'] ?? 'desc') === 'asc') ? 'asc' : 'desc';
$find    = trim($_GET['find'] ?? '');
$totalRecords = count($ledger);
$searchSteps = 0;
$comparisons = 0;
if ($find !== '') {
    $ledger = $report->findCustomer($ledger, $find, $searchSteps);
}
$ledger = $report->sortLedger($ledger, $sortKey, $sortDir, $comparisons);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars(Site::getBusinessName()); ?> | Owner Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #faf8f5; margin: 40px; }
        h2 { color: #5c3d2e; font-size: 30px; }
        .nav-link { background: #faf8f5; color: #5c3d2e; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: background 0.2s; }
        .nav-link:hover { background: #eee; }
        .analytics-flex { display: flex; gap: 30px; margin-top: 30px; }
        .stat-card { background: white; border: 3px solid #e8dec9; border-left: 10px solid #865d43; padding: 40px; border-radius: 16px; flex: 1; box-shadow: 0 8px 16px rgba(0,0,0,0.02); }
        .stat-card h3 { margin: 0; font-size: 16px; color: #666; text-transform: uppercase; font-weight: bold; }
        .stat-card p { margin: 20px 0 0 0; font-size: 43px; font-weight: bold; color: #5c3d2e; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 16px rgba(0,0,0,0.02); }
        th, td { border: 2px solid #ebdcb9; padding: 14px; text-align: left; font-size: 13px; }
        th { background: #f5eedc; color: #5c3d2e; }
        .section-title { color:#5c3d2e; font-size: 21px; margin: 50px 0 5px 0; }
        select.filter { padding: 12px; font-size: 14px; border-radius: 8px; border: 2px solid #ccc; margin-top: 10px; }
    
        /* a roomier drop-down arrow, not stuck to the edge */
        select { -webkit-appearance: none; appearance: none; padding-right: 40px !important;
                 background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path d='M1 1l5 5 5-5' fill='none' stroke='%235c3d2e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/></svg>");
                 background-repeat: no-repeat; background-position: right 16px center; background-size: 12px 8px; background-color: #fff; }
    </style>
    <script>
        // Filter the ledger by date (or show all)
        function filterLedger() {
            var chosen = document.getElementById("dateFilter").value;
            var rows = document.querySelectorAll("#ledgerBody tr");
            rows.forEach(function (r) {
                r.style.display = (chosen === "all" || r.getAttribute("data-date") === chosen) ? "" : "none";
            });
        }
    </script>
</head>
<body>

    <!-- TOP NAVIGATION HEADER WITH SALES BY CASHIER + LOGOUT -->
    <div style="background: #5c3d2e; padding: 15px 30px; margin: -40px -40px 10px -40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 8px rgba(0,0,0,0.05);">
        <h2 style="color: white; margin: 0; font-size: 21px;"><?php echo htmlspecialchars(Site::getBusinessName()); ?> | Owner Dashboard</h2>
        <?php include 'clock.php'; ?>
        <div>
            <a href="cashier_sales.php" class="nav-link">Sales by Cashier</a>
            <a href="logout.php" class="nav-link" style="margin-left: 10px;">Back to Portal Gate (Logout)</a>
        </div>
    </div>

    <div class="analytics-flex">
        <div class="stat-card">
            <h3>Net Active Revenue (Excluding Voids)</h3>
            <p>₱<?php echo number_format($totals['revenue'], 2); ?></p>
        </div>
        <div class="stat-card">
            <h3>Net Audited Invoice Volume</h3>
            <p><?php echo (int)$totals['completed']; ?> Receipts</p>
        </div>
        <div class="stat-card">
            <h3>Discounts Given</h3>
            <p>₱<?php echo number_format($totals['discounts'], 2); ?></p>
        </div>
        <div class="stat-card">
            <h3>Voided Receipts</h3>
            <p><?php echo (int)$totals['voided']; ?></p>
        </div>
    </div>

    <!-- BEST SELLERS (priority queue / min-heap) -->
    <h3 class="section-title">Best Sellers (Top 5)</h3>
    <p style="color:#777; font-size:11px; margin:0 0 5px;">Chosen with a priority queue (min-heap): every product goes in, and the biggest seller comes out first. Voided orders are not counted.</p>
    <?php if (empty($topSellers)): ?>
        <p style="color: #777; font-style: italic; font-size: 14px;">No sales recorded yet.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Rank</th><th>Product</th><th>Units Sold</th><th>Revenue</th></tr></thead>
            <tbody>
                <?php foreach ($topSellers as $rank => $top): ?>
                    <tr>
                        <td><strong>#<?php echo $rank + 1; ?></strong></td>
                        <td><?php echo htmlspecialchars($top['ProductName']); ?></td>
                        <td><?php echo (int)$top['UnitsSold']; ?></td>
                        <td style="font-weight:bold; color:#2e7d32;">₱<?php echo number_format($top['Revenue'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- DAILY SALES BY DATE (ALL DATES AT ONCE) -->
    <h3 class="section-title">Daily Sales Summary (All Dates)</h3>
    <?php if (empty($dailySales)): ?>
        <p style="color: #777; font-style: italic; font-size: 14px;">No sales recorded yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Completed Orders</th>
                    <th>Net Sales</th>
                    <th>Voided Orders</th>
                    <th>Voided Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dailySales as $day): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars(date('M d, Y (D)', strtotime($day['SaleDate']))); ?></strong></td>
                        <td><?php echo (int)$day['completed']; ?></td>
                        <td style="font-weight:bold; color:#2e7d32;">₱<?php echo number_format($day['net'], 2); ?></td>
                        <td><?php echo (int)$day['voided']; ?></td>
                        <td style="color:#b71c1c;">₱<?php echo number_format($day['voidedAmount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f5eedc; font-weight:bold;">
                    <td>GRAND TOTAL</td>
                    <td><?php echo (int)$totals['completed']; ?></td>
                    <td style="color:#2e7d32;">₱<?php echo number_format($totals['revenue'], 2); ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <!-- FULL TRANSACTION LEDGER WITH DATE FILTER -->
    <h3 class="section-title">Live System Master Audit Ledger</h3>
    <?php if ($totalRecords > 0): ?>
        <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:end; background:#fdfaf4; border:2px dashed #865d43; border-radius:10px; padding:14px 18px; margin:10px 0;">
            <div><label style="display:block; font-size:11px; color:#666;">Find customer</label>
                <input type="text" name="find" value="<?php echo htmlspecialchars($find); ?>" placeholder="part of a name" style="padding:10px; font-size:13px; border:1px solid #ccc; border-radius:6px;"></div>
            <div><label style="display:block; font-size:11px; color:#666;">Sort by</label>
                <select name="sort" style="padding:10px; font-size:13px; border:1px solid #ccc; border-radius:6px;">
                    <?php foreach (['date' => 'Date', 'amount' => 'Amount', 'customer' => 'Customer', 'cashier' => 'Cashier', 'receipt' => 'Receipt number'] as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $sortKey === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label style="display:block; font-size:11px; color:#666;">Order</label>
                <select name="dir" style="padding:10px; font-size:13px; border:1px solid #ccc; border-radius:6px;">
                    <option value="desc" <?php echo $sortDir === 'desc' ? 'selected' : ''; ?>>Largest / newest first</option>
                    <option value="asc" <?php echo $sortDir === 'asc' ? 'selected' : ''; ?>>Smallest / oldest first</option>
                </select></div>
            <button type="submit" style="padding:11px 20px; font-size:13px; font-weight:bold; border:none; border-radius:8px; background:#865d43; color:white; cursor:pointer;">Apply</button>
            <a href="owner.php" style="padding:11px 18px; font-size:13px; font-weight:bold; border-radius:8px; background:#777; color:white; text-decoration:none;">Reset</a>
        </form>
        <p style="color:#777; font-size:11px; margin:4px 0;">
            <?php if ($find !== ''): ?>Linear search checked <?php echo $searchSteps; ?> of <?php echo $totalRecords; ?> records and found <?php echo count($ledger); ?>. <?php endif; ?>
            Merge sort ordered <?php echo count($ledger); ?> record(s) using <?php echo $comparisons; ?> comparisons.
        </p>
    <?php endif; ?>
    <?php if ($totalRecords === 0): ?>
        <p style="color: #777; font-style: italic; font-size: 14px;">No transaction history exists inside the relational database engine.</p>
    <?php elseif (empty($ledger)): ?>
        <p style="color: #777; font-style: italic; font-size: 14px;">No customer name contains "<?php echo htmlspecialchars($find); ?>".</p>
    <?php else: ?>
        <label for="dateFilter" style="font-size:14px; color:#5c3d2e; font-weight:bold;">Filter by date:</label><br>
        <select id="dateFilter" class="filter" onchange="filterLedger();">
            <option value="all">Show all dates</option>
            <?php foreach ($dailySales as $day): ?>
                <option value="<?php echo htmlspecialchars($day['SaleDate']); ?>"><?php echo htmlspecialchars(date('M d, Y', strtotime($day['SaleDate']))); ?></option>
            <?php endforeach; ?>
        </select>

        <table>
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Date &amp; Time</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Items Sold</th>
                    <th>Discount</th>
                    <th>Invoice Value</th>
                    <th>Payment</th>
                    <th>Cashier</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="ledgerBody">
                <?php foreach ($ledger as $transaction): ?>
                    <tr data-date="<?php echo htmlspecialchars($transaction['SaleDate']); ?>" style="<?php echo ($transaction['Status'] === 'Voided') ? 'background: #fff5f5; color: #b71c1c;' : 'background: #fdfdfd; color: #333;'; ?>">
                        <td><strong>#<?php echo \App\Core\Site::receiptNo($transaction['OrderID']); ?></strong></td>
                        <td><?php echo htmlspecialchars($transaction['OrderDate']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['CustomerName']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['OrderType']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['Items'] !== '' ? $transaction['Items'] : '—'); ?></td>
                        <td>
                            <?php if ($transaction['DiscountAmount'] > 0): ?>
                                <?php echo htmlspecialchars($transaction['DiscountName']); ?><br>
                                <small>-₱<?php echo number_format($transaction['DiscountAmount'], 2); ?> (of ₱<?php echo number_format($transaction['SubtotalAmount'], 2); ?>)<br>ID/Card: <?php echo htmlspecialchars($transaction['DiscountIDNumber']); ?></small>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td style="font-weight: bold;">
                            <?php echo ($transaction['Status'] === 'Voided') ? '-₱' : '₱'; ?><?php echo number_format($transaction['TotalAmount'], 2); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($transaction['PaymentMethod']); ?>
                            <?php if (!empty($transaction['ReferenceNumber'])): ?><br><small>Ref: <?php echo htmlspecialchars($transaction['ReferenceNumber']); ?></small><?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($transaction['CashierName'] ?? '—'); ?></td>
                        <td>
                            <?php if ($transaction['Status'] === 'Voided'): ?>
                                <span style="background: #ffcdd2; color: #b71c1c; padding: 6px 12px; border-radius: 6px; font-weight: bold; font-size: 11px;">VOIDED / REVERSED</span>
                                <?php if (!empty($transaction['ApproverName'])): ?><br><small>Approved by <?php echo htmlspecialchars($transaction['ApproverName']); ?></small><?php endif; ?>
                                <?php if (!empty($transaction['VoidReason'])): ?><br><small>Reason: <?php echo htmlspecialchars($transaction['VoidReason']); ?></small><?php endif; ?>
                            <?php else: ?>
                                <span style="background: #c8e6c9; color: #2e7d32; padding: 6px 12px; border-radius: 6px; font-weight: bold; font-size: 11px;">COMPLETED</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>


<?php include 'footer.php'; ?>
</body>
</html>
