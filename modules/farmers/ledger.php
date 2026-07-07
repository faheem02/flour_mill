<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'farmer_ledger';
$page_title = 'Farmer Ledger';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$farmer_id = (int)($_GET['id'] ?? 0);
$farmer = $farmer_id ? $conn->query("SELECT * FROM farmers WHERE id = $farmer_id")->fetch_assoc() : null;

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-1"></i> Farmer Ledger</h1>
    <div>
        <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Farmer List</a>
        <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
        <?php if ($farmer): ?>
        <a href="payment.php?farmer_id=<?= $farmer['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-money-bill-wave mr-1"></i> Make Payment</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$farmer): ?>
<?php
$farmers = $conn->query("SELECT * FROM farmers ORDER BY name");
?>
<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Select a Farmer</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Village</th>
                        <th>City</th>
                        <th class="text-right">Balance</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($f = $farmers->fetch_assoc()): ?>
                    <tr>
                        <td><?= $f['id'] ?></td>
                        <td><strong><?= htmlspecialchars($f['name']) ?></strong></td>
                        <td><?= htmlspecialchars($f['phone']) ?></td>
                        <td><?= htmlspecialchars($f['village']) ?></td>
                        <td><?= htmlspecialchars($f['city']) ?></td>
                        <td class="text-right"><?= money($f['balance']) ?></td>
                        <td><a href="ledger.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-primary">View Ledger</a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>

<!-- Farmer Info -->
<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0"><?= htmlspecialchars($farmer['name']) ?></h6></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <small class="text-muted d-block">Phone</small>
                <strong><?= htmlspecialchars($farmer['phone'] ?: '-') ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Village / City</small>
                <strong><?= htmlspecialchars($farmer['village'] ?: '-') ?>, <?= htmlspecialchars($farmer['city'] ?: '-') ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Current Balance</small>
                <strong class="text-<?= $farmer['balance'] > 0 ? 'success' : 'muted' ?>">Rs <?= money($farmer['balance']) ?></strong>
            </div>
            <div class="col-md-3 text-right">
                <a href="payment.php?farmer_id=<?= $farmer['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-money-bill-wave mr-1"></i> Pay</a>
            </div>
        </div>
    </div>
</div>

<!-- Bookings -->
<?php
$bookings = $conn->query("
    SELECT b.*,
        COALESCE((SELECT SUM(amount) FROM farmer_payments WHERE booking_id = b.id AND type='payment'), 0) AS extra_paid
    FROM bookings b
    WHERE b.farmer_id = $farmer_id
    ORDER BY b.date DESC, b.id DESC
");
?>
<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Bookings</h6></div>
    <div class="card-body">
        <?php if ($bookings->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered datatable">
                <thead class="thead-dark">
                    <tr>
                        <th>Booking No</th>
                        <th>Date</th>
                        <th class="text-right">Qty (KG)</th>
                        <th class="text-right">Rate</th>
                        <th class="text-right">Total Value</th>
                        <th class="text-right">Advance</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Remaining</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($b = $bookings->fetch_assoc()):
                        $total_value = $b['booked_qty'] * $b['rate'];
                        $total_paid = $b['advance_amount'] + $b['extra_paid'];
                        $remaining = max(0, $total_value - $total_paid);
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($b['booking_no']) ?></strong></td>
                        <td><?= $b['date'] ?></td>
                        <td class="text-right"><?= qty($b['booked_qty']) ?></td>
                        <td class="text-right"><?= money($b['rate']) ?></td>
                        <td class="text-right"><?= money($total_value) ?></td>
                        <td class="text-right"><?= money($b['advance_amount']) ?></td>
                        <td class="text-right"><?= money($total_paid) ?></td>
                        <td class="text-right"><?= money($remaining) ?></td>
                        <td><span class="badge badge-<?= match($b['status']) { 'completed' => 'success', 'partial' => 'warning', 'cancelled' => 'danger', default => 'secondary' } ?>"><?= ucfirst($b['status']) ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0">No bookings for this farmer.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Payment History -->
<?php
$payments = $conn->query("
    SELECT p.*, b.booking_no
    FROM farmer_payments p
    LEFT JOIN bookings b ON p.booking_id = b.id
    WHERE p.farmer_id = $farmer_id
    ORDER BY p.date DESC, p.id DESC
");
?>
<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Payment History</h6></div>
    <div class="card-body">
        <?php if ($payments->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered datatable">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Mode</th>
                        <th>Booking</th>
                        <th class="text-right">Amount (Rs)</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($p = $payments->fetch_assoc()): ?>
                    <tr>
                        <td><?= $p['date'] ?></td>
                        <td><span class="badge badge-<?= $p['type'] == 'advance' ? 'info' : 'success' ?>"><?= ucfirst($p['type']) ?></span></td>
                        <td><span class="badge badge-<?= $p['payment_mode'] == 'bank' ? 'primary' : 'secondary' ?>"><?= ucfirst($p['payment_mode'] ?? 'cash') ?></span></td>
                        <td><?= htmlspecialchars($p['booking_no'] ?? '-') ?></td>
                        <td class="text-right"><?= money($p['amount']) ?></td>
                        <td><?= htmlspecialchars($p['notes']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0">No payments recorded.</p>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
