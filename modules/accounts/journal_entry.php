<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'journal_entry';
$page_title = 'Journal Voucher';
require_once '../../includes/db.php';
include '../../includes/header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $description = sanitize($_POST['description']);
    $account_ids = $_POST['account_id'];
    $debits = $_POST['debit'];
    $credits = $_POST['credit'];

    $total_d = 0; $total_c = 0;
    $debit_entries = [];
    $credit_entries = [];

    foreach ($account_ids as $i => $aid) {
        $d = str_replace(',', '', $debits[$i]);
        $c = str_replace(',', '', $credits[$i]);
        if ($d > 0) { $total_d += $d; $debit_entries[$aid] = ($debit_entries[$aid] ?? 0) + $d; }
        if ($c > 0) { $total_c += $c; $credit_entries[$aid] = ($credit_entries[$aid] ?? 0) + $c; }
    }

    if (abs($total_d - $total_c) > 0.01) { $error = "Debit ($total_d) and Credit ($total_c) must be equal."; }
    else {
        $result = autoJournalEntry($date, $description, $debit_entries, $credit_entries, $_SESSION['user_id']);
        if ($result) {
            $success = "Journal voucher posted successfully.";
        } else {
            $error = "Error posting journal entry.";
        }
    }
}

$accounts = $conn->query("SELECT id, code, name, type FROM chart_of_accounts WHERE parent_id IS NOT NULL AND status='active' ORDER BY code");
$journals = $conn->query("SELECT * FROM journal_entries ORDER BY date DESC, id DESC LIMIT 20");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-pen-alt mr-1"></i> Journal Voucher</h1>
    <div>
        <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header"><h6 class="font-weight-bold m-0">New Journal Entry</h6></div>
            <div class="card-body">
                <form method="POST" id="journalForm">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Date <span class="text-danger">*</span></label>
                                <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Description <span class="text-danger">*</span></label>
                                <input type="text" name="description" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="card bg-light mb-3">
                        <div class="card-header"><strong>Entries</strong> <button type="button" class="btn btn-sm btn-success ml-2" onclick="addJournalRow()"><i class="fas fa-plus"></i> Add Row</button></div>
                        <div class="card-body">
                            <table class="table table-bordered" id="journalTable">
                                <thead>
                                    <tr>
                                        <th>Account</th>
                                        <th class="text-right">Debit</th>
                                        <th class="text-right">Credit</th>
                                        <th width="50">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <select name="account_id[]" class="form-control form-control-sm" required>
                                                <option value="">Select</option>
                                                <?php while ($a = $accounts->fetch_assoc()): ?>
                                                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['code']) ?> - <?= htmlspecialchars($a['name']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </td>
                                        <td><input type="text" name="debit[]" class="form-control form-control-sm text-right" placeholder="0.00" oninput="calcJournalTotal()"></td>
                                        <td><input type="text" name="credit[]" class="form-control form-control-sm text-right" placeholder="0.00" oninput="calcJournalTotal()"></td>
                                        <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();calcJournalTotal()"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="table-active">
                                        <th class="text-right">Total:</th>
                                        <th class="text-right text-success" id="totalDebit">0.00</th>
                                        <th class="text-right text-danger" id="totalCredit">0.00</th>
                                        <th></th>
                                    </tr>
                                    <tr>
                                        <th colspan="4" id="balanceMsg" class="text-center"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check mr-1"></i> Post Journal</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header"><h6 class="font-weight-bold m-0">Recent Journals</h6></div>
            <div class="card-body" style="max-height:500px;overflow-y:auto">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Date</th><th>Voucher</th><th class="text-right">Total</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($j = $journals->fetch_assoc()): ?>
                            <tr>
                                <td><?= $j['date'] ?></td>
                                <td><?= htmlspecialchars($j['voucher_no']) ?></td>
                                <td class="text-right"><?= money($j['total_debit']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function addJournalRow() {
    var options = '<?php $accounts->data_seek(0); while ($a = $accounts->fetch_assoc()): ?><option value="<?= $a["id"] ?>"><?= htmlspecialchars($a["code"]) ?> - <?= htmlspecialchars($a["name"]) ?></option><?php endwhile; ?>';
    var html = '<tr><td><select name="account_id[]" class="form-control form-control-sm" required><option value="">Select</option>' + options + '</select></td>' +
        '<td><input type="text" name="debit[]" class="form-control form-control-sm text-right" placeholder="0.00" oninput="calcJournalTotal()"></td>' +
        '<td><input type="text" name="credit[]" class="form-control form-control-sm text-right" placeholder="0.00" oninput="calcJournalTotal()"></td>' +
        '<td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'tr\').remove();calcJournalTotal()"><i class="fas fa-times"></i></button></td></tr>';
    $('#journalTable tbody').append(html);
}

function calcJournalTotal() {
    var td = 0, tc = 0;
    $('input[name="debit[]"]').each(function() { td += parseFloat($(this).val()) || 0; });
    $('input[name="credit[]"]').each(function() { tc += parseFloat($(this).val()) || 0; });
    $('#totalDebit').text(td.toFixed(2));
    $('#totalCredit').text(tc.toFixed(2));
    if (td === tc && td > 0) {
        $('#balanceMsg').html('<span class="text-success font-weight-bold"><i class="fas fa-check-circle"></i> Balanced: Rs ' + td.toFixed(2) + '</span>');
    } else if (td !== tc) {
        $('#balanceMsg').html('<span class="text-danger font-weight-bold"><i class="fas fa-exclamation-circle"></i> Difference: Rs ' + Math.abs(td - tc).toFixed(2) + '</span>');
    } else {
        $('#balanceMsg').html('');
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
