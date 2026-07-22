<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'expense_list';
$page_title = 'Expense List';
require_once '../../includes/db.php';
include '../../includes/header.php';

$result = $conn->query("SELECT e.*, ec.name as category_name FROM expenses e LEFT JOIN expense_categories ec ON e.category_id=ec.id ORDER BY e.date DESC, e.id DESC");

$total = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM expenses")->fetch_assoc()['t'];
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-coins mr-1"></i> Expense List</h1>
    <div>
        <a href="add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle mr-1"></i> Add Expense</a>
        <a href="print_list.php" class="btn btn-sm btn-info" target="_blank"><i class="fas fa-print mr-1"></i> Print Register</a>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Expenses (Total: Rs <?= money($total) ?>)</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th class="text-right">Amount</th>
                        <th>Paid To</th>
                        <th>Type</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['date'] ?></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($row['category_name']) ?></span></td>
                        <td class="text-right text-danger font-weight-bold"><?= money($row['amount']) ?></td>
                        <td><?= htmlspecialchars($row['paid_to']) ?></td>
                        <td><span class="badge badge-<?= $row['payment_type']=='cash'?'success':'primary' ?>"><?= ucfirst($row['payment_type']) ?></span></td>
                        <td><?= htmlspecialchars($row['notes']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
