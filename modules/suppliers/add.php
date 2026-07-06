<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'supplier_add';
$page_title = 'Add Supplier';
require_once '../../includes/db.php';
include '../../includes/header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $opening = str_replace(',', '', $_POST['opening_balance']);

    if (empty($name)) {
        $error = "Supplier name is required.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO suppliers (name, phone, address, opening_balance, balance) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdd", $name, $phone, $address, $opening, $opening);
            $stmt->execute();
            $sid = $conn->insert_id;

            if ($opening > 0) {
                $today = date('Y-m-d');
                $notes = "Opening balance";
                $stmt2 = $conn->prepare("INSERT INTO supplier_ledger (supplier_id, date, type, debit, credit, balance, notes) VALUES (?, ?, 'opening', ?, 0, ?, ?)");
                $stmt2->bind_param("isdds", $sid, $today, $opening, $opening, $notes);
                $stmt2->execute();
            }

            $conn->commit();
            $success = "Supplier added successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-truck mr-1"></i> Add Supplier</h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back to List</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Supplier Information</h6></div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Opening Balance (Cr)</label>
                        <input type="text" name="opening_balance" class="form-control" placeholder="0.00" oninput="this.value = this.value.replace(/[^0-9.]/g,'')">
                        <small class="text-muted">Amount you owe to supplier (if any)</small>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Supplier</button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
