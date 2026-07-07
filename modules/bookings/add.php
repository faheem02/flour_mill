<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'booking_add';
$page_title = 'New Booking';
require_once '../../includes/db.php';
include '../../includes/header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $farmer_id      = (int)$_POST['farmer_id'];
    $date           = $_POST['date'];
    $booked_qty     = str_replace(',', '', $_POST['booked_qty']);
    $rate           = str_replace(',', '', $_POST['rate']);
    $advance_amount = str_replace(',', '', $_POST['advance_amount']);
    $expected_date  = $_POST['expected_date'] ?: null;
    $notes          = sanitize($_POST['notes']);

    $conn->begin_transaction();
    try {
        $booking_no = generateBookingNo();
        $stmt = $conn->prepare("INSERT INTO bookings (booking_no, farmer_id, date, booked_qty, rate, advance_amount, expected_date, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisddsss", $booking_no, $farmer_id, $date, $booked_qty, $rate, $advance_amount, $expected_date, $notes);
        $stmt->execute();
        $booking_id = $conn->insert_id;

        if ($advance_amount > 0) {
            $farmer_name = $conn->query("SELECT name FROM farmers WHERE id=$farmer_id")->fetch_row()[0];
            $conn->query("INSERT INTO farmer_payments (farmer_id, date, amount, type, payment_mode, booking_id, notes)
                VALUES ($farmer_id, '$date', $advance_amount, 'advance', 'cash', $booking_id, 'Advance against $booking_no')");
            $conn->query("UPDATE farmers SET balance = balance + $advance_amount WHERE id = $farmer_id");

            $desc = "Advance payment to farmer - $farmer_name (Booking #$booking_no)";
            autoJournalEntry($date, $desc, [17 => $advance_amount], [2 => $advance_amount], $_SESSION['user_id']);
        }

        $conn->commit();
        $success = "Booking <strong>$booking_no</strong> created successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

$farmers = $conn->query("SELECT id, name FROM farmers WHERE status='active' ORDER BY name");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-signature mr-1"></i> New Booking</h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-list mr-1"></i> Booking List</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Booking Details</h6></div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Farmer <span class="text-danger">*</span></label>
                        <select name="farmer_id" class="form-control" required>
                            <option value="">Select Farmer</option>
                            <?php while ($f = $farmers->fetch_assoc()): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <small><a href="farmers.php" target="_blank">+ Add New Farmer</a></small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Expected Delivery Date</label>
                        <input type="date" name="expected_date" class="form-control">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Booked Quantity (KG) <span class="text-danger">*</span></label>
                        <input type="text" name="booked_qty" class="form-control" placeholder="0" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Rate (per KG)</label>
                        <input type="text" name="rate" class="form-control" placeholder="0.00">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Advance Amount</label>
                        <input type="text" name="advance_amount" class="form-control" placeholder="0.00">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Total Estimated Value</label>
                        <input type="text" id="estimated_value" class="form-control" placeholder="0.00" readonly style="background:#f5f5f5">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Booking</button>
        </form>
    </div>
</div>

<script>
$('input[name="booked_qty"], input[name="rate"]').on('input', function() {
    var qty = parseFloat($('input[name="booked_qty"]').val().replace(/,/g, '')) || 0;
    var rate = parseFloat($('input[name="rate"]').val().replace(/,/g, '')) || 0;
    $('#estimated_value').val((qty * rate).toFixed(2));
});
</script>

<?php include '../../includes/footer.php'; ?>
