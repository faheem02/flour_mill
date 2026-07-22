<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
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

$rows = [];
$total_dr = 0;
$total_cr = 0;
while ($row = $entries->fetch_assoc()) {
    $rows[] = $row;
    $total_dr += $row['debit'];
    $total_cr += $row['credit'];
}
$closing = $opening + $total_dr - $total_cr;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>FLOUR MILL / CASH BOOK</title>
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
@media print { body { padding: 0; font-size: 11px; } .no-print { display: none !important; } @page { margin: 0.4in; size: landscape; } tr:nth-child(even) td { background: #f8f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { background: #1B2A4A !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .summary-table .grand-row td { background: #eef2f7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } tfoot td, tfoot th { background: #eef2f7 !important; font-weight: 700 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128424; Print</button>
    <a href="cash_book.php" class="btn-back">&#8592; Back to Cash Book</a>
    <form class="filter-form" method="GET">
        <label>From:</label>
        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
        <label>To:</label>
        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
        <button type="submit">Filter</button>
        <?php if ($from != date('Y-m-01') || $to != date('Y-m-d')): ?>
            <a href="print_cash_book.php" style="padding:5px 10px;font-size:12px;color:#dc3545;">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="print-header">
    <h2>FLOUR MILL</h2>
    <h4>CASH BOOK</h4>
    <div class="date-range">
        <?= date('d-M-Y', strtotime($from)) ?> &mdash; <?= date('d-M-Y', strtotime($to)) ?>
    </div>
</div>

<div class="info-row">
    <span><span class="label">Cash Account:</span> Main Cash (ID: 2)</span>
    <span><span class="label">Printed on:</span> <?= date('d-M-Y H:i A') ?></span>
</div>

<table>
    <thead>
        <tr>
            <th width="30">#</th>
            <th width="80">Date</th>
            <th width="100">Voucher #</th>
            <th>Account</th>
            <th>Description</th>
            <th class="text-right" width="110">Receipts Dr (Rs)</th>
            <th class="text-right" width="110">Payments Cr (Rs)</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:20px;">No entries found for this date range.</td></tr>
        <?php else: ?>
        <?php $i = 1; foreach ($rows as $row): ?>
        <tr>
            <td class="text-center"><?= $i++ ?></td>
            <td><?= date('d-m-Y', strtotime($row['date'])) ?></td>
            <td class="font-bold"><?= htmlspecialchars($row['voucher_no']) ?></td>
            <td><?= htmlspecialchars($row['party_account'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['description']) ?></td>
            <td class="text-right text-success font-bold"><?= $row['debit'] > 0 ? money($row['debit']) : '-' ?></td>
            <td class="text-right text-danger font-bold"><?= $row['credit'] > 0 ? money($row['credit']) : '-' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <?php if (!empty($rows)): ?>
    <tfoot>
        <tr>
            <th colspan="5" class="text-right">Total</th>
            <th class="text-right"><?= money($total_dr) ?></th>
            <th class="text-right"><?= money($total_cr) ?></th>
        </tr>
        <tr>
            <th colspan="5" class="text-right">Opening Balance</th>
            <th colspan="2" class="text-right">Rs <?= money($opening) ?></th>
        </tr>
        <tr>
            <th colspan="5" class="text-right">Closing Balance</th>
            <th colspan="2" class="text-right <?= $closing >= 0 ? 'text-success' : 'text-danger' ?>">Rs <?= money($closing) ?></th>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>

<table class="summary-table">
    <tr>
        <td class="label">Opening Balance:</td>
        <td class="text-right">Rs <?= money($opening) ?></td>
    </tr>
    <tr>
        <td class="label">Total Receipts (Dr):</td>
        <td class="text-right text-success font-bold">Rs <?= money($total_dr) ?></td>
    </tr>
    <tr>
        <td class="label">Total Payments (Cr):</td>
        <td class="text-right text-danger font-bold">Rs <?= money($total_cr) ?></td>
    </tr>
    <tr class="grand-row">
        <td>Closing Balance:</td>
        <td class="text-right <?= $closing >= 0 ? 'text-success' : 'text-danger' ?>">Rs <?= money($closing) ?></td>
    </tr>
</table>

<div class="footer">
    Flour Mill Management System &bull; Cash Book &bull; Generated <?= date('d-M-Y H:i:s A') ?>
</div>

<script>window.onload = function() { setTimeout(function(){ window.print(); }, 400); };</script>
</body>
</html>