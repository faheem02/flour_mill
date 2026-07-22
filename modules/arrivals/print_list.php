<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$sql = "SELECT a.*, w.name as warehouse_name, b.booking_no, f.name as farmer_name, d.name as driver_name, bt.name as bag_type_name
        FROM wheat_arrivals a
        LEFT JOIN warehouses w ON a.warehouse_id = w.id
        LEFT JOIN bookings b ON a.booking_id = b.id
        LEFT JOIN farmers f ON b.farmer_id = f.id
        LEFT JOIN drivers d ON a.driver_id = d.id
        LEFT JOIN bag_types bt ON a.bag_type_id = bt.id
        WHERE 1=1";
$params = [];
$types = '';

if ($date_from !== '') {
    $sql .= " AND a.date >= ?";
    $params[] = $date_from;
    $types .= 's';
}
if ($date_to !== '') {
    $sql .= " AND a.date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$sql .= " ORDER BY a.date ASC, a.id ASC";

if ($types !== '') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$arrivals = [];
while ($row = $result->fetch_assoc()) {
    $arrivals[] = $row;
}

$total_bags = 0;
$total_net_kg = 0;
$total_actual_kg = 0;
$total_gross = 0;
$total_charges = 0;
$total_net_amt = 0;
$total_paid = 0;

foreach ($arrivals as $a) {
    $total_bags += (float)($a['num_bags'] ?? 0);
    $total_net_kg += (float)($a['net_weight'] ?? 0);
    $total_actual_kg += (float)($a['actual_weight'] ?? 0);
    $total_gross += (float)($a['gross_amount'] ?? 0);
    $charges = (float)($a['bag_amount'] ?? 0) + (float)($a['labour_charges'] ?? 0) + (float)($a['transport_charges'] ?? 0) + (float)($a['other_charges'] ?? 0);
    $total_charges += $charges;
    $net_amt = (float)($a['gross_amount'] ?? 0) - $charges;
    $total_net_amt += $net_amt;
    $total_paid += (float)($a['payment_now'] ?? 0);
}

$now = date('d-M-Y h:i A');
$generated_date = date('d-M-Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Arrival Register</title>
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
tr:hover td { background: #eef2f7; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.font-bold { font-weight: 700; }
.text-success { color: #28a745; }
.text-danger { color: #dc3545; }
.text-muted { color: #888; }
.summary-table { width: 300px; margin-left: auto; }
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
@media print { body { padding: 0; font-size: 11px; } .no-print { display: none !important; } @page { margin: 0.4in; size: landscape; } tr:nth-child(even) td { background: #f8f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { background: #1B2A4A !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .summary-table .grand-row td { background: #eef2f7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } tfoot td, tfoot th { background: #eef2f7 !important; font-weight: 700 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print();">🖨 Print</button>
    <a href="list.php" class="btn-back">← Back</a>
    <form class="filter-form" method="get">
        <label>From:</label>
        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
        <label>To:</label>
        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
        <button type="submit">Filter</button>
        <?php if ($date_from !== '' || $date_to !== ''): ?>
            <a href="print_list.php" style="padding:5px 10px;background:#e9ecef;color:#333;border-radius:4px;font-size:12px;text-decoration:none;">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="print-header">
    <h2>FLOUR MILL / ARRIVAL REGISTER</h2>
    <?php if ($date_from !== '' || $date_to !== ''): ?>
        <div class="date-range">
            <?php echo $date_from !== '' ? date('d-M-Y', strtotime($date_from)) : 'Start'; ?>
            &mdash;
            <?php echo $date_to !== '' ? date('d-M-Y', strtotime($date_to)) : 'End'; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (count($arrivals) === 0): ?>
    <p style="text-align:center;padding:30px;color:#888;font-size:14px;">No arrivals found.</p>
<?php else: ?>

<table>
    <thead>
        <tr>
            <th class="text-center">#</th>
            <th>Date</th>
            <th>Booking</th>
            <th>Farmer</th>
            <th>Vehicle#</th>
            <th class="text-right">Bags</th>
            <th class="text-right">Net KG</th>
            <th class="text-right">Actual KG</th>
            <th class="text-right">Diff</th>
            <th class="text-right">Gross (Rs)</th>
            <th class="text-right">Charges (Rs)</th>
            <th class="text-right">Net Amt (Rs)</th>
            <th class="text-right">Paid (Rs)</th>
        </tr>
    </thead>
    <tbody>
        <?php $i = 1; foreach ($arrivals as $a):
            $bags = (float)($a['num_bags'] ?? 0);
            $net = (float)($a['net_weight'] ?? 0);
            $actual = (float)($a['actual_weight'] ?? 0);
            $diff = $actual - $net;
            $gross = (float)($a['gross_amount'] ?? 0);
            $charges = (float)($a['bag_amount'] ?? 0) + (float)($a['labour_charges'] ?? 0) + (float)($a['transport_charges'] ?? 0) + (float)($a['other_charges'] ?? 0);
            $net_amt = $gross - $charges;
            $paid = (float)($a['payment_now'] ?? 0);
            $diff_class = $diff < 0 ? 'text-danger' : ($diff > 0 ? 'text-success' : '');
        ?>
        <tr>
            <td class="text-center"><?php echo $i++; ?></td>
            <td><?php echo date('d-M-Y', strtotime($a['date'])); ?></td>
            <td><?php echo htmlspecialchars($a['booking_no'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($a['farmer_name'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($a['vehicle_no'] ?? '-'); ?></td>
            <td class="text-right"><?php echo qty($bags); ?></td>
            <td class="text-right"><?php echo qty($net); ?></td>
            <td class="text-right"><?php echo qty($actual); ?></td>
            <td class="text-right <?php echo $diff_class; ?>"><?php echo qty($diff); ?></td>
            <td class="text-right"><?php echo money($gross); ?></td>
            <td class="text-right"><?php echo money($charges); ?></td>
            <td class="text-right font-bold"><?php echo money($net_amt); ?></td>
            <td class="text-right"><?php echo money($paid); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="5" class="text-right">TOTAL</th>
            <th class="text-right"><?php echo qty($total_bags); ?></th>
            <th class="text-right"><?php echo qty($total_net_kg); ?></th>
            <th class="text-right"><?php echo qty($total_actual_kg); ?></th>
            <th class="text-right"><?php echo qty($total_actual_kg - $total_net_kg); ?></th>
            <th class="text-right"><?php echo money($total_gross); ?></th>
            <th class="text-right"><?php echo money($total_charges); ?></th>
            <th class="text-right"><?php echo money($total_net_amt); ?></th>
            <th class="text-right"><?php echo money($total_paid); ?></th>
        </tr>
    </tfoot>
</table>

<table class="summary-table">
    <tr>
        <td class="font-bold">Total Entries</td>
        <td class="text-right"><?php echo count($arrivals); ?></td>
    </tr>
    <tr>
        <td class="font-bold">Total Bags</td>
        <td class="text-right"><?php echo qty($total_bags); ?></td>
    </tr>
    <tr>
        <td class="font-bold">Total Net KG</td>
        <td class="text-right"><?php echo qty($total_net_kg); ?></td>
    </tr>
    <tr>
        <td class="font-bold">Total Amount</td>
        <td class="text-right"><?php echo money($total_net_amt); ?></td>
    </tr>
    <tr>
        <td class="font-bold">Total Paid</td>
        <td class="text-right"><?php echo money($total_paid); ?></td>
    </tr>
    <tr class="grand-row">
        <td>Balance</td>
        <td class="text-right"><?php echo money($total_net_amt - $total_paid); ?></td>
    </tr>
</table>

<?php endif; ?>

<div class="footer">
    Flour Mill Management System | Arrival Register | Generated <?php echo $now; ?>
</div>

<script>
window.onload = function() {
    setTimeout(function(){ window.print(); }, 400);
};
</script>

</body>
</html>
