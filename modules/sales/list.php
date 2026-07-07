<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'sale_list';
$page_title = 'Sales List';
require_once '../../includes/db.php';
include '../../includes/header.php';

$result = $conn->query("SELECT s.*, c.name as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id ORDER BY s.date DESC, s.id DESC");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-cash-register mr-1"></i> Sales List</h1>
    <div>
        <a href="add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle mr-1"></i> New Sale</a>
        <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Sales</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th class="text-right">Qty (KG)</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['date'] ?></td>
                        <td><?= htmlspecialchars($row['invoice_no']) ?></td>
                        <td><a href="../customers/ledger.php?customer_id=<?= $row['customer_id'] ?>"><?= htmlspecialchars($row['customer_name']) ?></a></td>
                        <td class="text-right"><?= qty($row['total_qty']) ?></td>
                        <td class="text-right"><?= money($row['total_amount']) ?></td>
                        <td class="text-right"><?= money($row['paid_amount']) ?></td>
                        <td class="text-right text-danger font-weight-bold"><?= money($row['total_amount'] - $row['paid_amount']) ?></td>
                        <td><span class="badge badge-<?= $row['status']=='completed'?'success':'warning' ?>"><?= ucfirst($row['status']) ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
