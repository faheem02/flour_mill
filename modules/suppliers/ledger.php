<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'supplier_ledger';
$page_title = 'Supplier Ledger';
require_once '../../includes/db.php';
include '../../includes/header.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$sid = (int)($_GET['supplier_id'] ?? 0);

// Get selected supplier info
$selected = null;
if ($sid > 0) {
    $selected = $conn->query("SELECT * FROM suppliers WHERE id=$sid")->fetch_assoc();
}

// Get transactions
$transactions = null;
if ($selected) {
    $transactions = $conn->query("SELECT * FROM supplier_ledger WHERE supplier_id=$sid AND date BETWEEN '$from' AND '$to' ORDER BY date ASC, id ASC");
}

// Get all suppliers with balances
$suppliers = $conn->query("SELECT id, name, phone, balance FROM suppliers ORDER BY name");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-truck mr-1"></i> Supplier Ledger</h1>
    <div>
        <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
        <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header">
        <form method="GET" class="form-inline">
            <label class="mr-1 small">Supplier:</label>
            <select name="supplier_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()" style="min-width:200px">
                <option value="">-- Select Supplier --</option>
                <?php while ($s = $suppliers->fetch_assoc()): ?>
                <option value="<?= $s['id'] ?>" <?= $sid==$s['id']?'selected':'' ?>>
                    <?= htmlspecialchars($s['name']) ?> (Rs <?= money($s['balance']) ?>)
                </option>
                <?php endwhile; ?>
            </select>
            <label class="mr-1 small">From:</label>
            <input type="date" name="from" class="form-control form-control-sm mr-2" value="<?= $from ?>">
            <label class="mr-1 small">To:</label>
            <input type="date" name="to" class="form-control form-control-sm mr-2" value="<?= $to ?>">
            <button type="submit" class="btn btn-sm btn-primary">View</button>
        </form>
    </div>

    <?php if ($selected): ?>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <h6 class="font-weight-bold m-0"><?= htmlspecialchars($selected['name']) ?></h6>
                <small class="text-muted">Phone: <?= htmlspecialchars($selected['phone']) ?></small>
            </div>
            <div class="col-md-6 text-right">
                <strong>Balance: <span class="<?= $selected['balance'] > 0 ? 'text-danger' : 'text-success' ?>">Rs <?= money($selected['balance']) ?></span></strong>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Notes</th>
                        <th class="text-right">Debit (Purchase)</th>
                        <th class="text-right">Credit (Payment)</th>
                        <th class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $bal = 0; if ($transactions && $transactions->num_rows > 0): while ($row = $transactions->fetch_assoc()): $bal = $row['balance']; ?>
                    <tr>
                        <td><?= $row['date'] ?></td>
                        <td><span class="badge badge-<?= $row['type']=='payment'?'success':($row['type']=='purchase'?'primary':'secondary') ?>"><?= ucfirst($row['type']) ?></span></td>
                        <td><?= htmlspecialchars($row['notes']) ?></td>
                        <td class="text-right"><?= $row['debit'] > 0 ? money($row['debit']) : '-' ?></td>
                        <td class="text-right"><?= $row['credit'] > 0 ? money($row['credit']) : '-' ?></td>
                        <td class="text-right font-weight-bold"><?= money($row['balance']) ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center">No entries found for this period.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-active">
                    <tr>
                        <th colspan="3" class="text-right">Total</th>
                        <th class="text-right"><?= money($conn->query("SELECT COALESCE(SUM(debit),0) FROM supplier_ledger WHERE supplier_id=$sid AND date BETWEEN '$from' AND '$to'")->fetch_row()[0]) ?></th>
                        <th class="text-right"><?= money($conn->query("SELECT COALESCE(SUM(credit),0) FROM supplier_ledger WHERE supplier_id=$sid AND date BETWEEN '$from' AND '$to'")->fetch_row()[0]) ?></th>
                        <th class="text-right"><?= money($bal) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="card-body text-center py-5">
        <i class="fas fa-hand-pointer fa-3x text-muted mb-3"></i>
        <p class="text-muted">Select a supplier from the dropdown above to view their ledger.</p>
        <a href="list.php" class="btn btn-primary btn-sm"><i class="fas fa-list mr-1"></i> Supplier List</a>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
