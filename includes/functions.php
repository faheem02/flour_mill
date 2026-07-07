<?php

function sanitize($value) {
    global $conn;
    return $conn->real_escape_string(trim($value));
}

function money($amount) {
    return number_format($amount, 2);
}

function qty($qty) {
    return number_format($qty, 3);
}

function navActive($page, $current) {
    return $page === $current ? 'active' : '';
}

function generateVoucherNo($type = 'JV') {
    global $conn;
    $result = $conn->query("SELECT COUNT(*) as c FROM journal_entries WHERE voucher_no LIKE '$type-%'");
    $row = $result->fetch_assoc();
    $num = str_pad($row['c'] + 1, 4, '0', STR_PAD_LEFT);
    return $type . '-' . $num;
}

function generateInvoiceNo($type = 'SALE') {
    global $conn;
    $result = $conn->query("SELECT COUNT(*) as c FROM sales WHERE invoice_no LIKE '$type-%'");
    $row = $result->fetch_assoc();
    $num = str_pad($row['c'] + 1, 4, '0', STR_PAD_LEFT);
    return $type . '-' . $num;
}

function generatePurchaseNo() {
    global $conn;
    $result = $conn->query("SELECT COUNT(*) as c FROM purchases");
    $row = $result->fetch_assoc();
    $num = str_pad($row['c'] + 1, 4, '0', STR_PAD_LEFT);
    return 'PUR-' . $num;
}

function generateProductionNo() {
    global $conn;
    $result = $conn->query("SELECT COUNT(*) as c FROM productions");
    $row = $result->fetch_assoc();
    $num = str_pad($row['c'] + 1, 4, '0', STR_PAD_LEFT);
    return 'PROD-' . $num;
}

function generateBookingNo() {
    global $conn;
    $result = $conn->query("SELECT COUNT(*) as c FROM bookings");
    $row = $result->fetch_assoc();
    $num = str_pad($row['c'] + 1, 4, '0', STR_PAD_LEFT);
    return 'BK-' . $num;
}

function flashMessage() {
    if (isset($_SESSION['flash'])) {
        $msg = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $msg;
    }
    return '';
}

function setFlash($msg) {
    $_SESSION['flash'] = $msg;
}

// Auto-posting: Post a journal entry from transaction
function autoJournalEntry($date, $description, $debits, $credits, $created_by = 1) {
    global $conn;

    $voucher_no = generateVoucherNo();
    $total_debit = 0;
    $total_credit = 0;

    $conn->begin_transaction();
    try {
        // Insert journal header
        $stmt = $conn->prepare("INSERT INTO journal_entries (date, voucher_no, description, total_debit, total_credit, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssddi", $date, $voucher_no, $description, $total_debit, $total_credit, $created_by);
        $stmt->execute();
        $journal_id = $conn->insert_id;

        // Insert debit entries
        $stmt2 = $conn->prepare("INSERT INTO journal_entry_items (journal_id, account_id, debit, credit) VALUES (?, ?, ?, ?)");

        foreach ($debits as $account_id => $amount) {
            $credit = 0;
            $stmt2->bind_param("iidd", $journal_id, $account_id, $amount, $credit);
            $stmt2->execute();
            $total_debit += $amount;

            // Update account balance
            $conn->query("UPDATE chart_of_accounts SET balance = balance + $amount WHERE id = $account_id");
        }

        // Insert credit entries
        foreach ($credits as $account_id => $amount) {
            $debit = 0;
            $stmt2->bind_param("iidd", $journal_id, $account_id, $debit, $amount);
            $stmt2->execute();
            $total_credit += $amount;

            // Update account balance
            $conn->query("UPDATE chart_of_accounts SET balance = balance - $amount WHERE id = $account_id");
        }

        // Update total in header
        $conn->query("UPDATE journal_entries SET total_debit = $total_debit, total_credit = $total_credit WHERE id = $journal_id");

        $conn->commit();
        return $journal_id;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}
