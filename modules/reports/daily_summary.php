<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'daily_summary';
$page_title = 'Daily Summary';
require_once '../../includes/db.php';
include '../../includes/header.php';

$date = $_GET['date'] ?? date('Y-m-d');

// Today's arrivals
$arrivals = $conn->query("SELECT COUNT(*) as cnt, COALESCE(SUM(net_weight),0) as wt FROM wheat_arrivals WHERE date='$date'")->fetch_assoc();

// Today's purchases
$purchases = $conn->query("SELECT COUNT(*) as cnt, COALESCE(SUM(total_qty),0) as qty, COALESCE(SUM(total_amount),0) as amt FROM purchases WHERE date='$date'")->fetch_assoc();

// Today's production
$prod = $conn->query("SELECT COUNT(*) as cnt, COALESCE(SUM(wheat_qty),0) as wheat, COALESCE(SUM(total_output),0) as output FROM productions WHERE date='$date'")->fetch_assoc();

// Today's sales
$sales = $conn->query("SELECT COUNT(*) as cnt, COALESCE(SUM(total_qty),0) as qty, COALESCE(SUM(total_amount),0) as amt FROM sales WHERE date='$date'")->fetch_assoc();

// Today's expenses
$expenses = $conn->query("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as amt FROM expenses WHERE date='$date'")->fetch_assoc();

// Today's receipts
$receipts = $conn->query("SELECT COALESCE(SUM(credit),0) as amt FROM customer_ledger WHERE date='$date' AND type='receipt'")->fetch_assoc();

// Today's payments
$payments = $conn->query("SELECT COALESCE(SUM(credit),0) as amt FROM supplier_ledger WHERE date='$date' AND type='payment'")->fetch_assoc();
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-bar mr-1"></i> Daily Summary</h1>
    <form method="GET" class="form-inline">
        <input type="date" name="date" class="form-control form-control-sm mr-2" value="<?= $date ?>">
        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> View</button>
    </form>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card border-left-primary shadow h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Wheat Arrivals</div>
                <div class="h5 mb-0 font-weight-bold"><?= $arrivals['cnt'] ?> entries</div>
                <small class="text-muted"><?= qty($arrivals['wt']) ?> KG net weight</small>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card border-left-success shadow h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Purchases</div>
                <div class="h5 mb-0 font-weight-bold"><?= $purchases['cnt'] ?> entries</div>
                <small class="text-muted"><?= qty($purchases['qty']) ?> KG | Rs <?= money($purchases['amt']) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card border-left-info shadow h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Production</div>
                <div class="h5 mb-0 font-weight-bold"><?= $prod['cnt'] ?> batches</div>
                <small class="text-muted">Wheat: <?= qty($prod['wheat']) ?> KG | Output: <?= qty($prod['output']) ?> KG</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card border-left-warning shadow h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Sales</div>
                <div class="h5 mb-0 font-weight-bold"><?= $sales['cnt'] ?> invoices</div>
                <small class="text-muted"><?= qty($sales['qty']) ?> KG | Rs <?= money($sales['amt']) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card border-left-danger shadow h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Expenses</div>
                <div class="h5 mb-0 font-weight-bold"><?= $expenses['cnt'] ?> entries</div>
                <small class="text-muted">Rs <?= money($expenses['amt']) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card border-left-secondary shadow h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Cash Summary</div>
                <div class="h5 mb-0 font-weight-bold text-success">+Rs <?= money($receipts['amt']) ?></div>
                <small class="text-muted text-danger">-Rs <?= money($payments['amt']) ?> (payments)</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Day at a Glance — <?= $date ?></h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th>Metric</th>
                        <th class="text-right">Count</th>
                        <th class="text-right">Quantity (KG)</th>
                        <th class="text-right">Amount (Rs)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Wheat Arrivals</td><td class="text-right"><?= $arrivals['cnt'] ?></td><td class="text-right"><?= qty($arrivals['wt']) ?></td><td class="text-right">-</td></tr>
                    <tr><td>Purchases</td><td class="text-right"><?= $purchases['cnt'] ?></td><td class="text-right"><?= qty($purchases['qty']) ?></td><td class="text-right"><?= money($purchases['amt']) ?></td></tr>
                    <tr><td>Production (Wheat Crushed)</td><td class="text-right"><?= $prod['cnt'] ?></td><td class="text-right"><?= qty($prod['wheat']) ?></td><td class="text-right">-</td></tr>
                    <tr><td>Production (Output)</td><td class="text-right">-</td><td class="text-right"><?= qty($prod['output']) ?></td><td class="text-right">-</td></tr>
                    <tr><td>Sales</td><td class="text-right"><?= $sales['cnt'] ?></td><td class="text-right"><?= qty($sales['qty']) ?></td><td class="text-right"><?= money($sales['amt']) ?></td></tr>
                    <tr><td>Expenses</td><td class="text-right"><?= $expenses['cnt'] ?></td><td class="text-right">-</td><td class="text-right"><?= money($expenses['amt']) ?></td></tr>
                    <tr class="table-success"><td><strong>Cash Received (Receipts)</strong></td><td class="text-right">-</td><td class="text-right">-</td><td class="text-right"><?= money($receipts['amt']) ?></td></tr>
                    <tr class="table-danger"><td><strong>Cash Paid (Payments)</strong></td><td class="text-right">-</td><td class="text-right">-</td><td class="text-right"><?= money($payments['amt']) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
