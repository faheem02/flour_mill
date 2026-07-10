<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'arrival_add';
$page_title = 'New Wheat Arrival';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date           = $_POST['date'];
    $booking_id     = (int)$_POST['booking_id'];
    $vehicle_no     = sanitize($_POST['vehicle_no']);
    $driver_id      = (int)$_POST['driver_id'];
    $warehouse_id   = (int)$_POST['warehouse_id'];
    $bag_type_id    = (int)$_POST['bag_type_id'];
    $num_bags       = (int)str_replace(',', '', $_POST['num_bags']);
    $wheat_kg       = str_replace(',', '', $_POST['wheat_kg']);
    $katt_applied   = str_replace(',', '', $_POST['katt_applied']);
    $net_weight     = str_replace(',', '', $_POST['net_weight']);
    $actual_weight  = str_replace(',', '', $_POST['actual_weight']);
    $weight_slip_no = sanitize($_POST['weight_slip_no']);
    $weight_diff    = str_replace(',', '', $_POST['weight_diff']);
    $moisture_pct   = str_replace(',', '', $_POST['moisture_pct']);
    $gross_amount   = str_replace(',', '', $_POST['gross_amount']);
    $bag_amount     = str_replace(',', '', $_POST['bag_amount']);
    $labour_charges = str_replace(',', '', $_POST['labour_charges']);
    $transport_charges = str_replace(',', '', $_POST['transport_charges']);
    $other_charges  = str_replace(',', '', $_POST['other_charges']);
    $net_amount     = str_replace(',', '', $_POST['net_amount']);
    $broker_id      = (int)$_POST['broker_id'];
    $notes          = sanitize($_POST['notes']);

    $bag_weight     = 0;
    if ($bag_type_id > 0) {
        $bt = $conn->query("SELECT bag_weight_kg FROM bag_types WHERE id = $bag_type_id")->fetch_assoc();
        $bag_weight = $bt ? $bt['bag_weight_kg'] : 0;
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO wheat_arrivals (booking_id, date, vehicle_no, warehouse_id, bag_type_id, num_bags, gross_weight, bag_weight, net_weight, actual_weight, weight_slip_no, weight_diff, katt_applied, moisture_pct, gross_amount, bag_amount, labour_charges, transport_charges, other_charges, net_amount, driver_id, broker_id, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issiiiddddsdddddddddiis", $booking_id, $date, $vehicle_no, $warehouse_id, $bag_type_id, $num_bags, $wheat_kg, $bag_weight, $net_weight, $actual_weight, $weight_slip_no, $weight_diff, $katt_applied, $moisture_pct, $gross_amount, $bag_amount, $labour_charges, $transport_charges, $other_charges, $net_amount, $driver_id, $broker_id, $notes);
        $stmt->execute();
        $arrival_id = $conn->insert_id;

        // Stock: use net_weight (wheat + katt) for warehouse
        if ($warehouse_id > 0 && $net_weight > 0) {
            $wheat = $conn->query("SELECT id FROM products WHERE name = 'Wheat (Gandam)' LIMIT 1");
            if ($wheat_row = $wheat->fetch_assoc()) {
                $product_id = $wheat_row['id'];
                $conn->query("INSERT INTO warehouse_stock (warehouse_id, product_id, stock_qty)
                    VALUES ($warehouse_id, $product_id, $net_weight)
                    ON DUPLICATE KEY UPDATE stock_qty = stock_qty + $net_weight");
                $conn->query("INSERT INTO stock_ledger (product_id, warehouse_id, type, ref_id, date, qty_in, qty_out, balance, description)
                    VALUES ($product_id, $warehouse_id, 'arrival', $arrival_id, '$date', $net_weight, 0, 0, 'Wheat arrival - $vehicle_no')");
            }
        }

        // Update booking received_qty
        if ($booking_id > 0 && $net_weight > 0) {
            $conn->query("UPDATE bookings SET received_qty = received_qty + $net_weight WHERE id = $booking_id");
            $conn->query("UPDATE bookings SET status = 'partial' WHERE id = $booking_id AND received_qty > 0 AND received_qty < booked_qty AND status != 'completed'");
            $conn->query("UPDATE bookings SET status = 'completed' WHERE id = $booking_id AND received_qty >= booked_qty");
        }

        // Farmer ledger entry for net amount
        if ($booking_id > 0 && $net_amount > 0) {
            $bk = $conn->query("SELECT farmer_id, booking_no FROM bookings WHERE id = $booking_id")->fetch_assoc();
            if ($bk) {
                $farmer_id = $bk['farmer_id'];
                $booking_no = $bk['booking_no'];
                $conn->query("INSERT INTO farmer_payments (farmer_id, date, amount, type, payment_mode, booking_id, notes)
                    VALUES ($farmer_id, '$date', $net_amount, 'payment', 'cash', $booking_id, 'Arrival payment - Net Amount for $booking_no')");
                $conn->query("UPDATE farmers SET balance = balance + $net_amount WHERE id = $farmer_id");
            }
        }

        $conn->commit();
        $_SESSION['flash'] = "Arrival recorded. Net Weight: " . qty($net_weight) . " KG";
        header("Location: list.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

include '../../includes/header.php';

$bookings = $conn->query("SELECT b.id, b.booking_no, b.moisture_percent, b.katt_per_bag, b.rate, f.name AS sname FROM bookings b JOIN farmers f ON b.farmer_id = f.id WHERE b.status IN ('pending','partial') ORDER BY b.booking_no DESC");
$warehouses = $conn->query("SELECT id, name FROM warehouses WHERE status='active' AND type='wheat' ORDER BY name");
$bag_types = $conn->query("SELECT id, name, bag_weight_kg FROM bag_types WHERE status='active' ORDER BY name");
$brokers = $conn->query("SELECT id, name FROM brokers WHERE status='active' ORDER BY name");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-truck-loading mr-1"></i> New Wheat Arrival</h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Arrival Register</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

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
            <!-- ROW 1: Booking + Vehicle + Driver -->
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Booking <span class="text-danger">*</span></label>
                        <select name="booking_id" id="bookingId" class="form-control" required>
                            <option value="">— Select Booking —</option>
                            <?php while ($bk = $bookings->fetch_assoc()): ?>
                            <option value="<?= $bk['id'] ?>" data-moisture="<?= $bk['moisture_percent'] ?>" data-katt="<?= $bk['katt_per_bag'] ?>" data-rate="<?= $bk['rate'] ?>"><?= htmlspecialchars($bk['booking_no']) ?> — <?= htmlspecialchars($bk['sname']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-5">
                    <div id="bookingInfo" class="p-2 border rounded bg-light" style="display:none;font-size:13px;">
                        <div class="row">
                            <div class="col-4"><span class="text-muted">Rate/Man:</span> <strong id="biRate"></strong></div>
                            <div class="col-4"><span class="text-muted">Moisture:</span> <strong id="biMoisture"></strong></div>
                            <div class="col-4"><span class="text-muted">Katt/Bag:</span> <strong id="biKatt"></strong></div>
                            <div class="col-4"><span class="text-muted">Bags:</span> <strong id="biBags"></strong></div>
                            <div class="col-4"><span class="text-muted">Booked Qty:</span> <strong id="biBookedQty"></strong></div>
                            <div class="col-4"><span class="text-muted">Bags Own:</span> <strong id="biBagOwn"></strong></div>
                            <div class="col-4"><span class="text-muted">Bag Rate:</span> <strong id="biBagRate"></strong></div>
                            <div class="col-4"><span class="text-muted">Farmer:</span> <strong id="biFarmer"></strong></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ROW 2: Vehicle + Driver -->
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Vehicle No.</label>
                        <input type="text" name="vehicle_no" class="form-control" placeholder="e.g. LEH-1234">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group has-search">
                        <label>Driver Name</label>
                        <input type="text" id="driverSearch" class="form-control" placeholder="Search driver..." autocomplete="off">
                        <input type="hidden" name="driver_id" id="driverId" value="0">
                        <div id="driverResults" class="dropdown-menu" style="width:100%; max-height:200px; overflow-y:auto; display:none;"></div>
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

            <hr>
            <h5 class="text-primary mb-3"><i class="fas fa-weight mr-1"></i> Weight & Calculation</h5>

            <!-- ROW 3: Bag & Auto Weight -->
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Bag Type</label>
                        <select name="bag_type_id" id="bagTypeId" class="form-control">
                            <option value="">Select</option>
                            <?php $bag_types->data_seek(0); while ($b = $bag_types->fetch_assoc()): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Bag Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="num_bags" id="numBags" class="form-control" min="0" required oninput="calcAuto()">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Wheat KG</label>
                        <input type="text" id="wheatKg" class="form-control" value="0" readonly style="background:#f5f5f5">
                        <input type="hidden" name="wheat_kg" id="wheatKgHidden">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Katt KG</label>
                        <input type="text" name="katt_applied" id="kattApplied" class="form-control" value="0" readonly style="background:#f5f5f5">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Net Weight (KG)</label>
                        <input type="text" id="netWeightDisplay" class="form-control" value="0" readonly style="background:#f5f5f5;font-weight:bold">
                        <input type="hidden" name="net_weight" id="netWeightHidden">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Moisture %</label>
                        <input type="text" name="moisture_pct" id="moisturePct" class="form-control" placeholder="0">
                    </div>
                </div>
            </div>

            <!-- ROW 4: Actual Weight -->
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Actual Weight (KG)</label>
                        <input type="text" name="actual_weight" id="actualWeight" class="form-control" placeholder="0" oninput="calcActual()">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Weight Slip No.</label>
                        <input type="text" name="weight_slip_no" class="form-control" placeholder="Slip #">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Weight Diff (KG)</label>
                        <input type="text" id="weightDiff" class="form-control" value="0" readonly style="background:#f5f5f5">
                        <input type="hidden" name="weight_diff" id="weightDiffHidden">
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

            <hr>
            <h5 class="text-primary mb-3"><i class="fas fa-money-bill-wave mr-1"></i> Financial</h5>

            <!-- ROW 5: Financial -->
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Gross Amount</label>
                        <input type="text" name="gross_amount" id="grossAmount" class="form-control" placeholder="0.00" readonly style="background:#f5f5f5">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Bag Amount</label>
                        <input type="text" name="bag_amount" id="bagAmount" class="form-control" placeholder="0.00" oninput="calcNetAmount()">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Labour Charges</label>
                        <input type="text" name="labour_charges" id="labourCharges" class="form-control" placeholder="0.00" oninput="calcNetAmount()">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Transport Charges</label>
                        <input type="text" name="transport_charges" id="transportCharges" class="form-control" placeholder="0.00" oninput="calcNetAmount()">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Other Charges</label>
                        <input type="text" name="other_charges" id="otherCharges" class="form-control" placeholder="0.00" oninput="calcNetAmount()">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Net Amount</label>
                        <input type="text" id="netAmount" class="form-control" value="0.00" readonly style="background:#e8f5e9;font-weight:bold">
                        <input type="hidden" name="net_amount" id="netAmountHidden">
                    </div>
                </div>
            </div>

            <!-- ROW 6: Notes -->
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

<style>
.form-group.has-search { position: relative; }
#driverResults { position: absolute; top: 100%; left: 0; right: 0; z-index: 1050; background: #fff; border: 1px solid #d1d3e2; border-top: none; border-radius: 0 0 5px 5px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
#driverResults .dropdown-item { cursor: pointer; padding: 8px 14px; font-size: 14px; }
#driverResults .dropdown-item:hover { background: #f8f9fc; }
#driverResults .dropdown-item .text-muted { font-size: 12px; }
</style>

<script>
// === Booking Select → Show Info ===
$('#bookingId').on('change', function() {
    var opt = $(this).find(':selected');
    var id = opt.val();
    if (!id) { $('#bookingInfo').hide(); return; }

    $('#bookingInfo').show();
    $('#biRate').text(opt.data('rate') ? parseFloat(opt.data('rate')).toLocaleString() : '-');
    $('#biMoisture').text(opt.data('moisture') ? opt.data('moisture') + '%' : '-');
    $('#biKatt').text(opt.data('katt') ? parseFloat(opt.data('katt')) + ' KG' : '-');
    $('#biBags').text('Loading...');
    $('#biBookedQty').text('Loading...');
    $('#biBagOwn').text('Loading...');
    $('#biBagRate').text('...');
    $('#biFarmer').text('...');

    $.get('get_booking_json.php?id=' + id, function(data) {
        var bookedQty = parseFloat(data.booked_qty) || 0;
        var bagQty = data.bag ? parseInt(data.bag.quantity) || 0 : 0;
        $('#biBags').text(bagQty);
        $('#biBookedQty').text(bookedQty.toLocaleString() + ' KG');
        $('#biFarmer').text(data.farmer_name || '-');

        if (data.bag) {
            $('#biBagOwn').text(data.bag.ownership === 'farmer' ? 'Farmer' : 'Company');
            var bagRate = parseFloat(data.bag.bag_rate) || 0;
            $('#biBagRate').text(data.bag.ownership === 'farmer' ? bagRate.toLocaleString(undefined, {minimumFractionDigits:2}) : '—');
            if ($('#bagTypeId option').length) {
                $('#bagTypeId').val(data.bag.bag_type_id);
            }
            $('#moisturePct').val(data.moisture_percent || '');
            opt.data('bag-ownership', data.bag.ownership);
            opt.data('bag-rate', parseFloat(data.bag.bag_rate) || 0);
            calcAuto();
        } else {
            $('#biBagOwn').text('-');
            opt.removeData('bag-ownership');
            opt.removeData('bag-rate');
        }
    });
});

// === Auto Calculate: Bag Qty × 50 = Wheat, + Katt = Net ===
function calcAuto() {
    var bagQty = parseFloat($('#numBags').val()) || 0;
    var kattPerBag = 0;
    var opt = $('#bookingId').find(':selected');
    if (opt.val()) {
        kattPerBag = parseFloat(opt.data('katt')) || 0;
    }

    var wheat = bagQty * 50;
    var katt = bagQty * kattPerBag;
    var net = wheat + katt;

    $('#wheatKg').val(wheat.toFixed(3));
    $('#wheatKgHidden').val(wheat.toFixed(3));
    $('#kattApplied').val(katt.toFixed(3));
    $('#netWeightDisplay').val(net.toFixed(3));
    $('#netWeightHidden').val(net.toFixed(3));

    // Auto-calc Bag Amount (only if farmer's bags)
    var bagOwnership = opt.data('bag-ownership');
    var bagRate = parseFloat(opt.data('bag-rate')) || 0;
    var bagAmount = (bagOwnership === 'farmer') ? bagQty * bagRate : 0;
    $('#bagAmount').val(bagAmount.toFixed(2));

    // Re-calc financial
    calcActual();
    calcGrossAmount(net);
}

// === Actual Weight → Diff ===
function calcActual() {
    var net = parseFloat($('#netWeightDisplay').val()) || 0;
    var actual = parseFloat($('#actualWeight').val()) || 0;
    var diff = actual - net;
    $('#weightDiff').val(diff.toFixed(3));
    $('#weightDiffHidden').val(diff.toFixed(3));

    // Use actual weight for gross if provided
    var weight = actual > 0 ? actual : net;
    calcGrossAmount(weight);
}

// === Gross Amount = (Weight KG ÷ 40) × Rate ===
function calcGrossAmount(weight) {
    var opt = $('#bookingId').find(':selected');
    var rate = parseFloat(opt.data('rate')) || 0;
    var mans = weight / 40;
    var gross = mans * rate;
    $('#grossAmount').val(gross.toFixed(2));
    calcNetAmount();
}

// === Net Amount = Gross - Bag - Labour - Transport - Other ===
function calcNetAmount() {
    var gross = parseFloat($('#grossAmount').val()) || 0;
    var bag = parseFloat($('#bagAmount').val()) || 0;
    var labour = parseFloat($('#labourCharges').val()) || 0;
    var transport = parseFloat($('#transportCharges').val()) || 0;
    var other = parseFloat($('#otherCharges').val()) || 0;
    var net = gross - bag - labour - transport - other;
    $('#netAmount').val(net.toFixed(2));
    $('#netAmountHidden').val(net.toFixed(2));
}

// === Driver Autocomplete ===
var driverTimer;
$('#driverSearch').on('input', function() {
    var q = $(this).val();
    clearTimeout(driverTimer);
    if (q.length < 1) {
        $('#driverResults').hide();
        $('#driverId').val(0);
        return;
    }
    driverTimer = setTimeout(function() {
        $.get('get_drivers.php', { q: q }, function(data) {
            var results = $('#driverResults');
            results.empty().show();
            if (data.length === 0) {
                results.append('<div class="dropdown-item text-muted">No drivers found</div>');
                return;
            }
            $.each(data, function(i, d) {
                var label = d.name;
                if (d.mobile) label += ' <small class="text-muted">' + d.mobile + '</small>';
                results.append('<div class="dropdown-item" data-id="' + d.id + '" data-name="' + d.name + '">' + label + '</div>');
            });
        });
    }, 200);
});

$(document).on('click', '#driverResults .dropdown-item', function() {
    $('#driverId').val($(this).data('id'));
    $('#driverSearch').val($(this).data('name'));
    $('#driverResults').hide();
});

$(document).on('click', function(e) {
    if (!$(e.target).closest('.has-search').length) {
        $('#driverResults').hide();
    }
});

// === Init: trigger calc on load ===
$(document).ready(function() {
    calcAuto();
});
</script>

<?php include '../../includes/footer.php'; ?>
