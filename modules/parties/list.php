<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'party_list';
$page_title = 'General Parties';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$error = $success = '';

// Add Party (modal POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_party'])) {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $opening_balance = (float)str_replace(',', '', $_POST['opening_balance']);

    if (empty($name)) {
        $error = "Party name is required.";
    } else {
        $conn->query("INSERT INTO general_parties (name, phone, address, opening_balance, balance)
            VALUES ('$name', '$phone', '$address', $opening_balance, $opening_balance)");
        setFlash("Party '$name' added successfully.");
        header("Location: list.php");
        exit;
    }
}

// Delete party
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM general_parties WHERE id = $del_id");
    setFlash("Party deleted.");
    header("Location: list.php");
    exit;
}

$parties = $conn->query("SELECT * FROM general_parties WHERE status='active' ORDER BY name ASC");

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-building mr-1"></i> General Parties</h1>
    <div>
        <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addPartyModal"><i class="fas fa-plus-circle mr-1"></i> Add Party</button>
        <button class="btn btn-sm btn-secondary ml-1" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php $flash = flashMessage(); if ($flash): ?><div class="alert alert-success alert-auto"><?= $flash ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Parties</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th class="text-right">Opening Balance</th>
                        <th class="text-right">Current Balance</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; while ($p = $parties->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                        <td><?= htmlspecialchars($p['phone'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($p['address'] ?: '-') ?></td>
                        <td class="text-right"><?= money($p['opening_balance']) ?></td>
                        <td class="text-right">
                            <strong class="<?= $p['balance'] > 0 ? 'text-danger' : ($p['balance'] < 0 ? 'text-success' : 'text-muted') ?>">
                                <?= money($p['balance']) ?>
                            </strong>
                        </td>
                        <td>
                            <a href="ledger.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-info" title="View Ledger"><i class="fas fa-book"></i></a>
                            <a href="list.php?delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this party?')" title="Delete"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Party Modal -->
<div class="modal fade" id="addPartyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle mr-1"></i> Add Party</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="add_party" value="1">
                    <div class="form-group">
                        <label>Party Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Khan Traders" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="Phone number">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Address (optional)"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Opening Balance</label>
                        <input type="text" name="opening_balance" class="form-control" placeholder="0" value="0" oninput="this.value=this.value.replace(/[^0-9.\-]/g,'')">
                        <small class="text-muted">Positive = we owe them, Negative = they owe us</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Party</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
