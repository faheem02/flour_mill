<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$page_title = 'Arrival Slip';
require_once '../../includes/db.php';

$id = (int)$_GET['id'];
$row = $conn->query("SELECT a.*, w.name as warehouse_name, 
    b.name as bag_type_name, br.name as broker_name,
    bk.booking_no, f.name as farmer_name
    FROM wheat_arrivals a
    LEFT JOIN warehouses w ON a.warehouse_id = w.id
    LEFT JOIN bag_types b ON a.bag_type_id = b.id
    LEFT JOIN brokers br ON a.broker_id = br.id
    LEFT JOIN bookings bk ON a.booking_id = bk.id
    LEFT JOIN farmers f ON bk.farmer_id = f.id
    WHERE a.id = $id")->fetch_assoc();

if (!$row) { die("Arrival not found"); }
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
        table { width: 100%; }
        td { padding: 4px 8px; }
        .label { font-weight: bold; width: 150px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="header">
        <h3>Flour Mill - Wheat Arrival Slip</h3>
        <small>Date: <?= $row['date'] ?></small>
    </div>

    <table>
        <tr><td class="label">Booking No:</td><td><?= htmlspecialchars($row['booking_no'] ?? '-') ?></td></tr>
        <tr><td class="label">Farmer:</td><td><?= htmlspecialchars($row['farmer_name'] ?? '-') ?></td></tr>
        <tr><td class="label">Warehouse:</td><td><?= htmlspecialchars($row['warehouse_name']) ?></td></tr>
        <tr><td class="label">Bag Type:</td><td><?= htmlspecialchars($row['bag_type_name']) ?></td></tr>
        <tr><td class="label">No of Bags:</td><td><?= $row['num_bags'] ?></td></tr>
        <tr><td class="label">Gross Weight:</td><td><?= qty($row['gross_weight']) ?> KG</td></tr>
        <tr><td class="label">Bag Weight:</td><td><?= qty($row['bag_weight']) ?> KG</td></tr>
        <tr><td class="label">Net Weight:</td><td><strong><?= qty($row['net_weight']) ?></strong> KG</td></tr>
        <tr><td class="label">Moisture:</td><td><?= $row['moisture_pct'] ? $row['moisture_pct'] . '%' : '-' ?></td></tr>
        <tr><td class="label">Quality:</td><td><?= $row['quality_grade'] ?: '-' ?></td></tr>
        <tr><td class="label">Broker:</td><td><?= htmlspecialchars($row['broker_name']) ?: '-' ?></td></tr>
    </table>

    <div class="mt-4 no-print">
        <button class="btn btn-primary" onclick="window.print()">Print</button>
        <a href="list.php" class="btn btn-secondary">Back</a>
    </div>

    <script>
        window.onload = function() { setTimeout(function() { window.print(); }, 500); }
    </script>
</body>
</html>
