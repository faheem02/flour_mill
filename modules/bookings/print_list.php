<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';

$sql = "SELECT b.*, f.name AS farmer_name,
    COALESCE((SELECT quantity FROM booking_bags WHERE booking_id = b.id LIMIT 1), 0) AS bag_qty,
    COALESCE((SELECT ownership FROM booking_bags WHERE booking_id = b.id LIMIT 1), 'company') AS bag_ownership,
    COALESCE((SELECT bag_rate FROM booking_bags WHERE booking_id = b.id LIMIT 1), 0) AS bag_rate,
    COALESCE((SELECT SUM(amount) FROM farmer_payments WHERE booking_id = b.id AND type='payment'), 0) AS total_paid
    FROM bookings b
    JOIN farmers f ON b.farmer_id = f.id
    WHERE 1=1";
if ($date_from) $sql .= " AND b.date >= '" . $conn->real_escape_string($date_from) . "'";
if ($date_to)   $sql .= " AND b.date <= '" . $conn->real_escape_string($date_to) . "'";
$sql .= " ORDER BY b.date ASC, b.id ASC";
$result = $conn->query($sql);

$rows = [];
$grand_booked = 0;
$grand_received = 0;
$grand_total_value = 0;
$grand_total_paid = 0;
$grand_total_remaining = 0;

