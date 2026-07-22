<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$filter_type = $_GET['type'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

$sql = "SELECT bsl.*, w.name as warehouse_name
    FROM bag_stock_ledger bsl
    JOIN warehouses w ON bsl.warehouse_id = w.id
    WHERE 1=1";

if ($filter_type) $sql .= " AND bsl.type = '" . $conn->real_escape_string($filter_type) . "'";
if ($filter_date_from) $sql .= " AND bsl.date >= '" . $conn->real_escape_string($filter_date_from) . "'";
if ($filter_date_to) $sql .= " AND bsl.date <= '" . $conn->real_escape_string($filter_date_to) . "'";

$sql .= " ORDER BY bsl.date ASC, bsl.id ASC";
$result = $conn->query($sql);

$rows = [];
$total_in = 0;
$total_out = 0;
while ($row = $result->fetch_assoc()) {
    $total_in += $row['qty_in'];
    $total_out += $row['qty_out'];
    $rows[] = $row;
}

$current_stock = $conn->query("SELECT COALESCE(SUM(qty),0) as t FROM bag_stock")->fetch_assoc()['t'];

$type_labels = [
    'opening' => 'Opening',
    'booking_out' => 'Booking OUT',
    'arrival_in' => 'Arrival IN',
    'manual_in' => 'Manual IN',
    'manual_out' => 'Manual OUT',
    'adjustment' => 'Adjustment',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FLOUR MILL / BAG STOCK LEDGER</title>
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
.no-print .filter-form input, .no-print .filter-form select { padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; }
.no-print .filter-form button { padding: 5px 14px; background: #4e73df; color: #fff; border-radius: 4px; font-size: 12px; }
@media print { body { padding: 0; font-size: 11px; } .no-print { display: none !important; } @page { margin: 0.4in; size: landscape; } tr:nth-child(even) td { background: #f8f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { background: #1B2A4A !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .summary-table .grand-row td { background: #eef2f7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } tfoot td, tfoot th { background: #eef2f7 !important; font-weight: 700 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128424; Print</button>
    <a href="ledger.php" class="btn-back">&#8592; Back to Bag Ledger</a>
    <form class="filter-form" method="GET">
        <label>Type:</label>
        <select name="type">
            <option value="">All</option>
            <option value="opening" <?= $filter_type === 'opening' ? 'selected' : '' ?>>Opening</option>
            <option value="booking_out" <?= $filter_type === 'booking_out' ? 'selected' : '' ?>>Booking OUT</option>
            <option value="arrival_in" <?= $filter_type === 'arrival_in' ? 'selected' : '' ?>>Arrival IN</option>
            <option value="manual_in" <?= $filter_type === 'manual_in' ? 'selected' : '' ?>>Manual IN</option>
            <option value="manual_out" <?= $filter_type === 'manual_out' ? 'selected' : '' ?>>Manual OUT</option>
            <option value="adjustment" <?= $filter_type === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
        </select>
        <label>From:</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>">
        <label>To:</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>">
        <button type="submit">Filter</button>
        <?php if ($filter_type !== '' || $filter_date_from !== '' || $filter_date_to !== ''): ?>
            <a href="print_ledger.php" style="padding:5px 10px;font-size:12px;color:#dc3545;">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="print-header">
    <h2>FLOUR MILL</h2>
    <h4>BAG STOCK LEDGER</h4>
    <?php if ($filter_type !== '' || $filter_date_from !== '' || $filter_date_to !== ''): ?>
    <div class="date-range">
        <?= $filter_date_from ? date('d-M-Y', strtotime($filter_date_from)) : 'Start' ?>
        &mdash;
        <?= $filter_date_to ? date('d-M-Y', strtotime($filter_date_to)) : 'End' ?>
    </div>
    <?php endif; ?>
</div>

<div class="info-row">
    <span><span class="label">Current Bag Stock:</span> <?= number_format($current_stock) ?> Bags</span>
    <span><span class="label">Printed on:</span> <?= date('d-M-Y H:i A') ?></span>
</div>

<?php if (empty($rows)): ?>
    <p style="text-align:center;padding:30px;color:#888;font-size:14px;">No bag stock entries found.</p>
<?php else: ?>

<table>
    <thead>
        <tr>
            <th width="30">#</th>
            <th width="80">Date</th>
            <th>Warehouse</th>
            <th class="text-right" width="80">IN</th>
            <th class="text-right" width="80">OUT</th>
            <th class="text-right" width="90">Quantity (Balance)</th>
            <th width="100">Type</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1; foreach ($rows as $row): ?>
        <tr>
            <td class="text-center"><?= $i++ ?></td>
            <td><?= date('d-m-Y', strtotime($row['date'])) ?></td>
            <td><?= htmlspecialchars($row['warehouse_name']) ?></td>
            <td class="text-right text-success font-bold"><?= $row['qty_in'] > 0 ? number_format($row['qty_in']) : '-' ?></td>
            <td class="text-right text-danger font-bold"><?= $row['qty_out'] > 0 ? number_format($row['qty_out']) : '-' ?></td>
            <td class="text-right font-bold"><?= number_format($row['balance_qty']) ?></td>
            <td><?= $type_labels[$row['type']] ?? ucfirst(str_replace('_', ' ', $row['type'])) ?></td>
            <td><small><?= htmlspecialchars($row['notes'] ?? '') ?></small></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="3" class="text-right">TOTAL</th>
            <th class="text-right"><?= number_format($total_in) ?></th>
            <th class="text-right"><?= number_format($total_out) ?></th>
            <th colspan="3"></th>
        </tr>
    </tfoot>
</table>

<table class="summary-table">
    <tr>
        <td class="font-bold">Current Stock:</td>
        <td class="text-right font-bold"><?= number_format($current_stock) ?> Bags</td>
    </tr>
    <tr>
        <td class="font-bold">Total IN:</td>
        <td class="text-right text-success font-bold"><?= number_format($total_in) ?> Bags</td>
    </tr>
    <tr>
        <td class="font-bold">Total OUT:</td>
        <td class="text-right text-danger font-bold"><?= number_format($total_out) ?> Bags</td>
    </tr>
    <tr class="grand-row">
        <td>Net Movement:</td>
        <td class="text-right"><?= number_format($total_in - $total_out) ?> Bags</td>
    </tr>
</table>

<?php endif; ?>

<div class="footer">
    Flour Mill Management System &bull; Bag Stock Ledger &bull; Generated <?= date('d-M-Y H:i:s A') ?>
</div>

<script>window.onload = function() { setTimeout(function(){ window.print(); }, 400); };</script>
</body>
</html>
