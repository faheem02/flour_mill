<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'product_stock';
$page_title = 'Current Stock';
require_once '../../includes/db.php';
include '../../includes/header.php';

$result = $conn->query("SELECT p.*, 
  (SELECT pi.rate_per_kg FROM production_items pi 
   JOIN productions pr ON pi.production_id = pr.id 
   WHERE pi.product_id = p.id 
   ORDER BY pr.date DESC, pr.id DESC LIMIT 1) as latest_cost_rate
  FROM products p WHERE status='active' ORDER BY category, name");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-warehouse mr-1"></i> Current Stock</h1>
    <div>
        <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Products</a>
        <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Stock Position</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="text-right">Stock (KG)</th>
                        <th class="text-right">Cost Rate/KG</th>
                        <th class="text-right">Stock Value (Cost)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $total_qty=0; $total_value=0; while ($row = $result->fetch_assoc()):
                        $rate = ($row['latest_cost_rate'] ?: $row['cost_rate']) ?: $row['sale_price'];
                        $value = $row['stock_qty'] * $rate;
                        $total_qty += $row['stock_qty'];
                        $total_value += $value;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= $row['category'] ?></td>
                        <td class="text-right font-weight-bold <?= $row['stock_qty']<=0?'text-danger':'' ?>"><?= qty($row['stock_qty']) ?></td>
                        <td class="text-right"><?= money($rate) ?></td>
                        <td class="text-right"><?= money($value) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot class="table-active">
                    <tr>
                        <th colspan="2" class="text-right">Total</th>
                        <th class="text-right"><?= qty($total_qty) ?> KG</th>
                        <th></th>
                        <th class="text-right">Rs <?= money($total_value) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
