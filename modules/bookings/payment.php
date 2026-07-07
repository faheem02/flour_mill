<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'booking_list';
$page_title = 'Farmer Payment';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
    $farmer_id = (int)$_POST['farmer_id'];
    $booking_id = (int)$_POST['booking_id'];
    $date = $_POST['date'];
    $amount = str_replace(',', '', $_POST['amount']);
    $notes = sanitize($_POST['notes']);

    if ($amount > 0) {
        $conn->query("INSERT INTO farmer_payments (farmer_id, date, amount, type, booking_id, notes)
            VALUES ($farmer_id, '$date', $amount, 'payment', $booking_id, '$notes')");
        $conn->query("UPDATE farmers SET balance = balance + $amount WHERE id = $farmer_id");
        setFlash("Payment of Rs " . money($amount) . " recorded.");
    }
    header("Location: payment.php?booking_id=$booking_id");
    exit;
}

$booking_id = (int)($_GET['booking_id'] ?? 0);
$farmer_id = (int)($_GET['farmer_id'] ?? 0);

include '../../includes/header.php';

// Load booking
$booking = null;
if ($booking_id) {
    $booking = $conn->query("SELECT b.*, f.name AS farmer_name, f.balance AS farmer_balance, f.id AS f_id
        FROM bookings b JOIN farmers f ON b.farmer_id = f.id WHERE b.id = $booking_id")->fetch_assoc();
    $farmer_id = $booking['f_id'];
}

// Load farmer
$farmer = null;
if ($farmer_id && !$booking) {
    $farmer = $conn->query("SELECT * FROM farmers WHERE id = $farmer_id")->fetch_assoc();
}

// Get payments
$payments = [];
if ($booking_id) {
    $payments = $conn->query("SELECT * FROM farmer_payments WHERE booking_id = $booking_id ORDER BY date, id");
} elseif ($farmer_id) {
    $payments = $conn->query("SELECT p.*, b.booking_no FROM farmer_payments p LEFT JOIN bookings b ON p.booking_id = b.id WHERE p.farmer_id = $farmer_id ORDER BY p.date DESC, p.id DESC");
}

$total_paid = 0;
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-money-bill-wave mr-1"></i> Farmer Payment</h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Booking List</a>
</div>

<?= flashMessage() ?>

<?php if ($booking): ?>
<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Booking: <?= htmlspecialchars($booking['booking_no']) ?></h6></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <small class="text-muted d-block">Farmer</small>
                <strong><?= htmlspecialchars($booking['farmer_name']) ?></strong>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Booked Qty</small>
                <strong><?= qty($booking['booked_qty']) ?> KG</strong>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Rate / KG</small>
                <strong>Rs <?= money($booking['rate']) ?></strong>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Total Value</small>
                <strong>Rs <?= money($booking['booked_qty'] * $booking['rate']) ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Farmer Balance</small>
                <strong class="text-<?= $booking['farmer_balance'] > 0 ? 'success' : 'muted' ?>">Rs <?= money($booking['farmer_balance']) ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Record Payment</h6></div>
    <div class="card-body">
        <form method="POST" class="form-inline">
            <input type="hidden" name="farmer_id" value="<?= $booking['f_id'] ?>">
            <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
            <div class="form-group mr-2">
                <label class="mr-1">Date</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group mr-2">
                <label class="mr-1">Amount (Rs)</label>
                <input type="text" name="amount" class="form-control form-control-sm" placeholder="0.00" style="width:150px" required>
            </div>
            <div class="form-group mr-2">
                <label class="mr-1">Notes</label>
                <input type="text" name="notes" class="form-control form-control-sm" placeholder="Remaining payment" style="width:200px">
            </div>
            <button type="submit" name="pay" class="btn btn-primary btn-sm"><i class="fas fa-money-bill-wave mr-1"></i> Pay</button>
        </form>
    </div>
</div>
<?php elseif ($farmer): ?>
<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Farmer: <?= htmlspecialchars($farmer['name']) ?></h6></div>
    <div class="card-body">
        <p>Select a booking to make payment against, or view all payments for this farmer.</p>
        <?php
        $bookings = $conn->query("SELECT id, booking_no, date, booked_qty, rate, received_qty, advance_amount, status FROM bookings WHERE farmer_id = $farmer_id ORDER BY date DESC");
        if ($bookings->num_rows > 0):
        ?>
        <table class="table table-bordered table-sm">
            <thead><tr><th>Booking</th><th>Date</th><th>Qty</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php while ($b = $bookings->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($b['booking_no']) ?></td>
                    <td><?= $b['date'] ?></td>
                    <td><?= qty($b['booked_qty']) ?> KG</td>
                    <td><?= ucfirst($b['status']) ?></td>
                    <td><a href="payment.php?booking_id=<?= $b['id'] ?>" class="btn btn-sm btn-primary">Pay</a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="text-muted">No bookings for this farmer.</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Payment History -->
<?php if ($payments && $payments->num_rows > 0): ?>
<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Payment History</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <?php if ($farmer_id && !$booking_id): ?><th>Booking</th><?php endif; ?>
                        <th class="text-right">Amount (Rs)</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($p = $payments->fetch_assoc()): $total_paid += $p['amount']; ?>
                    <tr>
                        <td><?= $p['date'] ?></td>
                        <td><span class="badge badge-<?= $p['type'] == 'advance' ? 'info' : 'success' ?>"><?= ucfirst($p['type']) ?></span></td>
                        <?php if ($farmer_id && !$booking_id): ?><td><?= htmlspecialchars($p['booking_no'] ?? '-') ?></td><?php endif; ?>
                        <td class="text-right"><?= money($p['amount']) ?></td>
                        <td><?= htmlspecialchars($p['notes']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot class="table-active">
                    <tr>
                        <th colspan="<?= ($farmer_id && !$booking_id) ? 3 : 2 ?>" class="text-right">Total Paid</th>
                        <th class="text-right">Rs <?= money($total_paid) ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!$booking_id && !$farmer_id): ?>
<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Farmers</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable">
                <thead class="thead-dark">
                    <tr>
                        <th>Farmer</th>
                        <th>Village</th>
                        <th class="text-right">Balance</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $farmers = $conn->query("SELECT * FROM farmers ORDER BY name");
                    while ($f = $farmers->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($f['name']) ?></td>
                        <td><?= htmlspecialchars($f['village']) ?></td>
                        <td class="text-right">Rs <?= money($f['balance']) ?></td>
                        <td><a href="payment.php?farmer_id=<?= $f['id'] ?>" class="btn btn-sm btn-primary">Pay</a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
