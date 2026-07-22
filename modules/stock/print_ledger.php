<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$pid = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$products = $conn->query("SELECT id, name FROM products WHERE status='active' ORDER BY name");

$product_name = '';
if ($pid > 0) {
    $p = $conn->query("SELECT name FROM products WHERE id=$pid")->fetch_assoc();
    $product_name = $p['name'] ?? '';
}

if ($pid > 0) {
    $result = $conn->query("SELECT sl.*, pr.name as product_name FROM stock_ledger sl LEFT JOIN products pr ON sl.product_id=pr.id WHERE sl.product_id=$pid ORDER BY sl.date ASC, sl.id ASC");
} else {
    $result = $conn->query("SELECT sl.*, pr.name as product_name FROM stock_ledger sl LEFT JOIN products pr ON sl.product_id=pr.id ORDER BY sl.product_id, sl.date ASC, sl.id ASC");
}

$rows = [];
$total_in = 0;
$total_out = 0;
while ($row = $result->fetch_assoc()) {
    $total_in += $row['qty_in'];
    $total_out += $row['qty_out'];
    $rows[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FLOUR MILL / STOCK LEDGER</title>
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
    <a href="ledger.php" class="btn-back">&#8592; Back to Stock Ledger</a>
    <form class="filter-form" method="GET">
        <label>Product:</label>
        <select name="product_id">
            <option value="">All Products</option>
            <?php $products->data_seek(0); while ($p = $products->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>" <?= $pid == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
            <?php endwhile; ?>
        </select>
        <button type="submit">Filter</button>
        <?php if ($pid > 0): ?>
            <a href="print_ledger.php" style="padding:5px 10px;font-size:12px;color:#dc3545;">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="print-header">
    <h2>FLOUR MILL</h2>
    <h4>STOCK LEDGER</h4>
    <?php if ($pid > 0): ?>
    <div class="date-range">Product: <?= htmlspecialchars($product_name) ?></div>
    <?php endif; ?>
</div>

<div class="info-row">
    <span><span class="label">Product:</span> <?= $pid > 0 ? htmlspecialchars($product_name) : 'All Products' ?></span>
    <span><span class="label">Printed on:</span> <?= date('d-M-Y H:i A') ?></span>
</div>

<?php if (empty($rows)): ?>
    <p style="text-align:center;padding:30px;color:#888;font-size:14px;">No stock entries found.</p>
<?php else: ?>

<table>
    <thead>
        <tr>
            <th width="30">#</th>
            <th width="80">Date</th>
            <th>Product</th>
            <th width="100">Type</th>
            <th class="text-right" width="100">In (KG)</th>
            <th class="text-right" width="100">Out (KG)</th>
            <th class="text-right" width="110">Balance (KG)</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1; foreach ($rows as $row): ?>
        <tr>
            <td class="text-center"><?= $i++ ?></td>
            <td><?= date('d-m-Y', strtotime($row['date'])) ?></td>
            <td><?= htmlspecialchars($row['product_name']) ?></td>
            <td><?= ucfirst(str_replace('_', ' ', $row['type'])) ?></td>
            <td class="text-right text-success font-bold"><?= $row['qty_in'] > 0 ? qty($row['qty_in']) : '-' ?></td>
            <td class="text-right text-danger font-bold"><?= $row['qty_out'] > 0 ? qty($row['qty_out']) : '-' ?></td>
            <td class="text-right font-bold"><?= qty($row['balance_qty']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="4" class="text-right">TOTAL</th>
            <th class="text-right"><?= qty($total_in) ?></th>
            <th class="text-right"><?= qty($total_out) ?></th>
            <th></th>
        </tr>
    </tfoot>
</table>

<table class="summary-table">
    <tr>
        <td class="font-bold">Total In:</td>
        <td class="text-right text-success font-bold"><?= qty($total_in) ?> KG</td>
    </tr>
    <tr>
        <td class="font-bold">Total Out:</td>
        <td class="text-right text-danger font-bold"><?= qty($total_out) ?> KG</td>
    </tr>
    <tr class="grand-row">
        <td>Net Movement:</td>
        <td class="text-right"><?= qty($total_in - $total_out) ?> KG</td>
    </tr>
</table>

<?php endif; ?>

<div class="footer">
    Flour Mill Management System &bull; Stock Ledger &bull; Generated <?= date('d-M-Y H:i:s A') ?>
</div>

<script>window.onload = function() { setTimeout(function(){ window.print(); }, 400); };</script>
</body>
</html>
