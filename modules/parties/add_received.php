<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'party_list';
$page_title = 'Add Received';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$party_id = (int)($_GET['id'] ?? 0);
$party = $party_id ? $conn->query("SELECT * FROM general_parties WHERE id = $party_id")->fetch_assoc() : null;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int)$_POST['party_id'];
    $date = $_POST['date'];
    $amount = (float)str_replace(',', '', $_POST['amount']);
    $notes = sanitize($_POST['notes']);

    if ($pid <= 0) { $error = "Select a party."; }
    elseif ($amount <= 0) { $error = "Amount must be greater than 0."; }
    else {
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE general_parties SET balance = balance + $amount WHERE id = $pid");
            $new_bal = $conn->query("SELECT balance FROM general_parties WHERE id=$pid")->fetch_assoc()['balance'];
            $conn->query("INSERT INTO party_transactions (party_id, date, type, amount, balance_after, notes)
                VALUES ($pid, '$date', 'receivable', $amount, $new_bal, '$notes')");
            $conn->commit();
            setFlash("Received Rs " . number_format($amount, 2) . " recorded.");
            header("Location: ledger.php?id=$pid");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$parties = $conn->query("SELECT id, name, balance FROM general_parties WHERE status='active' ORDER BY name");
include '../../includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-invoice-dollar mr-1 text-info"></i> Add Received</h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0"><i class="fas fa-plus-circle mr-1"></i> Received Entry <small class="text-muted">(We received from party)</small></h6></div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Party <span class="text-danger">*</span></label>
                        <select name="party_id" class="form-control" required>
                            <option value="">Select Party</option>
                            <?php while ($p = $parties->fetch_assoc()): ?>
                            <option value="<?= $p['id'] ?>" <?= ($party && $party['id'] == $p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?> (Bal: <?= money($p['balance']) ?>)</option>
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
                        <label>Amount (Rs) <span class="text-danger">*</span></label>
                        <input type="text" name="amount" class="form-control" placeholder="0" required oninput="this.value=this.value.replace(/[^0-9.]/g,'')">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes"></textarea>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-info"><i class="fas fa-save mr-1"></i> Save Received</button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
