<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'warehouses';
$page_title = 'Warehouses';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$edit_row = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $code = sanitize($_POST['code']);
    $name = sanitize($_POST['name']);
    $location = sanitize($_POST['location']);
    $capacity_kg = str_replace(',', '', $_POST['capacity_kg']);
    $type = sanitize($_POST['type']);

    if ($id) {
        $conn->query("UPDATE warehouses SET code='$code', name='$name', location='$location', capacity_kg='$capacity_kg', type='$type' WHERE id=$id");
        setFlash("Warehouse updated.");
    } else {
        $conn->query("INSERT INTO warehouses (code, name, location, capacity_kg, type) VALUES ('$code', '$name', '$location', '$capacity_kg', '$type')");
        setFlash("Warehouse added.");
    }
    header("Location: warehouses.php");
    exit;
}

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit_row = $conn->query("SELECT * FROM warehouses WHERE id = $id")->fetch_assoc();
}

$result = $conn->query("SELECT * FROM warehouses ORDER BY name");

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-warehouse mr-1"></i> Warehouses</h1>
    <a href="#" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#warehouseModal"><i class="fas fa-plus-circle mr-1"></i> Add Warehouse</a>
</div>

<?= flashMessage() ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Warehouses</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Location</th>
                        <th class="text-right">Capacity (KG)</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['code']) ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                        <td class="text-right"><?= qty($row['capacity_kg']) ?></td>
                        <td><span class="badge badge-info"><?= ucfirst($row['type']) ?></span></td>
                        <td>
                            <a href="warehouse_view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info" title="View Stock & Arrivals"><i class="fas fa-eye"></i></a>
                            <a href="#" class="btn btn-sm btn-warning" title="Edit" onclick="editWarehouse(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['code'])) ?>', '<?= htmlspecialchars(addslashes($row['name'])) ?>', '<?= htmlspecialchars(addslashes($row['location'])) ?>', '<?= $row['capacity_kg'] ?>', '<?= $row['type'] ?>')"><i class="fas fa-edit"></i></a>
                            <a href="warehouse_delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this warehouse?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="warehouseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Warehouse</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="warehouseId" value="">
                    <div class="form-group">
                        <label>Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" id="whCode" class="form-control" placeholder="WH-01" required>
                    </div>
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="whName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" id="whLocation" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Capacity (KG)</label>
                        <input type="text" name="capacity_kg" id="whCapacity" class="form-control" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type" id="whType" class="form-control">
                            <option value="wheat">Wheat Storage</option>
                            <option value="mill">Mill</option>
                            <option value="finished">Finished Goods</option>
                            <option value="general">General</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editWarehouse(id, code, name, location, capacity, type) {
    $('#warehouseId').val(id);
    $('#whCode').val(code);
    $('#whName').val(name);
    $('#whLocation').val(location);
    $('#whCapacity').val(capacity);
    $('#whType').val(type);
    $('#modalTitle').text('Edit Warehouse');
    $('#warehouseModal').modal('show');
}

$('#warehouseModal').on('hidden.bs.modal', function () {
    $('#warehouseId').val('');
    $('#whCode').val('');
    $('#whName').val('');
    $('#whLocation').val('');
    $('#whCapacity').val('');
    $('#whType').val('wheat');
    $('#modalTitle').text('Add Warehouse');
});
</script>

<?php include '../../includes/footer.php'; ?>
