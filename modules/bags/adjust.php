<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'bag_adjust';
$page_title = 'Bag Stock Adjustment';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $warehouse_id = (int)$_POST['warehouse_id'];
    $new_qty = (int)str_replace(',', '', $_POST['new_qty']);
    $notes = sanitize($_POST['notes']);

    if ($warehouse_id <= 0) { $error = "Select warehouse."; }
    elseif ($new_qty < 0) { $error = "New quantity cannot be negative."; }
    else {
        $old_row = $conn->query("SELECT qty FROM bag_stock WHERE warehouse_id=$warehouse_id")->fetch_assoc();
        $old_qty = $old_row ? $old_row['qty'] : 0;
        $diff = $new_qty - $old_qty;

        $conn->begin_transaction();
        try {
            if ($old_row) {
                $conn->query("UPDATE bag_stock SET qty = $new_qty WHERE warehouse_id=$warehouse_id");
            } else {
                $conn->query("INSERT INTO bag_stock (warehouse_id, qty) VALUES ($warehouse_id, $new_qty)");
            }

            if ($diff != 0) {
                if ($diff > 0) {
                    $conn->query("INSERT INTO bag_stock_ledger (date, warehouse_id, qty_in, qty_out, balance_qty, type, notes)
                        VALUES ('$date', $warehouse_id, $diff, 0, $new_qty, 'adjustment', 'Adjustment: $old_qty -> $new_qty. $notes')");
                } else {
                    $conn->query("INSERT INTO bag_stock_ledger (date, warehouse_id, qty_in, qty_out, balance_qty, type, notes)
                        VALUES ('$date', $warehouse_id, 0, " . abs($diff) . ", $new_qty, 'adjustment', 'Adjustment: $old_qty -> $new_qty. $notes')");
                }
            }

            $conn->commit();
            setFlash("Stock adjusted. $old_qty -> $new_qty bags.");
            header("Location: ledger.php");
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
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-sliders-h mr-1"></i> Bag Stock Adjustment</h1>
    <a href="ledger.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Ledger</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Adjust Stock</h6></div>
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
                        <select name="warehouse_id" id="warehouseSelect" class="form-control" required>
                            <option value="">Select</option>
                            <?php while ($w = $warehouses->fetch_assoc()): ?>
                            <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>New Quantity (Bags) <span class="text-danger">*</span></label>
                        <input type="number" name="new_qty" class="form-control" min="0" placeholder="0" required>
                        <small class="text-muted" id="stockInfo"></small>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Reason / Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Damaged bags removed, opening balance, stock count correction..."></textarea>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-info"><i class="fas fa-save mr-1"></i> Save Adjustment</button>
        </form>
    </div>
</div>

<script>
$('#warehouseSelect').on('change', function() {
    var wh = $(this).val();
    if (wh) {
        $.get('get_bag_stock.php', { warehouse_id: wh }, function(d) {
            $('#stockInfo').text('Current stock: ' + d.qty + ' bags');
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
