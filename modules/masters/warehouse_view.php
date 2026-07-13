<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'warehouses';
$page_title = 'Warehouse Detail';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: warehouses.php"); exit; }

$wh = $conn->query("SELECT * FROM warehouses WHERE id = $id")->fetch_assoc();
if (!$wh) { header("Location: warehouses.php"); exit; }

$current_stock = $conn->query("
    SELECT ws.stock_qty, p.name AS product_name, p.unit
    FROM warehouse_stock ws
    JOIN products p ON p.id = ws.product_id
    WHERE ws.warehouse_id = $id AND ws.stock_qty > 0
    ORDER BY p.name
");

$arrivals = $conn->query("
    SELECT wa.*, b.booking_no, f.name AS farmer_name, f.phone AS farmer_phone, f.village AS farmer_village
    FROM wheat_arrivals wa
    LEFT JOIN bookings b ON b.id = wa.booking_id
    LEFT JOIN farmers f ON f.id = b.farmer_id
    WHERE wa.warehouse_id = $id
    ORDER BY wa.date DESC, wa.id DESC
");

$total_received = $conn->query("SELECT COALESCE(SUM(net_weight),0) AS t FROM wheat_arrivals WHERE warehouse_id = $id")->fetch_assoc()['t'];

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-warehouse mr-1"></i> <?= htmlspecialchars($wh['name']) ?></h1>
    <a href="warehouses.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Back to Warehouses</a>
</div>

<?= flashMessage() ?>

<!-- Warehouse Info Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Code</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= htmlspecialchars($wh['code']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Location</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= htmlspecialchars($wh['location'] ?: '—') ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Capacity</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= qty($wh['capacity_kg']) ?> KG</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Type</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= ucfirst(htmlspecialchars($wh['type'])) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Current Stock -->
<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0"><i class="fas fa-boxes mr-1"></i> Current Stock</h6></div>
    <div class="card-body">
        <?php if ($current_stock->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th>Product</th>
                        <th class="text-right">Stock Qty (KG)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($s = $current_stock->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['product_name']) ?></td>
                        <td class="text-right font-weight-bold"><?= qty($s['stock_qty']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0">No stock currently in this warehouse.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Arrivals History -->
<div class="card shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="font-weight-bold m-0"><i class="fas fa-truck-loading mr-1"></i> Arrivals History</h6>
        <span class="badge badge-primary">Total: <?= qty($total_received) ?> KG (<?= $arrivals->num_rows ?> entries)</span>
    </div>
    <div class="card-body">
        <?php if ($arrivals->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Farmer</th>
                        <th>Booking No</th>
                        <th>Vehicle</th>
                        <th class="text-right">Bags</th>
                        <th class="text-right">Gross (KG)</th>
                        <th class="text-right">Net (KG)</th>
                        <th class="text-right">Amount (Rs)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($a = $arrivals->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('d-M-Y', strtotime($a['date'])) ?></td>
                        <td>
                            <?php if ($a['farmer_name']): ?>
                                <strong><?= htmlspecialchars($a['farmer_name']) ?></strong>
                                <?php if ($a['farmer_village']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($a['farmer_village']) ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($a['booking_no'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($a['vehicle_no'] ?? '—') ?></td>
                        <td class="text-right"><?= $a['num_bags'] ?></td>
                        <td class="text-right"><?= qty($a['gross_weight']) ?></td>
                        <td class="text-right font-weight-bold"><?= qty($a['net_weight']) ?></td>
                        <td class="text-right"><?= money($a['net_amount']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0">No arrivals recorded for this warehouse yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
