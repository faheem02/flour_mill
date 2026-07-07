<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'arrival_stock';
$page_title = 'Raw Material Stock';
require_once '../../includes/db.php';
include '../../includes/header.php';

$total_received = $conn->query("SELECT COALESCE(SUM(net_weight),0) as t FROM wheat_arrivals WHERE net_weight > 0")->fetch_assoc()['t'];
$total_used = $conn->query("SELECT COALESCE(SUM(wheat_qty),0) as t FROM productions WHERE wheat_qty > 0")->fetch_assoc()['t'];
$total_stock = $conn->query("SELECT COALESCE(SUM(ws.stock_qty),0) as t FROM warehouse_stock ws JOIN products p ON ws.product_id=p.id WHERE p.name='Wheat (Gandam)'")->fetch_assoc()['t'];
$warehouses = $conn->query("SELECT id, name FROM warehouses WHERE status='active' AND type='wheat' ORDER BY name");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-warehouse mr-1"></i> Raw Material Stock (Wheat / Gandam)</h1>
    <div>
        <a href="add.php" class="btn btn-sm btn-primary"><i class="fas fa-plus-circle mr-1"></i> New Arrival</a>
        <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
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
    $stock = $conn->query("SELECT ws.stock_qty FROM warehouse_stock ws WHERE ws.warehouse_id = $wh_id AND ws.product_id = (SELECT id FROM products WHERE name='Wheat (Gandam)' LIMIT 1)")->fetch_assoc();
    $qty = $stock ? $stock['stock_qty'] : 0;
?>
<div class="card shadow mb-4">
    <div class="card-header">
        <h6 class="font-weight-bold m-0"><i class="fas fa-building mr-1"></i> <?= htmlspecialchars($wh['name']) ?></h6>
    </div>
    <div class="card-body">
        <?php if ($qty > 0): ?>
        <div class="row align-items-center">
            <div class="col">
                <h4 class="text-success font-weight-bold mb-0"><?= qty($qty) ?> KG</h4>
            </div>
            <div class="col-auto">
                <div class="progress" style="height:25px;width:200px">
                    <div class="progress-bar bg-success" style="width:100%"><?= qty($qty) ?> KG</div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0">No stock in this warehouse.</p>
        <?php endif; ?>
    </div>
</div>
<?php endwhile; ?>

<?php include '../../includes/footer.php'; ?>
