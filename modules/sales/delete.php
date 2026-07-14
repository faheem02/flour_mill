<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: list.php"); exit; }

$row = $conn->query("SELECT s.*, c.name as customer_name, w.name as warehouse_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN warehouses w ON s.warehouse_id = w.id
    WHERE s.id = $id")->fetch_assoc();
if (!$row) { die("Sale not found"); }

$items = $conn->query("SELECT si.*, p.name as product_name FROM sale_items si JOIN products p ON si.product_id=p.id WHERE si.sale_id=$id");

// Reverse the sale — restore stock
$conn->begin_transaction();
try {
    foreach ($items as $it) {
        $conn->query("UPDATE products SET stock_qty = stock_qty + {$it['qty']} WHERE id = {$it['product_id']}");
        $conn->query("UPDATE warehouse_stock SET stock_qty = stock_qty + {$it['qty']} WHERE warehouse_id = {$row['warehouse_id']} AND product_id = {$it['product_id']}");
    }

    // Reverse customer ledger (debit entry)
    $conn->query("DELETE FROM customer_ledger WHERE type='sale' AND reference_id=$id");
    $conn->query("DELETE FROM customer_ledger WHERE type='receipt' AND reference_id=$id");
    $conn->query("UPDATE customers SET balance = balance - {$row['total_amount']} WHERE id = {$row['customer_id']}");
    if ($row['paid_amount'] > 0) {
        $conn->query("UPDATE customers SET balance = balance + {$row['paid_amount']} WHERE id = {$row['customer_id']}");
    }

    // Delete journal entry
    $conn->query("DELETE FROM journal_entry_items WHERE journal_id IN (SELECT id FROM journal_entries WHERE description LIKE '%Inv: {$row['invoice_no']}%')");
    $conn->query("DELETE FROM journal_entries WHERE description LIKE '%Inv: {$row['invoice_no']}%'");

    $conn->query("DELETE FROM sale_items WHERE sale_id = $id");
    $conn->query("DELETE FROM sales WHERE id = $id");

    $conn->commit();
    setFlash("Sale {$row['invoice_no']} deleted. Stock restored.");
} catch (Exception $e) {
    $conn->rollback();
    setFlash("Error deleting sale: " . $e->getMessage());
}

header("Location: list.php");
exit;
