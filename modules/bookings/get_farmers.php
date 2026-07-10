<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }

require_once '../../includes/db.php';

$q = $_GET['q'] ?? '';
$results = [];

if (strlen($q) >= 1) {
    $search = '%' . $conn->real_escape_string($q) . '%';
    $stmt = $conn->prepare("SELECT id, name, phone, village FROM farmers WHERE status='active' AND name LIKE ? ORDER BY name LIMIT 20");
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $results[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($results);
