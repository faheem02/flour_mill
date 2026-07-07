<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';

$id = (int)($_GET['id'] ?? 0);
$arrival = $conn->query("SELECT * FROM wheat_arrivals WHERE id = $id")->fetch_assoc();
if (!$arrival) { header("Location: list.php"); exit; }

$conn->begin_transaction();
try {
    // Reverse warehouse stock
    $wheat = $conn->query("SELECT id FROM products WHERE name = 'Wheat (Gandam)' LIMIT 1")->fetch_assoc();
    if ($wheat && $arrival['warehouse_id'] > 0 && $arrival['net_weight'] > 0) {
        $pid = $wheat['id'];
        $conn->query("UPDATE warehouse_stock SET stock_qty = GREATEST(stock_qty - {$arrival['net_weight']}, 0) WHERE warehouse_id = {$arrival['warehouse_id']} AND product_id = $pid");
    }

    // Reverse booking received_qty
    if ($arrival['booking_id'] > 0 && $arrival['net_weight'] > 0) {
        $conn->query("UPDATE bookings SET received_qty = GREATEST(received_qty - {$arrival['net_weight']}, 0) WHERE id = {$arrival['booking_id']}");
        $conn->query("UPDATE bookings SET status = 'pending' WHERE id = {$arrival['booking_id']} AND received_qty <= 0 AND status != 'completed'");
        $conn->query("UPDATE bookings SET status = 'partial' WHERE id = {$arrival['booking_id']} AND received_qty > 0 AND received_qty < booked_qty");
    }

    $conn->query("DELETE FROM wheat_arrivals WHERE id = $id");
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
}

setFlash("Arrival deleted.");
header("Location: list.php");
exit;
