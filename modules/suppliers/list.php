<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'supplier_list';
$page_title = 'Supplier List';
require_once '../../includes/db.php';
include '../../includes/header.php';

$result = $conn->query("SELECT * FROM suppliers ORDER BY name ASC");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-truck mr-1"></i> Suppliers</h1>
    <a href="add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle mr-1"></i> Add Supplier</a>
</div>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Suppliers</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Opening Balance</th>
                        <th>Current Balance</th>
                        <th width="200">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><a href="ledger.php?supplier_id=<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></a></td>
                        <td><?= htmlspecialchars($row['phone']) ?></td>
                        <td class="text-right"><?= money($row['opening_balance']) ?></td>
                        <td class="text-right <?= $row['balance'] > 0 ? 'text-danger font-weight-bold' : 'text-success' ?>"><?= money($row['balance']) ?></td>
                        <td>
                            <a href="ledger.php?supplier_id=<?= $row['id'] ?>" class="btn btn-info btn-sm"><i class="fas fa-book"></i></a>
                            <a href="payment.php?id=<?= $row['id'] ?>" class="btn btn-success btn-sm"><i class="fas fa-money-bill-wave"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
