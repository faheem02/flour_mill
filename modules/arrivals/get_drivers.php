<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$q = trim($_GET['q'] ?? '');
$drivers = [];
if (strlen($q) >= 1) {
    $q = $conn->real_escape_string($q);
    $result = $conn->query("SELECT id, name, mobile FROM drivers WHERE name LIKE '%$q%' AND status='active' ORDER BY name LIMIT 20");
    while ($row = $result->fetch_assoc()) {
        $drivers[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($drivers, JSON_UNESCAPED_UNICODE);
