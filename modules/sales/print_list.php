<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';

$sql = "SELECT s.*, c.name as customer_name, c.phone as cust_phone,
    w.name as warehouse_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN warehouses w ON s.warehouse_id = w.id
    WHERE 1=1";
if ($date_from) $sql .= " AND s.date >= '" . $conn->real_escape_string($date_from) . "'";
if ($date_to)   $sql .= " AND s.date <= '" . $conn->real_escape_string($date_to) . "'";
$sql .= " ORDER BY s.date ASC, s.id ASC";
$result = $conn->query($sql);

$grand_total = 0;
$grand_paid = 0;
$grand_qty = 0;
$rows = [];
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
    $grand_total += $r['total_amount'];
    $grand_paid  += $r['paid_amount'];
    $grand_qty   += $r['total_qty'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sales Register</title>
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
    tr:hover td { background: #eef2f7; }

    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-bold { font-weight: 700; }
    .text-success { color: #28a745; }
    .text-danger { color: #dc3545; }
    .text-muted { color: #888; }

    .type-badge { display: inline-block; padding: 1px 8px; border-radius: 3px; font-size: 9px; font-weight: 700; color: #fff; }
    .type-delivery { background: #4e73df; }
    .type-pickup { background: #858796; }

    .summary-table { width: 300px; margin-left: auto; }
    .summary-table td { padding: 5px 10px; font-size: 12px; }
    .summary-table .label { font-weight: 600; color: #555; }
    .summary-table .grand-row td { border-top: 2px solid #1B2A4A; font-size: 14px; font-weight: 800; color: #1B2A4A; background: #eef2f7 !important; }

    .footer { margin-top: 20px; padding-top: 10px; border-top: 1px dashed #ccc; text-align: center; font-size: 10px; color: #999; }

    .no-print { margin-bottom: 15px; }
    .no-print button, .no-print a { padding: 6px 16px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; margin-right: 6px; }
    .no-print .btn-print { background: #1B2A4A; color: #fff; }
    .no-print .btn-back { background: #e9ecef; color: #333; }
    .no-print .filter-form { display: inline-flex; align-items: center; gap: 8px; margin-left: 15px; }
    .no-print .filter-form input { padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; }
    .no-print .filter-form button { padding: 5px 14px; background: #4e73df; color: #fff; border-radius: 4px; font-size: 12px; }

    @media print {
        body { padding: 0; font-size: 11px; }
        .no-print { display: none !important; }
        @page { margin: 0.4in; size: landscape; }
        tr:nth-child(even) td { background: #f8f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        th { background: #1B2A4A !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .summary-table .grand-row td { background: #eef2f7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128424; Print</button>
    <a href="list.php" class="btn-back">&#8592; Back to Sales List</a>
    <form class="filter-form" method="GET">
        <label>From:</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
        <label>To:</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
        <button type="submit">Filter</button>
        <?php if ($date_from || $date_to): ?>
            <a href="print_list.php" style="padding:5px 10px;font-size:12px;color:#dc3545;">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="print-header">
    <h2>FLOUR MILL</h2>
    <h4>SALES REGISTER</h4>
    <div class="date-range">
        <?php if ($date_from || $date_to): ?>
            <?= $date_from ? date('d-M-Y', strtotime($date_from)) : 'Beginning' ?> &mdash; <?= $date_to ? date('d-M-Y', strtotime($date_to)) : 'Today' ?>
        <?php else: ?>
            All Sales (<?= count($rows) ?> records)
        <?php endif; ?>
    </div>
</div>

<div class="info-row">
    <span><span class="label">Total Invoices:</span> <?= count($rows) ?></span>
    <span><span class="label">Printed on:</span> <?= date('d-M-Y H:i A') ?></span>
</div>

<table>
    <thead>
        <tr>
            <th width="30">#</th>
            <th width="80">Date</th>
            <th width="100">Invoice #</th>
            <th>Customer</th>
            <th class="text-center" width="65">Type</th>
            <th class="text-right" width="80">Qty (KG)</th>
            <th class="text-right" width="70">Freight</th>
            <th class="text-right" width="90">Total (Rs)</th>
            <th class="text-right" width="80">Paid (Rs)</th>
            <th class="text-right" width="90">Due (Rs)</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="10" class="text-center text-muted" style="padding:20px;">No sales found.</td></tr>
        <?php else: ?>
        <?php $i = 1; foreach ($rows as $row): ?>
        <?php $due = $row['total_amount'] - $row['paid_amount']; ?>
        <tr>
            <td class="text-center"><?= $i++ ?></td>
            <td><?= date('d-m-Y', strtotime($row['date'])) ?></td>
            <td class="font-bold"><?= htmlspecialchars($row['invoice_no']) ?></td>
            <td>
                <?= htmlspecialchars($row['customer_name']) ?>
                <?php if ($row['cust_phone']): ?>
                    <br><span class="text-muted" style="font-size:10px;"><?= htmlspecialchars($row['cust_phone']) ?></span>
                <?php endif; ?>
            </td>
            <td class="text-center">
                <?php if ($row['delivery_type'] === 'delivery'): ?>
                    <span class="type-badge type-delivery">DELIVERY</span>
                <?php else: ?>
                    <span class="type-badge type-pickup">PICKUP</span>
                <?php endif; ?>
            </td>
            <td class="text-right font-bold"><?= qty($row['total_qty']) ?></td>
            <td class="text-right"><?= ($row['freight_amount'] ?? 0) > 0 ? money($row['freight_amount']) : '-' ?></td>
            <td class="text-right font-bold"><?= money($row['total_amount']) ?></td>
            <td class="text-right <?= $row['paid_amount'] > 0 ? 'text-success font-bold' : 'text-muted' ?>"><?= money($row['paid_amount']) ?></td>
            <td class="text-right <?= $due > 0 ? 'text-danger font-bold' : 'text-success' ?>"><?= money($due) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php if (!empty($rows)): ?>
<table class="summary-table">
    <tr>
        <td class="label">Total Qty:</td>
        <td class="text-right font-bold"><?= qty($grand_qty) ?> KG</td>
    </tr>
    <tr>
        <td class="label">Total Sales:</td>
        <td class="text-right font-bold">Rs <?= money($grand_total) ?></td>
    </tr>
    <tr>
        <td class="label">Total Received:</td>
        <td class="text-right font-bold text-success">Rs <?= money($grand_paid) ?></td>
    </tr>
    <tr class="grand-row">
        <td>Total Due:</td>
        <td class="text-right <?= ($grand_total - $grand_paid) > 0 ? 'text-danger' : 'text-success' ?>">Rs <?= money($grand_total - $grand_paid) ?></td>
    </tr>
</table>
<?php endif; ?>

<div class="footer">
    Flour Mill Management System &bull; Sales Register &bull; Generated <?= date('d-M-Y H:i:s A') ?>
</div>

<script>window.onload = function() { setTimeout(function(){ window.print(); }, 400); };</script>
</body>
</html>
