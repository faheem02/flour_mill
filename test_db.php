<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

$pass = 0;
$fail = 0;

function test($label, $ok, $detail = '') {
    global $pass, $fail;
    if ($ok) { echo "  PASS  $label\n"; $pass++; }
    else     { echo "  FAIL  $label" . ($detail ? " — $detail" : '') . "\n"; $fail++; }
}

echo "=== DATABASE CONNECTION ===\n";
test("DB connects", $conn->connect_error === null, $conn->connect_error);

echo "\n=== TABLE COUNT ===\n";
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema='flour_mill'");
$row = $r->fetch_assoc();
test("34+ tables exist", $row['c'] >= 34, "found {$row['c']}");

echo "\n=== SEED DATA ===\n";
$r = $conn->query("SELECT COUNT(*) c FROM users");
$row = $r->fetch_assoc();
test("Users seeded", $row['c'] >= 1, "found {$row['c']}");

$r = $conn->query("SELECT COUNT(*) c FROM products");
$row = $r->fetch_assoc();
test("Products seeded", $row['c'] >= 4, "found {$row['c']} — expected Atta/Maida/Suji/Bran");

$r = $conn->query("SELECT COUNT(*) c FROM warehouses");
$row = $r->fetch_assoc();
test("Warehouses seeded", $row['c'] >= 1, "found {$row['c']}");

$r = $conn->query("SELECT COUNT(*) c FROM chart_of_accounts");
$row = $r->fetch_assoc();
test("Chart of accounts seeded", $row['c'] >= 10, "found {$row['c']}");

$r = $conn->query("SELECT COUNT(*) c FROM bag_types");
$row = $r->fetch_assoc();
test("Bag types seeded", $row['c'] >= 1, "found {$row['c']}");

echo "\n=== MIGRATION COLUMNS ===\n";
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema='flour_mill' AND table_name='bag_stock_ledger' AND column_name='rate'");
$row = $r->fetch_assoc();
test("bag_stock_ledger.rate exists", $row['c'] == 1);

$r = $conn->query("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema='flour_mill' AND table_name='booking_bags' AND column_name='bag_action'");
$row = $r->fetch_assoc();
test("booking_bags.bag_action exists", $row['c'] == 1);

$r = $conn->query("SHOW TABLES LIKE 'general_parties'");
test("general_parties table exists", $r->num_rows == 1);

$r = $conn->query("SHOW TABLES LIKE 'party_transactions'");
test("party_transactions table exists", $r->num_rows == 1);

echo "\n=== KEY HELPERS ===\n";
require_once 'includes/functions.php';

$inv = generateInvoiceNo();
test("generateInvoiceNo() works", preg_match('/^SALE-\d{4}$/', $inv) === 1, $inv);

$bk = generateBookingNo();
test("generateBookingNo() works", preg_match('/^BK-\d{4}$/', $bk) === 1, $bk);

$prod = generateProductionNo();
test("generateProductionNo() works", preg_match('/^PROD-\d{4}$/', $prod) === 1, $prod);

$vno = generateVoucherNo();
test("generateVoucherNo() works", preg_match('/^JV-\d{4}$/', $vno) === 1, $vno);

echo "\n=== SUMMARY ===\n";
echo "PASSED: $pass\n";
echo "FAILED: $fail\n";
echo ($fail === 0 ? "\nAll tests passed!\n" : "\nSome tests failed.\n");

$conn->close();
