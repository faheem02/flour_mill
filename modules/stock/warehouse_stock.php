<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'warehouse_stock';
$page_title = 'Raw Material Stock';
require_once '../../includes/db.php';
include '../../includes/header.php';

$total_received = $conn->query("SELECT COALESCE(SUM(net_weight),0) as t FROM wheat_arrivals WHERE net_weight > 0")->fetch_assoc()['t'];
$total_used = $conn->query("SELECT COALESCE(SUM(wheat_qty),0) as t FROM productions WHERE wheat_qty > 0")->fetch_assoc()['t'];
$total_stock = $conn->query("SELECT COALESCE(SUM(ws.stock_qty),0) as t FROM warehouse_stock ws JOIN products p ON ws.product_id=p.id WHERE p.name='Wheat (Gandam)'")->fetch_assoc()['t'];
$warehouses = $conn->query("SELECT id, name FROM warehouses WHERE status='active' ORDER BY name");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-warehouse mr-1"></i> Raw Material Stock (Wheat)</h1>
    <div>
        <a href="<?= $base_url ?>modules/arrivals/add.php" class="btn btn-sm btn-primary"><i class="fas fa-plus-circle mr-1"></i> New Arrival</a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-xl-4 col-md-4 mb-3">
        <div class="card border-left-primary h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Received</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= qty($total_received) ?> KG</div>
                    </div>
                    <div class="col-auto"><i class="fas fa-download fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 mb-3">
        <div class="card border-left-warning h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Used in Production</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= qty($total_used) ?> KG</div>
                    </div>
                    <div class="col-auto"><i class="fas fa-upload fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 mb-3">
        <div class="card border-left-success h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Available Stock</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= qty($total_stock) ?> KG</div>
                    </div>
                    <div class="col-auto"><i class="fas fa-boxes fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php while ($wh = $warehouses->fetch_assoc()):
    $wh_id = $wh['id'];
    $stock = $conn->query("SELECT ws.stock_qty, p.name, p.category
        FROM warehouse_stock ws
        JOIN products p ON ws.product_id = p.id
        WHERE ws.warehouse_id = $wh_id AND ws.stock_qty > 0 AND p.name = 'Wheat (Gandam)'
        ORDER BY p.name");
?>
<div class="card shadow mb-4">
    <div class="card-header">
        <h6 class="font-weight-bold m-0">
            <i class="fas fa-building mr-1"></i> <?= htmlspecialchars($wh['name']) ?>
        </h6>
    </div>
    <div class="card-body">
        <?php if ($stock->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="thead-dark">
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="text-right">Stock (KG)</th>
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
        <p class="text-muted mb-0">No raw material stock in this warehouse.</p>
        <?php endif; ?>
    </div>
</div>
<?php endwhile; ?>

<?php if ($total_stock <= 0 && $total_received <= 0): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle mr-1"></i> No raw material stock yet. 
    <a href="<?= $base_url ?>modules/arrivals/add.php" class="alert-link">Record a wheat arrival</a> to see stock here.
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
