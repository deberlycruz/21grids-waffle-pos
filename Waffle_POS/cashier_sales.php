<?php
// cashier_sales.php  (Owner and Admin: sales separated per cashier)
session_start();
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['Owner', 'Admin'], true)) { header("Location: login.php"); exit(); }
require_once 'db.php';

use App\Core\Site;
use App\Services\ReportService;

$report  = new ReportService();
$backUrl = ($role === 'Admin') ? 'admin.php' : 'owner.php';

// Optional date range (YYYY-MM-DD). Empty = all dates.
function cleanDate($dateText) { return (isset($dateText) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateText)) ? $dateText : ''; }
function peso($amount) { return '₱' . number_format((float)$amount, 2); }
function qs($cashier, $from, $to) { return '?' . http_build_query(array_filter(['cashier' => $cashier, 'from' => $from, 'to' => $to], fn($value) => $value !== '')); }

$from = cleanDate($_GET['from'] ?? '');
$to   = cleanDate($_GET['to'] ?? '');
$selected = $_GET['cashier'] ?? 'all';

$cashiers = $report->getCashiers();
$validIds = array_map(fn($cashier) => (string)$cashier['StaffID'], $cashiers);
if ($selected !== 'all' && $selected !== 'none' && !in_array((string)$selected, $validIds, true)) { $selected = 'all'; }

$summary    = $report->getCashierSummary($from, $to);
$perCashier = $summary['perCashier'];
$unassigned = $summary['unassigned'];
$grand      = $summary['grand'];
$empty      = $summary['empty'];
$hasUnassigned = ($unassigned['completed'] + $unassigned['voided']) > 0;

