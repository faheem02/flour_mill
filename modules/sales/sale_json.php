<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';

$id = (int)($_GET['id'] ?? 0);
$row = $conn->query("SELECT s.*, c.name as customer_name, c.balance as cust_balance, c.phone as cust_phone,
    w.name as warehouse_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN warehouses w ON s.warehouse_id = w.id
    WHERE s.id = $id")->fetch_assoc();

if (!$row) { echo json_encode(['error' => 'Not found']); exit; }

$items = [];
$r = $conn->query("SELECT si.*, p.name as product_name FROM sale_items si JOIN products p ON si.product_id=p.id WHERE si.sale_id=$id");
while ($ir = $r->fetch_assoc()) { $items[] = $ir; }

$row['items'] = $items;

header('Content-Type: application/json');
echo json_encode($row, JSON_UNESCAPED_UNICODE);
