<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'drivers';
$page_title = 'Drivers';
require_once '../../includes/db.php';
include '../../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name = sanitize($_POST['name']);
    $cnic = sanitize($_POST['cnic']);
    $mobile = sanitize($_POST['mobile']);
    $license_no = sanitize($_POST['license_no']);
    $address = sanitize($_POST['address']);
    $conn->query("INSERT INTO drivers (name, cnic, mobile, license_no, address) 
        VALUES ('$name', '$cnic', '$mobile', '$license_no', '$address')");
    $success = "Driver added.";
}

$result = $conn->query("SELECT * FROM drivers ORDER BY name");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-id-card mr-1"></i> Drivers</h1>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addModal"><i class="fas fa-plus-circle mr-1"></i> Add Driver</button>
</div>

<?php if (isset($success)): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

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
                        <th>Status</th>
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
                        <td><span class="badge badge-<?= $row['status']=='active'?'success':'secondary' ?>"><?= ucfirst($row['status']) ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal">
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
                    <button type="submit" name="add" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
