<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'brokers';
$page_title = 'Brokers';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Add/Edit handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id = (int)($_POST['id'] ?? 0);
    $name = sanitize($_POST['name']);
    $commission_rate = str_replace(',', '', $_POST['commission_rate']);
    $mobile = sanitize($_POST['mobile']);
    $address = sanitize($_POST['address']);

    if ($id) {
        $conn->query("UPDATE brokers SET name='$name', commission_rate='$commission_rate', mobile='$mobile', address='$address' WHERE id=$id");
        setFlash("Broker updated.");
    } else {
        $conn->query("INSERT INTO brokers (name, commission_rate, mobile, address) VALUES ('$name', '$commission_rate', '$mobile', '$address')");
        setFlash("Broker added.");
    }
    header("Location: brokers.php");
    exit;
}

include '../../includes/header.php';

$result = $conn->query("SELECT * FROM brokers ORDER BY name");

$edit_row = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit_row = $conn->query("SELECT * FROM brokers WHERE id = $id")->fetch_assoc();
}
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-handshake mr-1"></i> Brokers</h1>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addModal"><i class="fas fa-plus-circle mr-1"></i> Add Broker</button>
</div>

<?= flashMessage() ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Brokers</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th class="text-right">Commission Rate (%)</th>
                        <th>Mobile</th>
                        <th>Address</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td class="text-right"><?= number_format($row['commission_rate'], 1) ?>%</td>
                        <td><?= htmlspecialchars($row['mobile']) ?></td>
                        <td><?= htmlspecialchars($row['address']) ?></td>
                        <td class="text-center text-nowrap">
                            <div class="btn-group btn-group-sm">
                                <a href="brokers.php?edit=<?= $row['id'] ?>" class="btn btn-warning btn-action" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="broker_delete.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-action" title="Delete" onclick="return confirm('Delete this broker?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5>Add Broker</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Commission Rate (%)</label>
                        <input type="text" name="commission_rate" class="form-control" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label>Mobile</label>
                        <input type="text" name="mobile" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="save" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<?php if ($edit_row): ?>
<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="id" value="<?= $edit_row['id'] ?>">
                <div class="modal-header"><h5>Edit Broker</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit_row['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Commission Rate (%)</label>
                        <input type="text" name="commission_rate" class="form-control" value="<?= number_format($edit_row['commission_rate'], 1) ?>">
                    </div>
                    <div class="form-group">
                        <label>Mobile</label>
                        <input type="text" name="mobile" class="form-control" value="<?= htmlspecialchars($edit_row['mobile']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($edit_row['address']) ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="save" class="btn btn-primary">Update</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
$(document).ready(function() {
    $('#editModal').modal('show');
    $('#editModal').on('hidden.bs.modal', function() { window.location.href = 'brokers.php'; });
});
</script>
<?php endif; ?>

<style>
.btn-action { padding: 4px 10px !important; font-size: 12px !important; }
</style>

<?php include '../../includes/footer.php'; ?>
