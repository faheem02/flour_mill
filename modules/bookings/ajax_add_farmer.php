<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }

require_once '../../includes/db.php';

$response = ['success' => false, 'message' => '', 'farmer' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $village = trim($_POST['village'] ?? '');
    $city   = trim($_POST['city'] ?? '');

    if (empty($name)) {
        $response['message'] = 'Name is required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO farmers (name, phone, village, city) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $phone, $village, $city);
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['farmer'] = [
                'id'   => $conn->insert_id,
                'name' => $name
            ];
        } else {
            $response['message'] = 'Database error.';
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
