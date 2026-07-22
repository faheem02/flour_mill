<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$from = isset($_GET['from']) && $_GET['from'] !== '' ? $_GET['from'] : date('Y-m-01');
$to = isset($_GET['to']) && $_GET['to'] !== '' ? $_GET['to'] : date('Y-m-d');

$bank_accounts = [];
$bank_q = $conn->query("SELECT id, account_name, bank_name, account_no, balance FROM bank_accounts WHERE account_name != 'Main Cash' AND status='active'");
if ($bank_q) {
    while ($row = $bank_q->fetch_assoc()) {
        $bank_accounts[] = $row;
    }
}

$total_balance = 0;
$bal_q = $conn->query("SELECT COALESCE(SUM(balance),0) as t FROM bank_accounts WHERE account_name != 'Main Cash'");
if ($bal_q && $r = $bal_q->fetch_assoc()) {
    $total_balance = $r['t'];
}

$bank_account_id = 3;

$entries = [];
$sum_dr = 0;
$sum_cr = 0;

$sql = "SELECT je.date, je.voucher_no, je.description, jei.debit, jei.credit,
        (SELECT coa.name FROM journal_entry_items jei2
         JOIN chart_of_accounts coa ON coa.id = jei2.account_id
         WHERE jei2.journal_id = je.id AND jei2.account_id != 3 LIMIT 1) as party_account
        FROM journal_entries je
        JOIN journal_entry_items jei ON je.id = jei.journal_id
        WHERE jei.account_id = 3 AND je.date BETWEEN '" . $conn->real_escape_string($from) . "' AND '" . $conn->real_escape_string($to) . "'
        ORDER BY je.date ASC, je.id ASC";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $entries[] = $row;
        $sum_dr += $row['debit'];
        $sum_cr += $row['credit'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FLOUR MILL / BANK BOOK</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; color: #222; padding: 15px; background: #fff; }
.print-header { text-align: center; border-bottom: 3px double #1B2A4A; padding-bottom: 10px; margin-bottom: 15px; }
.print-header h2 { color: #1B2A4A; font-size: 22px; margin: 0; letter-spacing: 1px; }
.print-header h4 { color: #555; font-size: 14px; font-weight: 600; margin: 4px 0 0; }
.print-header .date-range { font-size: 12px; color: #888; margin-top: 4px; }
.info-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 12px; }
.info-row .label { font-weight: 700; color: #555; }
table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
th, td { padding: 6px 8px; border: 1px solid #ccc; text-align: left; font-size: 11px; }
th { background: #1B2A4A; color: #fff; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
td { vertical-align: middle; }
tr:nth-child(even) td { background: #f8f9fc; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.font-bold { font-weight: 700; }
.text-success { color: #28a745; }
.text-danger { color: #dc3545; }
.text-muted { color: #888; }
.bank-cards { display: flex; gap: 12px; margin-bottom: 15px; flex-wrap: wrap; }
.bank-card { border: 1px solid #ccc; border-left: 4px solid #1B2A4A; padding: 10px 14px; border-radius: 4px; flex: 1; min-width: 200px; }
.bank-card .bank-name { font-weight: 700; font-size: 13px; }
.bank-card .bank-detail { font-size: 11px; color: #888; }
.bank-card .bank-balance { font-size: 16px; font-weight: 800; color: #1B2A4A; margin-top: 4px; }
.summary-table { width: 300px; margin-left: auto; }
.summary-table td { padding: 5px 10px; font-size: 12px; }
.summary-table .grand-row td { border-top: 2px solid #1B2A4A; font-size: 14px; font-weight: 800; color: #1B2A4A; background: #eef2f7 !important; }
.footer { margin-top: 20px; padding-top: 10px; border-top: 1px dashed #ccc; text-align: center; font-size: 10px; color: #999; }
.no-print { margin-bottom: 15px; }
.no-print button, .no-print a { padding: 6px 16px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; margin-right: 6px; text-decoration: none; display: inline-block; }
.no-print .btn-print { background: #1B2A4A; color: #fff; }
.no-print .btn-back { background: #e9ecef; color: #333; }
.no-print .filter-form { display: inline-flex; align-items: center; gap: 8px; margin-left: 15px; }
.no-print .filter-form input { padding: 5px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; }
.no-print .filter-form button { padding: 5px 14px; background: #4e73df; color: #fff; border-radius: 4px; font-size: 12px; }
@media print { body { padding: 0; font-size: 11px; } .no-print { display: none !important; } @page { margin: 0.4in; size: landscape; } tr:nth-child(even) td { background: #f8f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { background: #1B2A4A !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .summary-table .grand-row td { background: #eef2f7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } tfoot td, tfoot th { background: #eef2f7 !important; font-weight: 700 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .bank-card { border-color: #999 !important; } }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print();">Print</button>
    <a href="bank_book.php" class="btn-back">Back to Bank Book</a>
    <form class="filter-form" method="get" action="">
        <label>From:</label>
        <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
        <label>To:</label>
        <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
        <button type="submit">Filter</button>
    </form>
</div>

<div class="print-header">
    <h2>FLOUR MILL / BANK BOOK</h2>
    <h4>Bank Book Statement</h4>
    <div class="date-range">Period: <?php echo date('d M Y', strtotime($from)); ?> to <?php echo date('d M Y', strtotime($to)); ?></div>
</div>

<?php if (!empty($bank_accounts)): ?>
<div class="bank-cards">
    <?php foreach ($bank_accounts as $ba): ?>
    <div class="bank-card">
        <div class="bank-name"><?php echo htmlspecialchars($ba['account_name']); ?></div>
        <div class="bank-detail"><?php echo htmlspecialchars($ba['bank_name']); ?> | A/C: <?php echo htmlspecialchars($ba['account_no']); ?></div>
        <div class="bank-balance"><?php echo money($ba['balance']); ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th class="text-center" style="width:30px;">#</th>
            <th style="width:85px;">Date</th>
            <th style="width:100px;">Voucher #</th>
            <th>Account</th>
            <th>Description</th>
            <th class="text-right" style="width:120px;">Deposit Dr (Rs)</th>
            <th class="text-right" style="width:120px;">Withdrawal Cr (Rs)</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($entries)): ?>
        <tr>
            <td colspan="7" class="text-center text-muted" style="padding:20px;">No transactions found for this period.</td>
        </tr>
        <?php else: ?>
        <?php $i = 1; foreach ($entries as $e): ?>
        <tr>
            <td class="text-center"><?php echo $i++; ?></td>
            <td><?php echo date('d-M-Y', strtotime($e['date'])); ?></td>
            <td><?php echo htmlspecialchars($e['voucher_no']); ?></td>
            <td><?php echo htmlspecialchars($e['party_account']); ?></td>
            <td><?php echo htmlspecialchars($e['description']); ?></td>
            <td class="text-right"><?php echo $e['debit'] > 0 ? money($e['debit']) : ''; ?></td>
            <td class="text-right"><?php echo $e['credit'] > 0 ? money($e['credit']) : ''; ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="5" class="text-right">TOTAL</th>
            <th class="text-right"><?php echo money($sum_dr); ?></th>
            <th class="text-right"><?php echo money($sum_cr); ?></th>
        </tr>
    </tfoot>
</table>

<table class="summary-table">
    <tr>
        <td class="font-bold">Total Balance:</td>
        <td class="text-right font-bold text-success"><?php echo money($total_balance); ?></td>
    </tr>
    <tr>
        <td class="font-bold">Total Deposits:</td>
        <td class="text-right font-bold text-success"><?php echo money($sum_dr); ?></td>
    </tr>
    <tr>
        <td class="font-bold">Total Withdrawals:</td>
        <td class="text-right font-bold text-danger"><?php echo money($sum_cr); ?></td>
    </tr>
</table>

<div class="footer">
    Flour Mill Management System | Bank Book | Generated <?php echo date('d-M-Y h:i A'); ?>
</div>

<script>
window.onload = function() {
    setTimeout(function(){ window.print(); }, 400);
};
</script>
</body>
</html>
