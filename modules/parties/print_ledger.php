<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$party_id = (int)($_GET['id'] ?? 0);
$party = $party_id ? $conn->query("SELECT * FROM general_parties WHERE id = $party_id")->fetch_assoc() : null;

if (!$party) { die("Party not found."); }

$opening = (float)($party['opening_balance'] ?? 0);

$transactions = $conn->query("SELECT * FROM party_transactions WHERE party_id = $party_id ORDER BY date ASC, id ASC");
$ledger = [];
while ($t = $transactions->fetch_assoc()) {
    if ($t['type'] === 'receivable') {
        $ledger[] = ['date' => $t['date'], 'type' => 'received', 'desc' => 'Received Entry' . ($t['notes'] ? " - {$t['notes']}" : ''), 'debit' => 0, 'credit' => (float)$t['amount']];
    } else {
        $ledger[] = ['date' => $t['date'], 'type' => 'paid', 'desc' => 'Payment Made' . ($t['notes'] ? " - {$t['notes']}" : ''), 'debit' => (float)$t['amount'], 'credit' => 0];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($party['name']) ?> - Party Ledger</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; color: #222; padding: 15px; background: #fff; }
.print-header { text-align: center; border-bottom: 3px double #1B2A4A; padding-bottom: 10px; margin-bottom: 15px; }
.print-header h2 { color: #1B2A4A; font-size: 22px; margin: 0; letter-spacing: 1px; }
.print-header h4 { color: #555; font-size: 14px; font-weight: 600; margin: 4px 0 0; }
.print-header .date-range { font-size: 12px; color: #888; margin-top: 4px; }
.info-row { display: flex; gap: 30px; margin-bottom: 12px; font-size: 12px; flex-wrap: wrap; }
.info-row .label { font-weight: 700; color: #555; display: block; font-size: 10px; text-transform: uppercase; }
.info-row .value { font-weight: 600; font-size: 13px; }
.info-row .text-danger { color: #dc3545; }
.info-row .text-success { color: #28a745; }
.section-title { background: #1B2A4A; color: #fff; padding: 7px 12px; font-size: 13px; font-weight: 700; margin-top: 18px; margin-bottom: 0; border-radius: 4px 4px 0 0; }
table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
th, td { padding: 5px 8px; border: 1px solid #ccc; text-align: left; font-size: 11px; }
th { background: #e9ecef; color: #333; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
td { vertical-align: middle; }
tr:nth-child(even) td { background: #f8f9fc; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.font-bold { font-weight: 700; }
.text-danger { color: #dc3545; }
.text-success { color: #28a745; }
.text-muted { color: #888; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 9px; font-weight: 700; text-transform: uppercase; color: #fff; }
.badge-opening { background: #6c757d; }
.badge-receivable { background: #17a2b8; }
.badge-paid { background: #28a745; }
.badge-secondary { background: #6c757d; }
.opening-row td { background: #e8f0fe !important; font-weight: 700; }
.total-row td { background: #1B2A4A !important; color: #fff !important; font-weight: 700; font-size: 13px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.summary-table { width: 350px; margin-left: auto; margin-top: 10px; }
.summary-table td { padding: 5px 10px; font-size: 12px; }
.summary-table .grand-row td { border-top: 2px solid #1B2A4A; font-size: 14px; font-weight: 800; color: #1B2A4A; background: #eef2f7 !important; }
.footer { margin-top: 20px; padding-top: 10px; border-top: 1px dashed #ccc; text-align: center; font-size: 10px; color: #999; }
.no-print { margin-bottom: 15px; }
.no-print button, .no-print a { padding: 6px 16px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; margin-right: 6px; text-decoration: none; display: inline-block; }
.no-print .btn-print { background: #1B2A4A; color: #fff; }
.no-print .btn-back { background: #e9ecef; color: #333; }
@media print { body { padding: 0; font-size: 11px; } .no-print { display: none !important; } @page { margin: 0.4in; size: portrait; } tr:nth-child(even) td { background: #f8f9fc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .opening-row td { background: #e8f0fe !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .total-row td { background: #1B2A4A !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } th { background: #e9ecef !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .summary-table .grand-row td { background: #eef2f7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128424; Print</button>
    <a href="ledger.php?id=<?= $party_id ?>" class="btn-back">&#8592; Back to Ledger</a>
</div>

<div class="print-header">
    <h2>FLOUR MILL</h2>
    <h4>PARTY LEDGER</h4>
    <div class="date-range">Generated on <?= date('d-M-Y h:i A') ?></div>
</div>

<div class="info-row">
    <div>
        <span class="label">Party Name</span>
        <span class="value"><?= htmlspecialchars($party['name']) ?></span>
    </div>
    <div>
        <span class="label">Phone</span>
        <span class="value"><?= htmlspecialchars($party['phone'] ?: '-') ?></span>
    </div>
    <div>
        <span class="label">Address</span>
        <span class="value"><?= htmlspecialchars($party['address'] ?: '-') ?></span>
    </div>
    <div>
        <span class="label">Opening Balance</span>
        <span class="value">Rs <?= money($opening) ?></span>
    </div>
    <div>
        <span class="label">Current Balance</span>
        <span class="value <?= $party['balance'] > 0 ? 'text-danger' : ($party['balance'] < 0 ? 'text-success' : '') ?>">Rs <?= money($party['balance']) ?></span>
    </div>
</div>

<div class="section-title"><i class="fas fa-scroll"></i> Ledger</div>
<table>
    <thead>
        <tr>
            <th width="85">Date</th>
            <th width="90">Type</th>
            <th>Description</th>
            <th class="text-right" width="120">Credit (+)</th>
            <th class="text-right" width="120">Debit (-)</th>
            <th class="text-right" width="130">Balance</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $balance = $opening;
        $total_debit = 0;
        $total_credit = 0;
        ?>
        <tr class="opening-row">
            <td></td>
            <td><span class="badge badge-opening">Opening</span></td>
            <td>Opening Balance b/f</td>
            <td class="text-right font-bold">Rs <?= money($balance) ?></td>
            <td class="text-right"></td>
            <td class="text-right font-bold">Rs <?= money($balance) ?></td>
        </tr>
        <?php foreach ($ledger as $entry):
            if ($entry['type'] === 'received') {
                $balance += $entry['credit'];
                $total_credit += $entry['credit'];
            } else {
                $balance -= $entry['debit'];
                $total_debit += $entry['debit'];
            }
        ?>
        <tr>
            <td><?= date('d-M-Y', strtotime($entry['date'])) ?></td>
            <td>
                <?php if ($entry['type'] === 'received'): ?>
                    <span class="badge badge-receivable">Received</span>
                <?php else: ?>
                    <span class="badge badge-paid">Paid</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($entry['desc']) ?></td>
            <td class="text-right <?= $entry['credit'] > 0 ? 'font-bold' : '' ?>">
                <?= $entry['credit'] > 0 ? 'Rs ' . money($entry['credit']) : '' ?>
            </td>
            <td class="text-right <?= $entry['debit'] > 0 ? 'font-bold' : '' ?>">
                <?= $entry['debit'] > 0 ? 'Rs ' . money($entry['debit']) : '' ?>
            </td>
            <td class="text-right font-bold">Rs <?= money($balance) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td colspan="3" class="text-right">Total</td>
            <td class="text-right">Rs <?= money($total_credit) ?></td>
            <td class="text-right">Rs <?= money($total_debit) ?></td>
            <td class="text-right">Rs <?= money($balance) ?></td>
        </tr>
    </tfoot>
</table>

<table class="summary-table">
    <tr>
        <td class="font-bold">Total Credit (Received):</td>
        <td class="text-right">Rs <?= money($total_credit) ?></td>
    </tr>
    <tr>
        <td class="font-bold">Total Debit (Paid):</td>
        <td class="text-right">Rs <?= money($total_debit) ?></td>
    </tr>
    <tr class="grand-row">
        <td>Current Balance:</td>
        <td class="text-right <?= $party['balance'] > 0 ? 'text-danger' : ($party['balance'] < 0 ? 'text-success' : '') ?>">Rs <?= money($party['balance']) ?></td>
    </tr>
</table>

<div class="footer">
    Flour Mill Management System &bull; Party Ledger &bull; <?= htmlspecialchars($party['name']) ?> &bull; Generated <?= date('d-M-Y H:i:s A') ?>
</div>

<script>window.onload = function() { setTimeout(function(){ window.print(); }, 400); };</script>
</body>
</html>
