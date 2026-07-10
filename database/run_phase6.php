<?php
$conn = new mysqli('localhost', 'root', '', 'flour_mill');
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$sql1 = "CREATE TABLE IF NOT EXISTS booking_bags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    bag_type_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    bag_capacity_kg DECIMAL(10,2) DEFAULT 50.000,
    ownership ENUM('company','farmer') NOT NULL DEFAULT 'company',
    bag_rate DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (bag_type_id) REFERENCES bag_types(id)
) ENGINE=InnoDB";

if ($conn->query($sql1)) {
    echo "Table booking_bags created.\n";
} else {
    echo "Error creating booking_bags: " . $conn->error . "\n";
}

$result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'moisture_percent'");
if ($result->num_rows == 0) {
    $sql2 = "ALTER TABLE bookings ADD COLUMN moisture_percent DECIMAL(5,2) DEFAULT 0 AFTER advance_amount";
    if ($conn->query($sql2)) {
        echo "Column moisture_percent added.\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
} else {
    echo "Column moisture_percent already exists.\n";
}

$result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'katt_per_bag'");
if ($result->num_rows == 0) {
    $sql3 = "ALTER TABLE bookings ADD COLUMN katt_per_bag DECIMAL(5,3) DEFAULT 0 AFTER moisture_percent";
    if ($conn->query($sql3)) {
        echo "Column katt_per_bag added.\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
} else {
    echo "Column katt_per_bag already exists.\n";
}

$conn->close();
echo "Done.\n";
