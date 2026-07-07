<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'production_list';
$page_title = 'Production History';
require_once '../../includes/db.php';
include '../../includes/header.php';

$result = $conn->query("SELECT p.* FROM productions p ORDER BY p.date DESC, p.id DESC");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-industry mr-1"></i> Production History</h1>
    <div>
        <a href="add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle mr-1"></i> New Crush</a>
        <a href="report.php" class="btn btn-info btn-sm"><i class="fas fa-chart-bar mr-1"></i> Extraction Report</a>
        <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
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
                        <th>Products</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()):
                        $items = $conn->query("SELECT pi.qty, pi.rate_per_kg, pr.name FROM production_items pi LEFT JOIN products pr ON pi.product_id=pr.id WHERE pi.production_id=".$row['id']);
                        $products_list = [];
                        while ($it = $items->fetch_assoc()) {
                            $rate_text = $it['rate_per_kg'] > 0 ? ' @ Rs ' . money($it['rate_per_kg']) : '';
                            $products_list[] = $it['name'] . ': ' . qty($it['qty']) . ' KG' . $rate_text;
                        }
                    ?>
                    <tr>
                        <td><?= $row['date'] ?></td>
                        <td class="text-right"><?= qty($row['wheat_qty']) ?></td>
                        <td class="text-right"><?= qty($row['total_output']) ?></td>
                        <td class="text-right <?= $row['wastage_qty']>0?'text-danger':'' ?>"><?= qty($row['wastage_qty']) ?></td>
                        <td class="text-right font-weight-bold"><?= number_format($row['extraction_rate'],1) ?>%</td>
                        <td><small><?= implode(' | ', $products_list) ?></small></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
