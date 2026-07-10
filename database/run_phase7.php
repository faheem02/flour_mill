<?php
$conn = new mysqli('localhost', 'root', '', 'flour_mill');
if ($conn->connect_error) die("Connection failed\n");

$columns = [
    'vehicle_no'     => "ADD COLUMN vehicle_no VARCHAR(50) DEFAULT NULL AFTER date",
    'actual_weight'  => "ADD COLUMN actual_weight DECIMAL(12,3) DEFAULT 0 AFTER net_weight",
    'weight_slip_no' => "ADD COLUMN weight_slip_no VARCHAR(50) DEFAULT NULL AFTER actual_weight",
    'weight_diff'    => "ADD COLUMN weight_diff DECIMAL(12,3) DEFAULT 0 AFTER weight_slip_no",
    'katt_applied'   => "ADD COLUMN katt_applied DECIMAL(12,3) DEFAULT 0 AFTER weight_diff",
    'gross_amount'   => "ADD COLUMN gross_amount DECIMAL(12,2) DEFAULT 0 AFTER katt_applied",
    'bag_amount'     => "ADD COLUMN bag_amount DECIMAL(12,2) DEFAULT 0 AFTER gross_amount",
    'labour_charges' => "ADD COLUMN labour_charges DECIMAL(12,2) DEFAULT 0 AFTER bag_amount",
    'transport_charges' => "ADD COLUMN transport_charges DECIMAL(12,2) DEFAULT 0 AFTER labour_charges",
    'other_charges'  => "ADD COLUMN other_charges DECIMAL(12,2) DEFAULT 0 AFTER transport_charges",
    'net_amount'     => "ADD COLUMN net_amount DECIMAL(12,2) DEFAULT 0 AFTER other_charges",
];

foreach ($columns as $name => $sql) {
    $r = $conn->query("SHOW COLUMNS FROM wheat_arrivals LIKE '$name'");
    if ($r->num_rows == 0) {
        if ($conn->query("ALTER TABLE wheat_arrivals $sql")) {
            echo "Column $name added.\n";
        } else {
            echo "Error adding $name: " . $conn->error . "\n";
        }
    } else {
        echo "Column $name already exists.\n";
    }
}

$conn->close();
echo "Done.\n";
