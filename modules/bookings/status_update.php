<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }

$id = (int)($_GET['id'] ?? 0);
$status = $_GET['status'] ?? '';

if ($id && in_array($status, ['pending','partial','completed','cancelled'])) {
    require_once '../../includes/db.php';
    $conn->query("UPDATE bookings SET status = '$status' WHERE id = $id");
}

header("Location: list.php");
exit;
