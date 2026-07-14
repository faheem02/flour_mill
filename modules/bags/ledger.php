<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'bag_ledger';
$page_title = 'Bag Stock Ledger';
require_once '../../includes/db.php';
include '../../includes/header.php';

$filter_type = $_GET['type'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

$sql = "SELECT bsl.*, w.name as warehouse_name
    FROM bag_stock_ledger bsl
    JOIN warehouses w ON bsl.warehouse_id = w.id
    WHERE 1=1";

if ($filter_type) $sql .= " AND bsl.type = '" . $conn->real_escape_string($filter_type) . "'";
if ($filter_date_from) $sql .= " AND bsl.date >= '" . $conn->real_escape_string($filter_date_from) . "'";
if ($filter_date_to) $sql .= " AND bsl.date <= '" . $conn->real_escape_string($filter_date_to) . "'";

$sql .= " ORDER BY bsl.id DESC";
$result = $conn->query($sql);

$type_labels = [
    'opening' => '<span class="badge badge-dark">Opening</span>',
    'booking_out' => '<span class="badge badge-warning">Booking OUT</span>',
    'arrival_in' => '<span class="badge badge-success">Arrival IN</span>',
    'manual_in' => '<span class="badge badge-info">Manual IN</span>',
    'manual_out' => '<span class="badge badge-danger">Manual OUT</span>',
    'adjustment' => '<span class="badge badge-secondary">Adjustment</span>',
];
?>
<?php $flash = flashMessage(); if ($flash): ?>
<div class="alert alert-success alert-auto"><?= $flash ?></div>
<?php endif; ?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-1"></i> Bag Stock Ledger</h1>
    <div>
        <a href="add_in.php" class="btn btn-success btn-sm"><i class="fas fa-arrow-down mr-1"></i> Bags IN</a>
        <a href="add_out.php" class="btn btn-warning btn-sm text-white"><i class="fas fa-arrow-up mr-1"></i> Bags OUT</a>
        <a href="adjust.php" class="btn btn-info btn-sm"><i class="fas fa-sliders-h mr-1"></i> Adjust</a>
    </div>
</div>

<?php
$total_bags = $conn->query("SELECT COALESCE(SUM(qty),0) as t FROM bag_stock")->fetch_assoc()['t'];
?>
<div class="card bg-light mb-3">
    <div class="card-body py-2 text-center">
        <small class="text-muted">Current Bag Stock</small>
        <h3 class="mb-0 text-primary"><?= number_format($total_bags) ?> Bags</h3>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0"><i class="fas fa-filter mr-1"></i> Filters</h6></div>
    <div class="card-body py-2">
        <form method="GET" class="form-inline">
            <div class="form-group mr-2">
                <label class="mr-1">Type:</label>
                <select name="type" class="form-control form-control-sm">
                    <option value="">All</option>
                    <option value="opening" <?= $filter_type === 'opening' ? 'selected' : '' ?>>Opening</option>
                    <option value="booking_out" <?= $filter_type === 'booking_out' ? 'selected' : '' ?>>Booking OUT</option>
                    <option value="arrival_in" <?= $filter_type === 'arrival_in' ? 'selected' : '' ?>>Arrival IN</option>
                    <option value="manual_in" <?= $filter_type === 'manual_in' ? 'selected' : '' ?>>Manual IN</option>
                    <option value="manual_out" <?= $filter_type === 'manual_out' ? 'selected' : '' ?>>Manual OUT</option>
                    <option value="adjustment" <?= $filter_type === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
                </select>
            </div>
            <div class="form-group mr-2">
                <label class="mr-1">From:</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_from) ?>">
            </div>
            <div class="form-group mr-2">
                <label class="mr-1">To:</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_date_to) ?>">
            </div>
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search mr-1"></i> Filter</button>
            <a href="ledger.php" class="btn btn-sm btn-secondary ml-1">Clear</a>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Warehouse</th>
                        <th class="text-right">IN</th>
                        <th class="text-right">OUT</th>
                        <th class="text-right">Quantity</th>
                        <th>Type</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td class="text-nowrap"><?= date('d-m-Y', strtotime($row['date'])) ?></td>
                        <td><?= htmlspecialchars($row['warehouse_name']) ?></td>
                        <td class="text-right text-success font-weight-bold"><?= $row['qty_in'] > 0 ? number_format($row['qty_in']) : '-' ?></td>
                        <td class="text-right text-danger font-weight-bold"><?= $row['qty_out'] > 0 ? number_format($row['qty_out']) : '-' ?></td>
                        <td class="text-right font-weight-bold"><?= number_format($row['balance_qty']) ?></td>
                        <td><?= $type_labels[$row['type']] ?? $row['type'] ?></td>
                        <td><small><?= htmlspecialchars($row['notes'] ?? '') ?></small></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
