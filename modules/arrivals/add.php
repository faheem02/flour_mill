<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'arrival_add';
$page_title = 'New Wheat Arrival';
require_once '../../includes/db.php';
include '../../includes/header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $booking_id = (int)$_POST['booking_id'];
    $warehouse_id = (int)$_POST['warehouse_id'];
    $bag_type_id = (int)$_POST['bag_type_id'];
    $num_bags = (int)$_POST['num_bags'];
    $gross_weight = str_replace(',', '', $_POST['gross_weight']);
    $bag_weight = str_replace(',', '', $_POST['bag_weight']);
    $moisture_pct = str_replace(',', '', $_POST['moisture_pct']);
    $broker_id = (int)$_POST['broker_id'];
    $notes = sanitize($_POST['notes']);

    $total_bag_weight = $num_bags * $bag_weight;
    $net_weight = $gross_weight - $total_bag_weight;

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO wheat_arrivals (booking_id, date, warehouse_id, bag_type_id, num_bags, gross_weight, bag_weight, net_weight, moisture_pct, broker_id, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isiiiddddis", $booking_id, $date, $warehouse_id, $bag_type_id, $num_bags, $gross_weight, $bag_weight, $net_weight, $moisture_pct, $broker_id, $notes);
        $stmt->execute();
        $arrival_id = $conn->insert_id;

        // Update warehouse stock for wheat
        if ($warehouse_id > 0 && $net_weight > 0) {
            $wheat = $conn->query("SELECT id FROM products WHERE name = 'Wheat (Gandam)' LIMIT 1");
            if ($wheat_row = $wheat->fetch_assoc()) {
                $product_id = $wheat_row['id'];
                $conn->query("INSERT INTO warehouse_stock (warehouse_id, product_id, stock_qty)
                    VALUES ($warehouse_id, $product_id, $net_weight)
                    ON DUPLICATE KEY UPDATE stock_qty = stock_qty + $net_weight");
                $conn->query("INSERT INTO stock_ledger (product_id, warehouse_id, type, ref_id, date, qty_in, qty_out, balance, description)
                    VALUES ($product_id, $warehouse_id, 'arrival', $arrival_id, '$date', $net_weight, 0, 0, 'Wheat arrival - warehouse')");
            }
        }

        // Update booking received qty
        if ($booking_id > 0 && $net_weight > 0) {
            $conn->query("UPDATE bookings SET received_qty = received_qty + $net_weight WHERE id = $booking_id");
            $conn->query("UPDATE bookings SET status = 'partial' WHERE id = $booking_id AND received_qty > 0 AND received_qty < booked_qty AND status != 'completed'");
            $conn->query("UPDATE bookings SET status = 'completed' WHERE id = $booking_id AND received_qty >= booked_qty");
        }

        $conn->commit();
        $success = "Wheat arrival recorded successfully. Net Weight: " . qty($net_weight) . " KG";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

$warehouses = $conn->query("SELECT id, name FROM warehouses WHERE status='active' AND type='wheat' ORDER BY name");
$bag_types = $conn->query("SELECT id, name, bag_weight_kg FROM bag_types WHERE status='active' ORDER BY name");
$brokers = $conn->query("SELECT id, name FROM brokers WHERE status='active' ORDER BY name");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-truck-loading mr-1"></i> New Wheat Arrival</h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Arrival Register</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<?php
$wh_stock = $conn->query("SELECT w.name, COALESCE(ws.stock_qty,0) as qty
    FROM warehouses w
    LEFT JOIN warehouse_stock ws ON w.id=ws.warehouse_id AND ws.product_id=(SELECT id FROM products WHERE name='Wheat (Gandam)' LIMIT 1)
    WHERE w.status='active' AND w.type='wheat'
    ORDER BY w.name");
if ($wh_stock && $wh_stock->num_rows > 0):
?>
<div class="card bg-light mb-3">
    <div class="card-header py-2"><small class="font-weight-bold text-muted"><i class="fas fa-boxes mr-1"></i> Current Raw Material Stock</small></div>
    <div class="card-body py-2">
        <div class="row">
            <?php $total_wheat = 0; while ($s = $wh_stock->fetch_assoc()): $total_wheat += $s['qty']; ?>
            <div class="col-md-3 col-6 text-center">
                <small class="text-muted d-block"><?= htmlspecialchars($s['name']) ?></small>
                <strong class="h5 text-success"><?= qty($s['qty']) ?> KG</strong>
            </div>
            <?php endwhile; ?>
            <div class="col-md-3 col-6 text-center border-left">
                <small class="text-muted d-block">Total Wheat</small>
                <strong class="h5 text-primary"><?= qty($total_wheat) ?> KG</strong>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Arrival Details</h6></div>
    <div class="card-body">
        <form method="POST" id="arrivalForm">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Booking (Optional)</label>
                        <select name="booking_id" class="form-control">
                            <option value="">— Direct Arrival —</option>
                            <?php
                            $bookings = $conn->query("SELECT b.id, b.booking_no, f.name AS sname FROM bookings b JOIN farmers f ON b.farmer_id = f.id WHERE b.status IN ('pending','partial') ORDER BY b.booking_no DESC");
                            while ($bk = $bookings->fetch_assoc()):
                            ?>
                            <option value="<?= $bk['id'] ?>"><?= htmlspecialchars($bk['booking_no']) ?> — <?= htmlspecialchars($bk['sname'] ?? '') ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Warehouse <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-control" required>
                            <option value="">Select Warehouse</option>
                            <?php while ($w = $warehouses->fetch_assoc()): ?>
                            <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Bag Type</label>
                        <select name="bag_type_id" class="form-control" onchange="setBagWeight(this)">
                            <option value="">Select Bag Type</option>
                            <?php $bag_types->data_seek(0); while ($b = $bag_types->fetch_assoc()): ?>
                            <option value="<?= $b['id'] ?>" data-weight="<?= $b['bag_weight_kg'] ?>"><?= htmlspecialchars($b['name']) ?> (<?= qty($b['bag_weight_kg']) ?> KG)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Broker</label>
                        <select name="broker_id" class="form-control">
                            <option value="">Select Broker</option>
                            <?php $brokers->data_seek(0); while ($br = $brokers->fetch_assoc()): ?>
                            <option value="<?= $br['id'] ?>"><?= htmlspecialchars($br['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card bg-light mb-3">
                <div class="card-header"><strong>Weight & Quality</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>No. of Bags <span class="text-danger">*</span></label>
                                <input type="number" name="num_bags" class="form-control" min="0" required oninput="calcWeights()">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Gross Weight (KG) <span class="text-danger">*</span></label>
                                <input type="text" name="gross_weight" class="form-control" placeholder="0" required oninput="calcWeights()">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Bag Weight (KG)</label>
                                <input type="text" name="bag_weight" class="form-control" placeholder="0" readonly style="background:#f5f5f5" oninput="calcWeights()">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Total Bag Weight</label>
                                <input type="text" id="total_bag_weight" class="form-control" placeholder="0" readonly style="background:#f5f5f5">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Net Weight (KG)</label>
                                <input type="text" id="net_weight" class="form-control" placeholder="0" readonly style="background:#e8f5e9;font-weight:bold">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Moisture %</label>
                                <input type="text" name="moisture_pct" class="form-control" placeholder="0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Arrival</button>
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
