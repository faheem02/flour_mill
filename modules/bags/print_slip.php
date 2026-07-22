<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$page_title = 'Bag Slip';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$row = $conn->query("SELECT bsl.*, w.name as warehouse_name
    FROM bag_stock_ledger bsl
    JOIN warehouses w ON bsl.warehouse_id = w.id
    WHERE bsl.id = $id")->fetch_assoc();

if (!$row) { die("Entry not found"); }

$is_in = $row['qty_in'] > 0;
$type_label = $is_in ? 'BAGS RECEIVED (IN)' : 'BAGS DISPATCHED (OUT)';
$type_color = $is_in ? '#28a745' : '#dc3545';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bag <?= $is_in ? 'IN' : 'OUT' ?> Slip - #<?= $row['id'] ?></title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; color: #222; padding: 15px; background: #fff; }
.print-header { text-align: center; border-bottom: 3px double #1B2A4A; padding-bottom: 10px; margin-bottom: 15px; }
.print-header h2 { color: #1B2A4A; font-size: 22px; margin: 0; letter-spacing: 1px; }
.print-header h4 { font-size: 14px; font-weight: 700; margin: 4px 0 0; }
.print-header .date-range { font-size: 12px; color: #888; margin-top: 4px; }
.type-badge { display:inline-block; padding:4px 16px; border-radius:4px; font-size:12px; font-weight:700; color:#fff; }
.info-row { display: flex; gap: 30px; margin-bottom: 12px; font-size: 12px; flex-wrap: wrap; }
.info-row .label { font-weight: 700; color: #555; display: block; font-size: 10px; text-transform: uppercase; }
.info-row .value { font-weight: 600; font-size: 13px; }
.section-title { background: #1B2A4A; color: #fff; padding: 7px 12px; font-size: 13px; font-weight: 700; margin-top: 18px; margin-bottom: 0; border-radius: 4px 4px 0 0; }
table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
th, td { padding: 6px 10px; border: 1px solid #ccc; text-align: left; font-size: 11px; }
th { background: #e9ecef; color: #333; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
td { vertical-align: middle; }
.text-right { text-align: right; }
.font-bold { font-weight: 700; }
.text-success { color: #28a745; }
.text-danger { color: #dc3545; }
.summary-table { width: 350px; margin-left: auto; margin-top: 10px; }
.summary-table td { padding: 5px 10px; font-size: 12px; }
.summary-table .grand-row td { border-top: 2px solid #1B2A4A; font-size: 14px; font-weight: 800; color: #1B2A4A; background: #eef2f7 !important; }
.footer { margin-top: 20px; padding-top: 10px; border-top: 1px dashed #ccc; text-align: center; font-size: 10px; color: #999; }
.no-print { margin-bottom: 15px; }
.no-print button, .no-print a { padding: 6px 16px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; margin-right: 6px; text-decoration: none; display: inline-block; }
.no-print .btn-print { background: #1B2A4A; color: #fff; }
.no-print .btn-back { background: #e9ecef; color: #333; }
@media print { body { padding: 0; font-size: 11px; } .no-print { display: none !important; } @page { margin: 0.4in; size: portrait; } th { background: #e9ecef !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .summary-table .grand-row td { background: #eef2f7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128424; Print</button>
    <a href="ledger.php" class="btn-back">&#8592; Back to Ledger</a>
</div>

<div class="print-header">
    <h2>FLOUR MILL</h2>
    <h4 style="color:<?= $type_color ?>"><?= $type_label ?></h4>
    <div class="date-range">Slip #<?= $row['id'] ?> | <?= date('d-M-Y', strtotime($row['date'])) ?></div>
</div>

<div class="info-row">
    <div>
        <span class="label">Warehouse</span>
        <span class="value"><?= htmlspecialchars($row['warehouse_name']) ?></span>
    </div>
    <div>
        <span class="label">Date</span>
        <span class="value"><?= date('d-M-Y', strtotime($row['date'])) ?></span>
    </div>
    <div>
        <span class="label">Type</span>
        <span class="value"><span class="type-badge" style="background:<?= $type_color ?>"><?= $is_in ? 'IN' : 'OUT' ?></span></span>
    </div>
</div>

<div class="section-title">&#128230; Transaction Details</div>
<table>
    <thead>
        <tr>
            <th>Description</th>
            <th class="text-right" width="150">Value</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($is_in): ?>
        <tr>
            <td class="font-bold">Bags Received</td>
            <td class="text-right font-bold text-success"><?= number_format($row['qty_in']) ?> Bags</td>
        </tr>
        <?php else: ?>
        <tr>
            <td class="font-bold">Bags Dispatched</td>
            <td class="text-right font-bold text-danger"><?= number_format($row['qty_out']) ?> Bags</td>
        </tr>
        <?php endif; ?>
        <?php if ($row['rate'] > 0): ?>
        <tr>
            <td>Rate per Bag</td>
            <td class="text-right">Rs <?= money($row['rate']) ?></td>
        </tr>
        <tr>
            <td class="font-bold">Total Amount</td>
            <td class="text-right font-bold">Rs <?= money(($row['qty_in'] ?: $row['qty_out']) * $row['rate']) ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<table class="summary-table">
    <tr>
        <td class="font-bold"><?= $is_in ? 'Received:' : 'Dispatched:' ?></td>
        <td class="text-right font-bold <?= $is_in ? 'text-success' : 'text-danger' ?>"><?= number_format($row['qty_in'] ?: $row['qty_out']) ?> Bags</td>
    </tr>
    <tr>
        <td class="font-bold">Balance After:</td>
        <td class="text-right font-bold"><?= number_format($row['balance_qty']) ?> Bags</td>
    </tr>
    <?php if ($row['rate'] > 0): ?>
    <tr class="grand-row">
        <td>Total Amount:</td>
        <td class="text-right">Rs <?= money(($row['qty_in'] ?: $row['qty_out']) * $row['rate']) ?></td>
    </tr>
    <?php endif; ?>
</table>

<?php if ($row['notes']): ?>
<div class="section-title">&#128221; Notes</div>
<p><?= nl2br(htmlspecialchars($row['notes'])) ?></p>
<?php endif; ?>

<div class="footer">
    Flour Mill Management System &bull; Bag <?= $is_in ? 'IN' : 'OUT' ?> Slip &bull; #<?= $row['id'] ?> &bull; Generated <?= date('d-M-Y H:i:s A') ?>
</div>

<script>window.onload = function() { setTimeout(function(){ window.print(); }, 400); };</script>
</body>
</html>
