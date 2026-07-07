<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $conn->query("DELETE FROM bag_types WHERE id = $id");
    setFlash("Bag type deleted.");
}
header("Location: bags.php");
exit;
