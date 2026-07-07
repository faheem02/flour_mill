<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'farmer_payment';
$page_title = 'Farmer Payment';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$farmer_id = (int)($_GET['farmer_id'] ?? 0);
$booking_id = (int)($_GET['booking_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
    $farmer_id = (int)$_POST['farmer_id'];
    $booking_id = (int)$_POST['booking_id'];
    $date = $_POST['date'];
    $amount = str_replace(',', '', $_POST['amount']);
    $payment_mode = $_POST['payment_mode'] ?? 'cash';
    $notes = sanitize($_POST['notes']);

    if ($amount > 0) {
        $farmer_name = $conn->query("SELECT name FROM farmers WHERE id=$farmer_id")->fetch_row()[0];

        $conn->begin_transaction();
        try {
            $conn->query("INSERT INTO farmer_payments (farmer_id, date, amount, type, payment_mode, booking_id, notes)
                VALUES ($farmer_id, '$date', $amount, 'payment', '$payment_mode', " . ($booking_id ?: "NULL") . ", '$notes')");
            $conn->query("UPDATE farmers SET balance = balance + $amount WHERE id = $farmer_id");

            // Auto journal entry
            $desc = "Payment to farmer - $farmer_name" . ($booking_id ? " (Booking #$booking_id)" : "");
            $credit_account = ($payment_mode == 'bank') ? 3 : 2; // Bank (3) or Cash (2)
            autoJournalEntry($date, $desc, [17 => $amount], [$credit_account => $amount], $_SESSION['user_id']);

            $conn->commit();
            setFlash("Payment of Rs " . money($amount) . " recorded.");
        } catch (Exception $e) {
            $conn->rollback();
            setFlash("Error: " . $e->getMessage());
        }
    }
    header("Location: payment.php?farmer_id=$farmer_id" . ($booking_id ? "&booking_id=$booking_id" : ""));
    exit;
}

include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-money-bill-wave mr-1"></i> Farmer Payment</h1>
    <div>
        <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Farmer List</a>
        <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
</div>

<?= flashMessage() ?>

<?php
// If only booking_id is given, resolve farmer_id from booking
if (!$farmer_id && $booking_id) {
    $b = $conn->query("SELECT farmer_id FROM bookings WHERE id = $booking_id")->fetch_assoc();
    if ($b) $farmer_id = (int)$b['farmer_id'];
}

if (!$farmer_id && !$booking_id):
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
                        <td class="text-right"><?= money($f['balance']) ?></td>
                        <td><a href="payment.php?farmer_id=<?= $f['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-money-bill-wave mr-1"></i> Pay</a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($farmer_id): ?>
<?php
$farmer = $conn->query("SELECT * FROM farmers WHERE id = $farmer_id")->fetch_assoc();
if (!$farmer) { echo "<div class='alert alert-danger'>Farmer not found.</div>"; include '../../includes/footer.php'; exit; }

$bookings = $conn->query("
    SELECT b.*,
        COALESCE((SELECT SUM(amount) FROM farmer_payments WHERE booking_id = b.id AND type='payment'), 0) AS extra_paid
    FROM bookings b WHERE b.farmer_id = $farmer_id ORDER BY b.date DESC
");
?>

<!-- Farmer Info + Pay Form -->
<div class="row">
    <div class="col-md-5">
        <div class="card shadow mb-4">
            <div class="card-header"><h6 class="font-weight-bold m-0"><?= htmlspecialchars($farmer['name']) ?></h6></div>
            <div class="card-body">
                <p><strong>Balance:</strong> Rs <?= money($farmer['balance']) ?></p>
                <hr>
                <form method="POST">
                    <input type="hidden" name="farmer_id" value="<?= $farmer_id ?>">
                    <div class="form-group">
                        <label>Pay Against Booking <small>(optional)</small></label>
                        <select name="booking_id" class="form-control">
                            <option value="">-- Without Booking --</option>
                            <?php while ($b = $bookings->fetch_assoc()):
                                $total_val = $b['booked_qty'] * $b['rate'];
                                $total_paid = $b['advance_amount'] + $b['extra_paid'];
                                $remaining = max(0, $total_val - $total_paid);
                            ?>
                            <option value="<?= $b['id'] ?>" <?= $b['id'] == $booking_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['booking_no']) ?> (Remaining: Rs <?= money($remaining) ?>)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Amount (Rs) <span class="text-danger">*</span></label>
                        <input type="text" name="amount" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label>Payment Mode</label>
                        <select name="payment_mode" class="form-control">
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Payment details..."></textarea>
                    </div>
                    <button type="submit" name="pay" class="btn btn-primary btn-block"><i class="fas fa-money-bill-wave mr-1"></i> Record Payment</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
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
                    <table class="table table-bordered table-sm">
                        <thead class="thead-dark">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Mode</th>
                                <th>Booking</th>
                                <th class="text-right">Amount</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $total = 0; while ($p = $payments->fetch_assoc()): $total += $p['amount']; ?>
                            <tr>
                                <td><?= $p['date'] ?></td>
                                <td><span class="badge badge-<?= $p['type'] == 'advance' ? 'info' : 'success' ?>"><?= ucfirst($p['type']) ?></span></td>
                                <td><span class="badge badge-<?= $p['payment_mode'] == 'bank' ? 'primary' : 'secondary' ?>"><?= ucfirst($p['payment_mode']) ?></span></td>
                                <td><?= htmlspecialchars($p['booking_no'] ?? '-') ?></td>
                                <td class="text-right"><?= money($p['amount']) ?></td>
                                <td><?= htmlspecialchars($p['notes']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot class="table-active">
                            <tr>
                                <th colspan="4" class="text-right">Total</th>
                                <th class="text-right"><?= money($total) ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No payments recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Bookings -->
        <?php
        $conn->query("SET @prev_farmer = $farmer_id");
        $pending_b = $conn->query("
            SELECT b.*,
                COALESCE((SELECT SUM(amount) FROM farmer_payments WHERE booking_id = b.id AND type='payment'), 0) AS extra_paid
            FROM bookings b
            WHERE b.farmer_id = $farmer_id AND b.status IN ('pending','partial')
            ORDER BY b.date DESC
        ");
        ?>
        <?php if ($pending_b && $pending_b->num_rows > 0): ?>
        <div class="card shadow mb-4">
            <div class="card-header"><h6 class="font-weight-bold m-0">Pending Bookings</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="thead-light">
                            <tr>
                                <th>Booking</th>
                                <th class="text-right">Value</th>
                                <th class="text-right">Advance</th>
                                <th class="text-right">Paid</th>
                                <th class="text-right">Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($pb = $pending_b->fetch_assoc()):
                                $tv = $pb['booked_qty'] * $pb['rate'];
                                $tp2 = $pb['advance_amount'] + $pb['extra_paid'];
                                $rem = max(0, $tv - $tp2);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($pb['booking_no']) ?></td>
                                <td class="text-right"><?= money($tv) ?></td>
                                <td class="text-right"><?= money($pb['advance_amount']) ?></td>
                                <td class="text-right"><?= money($tp2) ?></td>
                                <td class="text-right"><?= money($rem) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
