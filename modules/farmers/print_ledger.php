<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$farmer_id = (int)($_GET['id'] ?? 0);
$farmer = $farmer_id ? $conn->query("SELECT * FROM farmers WHERE id = $farmer_id")->fetch_assoc() : null;

if (!$farmer) {
    echo "<p style='text-align:center;padding:50px;color:#dc3545;font-size:16px;'>Farmer not found. <a href='ledger.php'>Go Back</a></p>";
    exit;
}

$opening = (float)($farmer['opening_balance'] ?? 0);

$ledger = [];
$arrivals = $conn->query("
    SELECT a.id, a.date, a.net_amount, a.payment_now, b.booking_no
    FROM wheat_arrivals a
    LEFT JOIN bookings b ON a.booking_id = b.id
    WHERE b.farmer_id = $farmer_id
    ORDER BY a.date ASC, a.id ASC
");
while ($a = $arrivals->fetch_assoc()) {
    $ledger[] = [
        'date'    => $a['date'],
        'type'    => 'arrival',
        'ref'     => $a['booking_no'] ?? '-',
        'desc'    => 'Wheat Arrival',
        'debit'   => 0,
        'credit'  => (float)$a['net_amount'],
    ];
}

$payments = $conn->query("
    SELECT p.id, p.date, p.amount, p.type, p.payment_mode, p.notes, b.booking_no
    FROM farmer_payments p
    LEFT JOIN bookings b ON p.booking_id = b.id
    WHERE p.farmer_id = $farmer_id
    ORDER BY p.date ASC, p.id ASC
");
while ($p = $payments->fetch_assoc()) {
    $ledger[] = [
        'date'    => $p['date'],
        'type'    => $p['type'],
        'ref'     => $p['booking_no'] ?? '-',
        'desc'    => ucfirst($p['type']) . ($p['payment_mode'] ? " ({$p['payment_mode']})" : '') . ($p['notes'] ? " - {$p['notes']}" : ''),
        'debit'   => (float)$p['amount'],
        'credit'  => 0,
    ];
}

usort($ledger, function($a, $b) {
    return strcmp($a['date'], $b['date']);
});

$bookings = $conn->query("
    SELECT b.*,
        COALESCE((SELECT quantity FROM booking_bags WHERE booking_id = b.id LIMIT 1), 0) AS bag_qty,
        COALESCE((SELECT ownership FROM booking_bags WHERE booking_id = b.id LIMIT 1), 'company') AS bag_ownership,
        COALESCE((SELECT bag_rate FROM booking_bags WHERE booking_id = b.id LIMIT 1), 0) AS bag_rate,
        COALESCE((SELECT SUM(amount) FROM farmer_payments WHERE booking_id = b.id AND type='payment'), 0) AS extra_paid
    FROM bookings b
    WHERE b.farmer_id = $farmer_id
    ORDER BY b.date DESC, b.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($farmer['name']) ?> - Ledger</title>
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
.info-row .text-danger { color: #dc3545; }
.info-row .text-success { color: #28a745; }
.section-title { background: #1B2A4A; color: #fff; padding: 7px 12px; font-size: 13px; font-weight: 700; margin-top: 18px; margin-bottom: 0; border-radius: 4px 4px 0 0; }
table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
th, td { padding: 5px 8px; border: 1px solid #ccc; text-align: left; font-size: 11px; }
th { background: #e9ecef; color: #333; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
td { vertical-align: middle; }
tr:nth-child(even) td { background: #f8f9fc; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.font-bold { font-weight: 700; }
.text-danger { color: #dc3545; }
.text-success { color: #28a745; }
.text-muted { color: #888; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 9px; font-weight: 700; text-transform: uppercase; color: #fff; }
.badge-opening { background: #6c757d; }
.badge-arrival { background: #4e73df; }
.badge-payment { background: #28a745; }
.badge-advance { background: #17a2b8; }
.badge-completed { background: #28a745; }
.badge-partial { background: #ffc107; color: #333; }
.badge-cancelled { background: #dc3545; }
.badge-secondary { background: #6c757d; }
.opening-row td { background: #e8f0fe !important; font-weight: 700; }
.total-row td { background: #1B2A4A !important; color: #fff !important; font-weight: 700; font-size: 13px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.summary-table { width: 350px; margin-left: auto; margin-top: 10px; }
.summary-table td { padding: 5px 10px; font-size: 12px; }
.summary-table .grand-row td { border-top: 2px solid #1B2A4A; font-size: 14px; font-weight: 800; color: #1B2A4A; background: #eef2f7 !important; }
.footer { margin-top: 20px; padding-top: 10px; border-top: 1px dashed #ccc; text-align: center; font-size: 10px; color: #999; }
.no-print { margin-bottom: 15px; }
.no-print button, .no-print a { padding: 6px 16px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; margin-right: 6px; text-decoration: none; display: inline-block; }
.no-print .btn-print { background: #1B2A4A; color: #fff; }
.no-print .btn-back { background: #e9ecef; color: #333; }
@media print { body { padding: 0; font-size: 11px; } .no-print { display: none !important; } @page { margin: 0.4in; size: portrait; } tr:nth-child(even) td { background: #f8f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .opening-row td { background: #e8f0fe !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .total-row td { background: #1B2A4A !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { background: #e9ecef !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128424; Print</button>
    <a href="ledger.php?id=<?= $farmer_id ?>" class="btn-back">&#8592; Back to Ledger</a>
</div>

<div class="print-header">
    <h2>FLOUR MILL</h2>
    <h4>FARMER LEDGER</h4>
    <div class="date-range">Generated on <?= date('d-M-Y h:i A') ?></div>
</div>

<div class="info-row">
    <div>
        <span class="label">Farmer Name</span>
        <span class="value"><?= htmlspecialchars($farmer['name']) ?></span>
    </div>
    <div>
        <span class="label">Phone</span>
        <span class="value"><?= htmlspecialchars($farmer['phone'] ?: '-') ?></span>
    </div>
    <div>
        <span class="label">Village / City</span>
        <span class="value"><?= htmlspecialchars($farmer['village'] ?: '-') ?><?= $farmer['city'] ? ', ' . htmlspecialchars($farmer['city']) : '' ?></span>
    </div>
    <div>
        <span class="label">Opening Balance</span>
        <span class="value">Rs <?= money($opening) ?></span>
    </div>
    <div>
        <span class="label">Current Balance</span>
        <span class="value <?= $farmer['balance'] > 0 ? 'text-success' : ($farmer['balance'] < 0 ? 'text-danger' : '') ?>">Rs <?= money($farmer['balance']) ?></span>
    </div>
</div>

<div class="section-title"><i class="fas fa-scroll"></i> Ledger</div>
<table>
    <thead>
        <tr>
            <th width="85">Date</th>
            <th width="80">Type</th>
            <th>Reference / Description</th>
            <th class="text-right" width="120">Credit (+)</th>
            <th class="text-right" width="120">Debit (-)</th>
            <th class="text-right" width="130">Balance</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $balance = $opening;
        $total_debit = 0;
        $total_credit = 0;
        ?>
        <tr class="opening-row">
            <td></td>
            <td><span class="badge badge-opening">Opening</span></td>
            <td>Opening Balance b/f</td>
            <td class="text-right font-bold">Rs <?= money($balance) ?></td>
            <td class="text-right"></td>
            <td class="text-right font-bold">Rs <?= money($balance) ?></td>
        </tr>
        <?php foreach ($ledger as $entry):
            if ($entry['type'] === 'arrival') {
                $balance += $entry['credit'];
                $total_credit += $entry['credit'];
            } else {
                $balance -= $entry['debit'];
                $total_debit += $entry['debit'];
            }
        ?>
        <tr>
            <td><?= date('d-M-Y', strtotime($entry['date'])) ?></td>
            <td>
                <?php if ($entry['type'] === 'arrival'): ?>
                    <span class="badge badge-arrival">Arrival</span>
                <?php elseif ($entry['type'] === 'advance'): ?>
                    <span class="badge badge-advance">Advance</span>
                <?php else: ?>
                    <span class="badge badge-payment">Payment</span>
                <?php endif; ?>
            </td>
            <td>
                <?= htmlspecialchars($entry['desc']) ?>
                <?php if ($entry['ref'] !== '-'): ?>
                    <br><small class="text-muted"><?= htmlspecialchars($entry['ref']) ?></small>
                <?php endif; ?>
            </td>
            <td class="text-right <?= $entry['credit'] > 0 ? 'font-bold' : '' ?>">
                <?= $entry['credit'] > 0 ? 'Rs ' . money($entry['credit']) : '' ?>
            </td>
            <td class="text-right <?= $entry['debit'] > 0 ? 'font-bold' : '' ?>">
                <?= $entry['debit'] > 0 ? 'Rs ' . money($entry['debit']) : '' ?>
            </td>
            <td class="text-right font-bold">Rs <?= money($balance) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td colspan="3" class="text-right">Total</td>
            <td class="text-right">Rs <?= money($total_credit) ?></td>
            <td class="text-right">Rs <?= money($total_debit) ?></td>
            <td class="text-right">Rs <?= money($balance) ?></td>
        </tr>
    </tfoot>
</table>

<table class="summary-table">
    <tr>
        <td class="font-bold">Total Credit (Arrivals):</td>
        <td class="text-right">Rs <?= money($total_credit) ?></td>
    </tr>
    <tr>
        <td class="font-bold">Total Debit (Payments):</td>
        <td class="text-right">Rs <?= money($total_debit) ?></td>
    </tr>
    <tr class="grand-row">
        <td>Current Balance:</td>
        <td class="text-right <?= $farmer['balance'] > 0 ? 'text-success' : ($farmer['balance'] < 0 ? 'text-danger' : '') ?>">Rs <?= money($farmer['balance']) ?></td>
    </tr>
</table>

<div class="section-title"><i class="fas fa-file-signature"></i> Bookings</div>
<?php if ($bookings->num_rows > 0): ?>
<table>
    <thead>
        <tr>
            <th>Booking No</th>
            <th>Date</th>
            <th class="text-right" width="80">Qty (KG)</th>
            <th class="text-right" width="70">Rate</th>
            <th class="text-right" width="90">Wheat Value</th>
            <th class="text-right" width="80">Bag Chg</th>
            <th class="text-right" width="90">Total</th>
            <th class="text-right" width="80">Advance</th>
            <th class="text-right" width="80">Paid</th>
            <th class="text-right" width="80">Remaining</th>
            <th width="70">Status</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($b = $bookings->fetch_assoc()):
            $mans = ($b['bag_qty'] * 50) / 40;
            $bag_rate_total = ($b['bag_ownership'] === 'farmer' && $b['bag_rate'] > 0) ? ($b['bag_qty'] * $b['bag_rate']) : 0;
            $wheat_value = $mans * $b['rate'];
            $total_value = $wheat_value + $bag_rate_total;
            $total_paid = $b['advance_amount'] + $b['extra_paid'];
            $remaining = max(0, $total_value - $total_paid);
        ?>
        <tr>
            <td class="font-bold"><?= htmlspecialchars($b['booking_no']) ?></td>
            <td><?= $b['date'] ?></td>
            <td class="text-right"><?= qty($b['booked_qty']) ?></td>
            <td class="text-right"><?= money($b['rate']) ?></td>
            <td class="text-right"><?= money($wheat_value) ?></td>
            <td class="text-right"><?= $bag_rate_total > 0 ? money($bag_rate_total) : '-' ?></td>
            <td class="text-right font-bold"><?= money($total_value) ?></td>
            <td class="text-right"><?= money($b['advance_amount']) ?></td>
            <td class="text-right"><?= money($total_paid) ?></td>
            <td class="text-right"><?= money($remaining) ?></td>
            <td><span class="badge badge-<?= match($b['status']) { 'completed' => 'completed', 'partial' => 'partial', 'cancelled' => 'cancelled', default => 'secondary' } ?>"><?= ucfirst($b['status']) ?></span></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php else: ?>
<p style="text-align:center;padding:15px;color:#999;">No bookings for this farmer.</p>
<?php endif; ?>

<div class="footer">
    Flour Mill Management System &bull; Farmer Ledger &bull; <?= htmlspecialchars($farmer['name']) ?> &bull; Generated <?= date('d-M-Y H:i:s A') ?>
</div>

<script>window.onload = function() { setTimeout(function(){ window.print(); }, 400); };</script>
</body>
</html>
