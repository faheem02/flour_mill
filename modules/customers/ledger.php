<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'customer_ledger';
$page_title = 'Customer Ledger';
require_once '../../includes/db.php';
include '../../includes/header.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$cid = (int)($_GET['customer_id'] ?? 0);

// Get selected customer info
$selected = null;
if ($cid > 0) {
    $selected = $conn->query("SELECT * FROM customers WHERE id=$cid")->fetch_assoc();
}

// Get transactions
$transactions = null;
if ($selected) {
    $transactions = $conn->query("SELECT * FROM customer_ledger WHERE customer_id=$cid AND date BETWEEN '$from' AND '$to' ORDER BY date ASC, id ASC");
}

// Get all customers with balances
$customers = $conn->query("SELECT id, name, phone, balance FROM customers ORDER BY name");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-users mr-1"></i> Customer Ledger</h1>
    <div>
        <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
        <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header">
        <form method="GET" class="form-inline">
            <label class="mr-1 small">Customer:</label>
            <select name="customer_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()" style="min-width:200px">
                <option value="">-- Select Customer --</option>
                <?php while ($c = $customers->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>" <?= $cid==$c['id']?'selected':'' ?>>
                    <?= htmlspecialchars($c['name']) ?> (Rs <?= money($c['balance']) ?>)
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
                        <th class="text-right">Debit (Sale)</th>
                        <th class="text-right">Credit (Payment)</th>
                        <th class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $bal = 0; if ($transactions && $transactions->num_rows > 0): while ($row = $transactions->fetch_assoc()): $bal = $row['balance']; ?>
                    <tr>
                        <td><?= $row['date'] ?></td>
                        <td><span class="badge badge-<?= $row['type']=='payment'?'success':($row['type']=='sale'?'primary':'secondary') ?>"><?= ucfirst($row['type']) ?></span></td>
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
                        <th class="text-right"><?= money($conn->query("SELECT COALESCE(SUM(debit),0) FROM customer_ledger WHERE customer_id=$cid AND date BETWEEN '$from' AND '$to'")->fetch_row()[0]) ?></th>
                        <th class="text-right"><?= money($conn->query("SELECT COALESCE(SUM(credit),0) FROM customer_ledger WHERE customer_id=$cid AND date BETWEEN '$from' AND '$to'")->fetch_row()[0]) ?></th>
                        <th class="text-right"><?= money($bal) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="card-body text-center py-5">
        <i class="fas fa-hand-pointer fa-3x text-muted mb-3"></i>
        <p class="text-muted">Select a customer from the dropdown above to view their ledger.</p>
        <a href="list.php" class="btn btn-primary btn-sm"><i class="fas fa-list mr-1"></i> Customer List</a>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
