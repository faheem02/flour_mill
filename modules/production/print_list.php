<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';

$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$generated_at = date('d-M-Y h:i A');

$where = "WHERE 1=1";
if ($date_from !== '') { $where .= " AND p.date >= '" . $conn->real_escape_string($date_from) . "'"; }
if ($date_to !== '') { $where .= " AND p.date <= '" . $conn->real_escape_string($date_to) . "'"; }

$result = $conn->query("SELECT p.* FROM productions p $where ORDER BY p.date ASC, p.id ASC");

$total_batches = 0;
$total_wheat = 0;
$total_output = 0;
$total_wastage = 0;
$rows = [];
while ($row = $result->fetch_assoc()) {
    $total_batches++;
    $total_wheat += $row['wheat_qty'];
    $total_output += $row['total_output'];
    $total_wastage += $row['wastage_qty'];

    $items = $conn->query("SELECT pi.qty, pi.rate_per_kg, pr.name FROM production_items pi LEFT JOIN products pr ON pi.product_id=pr.id WHERE pi.production_id=" . (int)$row['id']);
    $products_list = [];
    while ($it = $items->fetch_assoc()) {
        $rate_text = $it['rate_per_kg'] > 0 ? ' @ Rs ' . number_format($it['rate_per_kg'], 2) : '';
        $products_list[] = htmlspecialchars($it['name']) . ': ' . number_format($it['qty'], 3) . ' KG' . $rate_text;
    }
    $row['products_str'] = implode(' | ', $products_list);
    $rows[] = $row;
}

$avg_extraction = $total_wheat > 0 ? round(($total_output / $total_wheat) * 100, 2) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FLOUR MILL / PRODUCTION REPORT</title>
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
        .summary-table { width: 320px; margin-left: auto; }
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
        @media print { body { padding: 0; font-size: 11px; } .no-print { display: none !important; } @page { margin: 0.4in; size: landscape; } tr:nth-child(even) td { background: #f8f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { background: #1B2A4A !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .summary-table .grand-row td { background: #eef2f7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } tfoot td, tfoot th { background: #eef2f7 !important; font-weight: 700 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">Print</button>
    <a href="list.php" class="btn-back">Back to List</a>
    <form class="filter-form" method="GET">
        <label>From:</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
        <label>To:</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
        <button type="submit">Filter</button>
    </form>
</div>

<div class="print-header">
    <h2>FLOUR MILL / PRODUCTION REPORT</h2>
    <h4>Production History</h4>
    <?php if ($date_from !== '' || $date_to !== ''): ?>
    <div class="date-range">
        Period: <?= $date_from !== '' ? date('d-M-Y', strtotime($date_from)) : 'Start' ?> to <?= $date_to !== '' ? date('d-M-Y', strtotime($date_to)) : 'End' ?>
    </div>
    <?php endif; ?>
</div>

<div class="info-row">
    <div><span class="label">Total Batches:</span> <?= $total_batches ?></div>
    <div><span class="label">Generated:</span> <?= $generated_at ?></div>
</div>

<table>
    <thead>
        <tr>
            <th class="text-center" width="40">#</th>
            <th width="90">Date</th>
            <th class="text-right" width="100">Wheat (KG)</th>
            <th class="text-right" width="100">Output (KG)</th>
            <th class="text-right" width="100">Wastage (KG)</th>
            <th class="text-right" width="90">Extraction %</th>
            <th>Products</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $i => $row): ?>
        <tr>
            <td class="text-center"><?= $i + 1 ?></td>
            <td><?= date('d-M-Y', strtotime($row['date'])) ?></td>
            <td class="text-right"><?= number_format($row['wheat_qty'], 3) ?></td>
            <td class="text-right"><?= number_format($row['total_output'], 3) ?></td>
            <td class="text-right <?= $row['wastage_qty'] > 0 ? 'text-danger' : '' ?>"><?= number_format($row['wastage_qty'], 3) ?></td>
            <td class="text-right font-bold"><?= number_format($row['extraction_rate'], 1) ?>%</td>
            <td><small><?= $row['products_str'] ?></small></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr>
            <td colspan="7" class="text-center text-muted">No production records found.</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<table class="summary-table">
    <tr>
        <td class="font-bold">Total Batches</td>
        <td class="text-right font-bold"><?= $total_batches ?></td>
    </tr>
    <tr>
        <td class="font-bold">Total Wheat Crushed (KG)</td>
        <td class="text-right font-bold"><?= number_format($total_wheat, 3) ?></td>
    </tr>
    <tr>
        <td class="font-bold">Total Output (KG)</td>
        <td class="text-right font-bold"><?= number_format($total_output, 3) ?></td>
    </tr>
    <tr>
        <td class="font-bold text-danger">Total Wastage (KG)</td>
        <td class="text-right font-bold text-danger"><?= number_format($total_wastage, 3) ?></td>
    </tr>
    <tr class="grand-row">
        <td>Avg Extraction %</td>
        <td class="text-right"><?= number_format($avg_extraction, 2) ?>%</td>
    </tr>
</table>

<div class="footer">
    Flour Mill Management System | Production Report | Generated <?= $generated_at ?>
</div>

<script>
window.onload = function() { setTimeout(function(){ window.print(); }, 400); };
</script>
</body>
</html>
