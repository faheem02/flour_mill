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
        COALESCE((SELECT SUM(amount) FROM farmer_payments WHERE booking_id = b.id AND type='payment'), 0) AS total_paid
    FROM bookings b
    JOIN farmers f ON b.farmer_id = f.id
    ORDER BY b.date DESC, b.id DESC
");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-signature mr-1"></i> Booking List</h1>
    <div>
        <a href="add.php" class="btn btn-sm btn-primary"><i class="fas fa-plus-circle mr-1"></i> New Booking</a>
        <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
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
                        <th>Booked (KG)</th>
                        <th>Received (KG)</th>
                        <th>Pending (KG)</th>
                        <th>Progress</th>
                        <th>Advance</th>
                        <th>Paid</th>
                        <th>Remaining</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($b = $bookings->fetch_assoc()):
                        $pending = max(0, $b['booked_qty'] - $b['received_qty']);
                        $pct = $b['booked_qty'] > 0 ? min(100, round($b['received_qty'] / $b['booked_qty'] * 100)) : 0;
                        $total_value = $b['booked_qty'] * $b['rate'];
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
                        <td class="text-right"><?= qty($b['booked_qty']) ?></td>
                        <td class="text-right"><?= qty($b['received_qty']) ?></td>
                        <td class="text-right"><?= qty($pending) ?></td>
                        <td style="min-width:120px">
                            <div class="progress" style="height:20px">
                                <div class="progress-bar bg-<?= $pct >= 100 ? 'success' : 'warning' ?>" role="progressbar" style="width:<?= $pct ?>%" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?= $pct ?>%
                                </div>
                            </div>
                        </td>
                        <td class="text-right"><?= money($b['advance_amount']) ?></td>
                        <td class="text-right"><?= money($total_paid) ?></td>
                        <td class="text-right"><?= money($remaining) ?></td>
                        <td><span class="badge badge-<?= $badge ?>"><?= ucfirst($b['status']) ?></span></td>
                        <td>
                            <a href="view.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
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
