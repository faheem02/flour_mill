<?php
$conn = new mysqli('localhost', 'root', '', 'flour_mill');
if ($conn->connect_error) die('Connection failed: ' . $conn->connect_error);

$sql = file_get_contents(__DIR__ . '/phase3_booking.sql');
$statements = explode(';', $sql);
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if (empty($stmt)) continue;
    if (!$conn->query($stmt)) {
        if (strpos($conn->error, 'Duplicate column') === false && 
            strpos($conn->error, 'already exists') === false) {
            echo "Error: " . $conn->error . "\n";
        } else {
            echo "Skipped: " . substr($stmt, 0, 50) . "...\n";
        }
    }
}
echo "Phase 3 migration done.\n";
$conn->close();
