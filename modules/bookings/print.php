<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$page_title = 'Booking Voucher';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$booking = $conn->query("
    SELECT b.*, f.name AS farmer_name, f.village, f.phone
    FROM bookings b
    JOIN farmers f ON b.farmer_id = f.id
    WHERE b.id = $id
")->fetch_assoc();

if (!$booking) { die("Booking not found"); }

$bag = $conn->query("
    SELECT bb.*, bt.name AS bag_type_name
    FROM booking_bags bb
    JOIN bag_types bt ON bb.bag_type_id = bt.id
    WHERE bb.booking_id = $id
")->fetch_assoc();

$farmer_wheat   = ($bag ? $bag['quantity'] : 0) * 50;
$katt_total     = $bag ? ($bag['quantity'] * $booking['katt_per_bag']) : 0;
$net_qty        = $farmer_wheat + $katt_total;
$mans           = $farmer_wheat / 40;
$bag_rate_total = ($bag && $bag['ownership'] === 'farmer' && $bag['bag_rate'] > 0) ? ($bag['quantity'] * $bag['bag_rate']) : 0;
$wheat_value    = $mans * $booking['rate'];
$total_value    = $wheat_value + $bag_rate_total;
$total_paid     = $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM farmer_payments WHERE booking_id = $id")->fetch_assoc()['t'];
$remaining      = max(0, $total_value - $total_paid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Booking Voucher - <?= $booking['booking_no'] ?></title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; color: #222; padding: 15px; background: #fff; }
.print-header { text-align: center; border-bottom: 3px double #1B2A4A; padding-bottom: 10px; margin-bottom: 15px; }
.print-header h2 { color: #1B2A4A; font-size: 22px; margin: 0; letter-spacing: 1px; }
.print-header h4 { color: #555; font-size: 14px; font-weight: 600; margin: 4px 0 0; }
.print-header .date-range { font-size: 12px; color: #888; margin-top: 4px; }
.info-row { display: flex; gap: 30px; margin-bottom: 12px; font-size: 12px; flex-wrap: wrap; }
.info-row .label { font-weight: 700; color: #555; display: block; font-size: 10px; text-transform: uppercase; }
.info-row .value { font-weight: 600; font-size: 13px; }
.section-title { background: #1B2A4A; color: #fff; padding: 7px 12px; font-size: 13px; font-weight: 700; margin-top: 18px; margin-bottom: 0; border-radius: 4px 4px 0 0; }
table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
th, td { padding: 5px 8px; border: 1px solid #ccc; text-align: left; font-size: 11px; }
th { background: #e9ecef; color: #333; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
td { vertical-align: middle; }
tr:nth-child(even) td { background: #f8f9fc; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.font-bold { font-weight: 700; }
.text-success { color: #28a745; }
.text-danger { color: #dc3545; }
.text-muted { color: #888; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 9px; font-weight: 700; text-transform: uppercase; color: #fff; }
.badge-booked { background: #6c757d; }
.badge-partial { background: #ffc107; color: #333; }
.badge-completed { background: #28a745; }
.badge-cancelled { background: #dc3545; }
.total-row td { background: #1B2A4A !important; color: #fff !important; font-weight: 700; font-size: 13px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.summary-table { width: 350px; margin-left: auto; margin-top: 10px; }
.summary-table td { padding: 5px 10px; font-size: 12px; }
.summary-table .grand-row td { border-top: 2px solid #1B2A4A; font-size: 14px; font-weight: 800; color: #1B2A4A; background: #eef2f7 !important; }
.footer { margin-top: 20px; padding-top: 10px; border-top: 1px dashed #ccc; text-align: center; font-size: 10px; color: #999; }
.no-print { margin-bottom: 15px; }
.no-print button, .no-print a { padding: 6px 16px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; margin-right: 6px; text-decoration: none; display: inline-block; }
.no-print .btn-print { background: #1B2A4A; color: #fff; }
.no-print .btn-back { background: #e9ecef; color: #333; }
@media print { body { padding: 0; font-size: 11px; } .no-print { display: none !important; } @page { margin: 0.4in; size: portrait; } tr:nth-child(even) td { background: #f8f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .total-row td { background: #1B2A4A !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { background: #e9ecef !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .summary-table .grand-row td { background: #eef2f7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128424; Print</button>
    <a href="list.php" class="btn-back">&#8592; Back to List</a>
</div>

<div class="print-header">
    <h2>FLOUR MILL</h2>
    <h4>BOOKING VOUCHER</h4>
    <div class="date-range">#<?= htmlspecialchars($booking['booking_no']) ?> | <?= date('d-M-Y', strtotime($booking['date'])) ?>
    <?php if ($booking['status'] !== 'booked'): ?>
        &mdash; <span class="badge badge-<?= $booking['status'] ?>"><?= ucfirst($booking['status']) ?></span>
    <?php endif; ?>
    </div>
</div>

<div class="info-row">
    <div>
        <span class="label">Farmer Name</span>
        <span class="value"><?= htmlspecialchars($booking['farmer_name']) ?></span>
    </div>
    <div>
        <span class="label">Phone</span>
        <span class="value"><?= htmlspecialchars($booking['phone'] ?? '-') ?></span>
    </div>
    <div>
        <span class="label">Address</span>
        <span class="value"><?= htmlspecialchars($booking['village'] ?? '-') ?></span>
    </div>
    <div>
        <span class="label">Delivery Type</span>
        <span class="value"><?= ($booking['delivery_type'] ?? 'pickup') === 'delivery' ? 'We Will Pickup' : 'Farmer Will Send' ?></span>
    </div>
    <?php if ($booking['expected_date']): ?>
    <div>
        <span class="label">Expected Delivery</span>
        <span class="value"><?= date('d-M-Y', strtotime($booking['expected_date'])) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($booking['moisture_percent'] > 0): ?>
    <div>
        <span class="label">Moisture</span>
        <span class="value"><?= $booking['moisture_percent'] ?>%</span>
    </div>
    <?php endif; ?>
</div>

<?php if ($bag): ?>
<div class="section-title">&#128230; Bag Details</div>
<table>
    <thead>
        <tr>
            <th>Bag Type</th>
            <th class="text-center">Quantity</th>
            <th class="text-center">Capacity</th>
            <th class="text-center">Ownership</th>
            <th class="text-center">Action</th>
            <th class="text-right">Rate/Bag</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="font-bold"><?= htmlspecialchars($bag['bag_type_name']) ?></td>
            <td class="text-center"><?= $bag['quantity'] ?></td>
            <td class="text-center"><?= $bag['bag_capacity_kg'] ?> KG</td>
            <td class="text-center"><?= $bag['ownership'] === 'company' ? 'Company' : 'Farmer' ?></td>
            <td class="text-center"><?= ucfirst($bag['bag_action'] ?? '-') ?></td>
            <td class="text-right"><?= $bag['bag_rate'] > 0 ? money($bag['bag_rate']) : '-' ?></td>
        </tr>
    </tbody>
</table>
<?php endif; ?>

<div class="section-title">&#9878; Quantity Breakdown</div>
<table>
    <thead>
        <tr>
            <th>Description</th>
            <th class="text-right" width="130">Quantity</th>
            <th>Formula</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="font-bold">Farmer's Wheat</td>
            <td class="text-right"><?= qty($farmer_wheat) ?> KG</td>
            <td class="text-muted"><?= $bag ? $bag['quantity'] : 0 ?> bags &times; 50 KG</td>
        </tr>
        <tr>
            <td>Katt @ <?= qty($booking['katt_per_bag']) ?> KG/bag</td>
            <td class="text-right text-success">+ <?= qty($katt_total) ?> KG</td>
            <td class="text-muted"><?= $bag ? $bag['quantity'] : 0 ?> &times; <?= qty($booking['katt_per_bag']) ?></td>
        </tr>
    </tbody>
    <tfoot>
        <tr class="total-row">
            <th>Net Qty (Stock)</th>
            <th class="text-right"><?= qty($net_qty) ?> KG</th>
            <th></th>
        </tr>
    </tfoot>
</table>

<div class="section-title">&#128176; Pricing</div>
<table>
    <thead>
        <tr>
            <th>Description</th>
            <th class="text-right" width="130">Amount</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Rate per Man (40 KG)</td>
            <td class="text-right font-bold"><?= money($booking['rate']) ?></td>
        </tr>
        <tr>
            <td>Mans</td>
            <td class="text-right"><?= qty($mans) ?></td>
        </tr>
        <tr>
            <td class="font-bold">Wheat Value</td>
            <td class="text-right font-bold"><?= money($wheat_value) ?></td>
        </tr>
        <?php if ($bag_rate_total > 0): ?>
        <tr>
            <td>Bag Charges (<?= $bag['quantity'] ?> &times; <?= money($bag['bag_rate']) ?>)</td>
            <td class="text-right"><?= money($bag_rate_total) ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr class="total-row">
            <th>Grand Total</th>
            <th class="text-right"><?= money($total_value) ?></th>
        </tr>
    </tfoot>
</table>

<div class="section-title">&#128179; Payment Summary</div>
<table>
    <tbody>
        <tr>
            <td>Wheat Value</td>
            <td class="text-right"><?= money($wheat_value) ?></td>
        </tr>
        <?php if ($bag_rate_total > 0): ?>
        <tr>
            <td>Bag Charges</td>
            <td class="text-right"><?= money($bag_rate_total) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($booking['advance_amount'] > 0): ?>
        <tr>
            <td>Advance Paid</td>
            <td class="text-right text-success"><?= money($booking['advance_amount']) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td class="font-bold">Total Paid</td>
            <td class="text-right font-bold text-success"><?= money($total_paid) ?></td>
        </tr>
    </tbody>
    <tfoot>
        <tr class="total-row">
            <th>Grand Total</th>
            <th class="text-right"><?= money($total_value) ?></th>
        </tr>
    </tfoot>
</table>

<table class="summary-table">
    <tr>
        <td class="font-bold">Grand Total:</td>
        <td class="text-right font-bold">Rs <?= money($total_value) ?></td>
    </tr>
    <tr>
        <td class="font-bold">Total Paid:</td>
        <td class="text-right text-success font-bold">Rs <?= money($total_paid) ?></td>
    </tr>
    <tr class="grand-row">
        <td>Remaining:</td>
        <td class="text-right <?= $remaining > 0 ? 'text-danger' : 'text-success' ?>">Rs <?= money($remaining) ?></td>
    </tr>
</table>

<?php if ($booking['notes']): ?>
<div class="section-title">&#128221; Notes</div>
<p><?= nl2br(htmlspecialchars($booking['notes'])) ?></p>
<?php endif; ?>

<div class="footer">
    Flour Mill Management System &bull; Booking Voucher &bull; <?= htmlspecialchars($booking['booking_no']) ?> &bull; Generated <?= date('d-M-Y H:i:s A') ?>
</div>

<script>window.onload = function() { setTimeout(function(){ window.print(); }, 400); };</script>
</body>
</html>
