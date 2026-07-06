<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'general_ledger';
$page_title = 'General Ledger';
require_once '../../includes/db.php';
include '../../includes/header.php';

// Ensure Manual GL account exists in chart_of_accounts
$gl = $conn->query("SELECT id FROM chart_of_accounts WHERE code='GL-MANUAL'")->fetch_assoc();
if (!$gl) {
    $conn->query("INSERT INTO chart_of_accounts (code, name, type, parent_id) VALUES ('GL-MANUAL', 'Manual GL Entry', 'liability', 6)");
    $gl_id = $conn->insert_id;
} else {
    $gl_id = $gl['id'];
}

$error = $success = '';

// Handle new entry submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $party_name = sanitize($_POST['party_name']);
    $description = sanitize($_POST['description']);
    $debit = str_replace(',', '', $_POST['debit']);
    $credit = str_replace(',', '', $_POST['credit']);
    $debit = (float)$debit;
    $credit = (float)$credit;

    if ($debit > 0 && $credit > 0) {
        $error = "Either Debit or Credit, not both.";
    } elseif ($debit <= 0 && $credit <= 0) {
        $error = "Enter an amount in Debit or Credit.";
    } else {
        // Use autoJournalEntry with the Manual GL account
        $full_desc = $party_name ? "[$party_name] $description" : $description;
        if ($debit > 0) {
            $result = autoJournalEntry($date, $full_desc, [$gl_id => $debit], [6 => $debit]);
        } else {
            $result = autoJournalEntry($date, $full_desc, [6 => $credit], [$gl_id => $credit]);
        }
        if ($result) {
            $success = "Entry added successfully.";
        } else {
            $error = "Error adding entry.";
        }
    }
}

// Fetch all Manual GL entries
$entries = $conn->query("SELECT je.date, je.voucher_no, je.description, jei.debit, jei.credit
    FROM journal_entries je
    JOIN journal_entry_items jei ON je.id = jei.journal_id
    WHERE jei.account_id = $gl_id
    ORDER BY je.date ASC, je.id ASC");

// Calculate total debit and credit
$totals = $conn->query("SELECT COALESCE(SUM(jei.debit),0) as td, COALESCE(SUM(jei.credit),0) as tc
    FROM journal_entries je
    JOIN journal_entry_items jei ON je.id = jei.journal_id
    WHERE jei.account_id = $gl_id")->fetch_assoc();
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-1"></i> General Ledger</h1>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#entryModal"><i class="fas fa-plus mr-1"></i> New Entry</button>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Voucher #</th>
                        <th>Party Name</th>
                        <th>Description</th>
                        <th class="text-right">Debit (Dr)</th>
                        <th class="text-right">Credit (Cr)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $bal = 0;
                    if ($entries->num_rows > 0):
                        while ($row = $entries->fetch_assoc()):
                            $dr = $row['debit'];
                            $cr = $row['credit'];
                            $bal += $dr - $cr;
                            // Extract party name from description [PartyName] rest
                            $party = '';
                            $desc = $row['description'];
                            if (preg_match('/^\[([^\]]+)\]\s*(.*)/', $desc, $m)) {
                                $party = $m[1];
                                $desc = $m[2];
                            }
                    ?>
                    <tr>
                        <td><?= $row['date'] ?></td>
                        <td><?= htmlspecialchars($row['voucher_no']) ?></td>
                        <td><?= htmlspecialchars($party) ?></td>
                        <td><?= htmlspecialchars($desc) ?></td>
                        <td class="text-right text-success"><?= $dr > 0 ? money($dr) : '-' ?></td>
                        <td class="text-right text-danger"><?= $cr > 0 ? money($cr) : '-' ?></td>
                    </tr>
                    <?php
                        endwhile;
                    else:
                    ?>
                    <tr><td colspan="6" class="text-center">No entries yet. Click "New Entry" to add one.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-active">
                    <tr>
                        <th colspan="4" class="text-right">Total</th>
                        <th class="text-right"><?= money($totals['td']) ?></th>
                        <th class="text-right"><?= money($totals['tc']) ?></th>
                    </tr>
                    <tr class="table-info">
                        <th colspan="4" class="text-right">Net Balance</th>
                        <th colspan="2" class="text-right font-weight-bold">
                            Rs <?= money($totals['td'] - $totals['tc']) ?>
                            <small class="text-muted">(<?= ($totals['td'] - $totals['tc']) >= 0 ? 'Receivable' : 'Payable' ?>)</small>
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- New Entry Modal -->
<div class="modal fade" id="entryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle mr-1"></i> New General Ledger Entry</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Party Name</label>
                        <input type="text" name="party_name" class="form-control" placeholder="e.g. Muhammad Ali">
                    </div>
                    <div class="form-group">
                        <label>Description <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Loan given, personal payment" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Debit (Dr) <small class="text-muted">- jo aapko lena hai</small></label>
                                <input type="text" name="debit" class="form-control text-right" placeholder="0.00" oninput="formatNumber(this)">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Credit (Cr) <small class="text-muted">- jo aap ne dena hai</small></label>
                                <input type="text" name="credit" class="form-control text-right" placeholder="0.00" oninput="formatNumber(this)">
                            </div>
                        </div>
                    </div>
                    <small class="text-muted">Either enter Debit OR Credit, not both.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check mr-1"></i> Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function formatNumber(el) {
    var v = el.value.replace(/,/g, '').replace(/[^0-9.]/g, '');
    if (v) {
        var parts = v.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        el.value = parts.join('.');
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
