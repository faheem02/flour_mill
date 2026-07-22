<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'party_list';
$page_title = 'Party Ledger';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$party_id = (int)($_GET['id'] ?? 0);
$party = $party_id ? $conn->query("SELECT * FROM general_parties WHERE id = $party_id")->fetch_assoc() : null;

include '../../includes/header.php';

if ($party) {
    $opening = (float)($party['opening_balance'] ?? 0);

    $transactions = $conn->query("
        SELECT * FROM party_transactions
        WHERE party_id = $party_id
        ORDER BY date ASC, id ASC
    ");
    $ledger = [];
    while ($t = $transactions->fetch_assoc()) {
        if ($t['type'] === 'receivable') {
            $ledger[] = [
                'date'   => $t['date'],
                'type'   => 'received',
                'desc'   => 'Received Entry' . ($t['notes'] ? " - {$t['notes']}" : ''),
                'debit'  => 0,
                'credit' => (float)$t['amount'],
            ];
        } else {
            $ledger[] = [
                'date'   => $t['date'],
                'type'   => 'paid',
                'desc'   => 'Payment Made' . ($t['notes'] ? " - {$t['notes']}" : ''),
                'debit'  => (float)$t['amount'],
                'credit' => 0,
            ];
        }
    }
}
?>

<?php if (!$party): ?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-1"></i> Party Ledger</h1>
</div>
<?php
$all_parties = $conn->query("SELECT * FROM general_parties WHERE status='active' ORDER BY name");
?>
<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Select a Party</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th class="text-right">Balance</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($f = $all_parties->fetch_assoc()): ?>
                    <tr>
                        <td><?= $f['id'] ?></td>
                        <td><strong><?= htmlspecialchars($f['name']) ?></strong></td>
                        <td><?= htmlspecialchars($f['phone'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($f['address'] ?: '-') ?></td>
                        <td class="text-right <?= $f['balance'] > 0 ? 'text-danger' : ($f['balance'] < 0 ? 'text-success' : 'muted') ?>">
                            <?= money($f['balance']) ?>
                        </td>
                        <td><a href="ledger.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-primary">View Ledger</a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php else: ?>

<!-- Party Info -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-building mr-1"></i> <?= htmlspecialchars($party['name']) ?> — Ledger
        </h6>
        <div>
            <a href="print_ledger.php?id=<?= $party['id'] ?>" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-print mr-1"></i> Print</a>
            <a href="list.php" class="btn btn-sm btn-secondary ml-1"><i class="fas fa-arrow-left mr-1"></i> Back</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-2">
                <small class="text-muted d-block">Phone</small>
                <strong><?= htmlspecialchars($party['phone'] ?: '-') ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Address</small>
                <strong><?= htmlspecialchars($party['address'] ?: '-') ?></strong>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Opening Balance</small>
                <strong>Rs <?= money($opening) ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Current Balance</small>
                <strong class="<?= $party['balance'] > 0 ? 'text-danger' : ($party['balance'] < 0 ? 'text-success' : 'text-muted') ?>">Rs <?= money($party['balance']) ?></strong>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-12">
                <a href="add_received.php?id=<?= $party['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-file-invoice-dollar mr-1"></i> Add Received</a>
                <a href="add_paid.php?id=<?= $party['id'] ?>" class="btn btn-sm btn-success ml-1"><i class="fas fa-money-bill-wave mr-1"></i> Add Paid</a>
            </div>
        </div>
    </div>
</div>

<!-- Ledger Table -->
<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0"><i class="fas fa-scroll mr-1"></i> Ledger</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" style="font-size:13px;">
                <thead class="thead-dark">
                    <tr>
                        <th style="width:90px">Date</th>
                        <th style="width:100px">Type</th>
                        <th>Description</th>
                        <th class="text-right" style="width:130px">Credit (+)</th>
                        <th class="text-right" style="width:130px">Debit (-)</th>
                        <th class="text-right" style="width:150px">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $balance = $opening;
                    $total_debit = 0;
                    $total_credit = 0;
                    ?>
                    <!-- Opening Balance Row -->
                    <tr style="background:#e8f0fe; font-weight:bold;">
                        <td></td>
                        <td><span class="badge badge-secondary">Opening</span></td>
                        <td>Opening Balance b/f</td>
                        <td class="text-right"><strong>Rs <?= money($balance) ?></strong></td>
                        <td class="text-right"></td>
                        <td class="text-right"><strong>Rs <?= money($balance) ?></strong></td>
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
                                <span class="badge badge-info">Received</span>
                            <?php else: ?>
                                <span class="badge badge-success">Paid</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($entry['desc']) ?></td>
                        <td class="text-right <?= $entry['credit'] > 0 ? 'font-weight-bold text-primary' : '' ?>">
                            <?= $entry['credit'] > 0 ? 'Rs ' . money($entry['credit']) : '' ?>
                        </td>
                        <td class="text-right <?= $entry['debit'] > 0 ? 'font-weight-bold text-danger' : '' ?>">
                            <?= $entry['debit'] > 0 ? 'Rs ' . money($entry['debit']) : '' ?>
                        </td>
                        <td class="text-right font-weight-bold">Rs <?= money($balance) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="font-weight-bold" style="font-size:14px;">
                    <tr style="background:#1B2A4A; color:#fff;">
                        <td colspan="3" class="text-right">Total</td>
                        <td class="text-right">Rs <?= money($total_credit) ?></td>
                        <td class="text-right">Rs <?= money($total_debit) ?></td>
                        <td class="text-right">Rs <?= money($balance) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
