<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'cash_book';
$page_title = 'Cash Book';
require_once '../../includes/db.php';
include '../../includes/header.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

// Get cash account ID
$cash_account_id = 2;
$entries = $conn->query("SELECT je.date, je.voucher_no, je.description,
    jei.debit, jei.credit,
    (SELECT coa.name FROM journal_entry_items jei2
     JOIN chart_of_accounts coa ON coa.id = jei2.account_id
     WHERE jei2.journal_id = je.id AND jei2.account_id != $cash_account_id
     LIMIT 1) as party_account
    FROM journal_entries je
    JOIN journal_entry_items jei ON je.id = jei.journal_id
    WHERE jei.account_id = $cash_account_id AND je.date BETWEEN '$from' AND '$to'
    ORDER BY je.date ASC, je.id ASC");

$opening = $conn->query("SELECT opening_balance FROM chart_of_accounts WHERE id=$cash_account_id")->fetch_assoc()['opening_balance'];
$cash_balance = $conn->query("SELECT balance FROM bank_accounts WHERE account_name='Main Cash'")->fetch_assoc()['balance'];
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-money-bill-wave mr-1"></i> Cash Book</h1>
    <div>
        <span class="font-weight-bold mr-3">Balance: Rs <?= money($cash_balance) ?></span>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header">
        <form method="GET" class="form-inline">
            <label class="mr-1 small">From:</label>
            <input type="date" name="from" class="form-control form-control-sm mr-2" value="<?= $from ?>">
            <label class="mr-1 small">To:</label>
            <input type="date" name="to" class="form-control form-control-sm mr-2" value="<?= $to ?>">
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Voucher #</th>
                        <th>Account</th>
                        <th>Description</th>
                        <th class="text-right">Receipts (Dr)</th>
                        <th class="text-right">Payments (Cr)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $td=0; $tc=0; while ($row = $entries->fetch_assoc()): $td+=$row['debit']; $tc+=$row['credit']; ?>
                    <tr>
                        <td><?= $row['date'] ?></td>
                        <td><?= htmlspecialchars($row['voucher_no']) ?></td>
                        <td><?= htmlspecialchars($row['party_account'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td class="text-right text-success"><?= $row['debit']>0?money($row['debit']):'-' ?></td>
                        <td class="text-right text-danger"><?= $row['credit']>0?money($row['credit']):'-' ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot class="table-active">
                    <tr>
                        <th colspan="4" class="text-right">Total</th>
                        <th class="text-right"><?= money($td) ?></th>
                        <th class="text-right"><?= money($tc) ?></th>
                    </tr>
                    <tr class="table-info">
                        <th colspan="4" class="text-right">Closing Balance</th>
                        <th colspan="2" class="text-right font-weight-bold">Rs <?= money($opening + $td - $tc) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
