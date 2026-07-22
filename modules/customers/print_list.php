<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$customers = $conn->query("SELECT * FROM customers ORDER BY name ASC");

$rows = [];
$total_receivable = 0;
while ($row = $customers->fetch_assoc()) {
    if ($row['balance'] > 0) {
        $total_receivable += $row['balance'];
    }
    $rows[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FLOUR MILL / CUSTOMER DIRECTORY</title>
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
    <a href="list.php" class="btn-back">&#8592; Back to Customers</a>
</div>

<div class="print-header">
    <h2>FLOUR MILL</h2>
    <h4>CUSTOMER DIRECTORY</h4>
    <div class="date-range">Master List &mdash; All Customers</div>
</div>

<div class="info-row">
    <span><span class="label">Total Customers:</span> <?= count($rows) ?></span>
    <span><span class="label">Printed on:</span> <?= date('d-M-Y H:i A') ?></span>
</div>

<?php if (empty($rows)): ?>
    <p style="text-align:center;padding:30px;color:#888;font-size:14px;">No customers found.</p>
<?php else: ?>

<table>
    <thead>
        <tr>
            <th width="30">#</th>
            <th>Name</th>
            <th>Business</th>
            <th>Phone</th>
            <th class="text-right" width="110">Balance (Rs)</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1; foreach ($rows as $row): ?>
        <tr>
            <td class="text-center"><?= $i++ ?></td>
            <td class="font-bold"><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['business_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['phone'] ?? '-') ?></td>
            <td class="text-right <?= ($row['balance'] ?? 0) > 0 ? 'text-danger font-bold' : 'text-success' ?>">
                <?= money($row['balance'] ?? 0) ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<table class="summary-table">
    <tr>
        <td class="font-bold">Total Customers:</td>
        <td class="text-right"><?= count($rows) ?></td>
    </tr>
    <tr class="grand-row">
        <td>Total Receivable:</td>
        <td class="text-right text-danger">Rs <?= money($total_receivable) ?></td>
    </tr>
</table>

<?php endif; ?>

<div class="footer">
    Flour Mill Management System &bull; Customer Directory &bull; Generated <?= date('d-M-Y H:i:s A') ?>
</div>

<script>window.onload = function() { setTimeout(function(){ window.print(); }, 400); };</script>
</body>
</html>
