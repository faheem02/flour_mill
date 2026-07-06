<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'customer_receipt';
$page_title = 'Record Receipt';
require_once '../../includes/db.php';
include '../../includes/header.php';

$error = $success = '';
$cid = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid = (int)$_POST['customer_id'];
    $amount = str_replace(',', '', $_POST['amount']);
    $date = $_POST['date'];
    $payment_type = sanitize($_POST['payment_type']);
    $notes = sanitize($_POST['notes']);

    if ($amount <= 0) { $error = "Amount must be greater than zero."; }
    else {
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE customers SET balance = balance - $amount WHERE id = $cid");

            $stmt = $conn->prepare("INSERT INTO customer_ledger (customer_id, date, type, credit, balance, notes)
                VALUES (?, ?, 'receipt', ?, (SELECT COALESCE(balance,0) FROM customers WHERE id=?), ?)");
            $stmt->bind_param("isdis", $cid, $date, $amount, $cid, $notes);
            $stmt->execute();

            $desc = "Receipt from customer - " . $conn->query("SELECT name FROM customers WHERE id=$cid")->fetch_row()[0];
            $cash_account = ($payment_type == 'bank') ? 3 : 2;
            autoJournalEntry($date, $desc, [$cash_account => $amount], [5 => $amount], $_SESSION['user_id']);

            $conn->commit();
            $success = "Receipt recorded successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$customers = $conn->query("SELECT id, name, balance FROM customers ORDER BY name ASC");
$selected = $conn->query("SELECT * FROM customers WHERE id=$cid")->fetch_assoc();
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-money-bill-wave mr-1"></i> Record Receipt</h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Payment from Customer</h6></div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-control" required>
                            <option value="">Select Customer</option>
                            <?php while ($c = $customers->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" <?= $cid==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?> (Bal: Rs <?= money($c['balance']) ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date <span class="text-danger">*</span></label>
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
            <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i> Record Receipt</button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
