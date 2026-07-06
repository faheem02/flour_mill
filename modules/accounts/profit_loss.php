<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'profit_loss';
$page_title = 'Profit & Loss Statement';
require_once '../../includes/db.php';
include '../../includes/header.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

// Income accounts (type = income)
$incomes = $conn->query("SELECT id, code, name, balance FROM chart_of_accounts WHERE type='income' AND parent_id IS NOT NULL AND status='active' ORDER BY code");
$total_income = 0;

// Expense accounts (type = expense)
$expenses = $conn->query("SELECT id, code, name, balance FROM chart_of_accounts WHERE type='expense' AND parent_id IS NOT NULL AND status='active' ORDER BY code");
$total_expense = 0;

// Actual journal totals in period
$income_journals = $conn->query("SELECT jei.account_id, COALESCE(SUM(jei.credit),0) as total FROM journal_entry_items jei JOIN journal_entries je ON jei.journal_id=je.id WHERE je.date BETWEEN '$from' AND '$to' AND jei.account_id IN (SELECT id FROM chart_of_accounts WHERE type='income') GROUP BY jei.account_id");
$income_map = []; while ($r = $income_journals->fetch_assoc()) $income_map[$r['account_id']] = $r['total'];

$expense_journals = $conn->query("SELECT jei.account_id, COALESCE(SUM(jei.debit),0) as total FROM journal_entry_items jei JOIN journal_entries je ON jei.journal_id=je.id WHERE je.date BETWEEN '$from' AND '$to' AND jei.account_id IN (SELECT id FROM chart_of_accounts WHERE type='expense') GROUP BY jei.account_id");
$expense_map = []; while ($r = $expense_journals->fetch_assoc()) $expense_map[$r['account_id']] = $r['total'];
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-line mr-1"></i> Profit & Loss Statement</h1>
    <button class="btn btn-sm btn-secondary no-print" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
</div>

<div class="card shadow mb-4">
    <div class="card-header">
        <form method="GET" class="form-inline">
            <label class="mr-2">From:</label>
            <input type="date" name="from" class="form-control form-control-sm mr-2" value="<?= $from ?>">
            <label class="mr-2">To:</label>
            <input type="date" name="to" class="form-control form-control-sm mr-2" value="<?= $to ?>">
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        </form>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h5 class="text-success font-weight-bold mb-3"><i class="fas fa-arrow-up"></i> Income</h5>
                <table class="table table-bordered">
                    <thead class="thead-dark"><tr><th>Account</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        <?php while ($inc = $incomes->fetch_assoc()):
                            $amt = $income_map[$inc['id']] ?? $inc['balance'];
                            $total_income += $amt;
                        ?>
                        <tr><td><?= htmlspecialchars($inc['name']) ?></td><td class="text-right"><?= money($amt) ?></td></tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="table-success">
                        <tr><th class="text-right">Total Income</th><th class="text-right">Rs <?= money($total_income) ?></th></tr>
                    </tfoot>
                </table>
            </div>
            <div class="col-md-6">
                <h5 class="text-danger font-weight-bold mb-3"><i class="fas fa-arrow-down"></i> Expenses</h5>
                <table class="table table-bordered">
                    <thead class="thead-dark"><tr><th>Account</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        <?php while ($exp = $expenses->fetch_assoc()):
                            $amt = $expense_map[$exp['id']] ?? $exp['balance'];
                            $total_expense += $amt;
                        ?>
                        <tr><td><?= htmlspecialchars($exp['name']) ?></td><td class="text-right"><?= money($amt) ?></td></tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="table-danger">
                        <tr><th class="text-right">Total Expenses</th><th class="text-right">Rs <?= money($total_expense) ?></th></tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card border-left-<?= ($total_income - $total_expense) >= 0 ? 'success' : 'danger' ?> shadow">
                    <div class="card-body text-center">
                        <h4 class="font-weight-bold">
                            Net <?= ($total_income - $total_expense) >= 0 ? 'Profit' : 'Loss' ?>:
                            <span class="<?= ($total_income - $total_expense) >= 0 ? 'text-success' : 'text-danger' ?>">
                                Rs <?= money(abs($total_income - $total_expense)) ?>
                            </span>
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
