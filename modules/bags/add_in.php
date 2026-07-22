<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'bag_in';
$page_title = 'Bags IN';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $warehouse_id = (int)$_POST['warehouse_id'];
    $qty = (int)str_replace(',', '', $_POST['qty']);
    $rate = str_replace(',', '', $_POST['rate'] ?? '0');
    $notes = sanitize($_POST['notes']);

    if ($warehouse_id <= 0) { $error = "Select warehouse."; }
    elseif ($qty <= 0) { $error = "Quantity must be greater than 0."; }
    else {
        $conn->begin_transaction();
        try {
            $conn->query("INSERT INTO bag_stock (warehouse_id, qty)
                VALUES ($warehouse_id, $qty)
                ON DUPLICATE KEY UPDATE qty = qty + $qty");

            $bal = $conn->query("SELECT qty FROM bag_stock WHERE warehouse_id=$warehouse_id")->fetch_assoc()['qty'];

            $conn->query("INSERT INTO bag_stock_ledger (date, warehouse_id, qty_in, qty_out, balance_qty, rate, type, notes)
                VALUES ('$date', $warehouse_id, $qty, 0, $bal, $rate, 'manual_in', '$notes')");

            $conn->commit();
            $new_id = $conn->insert_id;
            setFlash("Bags IN recorded: $qty bags added.");
            header("Location: print_slip.php?id=$new_id");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

include '../../includes/header.php';

$warehouses = $conn->query("SELECT id, name FROM warehouses WHERE status='active' ORDER BY name");
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-arrow-down text-success mr-1"></i> Bags IN</h1>
    <div>
        <a href="ledger.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Ledger</a>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Receive Bags</h6></div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Warehouse <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-control" required>
                            <option value="">Select</option>
                            <?php while ($w = $warehouses->fetch_assoc()): ?>
                            <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Quantity (Bags) <span class="text-danger">*</span></label>
                        <input type="number" name="qty" class="form-control" min="1" placeholder="0" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Rate (per Bag)</label>
                        <input type="number" name="rate" class="form-control" min="0" step="0.01" placeholder="0">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Opening stock, purchased from market, returned by farmer..."></textarea>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Save Bags IN</button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
