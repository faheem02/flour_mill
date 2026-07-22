<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'stock_adjustment';
$page_title = 'Stock Adjustment';
require_once '../../includes/db.php';
include '../../includes/header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)$_POST['product_id'];
    $type = sanitize($_POST['adjustment_type']);
    $qty = str_replace(',', '', $_POST['qty']);
    $reason = sanitize($_POST['reason']);
    $date = $_POST['date'];

    if ($qty <= 0) { $error = "Qty must be greater than zero."; }
    else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO stock_adjustments (date, product_id, type, qty, reason) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sisds", $date, $product_id, $type, $qty, $reason);
            $stmt->execute();

            if ($type == 'excess') {
                $conn->query("UPDATE products SET stock_qty = stock_qty + $qty WHERE id = $product_id");
                $conn->query("INSERT INTO stock_ledger (product_id, date, type, qty_in, balance_qty, notes)
                    VALUES ($product_id, '$date', 'adjustment', $qty, (SELECT COALESCE(stock_qty,0) FROM products WHERE id=$product_id), 'Excess adjustment - $reason')");
            } else {
                $conn->query("UPDATE products SET stock_qty = stock_qty - $qty WHERE id = $product_id");
                $conn->query("INSERT INTO stock_ledger (product_id, date, type, qty_out, balance_qty, notes)
                    VALUES ($product_id, '$date', 'adjustment', $qty, (SELECT COALESCE(stock_qty,0) FROM products WHERE id=$product_id), 'Shortage/Damage - $reason')");
            }

            $conn->commit();
            $success = "Stock adjustment recorded.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$products = $conn->query("SELECT id, name, stock_qty FROM products WHERE status='active' ORDER BY name");
$adjustments = $conn->query("SELECT sa.*, p.name as product_name FROM stock_adjustments sa LEFT JOIN products p ON sa.product_id=p.id ORDER BY sa.date DESC LIMIT 50");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-sliders-h mr-1"></i> Stock Adjustment</h1>
    <div>
        <a href="ledger.php" class="btn btn-sm btn-info"><i class="fas fa-book mr-1"></i> Stock Ledger</a>
        <button class="btn btn-sm btn-primary ml-1" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<div class="row">
    <div class="col-md-5">
        <div class="card shadow mb-4">
            <div class="card-header"><h6 class="font-weight-bold m-0">New Adjustment</h6></div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-control" required>
                            <option value="">Select</option>
                            <?php while ($p = $products->fetch_assoc()): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (Stock: <?= qty($p['stock_qty']) ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="adjustment_type" class="form-control">
                            <option value="shortage">Shortage (Decrease Stock)</option>
                            <option value="excess">Excess (Increase Stock)</option>
                            <option value="damage">Damage (Decrease Stock)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Qty (KG) <span class="text-danger">*</span></label>
                        <input type="text" name="qty" class="form-control" placeholder="0" required oninput="this.value = this.value.replace(/[^0-9.]/g,'')">
                    </div>
                    <div class="form-group">
                        <label>Reason</label>
                        <textarea name="reason" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-check mr-1"></i> Save Adjustment</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card shadow mb-4">
            <div class="card-header"><h6 class="font-weight-bold m-0">Recent Adjustments</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%">
                        <thead class="thead-dark">
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Type</th>
                                <th class="text-right">Qty</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($a = $adjustments->fetch_assoc()): ?>
                            <tr>
                                <td><?= $a['date'] ?></td>
                                <td><?= htmlspecialchars($a['product_name']) ?></td>
                                <td><span class="badge badge-<?= $a['type']=='excess'?'success':'danger' ?>"><?= ucfirst($a['type']) ?></span></td>
                                <td class="text-right"><?= qty($a['qty']) ?> KG</td>
                                <td><?= htmlspecialchars($a['reason']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
