<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'farmer_list';
$page_title = 'Farmers';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$error = '';
$edit_row = null;

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM farmers WHERE id = $id");
    setFlash("Farmer deleted.");
    header("Location: list.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $village = sanitize($_POST['village']);
    $city = sanitize($_POST['city']);
    $opening_balance = str_replace(',', '', $_POST['opening_balance'] ?? 0);

    if (empty($name)) {
        $error = "Name is required.";
    } elseif ($id) {
        $conn->query("UPDATE farmers SET name='$name', phone='$phone', village='$village', city='$city', opening_balance=$opening_balance, balance=$opening_balance WHERE id=$id");
        setFlash("Farmer updated.");
        header("Location: list.php");
        exit;
    } else {
        $conn->query("INSERT INTO farmers (name, phone, village, city, opening_balance, balance) VALUES ('$name', '$phone', '$village', '$city', $opening_balance, $opening_balance)");
        setFlash("Farmer added.");
        header("Location: list.php");
        exit;
    }
}

if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $edit_row = $conn->query("SELECT * FROM farmers WHERE id = $id")->fetch_assoc();
}

$farmers = $conn->query("SELECT * FROM farmers ORDER BY name");

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-tractor mr-1"></i> Farmers</h1>
    <div>
        <a href="#" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#farmerModal"><i class="fas fa-plus-circle mr-1"></i> New Farmer</a>
        <a href="print_list.php" class="btn btn-sm btn-info" target="_blank"><i class="fas fa-print mr-1"></i> Print Directory</a>
    </div>
</div>

<?= flashMessage() ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Farmers</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>City</th>
                        <th class="text-right">Balance (Rs)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($f = $farmers->fetch_assoc()): ?>
                    <tr>
                        <td><?= $f['id'] ?></td>
                        <td><a href="ledger.php?id=<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></a></td>
                        <td><?= htmlspecialchars($f['phone']) ?></td>
                        <td><?= htmlspecialchars($f['village']) ?></td>
                        <td><?= htmlspecialchars($f['city']) ?></td>
                        <td class="text-right"><?= money($f['balance']) ?></td>
                        <td>
                            <a href="ledger.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-info mr-1" title="Ledger"><i class="fas fa-book"></i></a>
                            <a href="payment.php?farmer_id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-success mr-1" title="Pay"><i class="fas fa-money-bill-wave"></i></a>
                            <a href="#" class="btn btn-sm btn-outline-warning mr-1" title="Edit" onclick="editFarmer(<?= $f['id'] ?>, '<?= htmlspecialchars(addslashes($f['name'])) ?>', '<?= htmlspecialchars(addslashes($f['phone'])) ?>', '<?= htmlspecialchars(addslashes($f['village'])) ?>', '<?= htmlspecialchars(addslashes($f['city'])) ?>', <?= $f['opening_balance'] ?? 0 ?>)"><i class="fas fa-edit"></i></a>
                            <a href="list.php?delete=<?= $f['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this farmer?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="farmerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Farmer</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="farmerId" value="">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="farmerName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" id="farmerPhone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="village" id="farmerVillage" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" id="farmerCity" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Opening Balance (Rs)</label>
                        <input type="text" name="opening_balance" id="farmerOpeningBalance" class="form-control" value="0">
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
function editFarmer(id, name, phone, village, city, opening_balance) {
    $('#farmerId').val(id);
    $('#farmerName').val(name);
    $('#farmerPhone').val(phone);
    $('#farmerVillage').val(village);
    $('#farmerCity').val(city);
    $('#farmerOpeningBalance').val(opening_balance);
    $('#modalTitle').text('Edit Farmer');
    $('#farmerModal').modal('show');
}

$('#farmerModal').on('hidden.bs.modal', function () {
    $('#farmerId').val('');
    $('#farmerName').val('');
    $('#farmerPhone').val('');
    $('#farmerVillage').val('');
    $('#farmerCity').val('');
    $('#farmerOpeningBalance').val('0');
    $('#modalTitle').text('Add Farmer');
});
</script>

<?php include '../../includes/footer.php'; ?>
