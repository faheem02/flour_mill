<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'bags';
$page_title = 'Bag Types';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Add/Edit handler - before header output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id = (int)($_POST['id'] ?? 0);
    $name = sanitize($_POST['name']);
    $bag_weight_kg = str_replace(',', '', $_POST['bag_weight_kg']);
    $empty_bag_cost = str_replace(',', '', $_POST['empty_bag_cost']);

    if ($id) {
        $conn->query("UPDATE bag_types SET name='$name', bag_weight_kg='$bag_weight_kg', empty_bag_cost='$empty_bag_cost' WHERE id=$id");
        setFlash("Bag type updated.");
    } else {
        $conn->query("INSERT INTO bag_types (name, bag_weight_kg, empty_bag_cost) VALUES ('$name', '$bag_weight_kg', '$empty_bag_cost')");
        setFlash("Bag type added.");
    }
    header("Location: bags.php");
    exit;
}

include '../../includes/header.php';

$result = $conn->query("SELECT * FROM bag_types ORDER BY name");

$edit_row = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit_row = $conn->query("SELECT * FROM bag_types WHERE id = $id")->fetch_assoc();
}
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-shopping-bag mr-1"></i> Bag Types</h1>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addModal"><i class="fas fa-plus-circle mr-1"></i> Add Bag Type</button>
</div>

<?= flashMessage() ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Bag Types</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th class="text-right">Bag Weight (KG)</th>
                        <th class="text-right">Empty Bag Cost</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td class="text-right"><?= qty($row['bag_weight_kg']) ?></td>
                        <td class="text-right"><?= money($row['empty_bag_cost']) ?></td>
                        <td class="text-nowrap">
                            <div class="btn-group btn-group-sm">
                                <a href="bags.php?edit=<?= $row['id'] ?>" class="btn btn-warning btn-action" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="bag_delete.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-action" title="Delete" onclick="return confirm('Delete this bag type?')"><i class="fas fa-trash"></i></a>
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
                <div class="modal-header"><h5>Add Bag Type</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Bag Type Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="PP Bag / Jute Bag" required>
                    </div>
                    <div class="form-group">
                        <label>Bag Weight (KG) <small class="text-muted">(empty bag weight)</small></label>
                        <input type="text" name="bag_weight_kg" class="form-control" placeholder="0.5">
                    </div>
                    <div class="form-group">
                        <label>Empty Bag Cost (Rs)</label>
                        <input type="text" name="empty_bag_cost" class="form-control" placeholder="0.00">
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
                <div class="modal-header"><h5>Edit Bag Type</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Bag Type Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit_row['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Bag Weight (KG) <small class="text-muted">(empty bag weight)</small></label>
                        <input type="text" name="bag_weight_kg" class="form-control" value="<?= qty($edit_row['bag_weight_kg']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Empty Bag Cost (Rs)</label>
                        <input type="text" name="empty_bag_cost" class="form-control" value="<?= money($edit_row['empty_bag_cost']) ?>">
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
    $('#editModal').on('hidden.bs.modal', function() { window.location.href = 'bags.php'; });
});
</script>
<?php endif; ?>

<style>
.btn-action { padding: 4px 10px !important; font-size: 12px !important; }
</style>

<?php include '../../includes/footer.php'; ?>
