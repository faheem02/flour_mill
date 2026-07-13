<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';

$id = (int)($_GET['id'] ?? 0);
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

if (!$row) { echo json_encode(['error' => 'Not found']); exit; }

header('Content-Type: application/json');
echo json_encode($row, JSON_UNESCAPED_UNICODE);