while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
    $pending = max(0, $r['booked_qty'] - $r['received_qty']);
    $farmer_wheat = $r['bag_qty'] * 50;
    $mans = $farmer_wheat / 40;
    $bag_rate_total = ($r['bag_ownership'] === 'farmer' && $r['bag_rate'] > 0) ? ($r['bag_qty'] * $r['bag_rate']) : 0;
    $wheat_value = $mans * $r['rate'];
    $total_value = $wheat_value + $bag_rate_total;
    $total_paid = $r['advance_amount'] + $r['total_paid'];
    $remaining = max(0, $total_value - $total_paid);

    $grand_booked += $r['booked_qty'];
    $grand_received += $r['received_qty'];
    $grand_total_value += $total_value;
    $grand_total_paid += $total_paid;
    $grand_total_remaining += $remaining;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Booking Register</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; color: #222; padding: 15px; background: #fff; }

    .print-header { text-align: center; border-bottom: 3px double #1B2A4A; padding-bottom: 10px; margin-bottom: 15px; }
    .print-header h2 { color: #1B2A4A; font-size: 22px; margin: 0; letter-spacing: 1px; }
    .print-header h4 { color: #555; font-size: 14px; font-weight: 600; margin: 4px 0 0; }
    .print-header .date-range { font-size: 12px; color: #888; margin-top: 4px; }

    .info-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 12px; }
    .info-row .label { font-weight: 700; color: #555; }

    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    th, td { padding: 6px 8px; border: 1px solid #ccc; text-align: left; font-size: 11px; }
    th { background: #1B2A4A; color: #fff; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
    td { vertical-align: middle; }
    tr:nth-child(even) td { background: #f8f9fc; }

    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-bold { font-weight: 700; }
    .text-success { color: #28a745; }
    .text-danger { color: #dc3545; }
    .text-muted { color: #888; }

    .summary-table { width: 320px; margin-left: auto; }
    .summary-table td { padding: 5px 10px; font-size: 12px; }
    .summary-table .grand-row td { border-top: 2px solid #1B2A4A; font-size: 14px; font-weight: 800; color: #1B2A4A; background: #eef2f7 !important; }

    .footer { margin-top: 20px; padding-top: 10px; border-top: 1px dashed #ccc; text-align: center; font-size: 10px; color: #999; }

    .no-print { margin-bottom: 15px; }
    .no-print button, .no-print a { padding: 6px 16px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; margin-right: 6px; text-decoration: none; display: inline-block; }
    .no-print .btn-print { background: #1B2A4A; color: #fff; }
    .no-print .btn-back { background: #e9ecef; color: #333; }
    .no-print .filter-form { display: inline-flex; align-items: center; gap: 8px; margin-left: 15px; }
    .no-print .filter-form input { padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; }
    .no-print .filter-form button { padding: 5px 14px; background: #4e73df; color: #fff; border-radius: 4px; font-size: 12px; }

    @media print {
        body { padding: 0; font-size: 11px; }
        .no-print { display: none !important; }
        @page { margin: 0.4in; size: landscape; }
        tr:nth-child(even) td { background: #f8f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        th { background: #1B2A4A !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .summary-table .grand-row td { background: #eef2f7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128424; Print</button>
    <a href="list.php" class="btn-back">&#8592; Back to Booking List</a>
    <form class="filter-form" method="GET">
        <label>From:</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
        <label>To:</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
        <button type="submit">Filter</button>
        <?php if ($date_from || $date_to): ?>
            <a href="print_list.php" style="padding:5px 10px;font-size:12px;color:#dc3545;">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="print-header">
    <h2>FLOUR MILL / BOOKING REGISTER</h2>
    <h4>Booking Register</h4>
    <div class="date-range">
        <?php if ($date_from || $date_to): ?>
            <?= $date_from ? date('d-M-Y', strtotime($date_from)) : 'Beginning' ?> &mdash; <?= $date_to ? date('d-M-Y', strtotime($date_to)) : 'Today' ?>
        <?php else: ?>
            All Bookings (<?= count($rows) ?> records)
        <?php endif; ?>
    </div>
</div>

<div class="info-row">
    <span><span class="label">Total Bookings:</span> <?= count($rows) ?></span>
    <span><span class="label">Printed on:</span> <?= date('d-M-Y H:i A') ?></span>
</div>

<table>
    <thead>
        <tr>
            <th width="30">#</th>
            <th width="90">Booking No</th>
            <th width="80">Date</th>
            <th>Farmer</th>
            <th class="text-right" width="80">Booked (KG)</th>
            <th class="text-right" width="80">Received (KG)</th>
            <th class="text-right" width="80">Pending (KG)</th>
            <th class="text-right" width="70">Rate/Man</th>
            <th class="text-right" width="90">Wheat Value (Rs)</th>
            <th class="text-right" width="80">Bag Chg (Rs)</th>
            <th class="text-right" width="90">Total (Rs)</th>
            <th class="text-right" width="80">Advance (Rs)</th>
            <th class="text-right" width="80">Paid (Rs)</th>
            <th class="text-right" width="90">Remaining (Rs)</th>
            <th width="70">Status</th>
            <th width="80">Delivery</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="16" class="text-center text-muted" style="padding:20px;">No bookings found.</td></tr>
        <?php else: ?>
        <?php $i = 1; foreach ($rows as $row):
            $pending = max(0, $row['booked_qty'] - $row['received_qty']);
            $farmer_wheat = $row['bag_qty'] * 50;
            $mans = $farmer_wheat / 40;
            $bag_rate_total = ($row['bag_ownership'] === 'farmer' && $row['bag_rate'] > 0) ? ($row['bag_qty'] * $row['bag_rate']) : 0;
            $wheat_value = $mans * $row['rate'];
            $total_value = $wheat_value + $bag_rate_total;
            $total_paid = $row['advance_amount'] + $row['total_paid'];
            $remaining = max(0, $total_value - $total_paid);
        ?>
        <tr>
            <td class="text-center"><?= $i++ ?></td>
            <td class="font-bold"><?= htmlspecialchars($row['booking_no']) ?></td>
            <td><?= date('d-m-Y', strtotime($row['date'])) ?></td>
            <td><?= htmlspecialchars($row['farmer_name']) ?></td>
            <td class="text-right"><?= qty($row['booked_qty']) ?></td>
            <td class="text-right"><?= qty($row['received_qty']) ?></td>
            <td class="text-right <?= $pending > 0 ? 'text-danger font-bold' : 'text-success' ?>"><?= qty($pending) ?></td>
            <td class="text-right"><?= money($row['rate']) ?></td>
            <td class="text-right font-bold"><?= money($wheat_value) ?></td>
            <td class="text-right"><?= $bag_rate_total > 0 ? money($bag_rate_total) : '-' ?></td>
            <td class="text-right font-bold"><?= money($total_value) ?></td>
            <td class="text-right"><?= money($row['advance_amount']) ?></td>
            <td class="text-right <?= $total_paid > 0 ? 'text-success font-bold' : 'text-muted' ?>"><?= money($total_paid) ?></td>
            <td class="text-right <?= $remaining > 0 ? 'text-danger font-bold' : 'text-success' ?>"><?= money($remaining) ?></td>
            <td><?= ucfirst($row['status']) ?></td>
            <td><?= ($row['delivery_type'] ?? 'pickup') === 'delivery' ? 'We Pickup' : 'Farmer Sends' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php if (!empty($rows)): ?>
<table class="summary-table">
    <tr>
        <td class="label">Total Bookings:</td>
        <td class="text-right font-bold"><?= count($rows) ?></td>
    </tr>
    <tr>
        <td class="label">Total Booked KG:</td>
        <td class="text-right font-bold"><?= qty($grand_booked) ?> KG</td>
    </tr>
    <tr>
        <td class="label">Total Received KG:</td>
        <td class="text-right font-bold"><?= qty($grand_received) ?> KG</td>
    </tr>
    <tr>
        <td class="label">Total Value:</td>
        <td class="text-right font-bold">Rs <?= money($grand_total_value) ?></td>
    </tr>
    <tr>
        <td class="label">Total Paid:</td>
        <td class="text-right font-bold text-success">Rs <?= money($grand_total_paid) ?></td>
    </tr>
    <tr class="grand-row">
        <td>Total Remaining:</td>
        <td class="text-right <?= $grand_total_remaining > 0 ? 'text-danger' : 'text-success' ?>">Rs <?= money($grand_total_remaining) ?></td>
    </tr>
</table>
<?php endif; ?>

<div class="footer">
    Flour Mill Management System &bull; Booking Register &bull; Generated <?= date('d-M-Y H:i:s A') ?>
</div>

<script>window.onload = function() { setTimeout(function(){ window.print(); }, 400); };</script>
</body>
</html>
