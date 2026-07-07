<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'purchase_list';
$page_title = 'Purchase List';
require_once '../../includes/db.php';
include '../../includes/header.php';

$result = $conn->query("SELECT p.*, s.name as supplier_name FROM purchases p LEFT JOIN suppliers s ON p.supplier_id=s.id ORDER BY p.date DESC, p.id DESC");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-shopping-cart mr-1"></i> Purchase List</h1>
    <a href="add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle mr-1"></i> New Purchase</a>
</div>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Purchases</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Invoice</th>
                        <th>Supplier</th>
                        <th>Product</th>
                        <th class="text-right">Qty (KG)</th>
                        <th class="text-right">Rate/KG</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Paid</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['date'] ?></td>
                        <td><?= htmlspecialchars($row['invoice_no']) ?></td>
                        <td><a href="../suppliers/ledger.php?supplier_id=<?= $row['supplier_id'] ?>"><?= htmlspecialchars($row['supplier_name']) ?></a></td>
                        <td><?= htmlspecialchars($row['product_name'] ?? 'Wheat (Gandam)') ?></td>
                        <td class="text-right"><?= qty($row['total_qty']) ?></td>
                        <td class="text-right"><?= money($row['rate_per_kg']) ?></td>
                        <td class="text-right"><?= money($row['total_amount']) ?></td>
                        <td class="text-right"><?= money($row['paid_amount']) ?></td>
                        <td><span class="badge badge-<?= $row['status']=='completed'?'success':'warning' ?>"><?= ucfirst($row['status']) ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
