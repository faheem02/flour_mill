<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'customer_add';
$page_title = 'Add Customer';
require_once '../../includes/db.php';
include '../../includes/header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $business = sanitize($_POST['business_name']);
    $opening = str_replace(',', '', $_POST['opening_balance']);

    if (empty($name)) { $error = "Customer name is required."; }
    else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO customers (name, phone, address, business_name, opening_balance, balance) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssdd", $name, $phone, $address, $business, $opening, $opening);
            $stmt->execute();
            $cid = $conn->insert_id;

            if ($opening > 0) {
                $stmt2 = $conn->prepare("INSERT INTO customer_ledger (customer_id, date, type, debit, balance, notes) VALUES (?, ?, 'opening', ?, ?, 'Opening balance')");
                $today = date('Y-m-d');
                $stmt2->bind_param("isdd", $cid, $today, $opening, $opening);
                $stmt2->execute();
            }

            $conn->commit();
            $success = "Customer added successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-user-plus mr-1"></i> Add Customer</h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Customer Information</h6></div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Customer Name <span class="text-danger">*</span></label>
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
                        <label>Business Name</label>
                        <input type="text" name="business_name" class="form-control" placeholder="Shop / Business name">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Opening Balance (Dr)</label>
                        <input type="text" name="opening_balance" class="form-control" placeholder="0.00" oninput="this.value = this.value.replace(/[^0-9.]/g,'')">
                        <small class="text-muted">Amount customer owes you</small>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" class="form-control" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Customer</button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
