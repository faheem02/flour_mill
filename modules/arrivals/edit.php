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
    $date = $_POST['date'];
    $warehouse_id = (int)$_POST['warehouse_id'];
    $bag_type_id = (int)$_POST['bag_type_id'];
    $num_bags = (int)$_POST['num_bags'];
    $gross_weight = str_replace(',', '', $_POST['gross_weight']);
    $bag_weight = str_replace(',', '', $_POST['bag_weight']);
    $moisture_pct = str_replace(',', '', $_POST['moisture_pct']);
    $quality_grade = sanitize($_POST['quality_grade']);
    $broker_id = (int)$_POST['broker_id'];
    $notes = sanitize($_POST['notes']);

    $total_bag_weight = $num_bags * $bag_weight;
    $net_weight = $gross_weight - $total_bag_weight;

    $old_net = $arrival['net_weight'];
    $old_wh = $arrival['warehouse_id'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE wheat_arrivals SET date=?, warehouse_id=?, bag_type_id=?, num_bags=?, gross_weight=?, bag_weight=?, net_weight=?, moisture_pct=?, quality_grade=?, broker_id=?, notes=? WHERE id=?");
        $stmt->bind_param("siiiidddssi", $date, $warehouse_id, $bag_type_id, $num_bags, $gross_weight, $bag_weight, $net_weight, $moisture_pct, $quality_grade, $broker_id, $notes, $id);
        $stmt->execute();

        // Adjust warehouse stock
        $wheat = $conn->query("SELECT id FROM products WHERE name = 'Wheat (Gandam)' LIMIT 1")->fetch_assoc();
        if ($wheat) {
            $pid = $wheat['id'];

            // Remove old stock from old warehouse
            if ($old_wh > 0 && $old_net > 0) {
                $conn->query("UPDATE warehouse_stock SET stock_qty = GREATEST(stock_qty - $old_net, 0) WHERE warehouse_id = $old_wh AND product_id = $pid");
            }

            // Add new stock to new warehouse
            if ($warehouse_id > 0 && $net_weight > 0) {
                $conn->query("INSERT INTO warehouse_stock (warehouse_id, product_id, stock_qty) VALUES ($warehouse_id, $pid, $net_weight)
                    ON DUPLICATE KEY UPDATE stock_qty = stock_qty + $net_weight");
            }

            // Stock ledger entry
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
                        <label>Quality Grade</label>
                        <input type="text" name="quality_grade" class="form-control" value="<?= htmlspecialchars($arrival['quality_grade']) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Bag Type</label>
                        <select name="bag_type_id" class="form-control" onchange="setBagWeight(this)">
                            <option value="">Select</option>
                            <?php $bag_types->data_seek(0); while ($b = $bag_types->fetch_assoc()): ?>
                            <option value="<?= $b['id'] ?>" data-weight="<?= $b['bag_weight_kg'] ?>" <?= $b['id'] == $arrival['bag_type_id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?> (<?= qty($b['bag_weight_kg']) ?> KG)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card bg-light mb-3">
                <div class="card-header"><strong>Weight</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Bags <span class="text-danger">*</span></label>
                                <input type="number" name="num_bags" class="form-control" value="<?= $arrival['num_bags'] ?>" required oninput="calcWeights()">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Gross (KG) <span class="text-danger">*</span></label>
                                <input type="text" name="gross_weight" class="form-control" value="<?= qty($arrival['gross_weight']) ?>" required oninput="calcWeights()">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Bag Weight (KG)</label>
                                <input type="text" name="bag_weight" class="form-control" value="<?= qty($arrival['bag_weight']) ?>" readonly style="background:#f5f5f5">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Total Bag Weight</label>
                                <input type="text" id="total_bag_weight" class="form-control" value="<?= qty($arrival['num_bags'] * $arrival['bag_weight']) ?>" readonly style="background:#f5f5f5">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Net (KG)</label>
                                <input type="text" id="net_weight" class="form-control" value="<?= qty($arrival['net_weight']) ?>" readonly style="background:#e8f5e9;font-weight:bold">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Moisture %</label>
                                <input type="text" name="moisture_pct" class="form-control" value="<?= $arrival['moisture_pct'] ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
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
                <div class="col-md-8">
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($arrival['notes']) ?></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Update Arrival</button>
        </form>
    </div>
</div>

<script>
function setBagWeight(sel) {
    var w = $(sel).find(':selected').data('weight') || 0;
    $('input[name="bag_weight"]').val(w);
    calcWeights();
}
function calcWeights() {
    var bags = parseInt($('input[name="num_bags"]').val()) || 0;
    var gross = parseFloat($('input[name="gross_weight"]').val()) || 0;
    var bagW = parseFloat($('input[name="bag_weight"]').val()) || 0;
    var totalBagW = bags * bagW;
    $('#total_bag_weight').val(totalBagW.toFixed(3));
    $('#net_weight').val((gross - totalBagW).toFixed(3));
}
</script>

<?php include '../../includes/footer.php'; ?>
