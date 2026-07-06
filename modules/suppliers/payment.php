<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'supplier_payment';
$page_title = 'Supplier Payment';
require_once '../../includes/db.php';
include '../../includes/header.php';

$error = $success = '';
$sid = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid = (int)$_POST['supplier_id'];
    $amount = str_replace(',', '', $_POST['amount']);
    $date = $_POST['date'];
    $payment_type = sanitize($_POST['payment_type']);
    $notes = sanitize($_POST['notes']);

    if ($amount <= 0) { $error = "Amount must be greater than zero."; }
    else {
        $conn->begin_transaction();
        try {
            // Update supplier balance (credit reduces balance)
            $conn->query("UPDATE suppliers SET balance = balance - $amount WHERE id = $sid");

            // Add ledger entry
            $stmt = $conn->prepare("INSERT INTO supplier_ledger (supplier_id, date, type, credit, balance, notes)
                VALUES (?, ?, 'payment', ?, (SELECT COALESCE(balance,0) FROM suppliers WHERE id=?), ?)");
            $stmt->bind_param("isdis", $sid, $date, $amount, $sid, $notes);
            $stmt->execute();

            // Auto journal entry
            $desc = "Payment to supplier - " . $conn->query("SELECT name FROM suppliers WHERE id=$sid")->fetch_row()[0];
            $cash_account = ($payment_type == 'bank') ? 3 : 2; // Bank (1-2000) or Cash (1-1000)
            autoJournalEntry($date, $desc, [6 => $amount], [$cash_account => $amount], $_SESSION['user_id']);

            $conn->commit();
            $success = "Payment recorded successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$suppliers = $conn->query("SELECT id, name, balance FROM suppliers ORDER BY name ASC");
$selected = $conn->query("SELECT * FROM suppliers WHERE id=$sid")->fetch_assoc();
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-money-bill-wave mr-1"></i> Supplier Payment</h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Record Payment</h6></div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-control" required>
                            <option value="">Select Supplier</option>
                            <?php while ($s = $suppliers->fetch_assoc()): ?>
                            <option value="<?= $s['id'] ?>" <?= $sid==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?> (Bal: <?= money($s['balance']) ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Payment Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Amount <span class="text-danger">*</span></label>
                        <input type="text" name="amount" class="form-control" placeholder="0.00" required oninput="this.value = this.value.replace(/[^0-9.]/g,'')">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Payment Type</label>
                        <select name="payment_type" class="form-control">
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="form-group">
                        <label>Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional notes">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> Record Payment</button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
