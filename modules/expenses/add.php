<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'expense_add';
$page_title = 'Add Expense';
require_once '../../includes/db.php';
include '../../includes/header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = (int)$_POST['category_id'];
    $date = $_POST['date'];
    $amount = str_replace(',', '', $_POST['amount']);
    $paid_to = sanitize($_POST['paid_to']);
    $payment_type = sanitize($_POST['payment_type']);
    $notes = sanitize($_POST['notes']);

    if ($amount <= 0) { $error = "Amount must be greater than zero."; }
    else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO expenses (category_id, date, amount, paid_to, payment_type, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isdsss", $category_id, $date, $amount, $paid_to, $payment_type, $notes);
            $stmt->execute();
            $exp_id = $conn->insert_id;

            $account_map = [1=>18, 2=>19, 3=>20, 4=>21, 5=>22, 6=>23, 7=>24];
            $expense_account = $account_map[$category_id] ?? 24;

            $cash_account = ($payment_type == 'bank') ? 3 : 2;
            autoJournalEntry($date, "Expense: $notes", [$expense_account => $amount], [$cash_account => $amount], $_SESSION['user_id']);

            $conn->commit();
            $success = "Expense recorded successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$categories = $conn->query("SELECT * FROM expense_categories WHERE status='active' ORDER BY name");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-coins mr-1"></i> Add Expense</h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Expense List</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">New Expense Entry</h6></div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Select</option>
                            <?php while ($c = $categories->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
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
                        <label>Paid To</label>
                        <input type="text" name="paid_to" class="form-control" placeholder="Person/Company name">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Payment Type</label>
                        <select name="payment_type" class="form-control">
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Description">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Save Expense</button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