$daily = []; $ledger = []; $selRow = $empty; $selName = '';
if ($selected !== 'all') {
    $cashierParam = ($selected === 'none') ? 0 : (int)$selected;
    $daily  = $report->getDailySales($cashierParam, $from, $to);
    $ledger = $report->getLedger($cashierParam, $from, $to);
    if ($selected === 'none') {
        $selRow = $unassigned; $selName = 'Older orders (no cashier recorded)';
    } else {
        $selRow = $perCashier[(int)$selected] ?? $empty;
        foreach ($cashiers as $cashier) { if ((string)$cashier['StaffID'] === (string)$selected) { $selName = $cashier['FullName']; } }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars(Site::getBusinessName()); ?> | Sales by Cashier</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #faf8f5; margin: 40px; color:#333; }
        .nav-link { background: #faf8f5; color: #5c3d2e; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-left: 10px; }
        .nav-link:hover { background: #eee; }
        .tabs { display:flex; gap:10px; flex-wrap:wrap; margin: 5px 0 20px; }
        .tab { padding: 14px 24px; border-radius: 10px; border: 3px solid #ebdcb9; background: white; color:#5c3d2e; font-weight: bold; font-size: 14px; text-decoration: none; }
        .tab:hover { background:#fdf6e6; }
        .tab.active { background:#5c3d2e; color:white; border-color:#5c3d2e; }
        .tab small { font-weight: normal; opacity: .75; }
        .cards { display:flex; gap:20px; flex-wrap:wrap; margin-bottom: 10px; }
        .card { background:white; border:3px solid #e8dec9; border-left:10px solid #865d43; border-radius:14px; padding:20px 26px; flex:1; min-width:200px; }
        .card h4 { margin:0; font-size:11px; color:#666; text-transform:uppercase; }
        .card p { margin:10px 0 0; font-size:27px; font-weight:bold; color:#5c3d2e; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; margin-top: 10px; }
        th, td { border: 2px solid #ebdcb9; padding: 12px 16px; text-align: left; font-size: 13px; }
        th { background: #f5eedc; color: #5c3d2e; }
        tfoot td { background: #f5eedc; font-weight: bold; color: #5c3d2e; }
        .num { text-align: right; white-space: nowrap; }
        h3.sec { color:#5c3d2e; font-size: 19px; margin: 35px 0 5px; }
        .filter { display:flex; gap:12px; align-items:end; flex-wrap:wrap; background:#fdfaf4; padding:14px 18px; border-radius:10px; border:2px dashed #865d43; }
        .filter label { display:block; font-size:11px; color:#666; margin-bottom:3px; }
        .filter input { padding:9px; font-size:13px; border:1px solid #ccc; border-radius:6px; }
        .filter button, .filter a.reset { padding:10px 18px; font-size:12px; font-weight:bold; border:none; border-radius:8px; background:#865d43; color:white; cursor:pointer; text-decoration:none; }
        .filter a.reset { background:#777; }
        .badge { padding:4px 10px; border-radius:6px; font-size:11px; font-weight:bold; }
        .ok { background:#c8e6c9; color:#2e7d32; } .void { background:#ffcdd2; color:#b71c1c; }
    
        /* a roomier drop-down arrow, not stuck to the edge */
        select { -webkit-appearance: none; appearance: none; padding-right: 40px !important;
                 background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path d='M1 1l5 5 5-5' fill='none' stroke='%235c3d2e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/></svg>");
                 background-repeat: no-repeat; background-position: right 16px center; background-size: 12px 8px; background-color: #fff; }
    </style>
</head>
<body>
    <div style="background:#5c3d2e; padding:15px 30px; margin:-40px -40px 30px -40px; display:flex; justify-content:space-between; align-items:center;">
        <h2 style="color:white; margin:0; font-size:21px;"><?php echo htmlspecialchars(Site::getBusinessName()); ?> | Sales by Cashier</h2>
        <?php include 'clock.php'; ?>
        <div><a href="<?php echo $backUrl; ?>" class="nav-link">Back to Dashboard</a><a href="logout.php" class="nav-link">Log out</a></div>
    </div>

    <!-- CASHIER BUTTONS -->
    <div class="tabs">
        <a class="tab <?php echo $selected === 'all' ? 'active' : ''; ?>" href="<?php echo qs('all', $from, $to); ?>">All Cashiers</a>
        <?php foreach ($cashiers as $cashier): ?>
            <a class="tab <?php echo (string)$selected === (string)$cashier['StaffID'] ? 'active' : ''; ?>" href="<?php echo qs($cashier['StaffID'], $from, $to); ?>">
                <?php echo htmlspecialchars($cashier['FullName']); ?><?php echo $cashier['IsActive'] ? '' : ' <small>(inactive)</small>'; ?>
            </a>
        <?php endforeach; ?>
        <?php if ($hasUnassigned): ?>
            <a class="tab <?php echo $selected === 'none' ? 'active' : ''; ?>" href="<?php echo qs('none', $from, $to); ?>">Older orders <small>(no cashier)</small></a>
        <?php endif; ?>
    </div>

    <!-- DATE FILTER -->
    <form method="GET" class="filter">
        <input type="hidden" name="cashier" value="<?php echo htmlspecialchars((string)$selected); ?>">
        <div><label>From date</label><input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>"></div>
        <div><label>To date</label><input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>"></div>
        <button type="submit">Apply</button>
        <a class="reset" href="<?php echo qs($selected, '', ''); ?>">Show all dates</a>
        <span style="color:#777;"><?php echo ($from || $to) ? 'Showing ' . htmlspecialchars($from ?: 'the beginning') . ' to ' . htmlspecialchars($to ?: 'today') : 'Showing all dates'; ?></span>
    </form>

<?php if ($selected === 'all'): ?>
    <h3 class="sec">All cashiers compared</h3>
    <table>
        <thead><tr><th>Cashier</th><th class="num">Completed orders</th><th class="num">Net sales</th><th class="num">Cash</th><th class="num">Online</th><th class="num">Voided orders</th><th class="num">Voided amount</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($cashiers as $cashier): $cashierTotals = $perCashier[$cashier['StaffID']] ?? $empty; ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($cashier['FullName']); ?></strong> <small style="color:#999;">(<?php echo htmlspecialchars($cashier['Username']); ?>)</small><?php echo $cashier['IsActive'] ? '' : ' <span class="badge void">INACTIVE</span>'; ?></td>
                <td class="num"><?php echo (int)$cashierTotals['completed']; ?></td>
                <td class="num" style="font-weight:bold; color:#2e7d32;"><?php echo peso($cashierTotals['net']); ?></td>
                <td class="num"><?php echo peso($cashierTotals['cash']); ?></td>
                <td class="num"><?php echo peso($cashierTotals['online']); ?></td>
                <td class="num" style="color:#b71c1c;"><?php echo (int)$cashierTotals['voided']; ?></td>
                <td class="num" style="color:#b71c1c;"><?php echo peso($cashierTotals['voidedAmount']); ?></td>
                <td><a href="<?php echo qs($cashier['StaffID'], $from, $to); ?>" style="color:#865d43; font-weight:bold;">View sales →</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($hasUnassigned): ?>
            <tr style="color:#777;">
                <td><em>Older orders (no cashier recorded)</em></td>
                <td class="num"><?php echo (int)$unassigned['completed']; ?></td>
                <td class="num"><?php echo peso($unassigned['net']); ?></td>
                <td class="num"><?php echo peso($unassigned['cash']); ?></td>
                <td class="num"><?php echo peso($unassigned['online']); ?></td>
                <td class="num"><?php echo (int)$unassigned['voided']; ?></td>
                <td class="num"><?php echo peso($unassigned['voidedAmount']); ?></td>
                <td><a href="<?php echo qs('none', $from, $to); ?>" style="color:#865d43; font-weight:bold;">View →</a></td>
            </tr>
        <?php endif; ?>
        </tbody>
        <tfoot><tr>
            <td>ALL CASHIERS TOTAL</td>
            <td class="num"><?php echo (int)$grand['completed']; ?></td><td class="num"><?php echo peso($grand['net']); ?></td>
            <td class="num"><?php echo peso($grand['cash']); ?></td><td class="num"><?php echo peso($grand['online']); ?></td>
            <td class="num"><?php echo (int)$grand['voided']; ?></td><td class="num"><?php echo peso($grand['voidedAmount']); ?></td><td></td>
        </tr></tfoot>
    </table>

<?php else: ?>
    <h3 class="sec">Sales of <?php echo htmlspecialchars($selName); ?></h3>
    <div class="cards">
        <div class="card"><h4>Completed orders</h4><p><?php echo (int)$selRow['completed']; ?></p></div>
        <div class="card"><h4>Net sales</h4><p style="color:#2e7d32;"><?php echo peso($selRow['net']); ?></p></div>
        <div class="card"><h4>Cash / Online</h4><p style="font-size:18px; line-height:1.4;"><?php echo peso($selRow['cash']); ?><br><?php echo peso($selRow['online']); ?></p></div>
        <div class="card"><h4>Voided</h4><p style="color:#b71c1c;"><?php echo (int)$selRow['voided']; ?> <span style="font-size:14px;">(<?php echo peso($selRow['voidedAmount']); ?>)</span></p></div>
    </div>

    <h3 class="sec">Daily sales</h3>
    <?php if (empty($daily)): ?>
        <p style="color:#777; font-style:italic;">No sales found for this selection.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Date</th><th class="num">Completed orders</th><th class="num">Net sales</th><th class="num">Voided orders</th><th class="num">Voided amount</th></tr></thead>
        <tbody>
        <?php foreach ($daily as $dayRow): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars(date('M d, Y (D)', strtotime($dayRow['SaleDate']))); ?></strong></td>
                <td class="num"><?php echo (int)$dayRow['completed']; ?></td>
                <td class="num" style="font-weight:bold; color:#2e7d32;"><?php echo peso($dayRow['net']); ?></td>
                <td class="num" style="color:#b71c1c;"><?php echo (int)$dayRow['voided']; ?></td>
                <td class="num" style="color:#b71c1c;"><?php echo peso($dayRow['voidedAmount']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td>TOTAL</td><td class="num"><?php echo (int)$selRow['completed']; ?></td><td class="num"><?php echo peso($selRow['net']); ?></td>
            <td class="num"><?php echo (int)$selRow['voided']; ?></td><td class="num"><?php echo peso($selRow['voidedAmount']); ?></td></tr></tfoot>
    </table>
    <?php endif; ?>

    <h3 class="sec">Every order by <?php echo htmlspecialchars($selName); ?></h3>
    <?php if (empty($ledger)): ?>
        <p style="color:#777; font-style:italic;">No orders found for this selection.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Receipt</th><th>Date &amp; time</th><th>Customer</th><th>Type</th><th>Items sold</th><th>Payment</th><th class="num">Amount</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($ledger as $order): $isVoided = ($order['Status'] === 'Voided'); ?>
            <tr style="<?php echo $isVoided ? 'background:#fff5f5; color:#b71c1c;' : ''; ?>">
                <td><strong>#<?php echo \App\Core\Site::receiptNo($order['OrderID']); ?></strong></td>
                <td><?php echo htmlspecialchars($order['OrderDate']); ?></td>
                <td><?php echo htmlspecialchars($order['CustomerName']); ?></td>
                <td><?php echo htmlspecialchars($order['OrderType']); ?></td>
                <td><?php echo htmlspecialchars($order['Items']); ?></td>
                <td><?php echo htmlspecialchars($order['PaymentMethod']); ?><?php echo $order['ReferenceNumber'] ? '<br><small>Ref: ' . htmlspecialchars($order['ReferenceNumber']) . '</small>' : ''; ?></td>
                <td class="num" style="font-weight:bold;"><?php echo ($isVoided ? '-' : '') . peso($order['TotalAmount']); ?>
                    <?php if ($order['DiscountAmount'] > 0): ?><br><small style="font-weight:normal;"><?php echo htmlspecialchars($order['DiscountName']); ?> -<?php echo peso($order['DiscountAmount']); ?></small><?php endif; ?></td>
                <td><span class="badge <?php echo $isVoided ? 'void' : 'ok'; ?>"><?php echo $isVoided ? 'VOIDED' : 'COMPLETED'; ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
<?php endif; ?>
<?php include 'footer.php'; ?>
</body>
</html>
