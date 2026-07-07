<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'vehicles';
$page_title = 'Vehicles';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Add/Edit handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id = (int)($_POST['id'] ?? 0);
    $vehicle_no = sanitize($_POST['vehicle_no']);
    $vehicle_type = sanitize($_POST['vehicle_type']);
    $owner_name = sanitize($_POST['owner_name']);
    $driver_name = sanitize($_POST['driver_name']);
    $driver_mobile = sanitize($_POST['driver_mobile']);
    $capacity_kg = str_replace(',', '', $_POST['capacity_kg']);

    if ($id) {
        $conn->query("UPDATE vehicles SET vehicle_no='$vehicle_no', vehicle_type='$vehicle_type', owner_name='$owner_name', driver_name='$driver_name', driver_mobile='$driver_mobile', capacity_kg='$capacity_kg' WHERE id=$id");
        setFlash("Vehicle updated.");
    } else {
        $conn->query("INSERT INTO vehicles (vehicle_no, vehicle_type, owner_name, driver_name, driver_mobile, capacity_kg) VALUES ('$vehicle_no', '$vehicle_type', '$owner_name', '$driver_name', '$driver_mobile', '$capacity_kg')");
        setFlash("Vehicle added.");
    }
    header("Location: vehicles.php");
    exit;
}

include '../../includes/header.php';

$result = $conn->query("SELECT * FROM vehicles ORDER BY vehicle_no");

$edit_row = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit_row = $conn->query("SELECT * FROM vehicles WHERE id = $id")->fetch_assoc();
}
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-truck mr-1"></i> Vehicles</h1>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addModal"><i class="fas fa-plus-circle mr-1"></i> Add Vehicle</button>
</div>

<?= flashMessage() ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Vehicles</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Vehicle No</th>
                        <th>Type</th>
                        <th>Owner</th>
                        <th>Driver</th>
                        <th>Driver Mobile</th>
                        <th class="text-right">Capacity (KG)</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['vehicle_no']) ?></td>
                        <td><?= htmlspecialchars($row['vehicle_type']) ?></td>
                        <td><?= htmlspecialchars($row['owner_name']) ?></td>
                        <td><?= htmlspecialchars($row['driver_name']) ?></td>
                        <td><?= htmlspecialchars($row['driver_mobile']) ?></td>
                        <td class="text-right"><?= qty($row['capacity_kg']) ?></td>
                        <td class="text-center text-nowrap">
                            <div class="btn-group btn-group-sm">
                                <a href="vehicles.php?edit=<?= $row['id'] ?>" class="btn btn-warning btn-action" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="vehicle_delete.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-action" title="Delete" onclick="return confirm('Delete this vehicle?')"><i class="fas fa-trash"></i></a>
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
                <div class="modal-header"><h5>Add Vehicle</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Vehicle No <span class="text-danger">*</span></label>
                        <input type="text" name="vehicle_no" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Vehicle Type</label>
                        <input type="text" name="vehicle_type" class="form-control" placeholder="Truck / Trolley / etc">
                    </div>
                    <div class="form-group">
                        <label>Owner Name</label>
                        <input type="text" name="owner_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Driver Name</label>
                        <input type="text" name="driver_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Driver Mobile</label>
                        <input type="text" name="driver_mobile" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Capacity (KG)</label>
                        <input type="text" name="capacity_kg" class="form-control" placeholder="0">
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
                <div class="modal-header"><h5>Edit Vehicle</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Vehicle No <span class="text-danger">*</span></label>
                        <input type="text" name="vehicle_no" class="form-control" value="<?= htmlspecialchars($edit_row['vehicle_no']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Vehicle Type</label>
                        <input type="text" name="vehicle_type" class="form-control" value="<?= htmlspecialchars($edit_row['vehicle_type']) ?>" placeholder="Truck / Trolley / etc">
                    </div>
                    <div class="form-group">
                        <label>Owner Name</label>
                        <input type="text" name="owner_name" class="form-control" value="<?= htmlspecialchars($edit_row['owner_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Driver Name</label>
                        <input type="text" name="driver_name" class="form-control" value="<?= htmlspecialchars($edit_row['driver_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Driver Mobile</label>
                        <input type="text" name="driver_mobile" class="form-control" value="<?= htmlspecialchars($edit_row['driver_mobile']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Capacity (KG)</label>
                        <input type="text" name="capacity_kg" class="form-control" value="<?= qty($edit_row['capacity_kg']) ?>">
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
    $('#editModal').on('hidden.bs.modal', function() { window.location.href = 'vehicles.php'; });
});
</script>
<?php endif; ?>

<style>
.btn-action { padding: 4px 10px !important; font-size: 12px !important; }
</style>

<?php include '../../includes/footer.php'; ?>
