<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'warehouse_stock';
$page_title = 'Warehouse Stock';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stock'])) {
    $warehouse_id = (int)$_POST['warehouse_id'];
    $product_id = (int)$_POST['product_id'];
    $qty = str_replace(',', '', $_POST['qty']);
    $date = $_POST['date'];
    $purchase_from = sanitize($_POST['purchase_from']);
    $notes = sanitize($_POST['notes']);

    if ($warehouse_id <= 0 || $product_id <= 0 || $qty <= 0) {
        $error = "Please fill all required fields.";
    } else {
        $conn->begin_transaction();
        try {
            $conn->query("INSERT INTO warehouse_stock (warehouse_id, product_id, stock_qty)
                VALUES ($warehouse_id, $product_id, $qty)
                ON DUPLICATE KEY UPDATE stock_qty = stock_qty + $qty");

            $conn->query("UPDATE products SET stock_qty = stock_qty + $qty WHERE id = $product_id");

            $conn->query("INSERT INTO stock_ledger (product_id, date, type, warehouse_id, qty_in, balance_qty, notes)
                VALUES ($product_id, '$date', 'adjustment', $warehouse_id, $qty,
                    (SELECT COALESCE(stock_qty,0) FROM products WHERE id=$product_id),
                    'Purchased from: $purchase_from - $notes')");

            $conn->commit();
            $success = "Stock added successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$warehouses = $conn->query("SELECT id, name, type, location FROM warehouses WHERE status='active' ORDER BY name");
$products = $conn->query("SELECT id, name, stock_qty FROM products WHERE status='active' ORDER BY name");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-warehouse mr-1"></i> Warehouse Stock</h1>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addStockModal"><i class="fas fa-plus-circle mr-1"></i> Add Stock</button>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<?php while ($wh = $warehouses->fetch_assoc()):
    $wh_id = $wh['id'];
    $total_wh = $conn->query("SELECT COALESCE(SUM(stock_qty),0) as t FROM warehouse_stock WHERE warehouse_id = $wh_id")->fetch_assoc()['t'];
    $stock = $conn->query("SELECT ws.stock_qty, p.name, p.category
        FROM warehouse_stock ws
        JOIN products p ON ws.product_id = p.id
        WHERE ws.warehouse_id = $wh_id AND ws.stock_qty > 0
        ORDER BY p.name");
    $arrivals = $conn->query("SELECT wa.date, wa.net_weight
        FROM wheat_arrivals wa
        WHERE wa.warehouse_id = $wh_id AND wa.net_weight > 0
        ORDER BY wa.date DESC, wa.id DESC LIMIT 15");
?>
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-building mr-1"></i> <?= htmlspecialchars($wh['name']) ?>
            <small class="text-muted">(<?= ucfirst($wh['type']) ?>)</small>
        </h6>
        <span class="badge badge-primary"><?= qty($total_wh) ?> KG</span>
    </div>
    <div class="card-body">
        <?php if ($stock->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered" width="100%" cellspacing="0">
                <thead class="thead-dark">
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="text-right" width="150">Stock (KG)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($s = $stock->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= htmlspecialchars($s['category']) ?></td>
                        <td class="text-right font-weight-bold"><?= qty($s['stock_qty']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0">No stock in this warehouse.</p>
        <?php endif; ?>

        <?php if ($arrivals->num_rows > 0): ?>
        <hr>
        <h6 class="font-weight-bold text-muted small"><i class="fas fa-download mr-1"></i> Wheat Arrivals</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-sm" width="100%" cellspacing="0">
                <thead class="thead-light">
                    <tr>
                        <th>Date</th>
                        <th class="text-right" width="150">Net Weight (KG)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($a = $arrivals->fetch_assoc()): ?>
                    <tr>
                        <td><?= $a['date'] ?></td>
                        <td class="text-right text-success font-weight-bold">+<?= qty($a['net_weight']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <small class="text-muted">Last 15 arrivals</small>
        <?php endif; ?>
    </div>
</div>
<?php endwhile; ?>

<div class="modal fade" id="addStockModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle mr-1"></i> Add Stock</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Warehouse <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-control" required>
                            <option value="">Select Warehouse</option>
                            <?php $warehouses->data_seek(0); while ($w = $warehouses->fetch_assoc()): ?>
                            <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?> (<?= ucfirst($w['type']) ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-control" required>
                            <option value="">Select Product</option>
                            <?php $products->data_seek(0); while ($p = $products->fetch_assoc()): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity (KG) <span class="text-danger">*</span></label>
                        <input type="text" name="qty" class="form-control" placeholder="0" required oninput="this.value = this.value.replace(/[^0-9.]/g,'')">
                    </div>
                    <div class="form-group">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Purchased From <span class="text-danger">*</span></label>
                        <input type="text" name="purchase_from" class="form-control" placeholder="Supplier / Party name" required>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_stock" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
