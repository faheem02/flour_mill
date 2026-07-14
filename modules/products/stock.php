<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'product_stock';
$page_title = 'Product Stock';
require_once '../../includes/db.php';
include '../../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = sanitize($_POST['name']);
    $category = sanitize($_POST['category']);
    $sale_price = str_replace(',', '', $_POST['sale_price']);
    $conn->query("INSERT INTO products (name, category, sale_price) VALUES ('$name', '$category', '$sale_price')");
    header("Location: stock.php?added=1");
    exit;
}

$result = $conn->query("SELECT p.*, 
  (SELECT pi.rate_per_kg FROM production_items pi 
   JOIN productions pr ON pi.production_id = pr.id 
   WHERE pi.product_id = p.id 
   ORDER BY pr.date DESC, pr.id DESC LIMIT 1) as latest_cost_rate
  FROM products p ORDER BY category, name");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-boxes mr-1"></i> Product Stock</h1>
    <div>
        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addModal"><i class="fas fa-plus-circle mr-1"></i> Add Product</button>
        <button class="btn btn-sm btn-secondary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
</div>

<?php if (isset($_GET['added'])): ?><div class="alert alert-success alert-auto">Product added.</div><?php endif; ?>

<?php
$total_qty = 0;
$total_value = 0;
$products_data = [];
while ($row = $result->fetch_assoc()) {
    $rate = ($row['latest_cost_rate'] ?: $row['cost_rate']) ?: $row['sale_price'];
    $value = $row['stock_qty'] * $rate;
    $total_qty += $row['stock_qty'];
    $total_value += $value;
    $row['calc_rate'] = $rate;
    $row['calc_value'] = $value;
    $products_data[] = $row;
}
?>

<div class="card shadow mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="text-right">Cost Rate/KG</th>
                        <th class="text-right">Sale Price/KG</th>
                        <th class="text-right">Stock (KG)</th>
                        <th class="text-right">Stock Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; foreach ($products_data as $row): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                        <td><?= htmlspecialchars($row['category']) ?></td>
                        <td class="text-right"><?= money($row['calc_rate']) ?></td>
                        <td class="text-right"><?= money($row['sale_price']) ?></td>
                        <td class="text-right font-weight-bold <?= $row['stock_qty']<=0?'text-danger':'text-success' ?>"><?= qty($row['stock_qty']) ?></td>
                        <td class="text-right">Rs <?= money($row['calc_value']) ?></td>
                        <td><span class="badge badge-<?= $row['status']=='active'?'success':'secondary' ?>"><?= ucfirst($row['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-active">
                    <tr>
                        <th colspan="5" class="text-right">Total</th>
                        <th class="text-right"><?= qty($total_qty) ?> KG</th>
                        <th class="text-right">Rs <?= money($total_value) ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5>Add Product</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" class="form-control">
                            <option value="Flour">Flour (Atta, Maida, Suji)</option>
                            <option value="By-Product">By-Product (Bran)</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sale Price (per KG)</label>
                        <input type="text" name="sale_price" class="form-control" placeholder="0.00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_product" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
