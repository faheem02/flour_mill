<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$payment_id = (int)($_GET['id'] ?? 0);
$payment = $payment_id ? $conn->query("SELECT p.*, f.name AS farmer_name, f.phone AS farmer_phone, f.village, f.city,
    b.booking_no, b.rate AS booking_rate, b.booked_qty
    FROM farmer_payments p
    JOIN farmers f ON p.farmer_id = f.id
    LEFT JOIN bookings b ON p.booking_id = b.id
    WHERE p.id = $payment_id")->fetch_assoc() : null;

if (!$payment) {
    echo "<p style='text-align:center;padding:50px;color:#dc3545;font-size:16px;'>Payment not found. <a href='payment.php'>Go Back</a></p>";
    exit;
}

$updated_balance = $conn->query("SELECT balance FROM farmers WHERE id = {$payment['farmer_id']}")->fetch_assoc()['balance'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment Receipt - <?= htmlspecialchars($payment['farmer_name']) ?></title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 13px; color: #222; padding: 20px; background: #fff; }

.receipt-wrapper { max-width: 600px; margin: 0 auto; }

.receipt-header { text-align: center; border-bottom: 3px double #1B2A4A; padding-bottom: 12px; margin-bottom: 15px; }
.receipt-header h2 { color: #1B2A4A; font-size: 24px; letter-spacing: 2px; }
.receipt-header h4 { color: #1B2A4A; font-size: 16px; font-weight: 700; margin: 3px 0 0; background: #1B2A4A; color: #fff; display: inline-block; padding: 3px 20px; border-radius: 3px; }
.receipt-header .date-line { font-size: 11px; color: #888; margin-top: 6px; }

.receipt-body { margin-top: 15px; }

.field-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dotted #ddd; }
.field-row .fl { font-weight: 600; color: #555; min-width: 160px; }
.field-row .fv { font-weight: 600; color: #222; text-align: right; }

.amount-box { background: #1B2A4A; color: #fff; text-align: center; padding: 15px; margin: 18px 0; border-radius: 6px; }
.amount-box .label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; }
.amount-box .value { font-size: 32px; font-weight: 800; letter-spacing: 1px; }
.amount-box .value small { font-size: 16px; }

.balance-box { display: flex; justify-content: space-between; margin: 12px 0; padding: 10px 15px; background: #f8f9fc; border: 1px solid #dee2e6; border-radius: 4px; }
.balance-box .bl { font-weight: 700; color: #555; }
.balance-box .bv { font-weight: 800; font-size: 16px; }
.balance-box .bv.text-danger { color: #dc3545; }
.balance-box .bv.text-success { color: #28a745; }

.receipt-footer { margin-top: 25px; padding-top: 15px; border-top: 2px solid #1B2A4A; text-align: center; }
.receipt-footer .signature-line { display: inline-block; width: 200px; border-top: 1px solid #333; margin-top: 50px; padding-top: 5px; font-size: 11px; color: #555; }
.receipt-footer .note { font-size: 10px; color: #999; margin-top: 15px; }

.watermark { text-align: center; margin: 10px 0; }
.watermark span { display: inline-block; padding: 4px 20px; border: 2px solid #28a745; color: #28a745; font-weight: 800; font-size: 14px; letter-spacing: 2px; border-radius: 4px; transform: rotate(-5deg); }

.no-print { margin-bottom: 15px; text-align: center; }
.no-print button, .no-print a { padding: 7px 18px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; margin: 0 5px; text-decoration: none; display: inline-block; }
.no-print .btn-print { background: #1B2A4A; color: #fff; }
.no-print .btn-back { background: #e9ecef; color: #333; }

@media print {
    body { padding: 0; background: #fff; }
    .no-print { display: none !important; }
    @page { margin: 0.5in; size: portrait; }
    .receipt-wrapper { max-width: 100%; }
    .amount-box { background: #1B2A4A !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .watermark span { -webkit-print-color-adjust: exact; print-color-adjust: exact; border-color: #28a745 !important; color: #28a745 !important; }
    .receipt-footer .signature-line { border-top: 1px solid #333 !important; }
}
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128424; Print Receipt</button>
    <a href="payment.php?farmer_id=<?= $payment['farmer_id'] ?>" class="btn-back">&#8592; Back to Payment</a>
</div>

<div class="receipt-wrapper">

    <div class="receipt-header">
        <h2>FLOUR MILL</h2>
        <h4>PAYMENT RECEIPT</h4>
        <div class="date-line">Receipt #PAY-<?= str_pad($payment['id'], 5, '0', STR_PAD_LEFT) ?> &bull; <?= date('d-M-Y h:i A', strtotime($payment['created_at'])) ?></div>
    </div>

    <div class="watermark"><span>PAID</span></div>

    <div class="receipt-body">
        <div class="field-row">
            <span class="fl">Farmer Name</span>
            <span class="fv"><?= htmlspecialchars($payment['farmer_name']) ?></span>
        </div>
        <div class="field-row">
            <span class="fl">Phone</span>
            <span class="fv"><?= htmlspecialchars($payment['farmer_phone'] ?: '-') ?></span>
        </div>
        <div class="field-row">
            <span class="fl">Village / City</span>
            <span class="fv"><?= htmlspecialchars($payment['village'] ?: '-') ?><?= $payment['city'] ? ', ' . htmlspecialchars($payment['city']) : '' ?></span>
        </div>
        <div class="field-row">
            <span class="fl">Payment Date</span>
            <span class="fv"><?= date('d-M-Y', strtotime($payment['date'])) ?></span>
        </div>
        <div class="field-row">
            <span class="fl">Payment Mode</span>
            <span class="fv"><?= ucfirst($payment['payment_mode']) ?></span>
        </div>
        <?php if ($payment['booking_no']): ?>
        <div class="field-row">
            <span class="fl">Against Booking</span>
            <span class="fv"><?= htmlspecialchars($payment['booking_no']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($payment['notes']): ?>
        <div class="field-row">
            <span class="fl">Notes</span>
            <span class="fv" style="max-width:300px;text-align:right;"><?= htmlspecialchars($payment['notes']) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="amount-box">
        <div class="label">Amount Paid</div>
        <div class="value"><small>Rs</small> <?= money($payment['amount']) ?></div>
    </div>

    <div class="balance-box">
        <span class="bl">Updated Farmer Balance:</span>
        <span class="bv <?= $updated_balance > 0 ? 'text-danger' : 'text-success' ?>">Rs <?= money($updated_balance) ?></span>
    </div>

    <div class="receipt-footer">
        <div class="signature-line">Authorized Signature</div>
        <div class="note">This is a computer-generated receipt. No signature required.</div>
    </div>

</div>

<script>window.onload = function() { setTimeout(function(){ window.print(); }, 500); };</script>
</body>
</html>
