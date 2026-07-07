<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'customer_list';
$page_title = 'Customer List';
require_once '../../includes/db.php';
include '../../includes/header.php';

$result = $conn->query("SELECT * FROM customers ORDER BY name ASC");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-users mr-1"></i> Customers</h1>
    <div>
        <a href="add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle mr-1"></i> Add Customer</a>
        <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Customers (Wholesale Buyers)</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Business</th>
                        <th>Phone</th>
                        <th class="text-right">Balance</th>
                        <th width="200">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><a href="ledger.php?customer_id=<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></a></td>
                        <td><?= htmlspecialchars($row['business_name']) ?></td>
                        <td><?= htmlspecialchars($row['phone']) ?></td>
                        <td class="text-right <?= $row['balance'] > 0 ? 'text-danger font-weight-bold' : 'text-success' ?>"><?= money($row['balance']) ?></td>
                        <td>
                            <a href="ledger.php?customer_id=<?= $row['id'] ?>" class="btn btn-info btn-sm" title="Ledger"><i class="fas fa-book"></i></a>
                            <a href="receipt.php?id=<?= $row['id'] ?>" class="btn btn-success btn-sm" title="Receipt"><i class="fas fa-money-bill-wave"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
