<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$page_title = 'Arrival Slip';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$id = (int)$_GET['id'];
$row = $conn->query("SELECT a.*, w.name as warehouse_name, 
    b.name as bag_type_name,
    bk.booking_no, f.name as farmer_name,
    d.name as driver_name
    FROM wheat_arrivals a
    LEFT JOIN warehouses w ON a.warehouse_id = w.id
    LEFT JOIN bag_types b ON a.bag_type_id = b.id
    LEFT JOIN bookings bk ON a.booking_id = bk.id
    LEFT JOIN farmers f ON bk.farmer_id = f.id
    LEFT JOIN drivers d ON a.driver_id = d.id
    WHERE a.id = $id")->fetch_assoc();

if (!$row) { die("Arrival not found"); }

$charges = ($row['bag_amount']??0) + ($row['labour_charges']??0) + ($row['transport_charges']??0) + ($row['other_charges']??0);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Arrival Slip</title>
    <link href="<?= $asset_path ?>vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Courier New', monospace; font-size: 14px; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        .header h3 { margin: 0; }
        .header small { color: #666; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 5px 8px; }
        .label { font-weight: bold; width: 180px; }
        .section-title { background: #f0f0f0; font-weight: bold; text-align: center; padding: 6px; margin-top: 12px; margin-bottom: 8px; border-top: 1px solid #000; border-bottom: 1px solid #000; }
        .amt { text-align: right; padding-right: 20px; }
        .total { font-weight: bold; border-top: 2px solid #000; }
        .net { font-size: 16px; font-weight: bold; color: #27ae60; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="header">
        <h3>Flour Mill - Wheat Arrival Slip</h3>
        <small>Date: <?= date('d-m-Y', strtotime($row['date'])) ?> | Slip #<?= $row['id'] ?></small>
    </div>

    <table>
        <tr><td class="label">Booking No:</td><td><?= htmlspecialchars($row['booking_no'] ?? '-') ?></td>
            <td class="label">Weight Slip No:</td><td><?= htmlspecialchars($row['weight_slip_no'] ?? '-') ?></td></tr>
        <tr><td class="label">Farmer:</td><td><?= htmlspecialchars($row['farmer_name'] ?? '-') ?></td>
            <td class="label">Vehicle No:</td><td><?= htmlspecialchars($row['vehicle_no'] ?? '-') ?></td></tr>
        <tr><td class="label">Driver:</td><td><?= htmlspecialchars($row['driver_name'] ?? '-') ?></td>
            <td class="label">Warehouse:</td><td><?= htmlspecialchars($row['warehouse_name']) ?></td></tr>
        <tr><td class="label">Bag Type:</td><td><?= htmlspecialchars($row['bag_type_name'] ?? '-') ?></td></tr>
    </table>

    <div class="section-title">Weight Details</div>
    <table>
        <tr><td class="label">No of Bags:</td><td><?= $row['num_bags'] ?></td>
            <td class="label">Wheat Weight:</td><td><?= qty($row['gross_weight'] ?? 0) ?> KG</td></tr>
        <tr><td class="label">Katt Applied:</td><td><?= qty($row['katt_applied'] ?? 0) ?> KG</td>
            <td class="label">Net Weight:</td><td><strong><?= qty($row['net_weight']) ?> KG</strong></td></tr>
        <tr><td class="label">Actual Weight:</td><td><?= qty($row['actual_weight']) ?> KG</td>
            <td class="label">Difference:</td><td class="<?= ($row['weight_diff'] ?? 0) < 0 ? 'text-danger' : '' ?>"><?= ($row['weight_diff'] ?? 0) >= 0 ? '+' : '' ?><?= qty($row['weight_diff'] ?? 0) ?> KG</td></tr>
        <tr><td class="label">Moisture:</td><td><?= $row['moisture_pct'] ? $row['moisture_pct'] . '%' : '-' ?></td>
            <td></td><td></td></tr>
    </table>

    <div class="section-title">Financial Summary</div>
    <table>
        <tr><td class="label">Gross Amount <small>(<?= qty($row['actual_weight'] ?: $row['net_weight']) ?> KG ÷ 40 × Rate)</small>:</td><td class="amt"><?= money($row['gross_amount']) ?></td></tr>
        <tr><td class="label">Bag Amount:</td><td class="amt"><?= money($row['bag_amount']) ?></td></tr>
        <tr><td class="label">Labour Charges:</td><td class="amt"><?= money($row['labour_charges']) ?></td></tr>
        <tr><td class="label">Transport Charges:</td><td class="amt"><?= money($row['transport_charges']) ?></td></tr>
        <tr><td class="label">Other Charges:</td><td class="amt"><?= money($row['other_charges']) ?></td></tr>
        <tr><td class="label">Total Deductions:</td><td class="amt"><?= money($charges) ?></td></tr>
        <tr class="total"><td class="label">Net Amount:</td><td class="amt net"><?= money($row['net_amount']) ?></td></tr>
        <?php if (($row['payment_now'] ?? 0) > 0): ?>
        <tr><td class="label">Paid on Arrival:</td><td class="amt" style="color:#1a73e8;font-weight:bold"><?= money($row['payment_now']) ?></td></tr>
        <?php endif; ?>
    </table>

    <?php if ($row['notes']): ?>
    <p class="mt-3"><strong>Notes:</strong> <?= nl2br(htmlspecialchars($row['notes'])) ?></p>
    <?php endif; ?>

    <div class="mt-4 no-print">
        <button class="btn btn-primary" onclick="window.print()">Print</button>
        <a href="list.php" class="btn btn-secondary">Back</a>
    </div>

    <script>
        window.onload = function() { setTimeout(function() { window.print(); }, 500); }
    </script>
</body>
</html>
