<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';

header('Content-Type: application/json');

$warehouse_id = (int)($_GET['warehouse_id'] ?? 0);

if ($warehouse_id <= 0) {
    echo json_encode(['qty' => 0]);
    exit;
}

$row = $conn->query("SELECT qty FROM bag_stock WHERE warehouse_id=$warehouse_id")->fetch_assoc();
echo json_encode(['qty' => $row ? (int)$row['qty'] : 0]);
