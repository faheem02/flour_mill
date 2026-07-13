<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { exit; }
require_once '../../includes/db.php';

$wh = (int)($_GET['wh'] ?? 0);
$pid = (int)($_GET['pid'] ?? 0);

header('Content-Type: application/json');

if ($wh <= 0 || $pid <= 0) {
    echo json_encode(['stock' => 0]);
    exit;
}

$row = $conn->query("SELECT COALESCE(stock_qty,0) AS s FROM warehouse_stock WHERE warehouse_id = $wh AND product_id = $pid")->fetch_assoc();
echo json_encode(['stock' => (float)$row['s']]);