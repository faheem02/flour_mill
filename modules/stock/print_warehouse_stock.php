<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$warehouses = $conn->query("SELECT id, name, type, location FROM warehouses WHERE status='active' ORDER BY name");

$wh_data = [];
$grand_total_stock = 0;
$grand_total_products = 0;

while ($wh = $warehouses->fetch_assoc()) {
    $wh_id = $wh['id'];
    $total_wh = $conn->query("SELECT COALESCE(SUM(stock_qty),0) as t FROM warehouse_stock WHERE warehouse_id = $wh_id")->fetch_assoc()['t'];
    $stock = $conn->query("SELECT ws.stock_qty, p.name, p.category
        FROM warehouse_stock ws
        JOIN products p ON ws.product_id = p.id
        WHERE ws.warehouse_id = $wh_id AND ws.stock_qty > 0
        ORDER BY p.name");
    $items = [];
    while ($s = $stock->fetch_assoc()) {
        $items[] = $s;
        $grand_total_products++;
    }
    $grand_total_stock += $total_wh;
    $wh_data[] = ['info' => $wh, 'total' => $total_wh, 'items' => $items];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Warehouse Stock</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; color: #222; padding: 15px; background: #fff; }
.print-header { text-align: center; border-bottom: 3px double #1B2A4A; padding-bottom: 10px; margin-bottom: 15px; }
.print-header h2 { color: #1B2A4A; font-size: 22px; margin: 0; letter-spacing: 1px; }
.print-header h4 { color: #555; font-size: 14px; font-weight: 600; margin: 4px 0 0; }
.print-header .date-range { font-size: 12px; color: #888; margin-top: 4px; }
.info-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 12px; }
.info-row .label { font-weight: 700; color: #555; }
.wh-section { margin-bottom: 20px; page-break-inside: avoid; }
.wh-title { background: #1B2A4A; color: #fff; padding: 8px 14px; font-size: 14px; font-weight: 700; border-radius: 4px 4px 0 0; display: flex; justify-content: space-between; align-items: center; }
.wh-title .wh-badge { background: rgba(255,255,255,0.2); padding: 2px 10px; border-radius: 3px; font-size: 12px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
th, td { padding: 6px 10px; border: 1px solid #ccc; text-align: left; font-size: 11px; }
th { background: #e9ecef; color: #333; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
td { vertical-align: middle; }
tr:nth-child(even) td { background: #f8f9fc; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.font-bold { font-weight: 700; }
.text-success { color: #28a745; }
.text-danger { color: #dc3545; }
.text-muted { color: #888; }
.no-stock { padding: 12px; text-align: center; color: #999; font-style: italic; background: #fafafa; border: 1px solid #eee; }
.summary-table { width: 350px; margin-left: auto; margin-top: 10px; }
.summary-table td { padding: 5px 10px; font-size: 12px; }
.summary-table .grand-row td { border-top: 2px solid #1B2A4A; font-size: 14px; font-weight: 800; color: #1B2A4A; background: #eef2f7 !important; }
.footer { margin-top: 20px; padding-top: 10px; border-top: 1px dashed #ccc; text-align: center; font-size: 10px; color: #999; }
.no-print { margin-bottom: 15px; }
.no-print button, .no-print a { padding: 6px 16px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; margin-right: 6px; text-decoration: none; display: inline-block; }
.no-print .btn-print { background: #1B2A4A; color: #fff; }
.no-print .btn-back { background: #e9ecef; color: #333; }
@media print { body { padding: 0; font-size: 11px; } .no-print { display: none !important; } @page { margin: 0.4in; size: portrait; } tr:nth-child(even) td { background: #f8f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .wh-title { background: #1B2A4A !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { background: #e9ecef !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .summary-table .grand-row td { background: #eef2f7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128424; Print</button>
    <a href="warehouse_stock.php" class="btn-back">&#8592; Back to Warehouse Stock</a>
</div>

<div class="print-header">
    <h2>FLOUR MILL</h2>
    <h4>WAREHOUSE STOCK REPORT</h4>
    <div class="date-range">Generated on <?= date('d-M-Y h:i A') ?></div>
</div>

<div class="info-row">
    <span><span class="label">Total Warehouses:</span> <?= count($wh_data) ?></span>
    <span><span class="label">Total Stock Items:</span> <?= $grand_total_products ?></span>
    <span><span class="label">Total Stock:</span> <?= qty($grand_total_stock) ?> KG</span>
</div>

<?php if (empty($wh_data)): ?>
    <p style="text-align:center;padding:30px;color:#888;font-size:14px;">No warehouses found.</p>
<?php else: foreach ($wh_data as $wd): ?>
<div class="wh-section">
    <div class="wh-title">
        <span><i class="fas fa-building"></i> <?= htmlspecialchars($wd['info']['name']) ?> <small>(<?= ucfirst($wd['info']['type']) ?>)</small></span>
        <span class="wh-badge"><?= qty($wd['total']) ?> KG</span>
    </div>
    <?php if (!empty($wd['items'])): ?>
    <table>
        <thead>
            <tr>
                <th width="30">#</th>
                <th>Product</th>
                <th>Category</th>
                <th class="text-right" width="130">Stock (KG)</th>
            </tr>
        </thead>
        <tbody>
            <?php $j = 1; foreach ($wd['items'] as $item): ?>
            <tr>
                <td class="text-center"><?= $j++ ?></td>
                <td class="font-bold"><?= htmlspecialchars($item['name']) ?></td>
                <td><?= htmlspecialchars($item['category']) ?></td>
                <td class="text-right font-bold"><?= qty($item['stock_qty']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="no-stock">No stock in this warehouse.</div>
    <?php endif; ?>
</div>
<?php endforeach; endif; ?>

<table class="summary-table">
    <tr>
        <td class="font-bold">Total Warehouses:</td>
        <td class="text-right"><?= count($wh_data) ?></td>
    </tr>
    <tr>
        <td class="font-bold">Total Stock Items:</td>
        <td class="text-right"><?= $grand_total_products ?></td>
    </tr>
    <tr class="grand-row">
        <td>Total Stock:</td>
        <td class="text-right"><?= qty($grand_total_stock) ?> KG</td>
    </tr>
</table>

<div class="footer">
    Flour Mill Management System &bull; Warehouse Stock Report &bull; Generated <?= date('d-M-Y H:i:s A') ?>
</div>

<script>window.onload = function() { setTimeout(function(){ window.print(); }, 400); };</script>
</body>
</html>
