<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$sql = "SELECT e.*, ec.name as category_name FROM expenses e LEFT JOIN expense_categories ec ON e.category_id=ec.id WHERE 1=1";
$params = [];
$types = '';

if ($date_from !== '') {
    $sql .= " AND e.date >= ?";
    $params[] = $date_from;
    $types .= 's';
}
if ($date_to !== '') {
    $sql .= " AND e.date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$sql .= " ORDER BY e.date ASC, e.id ASC";

if ($types !== '') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$expenses = [];
while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
}

$total_amount = 0;
foreach ($expenses as $e) {
    $total_amount += (float)($e['amount'] ?? 0);
}

$now = date('d-M-Y h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Expense Register</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; color: #222; padding: 15px; background: #fff; }
.print-header { text-align: center; border-bottom: 3px double #1B2A4A; padding-bottom: 10px; margin-bottom: 15px; }
.print-header h2 { color: #1B2A4A; font-size: 22px; margin: 0; letter-spacing: 1px; }
.print-header h4 { color: #555; font-size: 14px; font-weight: 600; margin: 4px 0 0; }
.print-header .date-range { font-size: 12px; color: #888; margin-top: 4px; }
.info-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 12px; }
.info-row .label { font-weight: 700; color: #555; }
table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
th, td { padding: 6px 8px; border: 1px solid #ccc; text-align: left; font-size: 11px; }
th { background: #1B2A4A; color: #fff; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
td { vertical-align: middle; }
tr:nth-child(even) td { background: #f8f9fc; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.font-bold { font-weight: 700; }
.text-success { color: #28a745; }
.text-danger { color: #dc3545; }
.text-muted { color: #888; }
.summary-table { width: 300px; margin-left: auto; }
.summary-table td { padding: 5px 10px; font-size: 12px; }
.summary-table .grand-row td { border-top: 2px solid #1B2A4A; font-size: 14px; font-weight: 800; color: #1B2A4A; background: #eef2f7 !important; }
.footer { margin-top: 20px; padding-top: 10px; border-top: 1px dashed #ccc; text-align: center; font-size: 10px; color: #999; }
.no-print { margin-bottom: 15px; }
.no-print button, .no-print a { padding: 6px 16px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; margin-right: 6px; text-decoration: none; display: inline-block; }
.no-print .btn-print { background: #1B2A4A; color: #fff; }
.no-print .btn-back { background: #e9ecef; color: #333; }
.no-print .filter-form { display: inline-flex; align-items: center; gap: 8px; margin-left: 15px; }
.no-print .filter-form input { padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; }
.no-print .filter-form button { padding: 5px 14px; background: #4e73df; color: #fff; border-radius: 4px; font-size: 12px; }
@media print { body { padding: 0; font-size: 11px; } .no-print { display: none !important; } @page { margin: 0.4in; size: landscape; } tr:nth-child(even) td { background: #f8f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { background: #1B2A4A !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .summary-table .grand-row td { background: #eef2f7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print();">🖨 Print</button>
    <a href="list.php" class="btn-back">← Back</a>
    <form class="filter-form" method="get">
        <label>From:</label>
        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
        <label>To:</label>
        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
        <button type="submit">Filter</button>
        <?php if ($date_from !== '' || $date_to !== ''): ?>
            <a href="print_list.php" style="padding:5px 10px;background:#e9ecef;color:#333;border-radius:4px;font-size:12px;text-decoration:none;">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="print-header">
    <h2>FLOUR MILL / EXPENSE REGISTER</h2>
    <?php if ($date_from !== '' || $date_to !== ''): ?>
        <div class="date-range">
            <?php echo $date_from !== '' ? date('d-M-Y', strtotime($date_from)) : 'Start'; ?>
            &mdash;
            <?php echo $date_to !== '' ? date('d-M-Y', strtotime($date_to)) : 'End'; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (count($expenses) === 0): ?>
    <p style="text-align:center;padding:30px;color:#888;font-size:14px;">No expenses found.</p>
<?php else: ?>

<table>
    <thead>
        <tr>
            <th class="text-center">#</th>
            <th>Date</th>
            <th>Category</th>
            <th>Paid To</th>
            <th>Type</th>
            <th>Notes</th>
            <th class="text-right">Amount (Rs)</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1; foreach ($expenses as $e): ?>
        <tr>
            <td class="text-center"><?php echo $i++; ?></td>
            <td><?php echo date('d-M-Y', strtotime($e['date'])); ?></td>
            <td><?php echo htmlspecialchars($e['category_name'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($e['paid_to'] ?? '-'); ?></td>
            <td><?php echo ucfirst(htmlspecialchars($e['payment_type'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars($e['notes'] ?? '-'); ?></td>
            <td class="text-right text-danger font-bold"><?php echo money($e['amount']); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<table class="summary-table">
    <tr>
        <td class="font-bold">Total Entries</td>
        <td class="text-right"><?php echo count($expenses); ?></td>
    </tr>
    <tr class="grand-row">
        <td>Total Amount (Rs)</td>
        <td class="text-right"><?php echo money($total_amount); ?></td>
    </tr>
</table>

<?php endif; ?>

<div class="footer">
    Flour Mill Management System | Expense Register | Generated <?php echo $now; ?>
</div>

<script>
window.onload = function() {
    setTimeout(function(){ window.print(); }, 400);
};
</script>

</body>
</html>
