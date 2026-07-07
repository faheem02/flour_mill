<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'product_list';
$page_title = 'Products';
require_once '../../includes/db.php';
include '../../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = sanitize($_POST['name']);
    $category = sanitize($_POST['category']);
    $sale_price = str_replace(',', '', $_POST['sale_price']);
    $conn->query("INSERT INTO products (name, category, sale_price) VALUES ('$name', '$category', $sale_price)");
    $success = "Product added.";
}

$result = $conn->query("SELECT p.*, 
  (SELECT pi.rate_per_kg FROM production_items pi 
   JOIN productions pr ON pi.production_id = pr.id 
   WHERE pi.product_id = p.id 
   ORDER BY pr.date DESC, pr.id DESC LIMIT 1) as latest_cost_rate
  FROM products p ORDER BY category, name");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-boxes mr-1"></i> Products</h1>
    <div>
        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addModal"><i class="fas fa-plus-circle mr-1"></i> Add Product</button>
        <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
</div>

<?php if (isset($success)): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Products</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th class="text-right">Cost Rate/KG</th>
                        <th class="text-right">Stock (KG)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['category']) ?></td>
                        <td><?= $row['unit'] ?></td>
                        <?php $rate = ($row['latest_cost_rate'] ?: $row['cost_rate']) ?: $row['sale_price']; ?>
                        <td class="text-right"><?= money($rate) ?></td>
                        <td class="text-right"><?= qty($row['stock_qty']) ?></td>
                        <td><span class="badge badge-<?= $row['status']=='active'?'success':'secondary' ?>"><?= ucfirst($row['status']) ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
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
