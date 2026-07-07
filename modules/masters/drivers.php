<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'drivers';
$page_title = 'Drivers';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Add/Edit handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id = (int)($_POST['id'] ?? 0);
    $name = sanitize($_POST['name']);
    $cnic = sanitize($_POST['cnic']);
    $mobile = sanitize($_POST['mobile']);
    $license_no = sanitize($_POST['license_no']);
    $address = sanitize($_POST['address']);

    if ($id) {
        $conn->query("UPDATE drivers SET name='$name', cnic='$cnic', mobile='$mobile', license_no='$license_no', address='$address' WHERE id=$id");
        setFlash("Driver updated.");
    } else {
        $conn->query("INSERT INTO drivers (name, cnic, mobile, license_no, address) VALUES ('$name', '$cnic', '$mobile', '$license_no', '$address')");
        setFlash("Driver added.");
    }
    header("Location: drivers.php");
    exit;
}

include '../../includes/header.php';

$result = $conn->query("SELECT * FROM drivers ORDER BY name");

$edit_row = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit_row = $conn->query("SELECT * FROM drivers WHERE id = $id")->fetch_assoc();
}
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-id-card mr-1"></i> Drivers</h1>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addModal"><i class="fas fa-plus-circle mr-1"></i> Add Driver</button>
</div>

<?= flashMessage() ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Drivers</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>CNIC</th>
                        <th>Mobile</th>
                        <th>License No</th>
                        <th>Address</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['cnic']) ?></td>
                        <td><?= htmlspecialchars($row['mobile']) ?></td>
                        <td><?= htmlspecialchars($row['license_no']) ?></td>
                        <td><?= htmlspecialchars($row['address']) ?></td>
                        <td class="text-center text-nowrap">
                            <div class="btn-group btn-group-sm">
                                <a href="drivers.php?edit=<?= $row['id'] ?>" class="btn btn-warning btn-action" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="driver_delete.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-action" title="Delete" onclick="return confirm('Delete this driver?')"><i class="fas fa-trash"></i></a>
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
                <div class="modal-header"><h5>Add Driver</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>CNIC</label>
                        <input type="text" name="cnic" class="form-control" placeholder="XXXXX-XXXXXXX-X">
                    </div>
                    <div class="form-group">
                        <label>Mobile</label>
                        <input type="text" name="mobile" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>License No</label>
                        <input type="text" name="license_no" class="form-control">
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
                <div class="modal-header"><h5>Edit Driver</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit_row['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>CNIC</label>
                        <input type="text" name="cnic" class="form-control" value="<?= htmlspecialchars($edit_row['cnic']) ?>" placeholder="XXXXX-XXXXXXX-X">
                    </div>
                    <div class="form-group">
                        <label>Mobile</label>
                        <input type="text" name="mobile" class="form-control" value="<?= htmlspecialchars($edit_row['mobile']) ?>">
                    </div>
                    <div class="form-group">
                        <label>License No</label>
                        <input type="text" name="license_no" class="form-control" value="<?= htmlspecialchars($edit_row['license_no']) ?>">
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
    $('#editModal').on('hidden.bs.modal', function() { window.location.href = 'drivers.php'; });
});
</script>
<?php endif; ?>

<style>
.btn-action { padding: 4px 10px !important; font-size: 12px !important; }
</style>

<?php include '../../includes/footer.php'; ?>
