<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { exit; }
require_once '../../includes/db.php';

$wh = (int)($_GET['warehouse_id'] ?? 0);

header('Content-Type: application/json');

if ($wh <= 0) {
    echo json_encode([]);
    exit;
}

$result = $conn->query("
    SELECT p.id, p.name, p.sale_price, p.cost_rate,
           COALESCE(ws.stock_qty, 0) AS stock_qty,
           (SELECT pi.rate_per_kg FROM production_items pi
            JOIN productions pr ON pi.production_id = pr.id
            WHERE pi.product_id = p.id
            ORDER BY pr.date DESC, pr.id DESC LIMIT 1) AS latest_cost_rate
    FROM products p
    LEFT JOIN warehouse_stock ws ON ws.product_id = p.id AND ws.warehouse_id = $wh
    WHERE p.status = 'active' AND p.name != 'Wheat (Gandam)'
    HAVING stock_qty > 0
    ORDER BY p.name
");

$products = [];
while ($row = $result->fetch_assoc()) {
    $rate = ($row['latest_cost_rate'] ?: $row['cost_rate']) ?: $row['sale_price'];
    $products[] = [
        'id'         => (int)$row['id'],
        'name'       => $row['name'],
        'stock_qty'  => (float)$row['stock_qty'],
        'rate'       => (float)$rate,
        'sale_price' => (float)$row['sale_price'],
    ];
}

echo json_encode($products, JSON_UNESCAPED_UNICODE);
