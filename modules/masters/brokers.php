<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'brokers';
$page_title = 'Brokers';
require_once '../../includes/db.php';
include '../../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name = sanitize($_POST['name']);
    $commission_rate = str_replace(',', '', $_POST['commission_rate']);
    $mobile = sanitize($_POST['mobile']);
    $address = sanitize($_POST['address']);
    $conn->query("INSERT INTO brokers (name, commission_rate, mobile, address) 
        VALUES ('$name', '$commission_rate', '$mobile', '$address')");
    $success = "Broker added.";
}

$result = $conn->query("SELECT * FROM brokers ORDER BY name");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-handshake mr-1"></i> Brokers</h1>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addModal"><i class="fas fa-plus-circle mr-1"></i> Add Broker</button>
</div>

<?php if (isset($success)): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

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
                        <th>Status</th>
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
                    <button type="submit" name="add" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
