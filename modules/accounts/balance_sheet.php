<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'balance_sheet';
$page_title = 'Balance Sheet';
require_once '../../includes/db.php';
include '../../includes/header.php';

$assets = $conn->query("SELECT code, name, balance FROM chart_of_accounts WHERE type='asset' AND parent_id IS NOT NULL AND status='active' ORDER BY code");
$liabilities = $conn->query("SELECT code, name, balance FROM chart_of_accounts WHERE type='liability' AND parent_id IS NOT NULL AND status='active' ORDER BY code");
$equities = $conn->query("SELECT code, name, balance FROM chart_of_accounts WHERE type='equity' AND parent_id IS NOT NULL AND status='active' ORDER BY code");

$total_assets = 0;
$total_liabilities = 0;
$total_equity = 0;

// P&L for current period
$from = date('Y-m-01');
$to = date('Y-m-d');
$profit = $conn->query("SELECT 
    (SELECT COALESCE(SUM(jei.credit),0) FROM journal_entry_items jei JOIN journal_entries je ON jei.journal_id=je.id WHERE je.date BETWEEN '$from' AND '$to' AND jei.account_id IN (SELECT id FROM chart_of_accounts WHERE type='income'))
    -
    (SELECT COALESCE(SUM(jei.debit),0) FROM journal_entry_items jei JOIN journal_entries je ON jei.journal_id=je.id WHERE je.date BETWEEN '$from' AND '$to' AND jei.account_id IN (SELECT id FROM chart_of_accounts WHERE type='expense'))
    as profit")->fetch_assoc()['profit'];
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-invoice mr-1"></i> Balance Sheet</h1>
    <button class="btn btn-sm btn-secondary no-print" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
</div>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">As at <?= date('d F Y') ?></h6></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h5 class="text-primary font-weight-bold mb-3"><i class="fas fa-building"></i> Assets</h5>
                <table class="table table-bordered">
                    <thead class="thead-dark"><tr><th>Account</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        <?php while ($a = $assets->fetch_assoc()): $total_assets += $a['balance']; ?>
                        <tr><td><?= htmlspecialchars($a['name']) ?></td><td class="text-right"><?= money($a['balance']) ?></td></tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="table-active">
                        <tr><th class="text-right">Total Assets</th><th class="text-right">Rs <?= money($total_assets) ?></th></tr>
                    </tfoot>
                </table>
            </div>
            <div class="col-md-6">
                <h5 class="text-warning font-weight-bold mb-3"><i class="fas fa-credit-card"></i> Liabilities</h5>
                <table class="table table-bordered">
                    <thead class="thead-dark"><tr><th>Account</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        <?php while ($l = $liabilities->fetch_assoc()): $total_liabilities += $l['balance']; ?>
                        <tr><td><?= htmlspecialchars($l['name']) ?></td><td class="text-right"><?= money($l['balance']) ?></td></tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="table-active">
                        <tr><th class="text-right">Total Liabilities</th><th class="text-right">Rs <?= money($total_liabilities) ?></th></tr>
                    </tfoot>
                </table>

                <h5 class="text-info font-weight-bold mt-4 mb-3"><i class="fas fa-chart-pie"></i> Equity</h5>
                <table class="table table-bordered">
                    <thead class="thead-dark"><tr><th>Account</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        <?php while ($e = $equities->fetch_assoc()): $total_equity += $e['balance']; ?>
                        <tr><td><?= htmlspecialchars($e['name']) ?></td><td class="text-right"><?= money($e['balance']) ?></td></tr>
                        <?php endwhile; ?>
                        <?php if ($profit > 0): $total_equity += $profit; ?>
                        <tr class="table-success">
                            <td>Current Period Profit</td><td class="text-right font-weight-bold">Rs <?= money($profit) ?></td>
                        </tr>
                        <?php elseif ($profit < 0): $total_equity += $profit; ?>
                        <tr class="table-danger">
                            <td>Current Period Loss</td><td class="text-right font-weight-bold">(Rs <?= money(abs($profit)) ?>)</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-active">
                        <tr><th class="text-right">Total Equity</th><th class="text-right">Rs <?= money($total_equity) ?></th></tr>
                    </tfoot>
                </table>

                <div class="card border-left-primary shadow mt-4">
                    <div class="card-body text-center">
                        <h5 class="font-weight-bold">
                            Total Liabilities + Equity: Rs <?= money($total_liabilities + $total_equity) ?>
                        </h5>
                        <small class="<?= abs($total_assets - ($total_liabilities + $total_equity)) < 0.01 ? 'text-success' : 'text-danger' ?>">
                            <i class="fas fa-<?= abs($total_assets - ($total_liabilities + $total_equity)) < 0.01 ? 'check-circle' : 'exclamation-circle' ?>"></i>
                            <?= abs($total_assets - ($total_liabilities + $total_equity)) < 0.01 ? 'Balanced' : 'Difference: Rs ' . money(abs($total_assets - ($total_liabilities + $total_equity))) ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
