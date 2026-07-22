<?php
session_start();
header('Content-Type: application/json');
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'Unauthorized']); exit; }
require_once '../../includes/db.php';

$name = sanitize($_POST['name'] ?? '');
$category = sanitize($_POST['category'] ?? 'Other');
$sale_price = str_replace(',', '', $_POST['sale_price'] ?? '0');

if (empty($name)) { echo json_encode(['error' => 'Product name is required']); exit; }

$existing = $conn->query("SELECT id, name FROM products WHERE name = '" . $name . "' LIMIT 1");
if ($existing->num_rows > 0) {
    $row = $existing->fetch_assoc();
    echo json_encode(['id' => $row['id'], 'name' => $row['name'], 'duplicate' => true]);
    exit;
}

$conn->query("INSERT INTO products (name, category, sale_price, status) VALUES ('$name', '$category', '$sale_price', 'active')");
echo json_encode(['id' => $conn->insert_id, 'name' => $name]);
