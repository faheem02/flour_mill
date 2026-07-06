<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'production_report';
$page_title = 'Extraction Report';
require_once '../../includes/db.php';
include '../../includes/header.php';

$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

$result = $conn->query("SELECT p.*, 
    (SELECT GROUP_CONCAT(CONCAT(pr.name,':',pi.qty,'KG') SEPARATOR ', ') FROM production_items pi JOIN products pr ON pi.product_id=pr.id WHERE pi.production_id=p.id) as output_detail
    FROM productions p ORDER BY p.date DESC");

$summary = $conn->query("SELECT 
    COALESCE(SUM(wheat_qty),0) as total_wheat,
    COALESCE(SUM(total_output),0) as total_output,
    COALESCE(AVG(extraction_rate),0) as avg_extraction,
    COALESCE(SUM(wastage_qty),0) as total_wastage
    FROM productions WHERE date>='$month_start' AND date<='$month_end'")->fetch_assoc();
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-bar mr-1"></i> Extraction Rate Report</h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</div>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2 card-dashboard">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">This Month - Wheat Crushed</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= qty($summary['total_wheat']) ?> KG</div>
                </div>
                <div class="col-auto"><i class="fas fa-wheat-alt fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2 card-dashboard">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">This Month - Total Output</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= qty($summary['total_output']) ?> KG</div>
                </div>
                <div class="col-auto"><i class="fas fa-boxes fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2 card-dashboard">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avg Extraction Rate</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($summary['avg_extraction'],1) ?>%</div>
                </div>
                <div class="col-auto"><i class="fas fa-percentage fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2 card-dashboard">
            <div class="card-body"><div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Wastage</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= qty($summary['total_wastage']) ?> KG</div>
                </div>
                <div class="col-auto"><i class="fas fa-trash fa-2x text-gray-300"></i></div>
            </div></div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Production Records</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th class="text-right">Wheat (KG)</th>
                        <th class="text-right">Output (KG)</th>
                        <th class="text-right">Wastage (KG)</th>
                        <th class="text-right">Extraction %</th>
                        <th>Output Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['date'] ?></td>
                        <td class="text-right"><?= qty($row['wheat_qty']) ?></td>
                        <td class="text-right"><?= qty($row['total_output']) ?></td>
                        <td class="text-right <?= $row['wastage_qty']>0?'text-danger':'' ?>"><?= qty($row['wastage_qty']) ?></td>
                        <td class="text-right font-weight-bold"><span class="badge badge-<?= $row['extraction_rate']>=85?'success':'warning' ?> p-2"><?= number_format($row['extraction_rate'],1) ?>%</span></td>
                        <td><small><?= htmlspecialchars($row['output_detail']) ?></small></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
