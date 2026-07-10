<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';

$id = (int)($_GET['id'] ?? 0);
$booking = $conn->query("
    SELECT b.*, f.name AS farmer_name
    FROM bookings b
    JOIN farmers f ON b.farmer_id = f.id
    WHERE b.id = $id
")->fetch_assoc();

if (!$booking) {
    echo json_encode(['error' => 'Not found']);
    exit;
}

$bag = $conn->query("
    SELECT bb.*, bt.name AS bag_type_name
    FROM booking_bags bb
    JOIN bag_types bt ON bb.bag_type_id = bt.id
    WHERE bb.booking_id = $id
")->fetch_assoc();

$booking['bag'] = $bag;

header('Content-Type: application/json');
echo json_encode($booking, JSON_UNESCAPED_UNICODE);
