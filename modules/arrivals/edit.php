<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'arrival_add';
$page_title = 'Edit Arrival';
require_once '../../includes/db.php';

$id = (int)($_GET['id'] ?? 0);
$arrival = $conn->query("SELECT * FROM wheat_arrivals WHERE id = $id")->fetch_assoc();
if (!$arrival) { header("Location: list.php"); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date           = $_POST['date'];
    $vehicle_no     = sanitize($_POST['vehicle_no']);
    $warehouse_id   = (int)$_POST['warehouse_id'];
    $bag_type_id    = (int)$_POST['bag_type_id'];
    $num_bags       = (int)str_replace(',', '', $_POST['num_bags']);
    $actual_weight  = str_replace(',', '', $_POST['actual_weight']);
    $weight_slip_no = sanitize($_POST['weight_slip_no']);
    $net_weight     = str_replace(',', '', $_POST['net_weight']);
    $katt_applied   = str_replace(',', '', $_POST['katt_applied']);
    $weight_diff    = str_replace(',', '', $_POST['weight_diff']);
    $moisture_pct   = str_replace(',', '', $_POST['moisture_pct']);
    $gross_amount   = str_replace(',', '', $_POST['gross_amount']);
    $bag_amount     = str_replace(',', '', $_POST['bag_amount']);
    $labour_charges = str_replace(',', '', $_POST['labour_charges']);
    $transport_charges = str_replace(',', '', $_POST['transport_charges']);
    $other_charges  = str_replace(',', '', $_POST['other_charges']);
    $net_amount     = str_replace(',', '', $_POST['net_amount']);
    $driver_id      = (int)$_POST['driver_id'];
    $broker_id      = (int)$_POST['broker_id'];
    $notes          = sanitize($_POST['notes']);

    $bag_weight = 0;
    if ($bag_type_id > 0) {
        $bt = $conn->query("SELECT bag_weight_kg FROM bag_types WHERE id = $bag_type_id")->fetch_assoc();
        $bag_weight = $bt ? $bt['bag_weight_kg'] : 0;
    }

    $old_net = $arrival['net_weight'];
    $old_wh  = $arrival['warehouse_id'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE wheat_arrivals SET date=?, vehicle_no=?, warehouse_id=?, bag_type_id=?, num_bags=?, gross_weight=?, bag_weight=?, net_weight=?, actual_weight=?, weight_slip_no=?, weight_diff=?, katt_applied=?, moisture_pct=?, gross_amount=?, bag_amount=?, labour_charges=?, transport_charges=?, other_charges=?, net_amount=?, driver_id=?, broker_id=?, notes=? WHERE id=?");
        $stmt->bind_param("ssiiidddsdddddddddiisi", $date, $vehicle_no, $warehouse_id, $bag_type_id, $num_bags, $actual_weight, $bag_weight, $net_weight, $actual_weight, $weight_slip_no, $weight_diff, $katt_applied, $moisture_pct, $gross_amount, $bag_amount, $labour_charges, $transport_charges, $other_charges, $net_amount, $driver_id, $broker_id, $notes, $id);
        $stmt->execute();

        // Adjust warehouse stock
        $wheat = $conn->query("SELECT id FROM products WHERE name = 'Wheat (Gandam)' LIMIT 1")->fetch_assoc();
        if ($wheat) {
            $pid = $wheat['id'];
            if ($old_wh > 0 && $old_net > 0) {
                $conn->query("UPDATE warehouse_stock SET stock_qty = GREATEST(stock_qty - $old_net, 0) WHERE warehouse_id = $old_wh AND product_id = $pid");
            }
            if ($warehouse_id > 0 && $net_weight > 0) {
                $conn->query("INSERT INTO warehouse_stock (warehouse_id, product_id, stock_qty) VALUES ($warehouse_id, $pid, $net_weight)
                    ON DUPLICATE KEY UPDATE stock_qty = stock_qty + $net_weight");
            }
            $conn->query("INSERT INTO stock_ledger (product_id, warehouse_id, type, ref_id, date, qty_in, qty_out, balance, description)
                VALUES ($pid, $warehouse_id, 'arrival_edit', $id, '$date', $net_weight, 0, 0, 'Arrival #$id edited')");
        }

        $conn->commit();
        setFlash("Arrival updated.");
        header("Location: list.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

// Get driver name if set
$driver_name = '';
$driver_id_val = $arrival['driver_id'];
if ($driver_id_val > 0) {
    $dr = $conn->query("SELECT name FROM drivers WHERE id = $driver_id_val")->fetch_assoc();
    $driver_name = $dr ? $dr['name'] : '';
}

$warehouses = $conn->query("SELECT id, name FROM warehouses WHERE status='active' AND type='wheat' ORDER BY name");
$bag_types = $conn->query("SELECT id, name, bag_weight_kg FROM bag_types WHERE status='active' ORDER BY name");
$brokers = $conn->query("SELECT id, name FROM brokers WHERE status='active' ORDER BY name");

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-edit mr-1"></i> Edit Arrival #<?= $id ?></h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Arrival Register</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Arrival Details</h6></div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?= $arrival['date'] ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Vehicle No.</label>
                        <input type="text" name="vehicle_no" class="form-control" value="<?= htmlspecialchars($arrival['vehicle_no'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Warehouse <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-control" required>
                            <option value="">Select</option>
                            <?php while ($w = $warehouses->fetch_assoc()): ?>
                            <option value="<?= $w['id'] ?>" <?= $w['id'] == $arrival['warehouse_id'] ? 'selected' : '' ?>><?= htmlspecialchars($w['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Broker</label>
                        <select name="broker_id" class="form-control">
                            <option value="">Select</option>
                            <?php $brokers->data_seek(0); while ($br = $brokers->fetch_assoc()): ?>
                            <option value="<?= $br['id'] ?>" <?= $br['id'] == $arrival['broker_id'] ? 'selected' : '' ?>><?= htmlspecialchars($br['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Bag Type</label>
                        <select name="bag_type_id" class="form-control">
                            <option value="">Select</option>
                            <?php $bag_types->data_seek(0); while ($b = $bag_types->fetch_assoc()): ?>
                            <option value="<?= $b['id'] ?>" <?= $b['id'] == $arrival['bag_type_id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Driver</label>
                        <input type="hidden" name="driver_id" value="<?= $arrival['driver_id'] ?>">
                        <input type="text" class="form-control" value="<?= htmlspecialchars($driver_name) ?>" readonly style="background:#f5f5f5">
                    </div>
                </div>
            </div>

            <div class="card bg-light mb-3">
                <div class="card-header"><strong>Weight & Financial</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Bags <span class="text-danger">*</span></label>
                                <input type="number" name="num_bags" class="form-control" value="<?= $arrival['num_bags'] ?>" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Actual Weight</label>
                                <input type="text" name="actual_weight" class="form-control" value="<?= qty($arrival['actual_weight']) ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Weight Slip No.</label>
                                <input type="text" name="weight_slip_no" class="form-control" value="<?= htmlspecialchars($arrival['weight_slip_no'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Net Weight</label>
                                <input type="text" name="net_weight" class="form-control" value="<?= qty($arrival['net_weight']) ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Weight Diff</label>
                                <input type="text" name="weight_diff" class="form-control" value="<?= qty($arrival['weight_diff']) ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Katt Applied</label>
                                <input type="text" name="katt_applied" class="form-control" value="<?= qty($arrival['katt_applied']) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Moisture %</label>
                                <input type="text" name="moisture_pct" class="form-control" value="<?= $arrival['moisture_pct'] ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Gross Amount</label>
                                <input type="text" name="gross_amount" class="form-control" value="<?= money($arrival['gross_amount']) ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Bag Amount</label>
                                <input type="text" name="bag_amount" class="form-control" value="<?= money($arrival['bag_amount']) ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Labour</label>
                                <input type="text" name="labour_charges" class="form-control" value="<?= money($arrival['labour_charges']) ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Transport</label>
                                <input type="text" name="transport_charges" class="form-control" value="<?= money($arrival['transport_charges']) ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Other Charges</label>
                                <input type="text" name="other_charges" class="form-control" value="<?= money($arrival['other_charges']) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Net Amount</label>
                                <input type="text" name="net_amount" class="form-control" value="<?= money($arrival['net_amount']) ?>" readonly style="background:#e8f5e9;font-weight:bold">
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($arrival['notes']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Update Arrival</button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
