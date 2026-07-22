<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'booking_add';
$page_title = 'New Booking';
require_once '../../includes/db.php';
include '../../includes/header.php';

$warehouses = $conn->query("SELECT id, name FROM warehouses WHERE status='active' ORDER BY name");

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $farmer_id      = (int)$_POST['farmer_id'];
    $date           = $_POST['date'];
    $expected_date  = $_POST['expected_date'] ?: null;
    $advance_amount = str_replace(',', '', $_POST['advance_amount']);
    $notes          = sanitize($_POST['notes']);
    $delivery_type  = $_POST['delivery_type'] ?? 'pickup';

    $bag_qty        = (int)str_replace(',', '', $_POST['bag_qty']);
    $bag_capacity   = 50;
    $ownership      = $_POST['ownership'] ?? 'company';
    $bag_action     = $_POST['bag_action'] ?? 'return';
    $bag_rate       = str_replace(',', '', $_POST['bag_rate'] ?? 0);

    // Auto-select first active bag type
    $bt_row = $conn->query("SELECT id FROM bag_types WHERE status='active' LIMIT 1")->fetch_assoc();
    $bag_type_id = $bt_row ? $bt_row['id'] : 0;

    $moisture       = str_replace(',', '', $_POST['moisture_percent'] ?? 0);
    $katt_per_bag   = str_replace(',', '', $_POST['katt_per_bag'] ?? 0);
    $rate_per_man   = str_replace(',', '', $_POST['rate_per_man'] ?? 0);

    if ($farmer_id <= 0) { $error = "Please select a valid farmer."; }
    if ($bag_type_id <= 0 || $bag_qty <= 0) { $error = "Please select bag type and enter quantity."; }
    if ($ownership === 'farmer' && $bag_action === 'purchase' && $bag_rate <= 0) { $error = "Please enter bag purchase rate."; }

    if (!$error) {
        $farmer_wheat   = $bag_qty * $bag_capacity;
        $katt_total     = $bag_qty * $katt_per_bag;
        // booked_qty stores only wheat (bag_qty × 50); katt shown separately on listing/view pages
        $booked_qty     = $farmer_wheat;
        $mans           = $farmer_wheat / 40;

        $conn->begin_transaction();
        try {
            $booking_no = generateBookingNo();
            $stmt = $conn->prepare("INSERT INTO bookings (booking_no, farmer_id, date, booked_qty, rate, advance_amount, moisture_percent, katt_per_bag, expected_date, notes, delivery_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisddddssss", $booking_no, $farmer_id, $date, $booked_qty, $rate_per_man, $advance_amount, $moisture, $katt_per_bag, $expected_date, $notes, $delivery_type);
            $stmt->execute();
            $booking_id = $conn->insert_id;

            $stmt2 = $conn->prepare("INSERT INTO booking_bags (booking_id, bag_type_id, quantity, bag_capacity_kg, ownership, bag_action, bag_rate) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt2->bind_param("iiidsds", $booking_id, $bag_type_id, $bag_qty, $bag_capacity, $ownership, $bag_action, $bag_rate);
            $stmt2->execute();

            if ($advance_amount > 0) {
                $farmer_name = $conn->query("SELECT name FROM farmers WHERE id=$farmer_id")->fetch_row()[0];
                $conn->query("INSERT INTO farmer_payments (farmer_id, date, amount, type, payment_mode, booking_id, notes)
                    VALUES ($farmer_id, '$date', $advance_amount, 'advance', 'cash', $booking_id, 'Advance against $booking_no')");
                $conn->query("UPDATE farmers SET balance = balance - $advance_amount WHERE id = $farmer_id");

                $desc = "Advance payment to farmer - $farmer_name (Booking #$booking_no)";
                autoJournalEntry($date, $desc, [17 => $advance_amount], [2 => $advance_amount], $_SESSION['user_id']);
            }

            $conn->commit();
            $success = "Booking <strong>$booking_no</strong> created successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-signature mr-1"></i> New Booking</h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-list mr-1"></i> Booking List</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Booking Details</h6></div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group has-search">
                        <label>Farmer <span class="text-danger">*</span></label>
                        <input type="text" id="farmerSearch" class="form-control" placeholder="Search farmer..." autocomplete="off" required>
                        <input type="hidden" name="farmer_id" id="farmerId" value="">
                        <div id="farmerResults" class="dropdown-menu" style="width:100%; max-height:200px; overflow-y:auto; display:none;"></div>
                        <small><a href="#" data-toggle="modal" data-target="#newFarmerModal">+ Add New Farmer</a></small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Expected Delivery Date</label>
                        <input type="date" name="expected_date" class="form-control">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Delivery Type <span class="text-danger">*</span></label><br>
                        <div class="btn-group btn-group-toggle" data-toggle="buttons">
                            <label class="btn btn-outline-primary btn-sm active">
                                <input type="radio" name="delivery_type" value="pickup" checked> Farmer Sends
                            </label>
                            <label class="btn btn-outline-primary btn-sm">
                                <input type="radio" name="delivery_type" value="delivery"> We Pickup
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <hr>
            <h5 class="text-primary mb-3"><i class="fas fa-shopping-bag mr-1"></i> Bag Details</h5>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Bag Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="bag_qty" id="bagQty" class="form-control" placeholder="0" min="1" required>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Bag Capacity</label>
                        <input type="text" class="form-control" value="50 KG" readonly style="background:#f5f5f5">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Farmer's Wheat (KG)</label>
                        <input type="text" id="farmerWheat" class="form-control" value="0" readonly style="background:#f5f5f5">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Bag Ownership</label><br>
                        <div class="btn-group btn-group-toggle" data-toggle="buttons">
                            <label class="btn btn-outline-primary btn-sm active">
                                <input type="radio" name="ownership" value="company" checked> Company Bags
                            </label>
                            <label class="btn btn-outline-primary btn-sm">
                                <input type="radio" name="ownership" value="farmer"> Farmer Bags
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row" id="bagActionRow" style="display:none;">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Bag Action <span class="text-danger">*</span></label><br>
                        <div class="btn-group btn-group-toggle" data-toggle="buttons">
                            <label class="btn btn-outline-success btn-sm active">
                                <input type="radio" name="bag_action" value="return" checked> Return After Use
                            </label>
                            <label class="btn btn-outline-warning btn-sm">
                                <input type="radio" name="bag_action" value="purchase"> Purchase from Farmer
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row" id="bagRateRow" style="display:none;">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Bag Purchase Rate (per bag) <span class="text-danger">*</span></label>
                        <input type="text" name="bag_rate" class="form-control" placeholder="0.00">
                    </div>
                </div>
            </div>

            <hr>
            <h5 class="text-primary mb-3"><i class="fas fa-flask mr-1"></i> Quality & Pricing</h5>
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Moisture %</label>
                        <input type="number" name="moisture_percent" class="form-control" placeholder="0" step="0.1" min="0" max="100">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Katt per Bag (KG)</label>
                        <input type="number" name="katt_per_bag" id="kattPerBag" class="form-control" placeholder="0" step="0.001" min="0">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Rate per Man (40 KG)</label>
                        <input type="text" name="rate_per_man" id="ratePerMan" class="form-control" placeholder="0.00">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Katt Total (KG)</label>
                        <input type="text" id="kattTotal" class="form-control" value="0" readonly style="background:#f5f5f5">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Net Qty (KG)</label>
                        <input type="text" id="netQty" class="form-control" value="0" readonly style="background:#f5f5f5">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Mans</label>
                        <input type="text" id="mans" class="form-control" value="0" readonly style="background:#f5f5f5">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Wheat Value</label>
                        <input type="text" id="wheatValue" class="form-control" placeholder="0.00" readonly style="background:#f5f5f5">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Grand Total</label>
                        <input type="text" id="estimatedValue" class="form-control" placeholder="0.00" readonly style="background:#f5f5f5">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Advance Amount</label>
                        <input type="text" name="advance_amount" class="form-control" placeholder="0.00">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" id="saveBtn"><i class="fas fa-save mr-1"></i> Save Booking</button>
        </form>
    </div>
</div>

<div class="modal fade" id="newFarmerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Farmer</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Name <span class="text-danger">*</span></label>
                    <input type="text" id="nfName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" id="nfPhone" class="form-control">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" id="nfVillage" class="form-control">
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" id="nfCity" class="form-control">
                </div>
                <div id="nfError" class="alert alert-danger" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="nfSaveBtn"><i class="fas fa-save mr-1"></i> Save</button>
            </div>
        </div>
    </div>
</div>

<style>
.form-group.has-search { position: relative; }
#farmerResults { position: absolute; top: 100%; left: 0; right: 0; z-index: 1050; background: #fff; border: 1px solid #d1d3e2; border-top: none; border-radius: 0 0 5px 5px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
#farmerResults .dropdown-item { cursor: pointer; padding: 8px 14px; font-size: 14px; }
#farmerResults .dropdown-item:hover { background: #f8f9fc; }
#farmerResults .dropdown-item .text-muted { font-size: 12px; }
.btn-group-toggle .btn { font-size: 12px; padding: 6px 12px !important; }
</style>

<script>
// === Form Validation ===
$('form').on('submit', function() {
    if (!$('#farmerId').val()) {
        alert('Please select a valid farmer from the search results.');
        $('#farmerSearch').focus();
        return false;
    }
});

// === Bag Ownership Toggle ===
$('input[name="ownership"]').on('change', function() {
    if ($(this).val() === 'farmer') {
        $('#bagActionRow').slideDown();
        // Reset to default action
        $('input[name="bag_action"][value="return"]').prop('checked', true).parent().addClass('active').siblings().removeClass('active');
        $('#bagRateRow').slideUp();
        $('input[name="bag_rate"]').val('');
    } else {
        $('#bagActionRow').slideUp();
        $('#bagRateRow').slideUp();
        $('input[name="bag_rate"]').val('');
    }
});

// === Bag Action Toggle (Return vs Purchase) ===
$('input[name="bag_action"]').on('change', function() {
    if ($(this).val() === 'purchase') {
        $('#bagRateRow').slideDown();
    } else {
        $('#bagRateRow').slideUp();
        $('input[name="bag_rate"]').val('');
    }
    calcBooking();
});

// === Auto Calculations ===
function calcBooking() {
    var bagQty = parseFloat($('#bagQty').val()) || 0;
    var katt = parseFloat($('#kattPerBag').val()) || 0;
    var rate = parseFloat($('#ratePerMan').val().replace(/,/g, '')) || 0;
    var bagRate = parseFloat($('input[name="bag_rate"]').val().replace(/,/g, '')) || 0;
    var bagAction = $('input[name="bag_action"]:checked').val();
    var ownership = $('input[name="ownership"]:checked').val();

    var farmerWheat = bagQty * 50;
    var kattTotal = bagQty * katt;
    var netQty = farmerWheat + kattTotal;
    var mans = farmerWheat / 40;
    var wheatValue = mans * rate;

    var bagPurchaseCost = 0;
    if (ownership === 'farmer' && bagAction === 'purchase' && bagRate > 0) {
        bagPurchaseCost = bagQty * bagRate;
    }
    var totalValue = wheatValue + bagPurchaseCost;

    $('#farmerWheat').val(farmerWheat.toFixed(3));
    $('#kattTotal').val(kattTotal.toFixed(3));
    $('#netQty').val(netQty.toFixed(3));
    $('#mans').val(mans.toFixed(3));
    $('#wheatValue').val(wheatValue.toFixed(2));

    if (totalValue > 0) {
        $('#estimatedValue').val(totalValue.toFixed(2));
    } else {
        $('#estimatedValue').val('0.00');
    }
}

$('#bagQty, #kattPerBag, #ratePerMan, input[name="bag_rate"]').on('input', calcBooking);
$('input[name="bag_action"]').on('change', calcBooking);

// === Farmer Search Autocomplete ===
var searchTimer;
$('#farmerSearch').on('input', function() {
    var q = $(this).val();
    clearTimeout(searchTimer);

    if (q.length < 1) {
        $('#farmerResults').hide();
        $('#farmerId').val('');
        return;
    }

    searchTimer = setTimeout(function() {
        $.get('get_farmers.php', { q: q }, function(data) {
            var results = $('#farmerResults');
            results.empty().show();

            if (data.length === 0) {
                results.append('<div class="dropdown-item text-muted">No farmers found</div>');
                return;
            }

            $.each(data, function(i, f) {
                var label = f.name;
                if (f.village) label += ' <small class="text-muted">' + f.village + '</small>';
                if (f.phone) label += ' <small class="text-muted">(' + f.phone + ')</small>';
                results.append('<div class="dropdown-item" data-id="' + f.id + '" data-name="' + f.name + '">' + label + '</div>');
            });
        });
    }, 200);
});

$(document).on('click', '#farmerResults .dropdown-item', function() {
    var id = $(this).data('id');
    var name = $(this).data('name');
    $('#farmerId').val(id);
    $('#farmerSearch').val(name);
    $('#farmerResults').hide();
});

$(document).on('click', function(e) {
    if (!$(e.target).closest('.form-group').find('#farmerSearch').length && !$(e.target).closest('#farmerResults').length) {
        $('#farmerResults').hide();
    }
});

// === Add New Farmer via Modal ===
$('#nfSaveBtn').on('click', function() {
    var btn = $(this);
    var name = $('#nfName').val().trim();
    var phone = $('#nfPhone').val().trim();
    var village = $('#nfVillage').val().trim();
    var city = $('#nfCity').val().trim();

    if (!name) { $('#nfError').text('Name is required.').show(); return; }
    $('#nfError').hide();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');

    $.post('ajax_add_farmer.php', { name: name, phone: phone, village: village, city: city }, function(res) {
        if (res.success) {
            $('#farmerId').val(res.farmer.id);
            $('#farmerSearch').val(res.farmer.name);
            $('#farmerResults').hide();
            $('#newFarmerModal').modal('hide');
            $('#nfName, #nfPhone, #nfVillage, #nfCity').val('');
        } else {
            $('#nfError').text(res.message).show();
        }
        btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save');
    });
});

$('#newFarmerModal').on('hidden.bs.modal', function() {
    $('#nfName, #nfPhone, #nfVillage, #nfCity').val('');
    $('#nfError').hide();
});
</script>

<?php include '../../includes/footer.php'; ?>
