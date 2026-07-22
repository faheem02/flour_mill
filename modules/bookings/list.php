<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'booking_list';
$page_title = 'Booking List';
require_once '../../includes/db.php';
include '../../includes/header.php';

    $bookings = $conn->query("
    SELECT b.*, f.name AS farmer_name,
        COALESCE((SELECT quantity FROM booking_bags WHERE booking_id = b.id LIMIT 1), 0) AS bag_qty,
        COALESCE((SELECT ownership FROM booking_bags WHERE booking_id = b.id LIMIT 1), 'company') AS bag_ownership,
        COALESCE((SELECT bag_rate FROM booking_bags WHERE booking_id = b.id LIMIT 1), 0) AS bag_rate,
        COALESCE((SELECT SUM(amount) FROM farmer_payments WHERE booking_id = b.id AND type='payment'), 0) AS total_paid,
        COALESCE((SELECT SUM(gross_weight) FROM wheat_arrivals WHERE booking_id = b.id), 0) AS arrival_weight,
        COALESCE((SELECT SUM(num_bags) FROM wheat_arrivals WHERE booking_id = b.id), 0) AS arrival_bags,
        COALESCE((SELECT SUM(katt_applied) FROM wheat_arrivals WHERE booking_id = b.id), 0) AS arrival_katt
    FROM bookings b
    JOIN farmers f ON b.farmer_id = f.id
    ORDER BY b.date DESC, b.id DESC
");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-signature mr-1"></i> Booking List</h1>
    <div>
        <a href="add.php" class="btn btn-sm btn-primary"><i class="fas fa-plus-circle mr-1"></i> New Booking</a>
        <a href="print_list.php" class="btn btn-sm btn-info" target="_blank"><i class="fas fa-print mr-1"></i> Print Register</a>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Bookings</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable">
                <thead class="thead-dark">
                    <tr>
                        <th>Booking No</th>
                        <th>Date</th>
                        <th>Farmer</th>
                        <th class="text-center">Bags</th>
                        <th class="text-right">Booked (KG)<br><small style="font-weight:normal;font-size:10px;">Wheat only</small></th>
                        <th class="text-right">Katt Agreed (KG)<br><small style="font-weight:normal;font-size:10px;">decided at booking</small></th>
                        <th class="text-right">Received (KG)<br><small style="font-weight:normal;font-size:10px;">Wheat arrived</small></th>
                        <th class="text-right">Katt Came (KG)<br><small style="font-weight:normal;font-size:10px;">from arrivals</small></th>
                        <th class="text-right">Pending (KG)</th>
                        <th>Progress</th>
                        <th class="text-right">Wheat Price</th>
                        <th class="text-right">Bag Charges<br><small style="font-weight:normal;font-size:10px;">Qty × Rate</small></th>
                        <th class="text-right">Grand Total</th>
                        <th>Advance</th>
                        <th>Paid</th>
                        <th>Remaining</th>
                        <th>Status</th>
                        <th>Delivery</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($b = $bookings->fetch_assoc()):
                        $pending = max(0, $b['booked_qty'] - $b['received_qty']);
                        $pct = $b['booked_qty'] > 0 ? min(100, round($b['received_qty'] / $b['booked_qty'] * 100)) : 0;
                        $farmer_wheat = $b['bag_qty'] * 50;
                        $katt_agreed  = $b['bag_qty'] * $b['katt_per_bag'];
                        $mans = $farmer_wheat / 40;
                        $bag_rate_total = ($b['bag_ownership'] === 'farmer' && $b['bag_rate'] > 0) ? ($b['bag_qty'] * $b['bag_rate']) : 0;
                        $wheat_value = $mans * $b['rate'];
                        $total_value = $wheat_value + $bag_rate_total;
                        $total_paid = $b['advance_amount'] + $b['total_paid'];
                        $remaining = max(0, $total_value - $total_paid);
                        $badge = match($b['status']) {
                            'completed' => 'success',
                            'partial'   => 'warning',
                            'cancelled' => 'danger',
                            default     => 'secondary'
                        };
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($b['booking_no']) ?></strong></td>
                        <td><?= $b['date'] ?></td>
                        <td><?= htmlspecialchars($b['farmer_name']) ?></td>
                        <td class="text-center"><span class="badge badge-dark" style="font-size:13px;"><?= $b['bag_qty'] ?></span></td>
                        <td class="text-right"><strong><?= qty($b['booked_qty']) ?></strong> <small class="text-muted d-block"><?= $b['bag_qty'] ?> bags × 50</small></td>
                        <td class="text-right"><?= qty($katt_agreed) ?> <small class="text-muted d-block"><?= $b['bag_qty'] ?> × <?= qty($b['katt_per_bag']) ?></small></td>
                        <td class="text-right"><strong class="text-success"><?= qty($b['received_qty']) ?></strong> <small class="text-muted d-block"><?= $b['arrival_bags'] ?> bags</small></td>
                        <td class="text-right"><?= qty($b['arrival_katt']) ?></td>
                        <td class="text-right <?= $pending > 0 ? 'text-danger font-weight-bold' : 'text-success' ?>"><?= qty($pending) ?></td>
                        <td style="min-width:120px">
                            <div class="progress" style="height:20px">
                                <div class="progress-bar bg-<?= $pct >= 100 ? 'success' : 'warning' ?>" role="progressbar" style="width:<?= $pct ?>%" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?= $pct ?>%
                                </div>
                            </div>
                        </td>
                        <td class="text-right"><?= money($wheat_value) ?></td>
                        <td class="text-right"><?= $bag_rate_total > 0 ? money($bag_rate_total) . '<small class="text-muted d-block" style="font-size:11px;">' . $b['bag_qty'] . ' × ' . money($b['bag_rate']) . '</small>' : '-' ?></td>
                        <td class="text-right"><strong><?= money($total_value) ?></strong></td>
                        <td class="text-right"><?= money($b['advance_amount']) ?></td>
                        <td class="text-right"><?= money($total_paid) ?></td>
                        <td class="text-right"><?= money($remaining) ?></td>
                        <td><span class="badge badge-<?= $badge ?>"><?= ucfirst($b['status']) ?></span></td>
                        <td><span class="badge badge-<?= ($b['delivery_type'] ?? 'pickup') === 'delivery' ? 'info' : 'primary' ?>"><?= ($b['delivery_type'] ?? 'pickup') === 'delivery' ? 'We Pickup' : 'Farmer Sends' ?></span></td>
                        <td>
                            <a href="print.php?id=<?= $b['id'] ?>" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <a href="../farmers/payment.php?booking_id=<?= $b['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-money-bill-wave"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
