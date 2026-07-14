<?php
session_start();
require_once 'includes/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . $base_url . "auth/login.php");
    exit;
}
$active_page = 'dashboard';
$page_title = 'Dashboard';
require_once 'includes/db.php';
include 'includes/header.php';

$today = date('Y-m-d');
$month_start = date('Y-m-01');

// Total current stock (all products)
$total_stock = $conn->query("SELECT COALESCE(SUM(stock_qty),0) AS t FROM products WHERE status='active'")->fetch_assoc()['t'];

// Today's production
$today_prod = $conn->query("SELECT COALESCE(SUM(wheat_qty),0) AS wheat, COALESCE(SUM(total_output),0) AS output FROM productions WHERE date='$today'")->fetch_assoc();

// Today's sales
$today_sale = $conn->query("SELECT COALESCE(SUM(total_qty),0) AS qty, COALESCE(SUM(total_amount),0) AS amount FROM sales WHERE date='$today'")->fetch_assoc();

// Today's expenses
$today_expense = $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE date='$today'")->fetch_assoc()['t'];

// Today's receipts from customers
$today_receipt = $conn->query("SELECT COALESCE(SUM(credit),0) AS t FROM customer_ledger WHERE date='$today' AND type='receipt'")->fetch_assoc()['t'];

// Total receivables (customers with debit balance)
$total_receivables = $conn->query("SELECT COALESCE(SUM(balance),0) AS t FROM customers WHERE balance > 0")->fetch_assoc()['t'];


// Cash balance
$cash_balance = $conn->query("SELECT COALESCE(balance,0) AS t FROM bank_accounts WHERE account_name='Main Cash'")->fetch_assoc()['t'];

// Bank balance
$bank_balance = $conn->query("SELECT COALESCE(SUM(balance),0) AS t FROM bank_accounts WHERE account_name != 'Main Cash'")->fetch_assoc()['t'];

// Monthly stats
$monthly_sale = $conn->query("SELECT COALESCE(SUM(total_amount),0) AS t FROM sales WHERE date>='$month_start' AND date<='$today'")->fetch_assoc()['t'];
$monthly_expense = $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE date>='$month_start' AND date<='$today'")->fetch_assoc()['t'];

// Average extraction rate
$avg_extraction = $conn->query("SELECT COALESCE(AVG(extraction_rate),0) AS t FROM productions")->fetch_assoc()['t'];

// Today's cash summary (cash book)
$today_cash_in = $conn->query("SELECT COALESCE(SUM(credit),0) AS t FROM customer_ledger WHERE date='$today' AND type='receipt'")->fetch_assoc()['t'];
$today_cash_out = $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE date='$today' AND payment_type='cash'")->fetch_assoc()['t'];
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-tachometer-alt mr-1"></i> Dashboard</h1>
    <div>
        <span class="text-muted small"><i class="fas fa-calendar-alt mr-1"></i> <?= date('l, d M Y') ?></span>
    </div>
</div>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Current Stock</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= qty($total_stock) ?> KG</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-warehouse fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Today's Production</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= qty($today_prod['output']) ?> KG</div>
                        <small class="text-muted">Wheat: <?= qty($today_prod['wheat']) ?> KG</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-industry fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Today's Sales</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= qty($today_sale['qty']) ?> KG</div>
                        <small class="text-muted">Rs <?= money($today_sale['amount']) ?></small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-cash-register fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Avg Extraction Rate</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($avg_extraction, 1) ?>%</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-percentage fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Receivables</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs <?= money($total_receivables) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Cash in Hand</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs <?= money($cash_balance) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2 card-dashboard">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Bank Balance</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">Rs <?= money($bank_balance) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-university fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-6 col-md-12 mb-4">
        <div class="card shadow h-100">
            <div class="card-header">
                <h6 class="font-weight-bold m-0"><i class="fas fa-chart-bar mr-1"></i> Monthly Summary (<?= date('F Y') ?>)</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 text-center py-3">
                        <h4 class="text-success font-weight-bold">Rs <?= money($monthly_sale) ?></h4>
                        <small class="text-muted">Total Sales</small>
                    </div>
                    <div class="col-6 text-center py-3">
                        <h4 class="text-danger font-weight-bold">Rs <?= money($monthly_expense) ?></h4>
                        <small class="text-muted">Total Expenses</small>
                    </div>
                    <div class="col-6 text-center py-3">
                        <h4 class="text-primary font-weight-bold">Rs <?= money($monthly_sale - $monthly_expense) ?></h4>
                        <small class="text-muted">Net Income</small>
                    </div>
                    <div class="col-6 text-center py-3">
                        <h4 class="text-info font-weight-bold"><?= qty($today_prod['output']) ?> KG</h4>
                        <small class="text-muted">Today's Production</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6 col-md-12 mb-4">
        <div class="card shadow h-100">
            <div class="card-header">
                <h6 class="font-weight-bold m-0"><i class="fas fa-bolt mr-1"></i> Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-4 text-center py-2">
                        <a href="<?= $base_url ?>modules/stock/issuance.php" class="btn btn-primary btn-sm btn-block">
                            <i class="fas fa-exchange-alt"></i> Issuance
                        </a>
                    </div>
                    <div class="col-4 text-center py-2">
                        <a href="<?= $base_url ?>modules/production/add.php" class="btn btn-success btn-sm btn-block">
                            <i class="fas fa-industry"></i> Crush
                        </a>
                    </div>
                    <div class="col-4 text-center py-2">
                        <a href="<?= $base_url ?>modules/sales/add.php" class="btn btn-info btn-sm btn-block">
                            <i class="fas fa-cash-register"></i> Sale
                        </a>
                    </div>
                    <div class="col-4 text-center py-2">
                        <a href="<?= $base_url ?>modules/expenses/add.php" class="btn btn-warning btn-sm btn-block">
                            <i class="fas fa-coins"></i> Expense
                        </a>
                    </div>
                    <div class="col-4 text-center py-2">
                        <a href="<?= $base_url ?>modules/customers/receipt.php" class="btn btn-secondary btn-sm btn-block">
                            <i class="fas fa-money-bill-wave"></i> Receipt
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
