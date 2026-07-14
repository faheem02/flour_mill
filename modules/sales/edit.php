<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'sale_list';
$page_title = 'Edit Sale';
require_once '../../includes/db.php';
include '../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: list.php"); exit; }

$error = $success = '';

$sale = $conn->query("SELECT * FROM sales WHERE id = $id")->fetch_assoc();
if (!$sale) { header("Location: list.php"); exit; }

$sale_items = $conn->query("SELECT si.*, p.name as product_name FROM sale_items si JOIN products p ON si.product_id=p.id WHERE si.sale_id=$id");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id    = (int)$_POST['customer_id'];
    $date           = $_POST['date'];
    $invoice_no     = sanitize($_POST['invoice_no']);
    $warehouse_id   = (int)$_POST['warehouse_id'];
    $delivery_type  = sanitize($_POST['delivery_type'] ?? 'pickup');
    $freight_amount = str_replace(',', '', $_POST['freight_amount'] ?? '0');
    $paid_amount    = str_replace(',', '', $_POST['paid_amount'] ?? '0');
    $vehicle_no     = sanitize($_POST['vehicle_no'] ?? '');
    $driver_name    = sanitize($_POST['driver_name'] ?? '');
    $driver_mobile  = sanitize($_POST['driver_mobile'] ?? '');
    $notes          = sanitize($_POST['notes'] ?? '');

    $product_ids = $_POST['product_id'] ?? [];
    $qtys        = $_POST['qty'] ?? [];
    $rates       = $_POST['rate'] ?? [];
    $amounts     = $_POST['amount'] ?? [];

    $total_qty = 0; $products_total = 0;
    foreach ($qtys as $i => $q) {
        $q_val = str_replace(',', '', $q);
        $total_qty += $q_val;
        $products_total += str_replace(',', '', $amounts[$i]);
    }

    $grand_total = $products_total + $freight_amount;

    if ($total_qty <= 0) { $error = "At least one product is required."; }
    elseif ($warehouse_id <= 0) { $error = "Please select a warehouse."; }
    elseif (empty($delivery_type)) { $error = "Please select delivery type."; }
    else {
        $conn->begin_transaction();
        try {
            // 1) Reverse old stock
            $old_items = $conn->query("SELECT * FROM sale_items WHERE sale_id=$id");
            while ($oi = $old_items->fetch_assoc()) {
                $conn->query("UPDATE products SET stock_qty = stock_qty + {$oi['qty']} WHERE id = {$oi['product_id']}");
                $conn->query("UPDATE warehouse_stock SET stock_qty = stock_qty + {$oi['qty']} WHERE warehouse_id = {$sale['warehouse_id']} AND product_id = {$oi['product_id']}");
            }

            // 2) Reverse customer ledger
            $conn->query("DELETE FROM customer_ledger WHERE type='sale' AND reference_id=$id");
            $conn->query("DELETE FROM customer_ledger WHERE type='receipt' AND reference_id=$id");
            $conn->query("UPDATE customers SET balance = balance - {$sale['total_amount']} WHERE id = {$sale['customer_id']}");
            if ($sale['paid_amount'] > 0) {
                $conn->query("UPDATE customers SET balance = balance + {$sale['paid_amount']} WHERE id = {$sale['customer_id']}");
            }

            // 3) Reverse journal entry
            $conn->query("DELETE FROM journal_entry_items WHERE journal_id IN (SELECT id FROM journal_entries WHERE description LIKE '%Inv: {$sale['invoice_no']}%')");
            $conn->query("DELETE FROM journal_entries WHERE description LIKE '%Inv: {$sale['invoice_no']}%'");

            // 4) Delete old items
            $conn->query("DELETE FROM sale_items WHERE sale_id = $id");

            // 5) Update sale header
            $stmt = $conn->prepare("UPDATE sales SET customer_id=?, warehouse_id=?, date=?, invoice_no=?, total_qty=?, total_amount=?, paid_amount=?, delivery_type=?, freight_amount=?, vehicle_no=?, driver_name=?, driver_mobile=?, notes=? WHERE id=?");
            $stmt->bind_param("iiisddddsdssssi", $customer_id, $warehouse_id, $date, $invoice_no, $total_qty, $grand_total, $paid_amount, $delivery_type, $freight_amount, $vehicle_no, $driver_name, $driver_mobile, $notes, $id);
            $stmt->execute();

            // 6) Insert new items + apply stock
            $stmt2 = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, qty, rate, amount) VALUES (?, ?, ?, ?, ?)");
            $stmt3 = $conn->prepare("INSERT INTO stock_ledger (product_id, date, type, reference_id, warehouse_id, qty_out, balance_qty, notes)
                VALUES (?, ?, 'sale', ?, ?, ?, (SELECT COALESCE(stock_qty,0) FROM products WHERE id=?), 'Sale - $invoice_no')");

            foreach ($product_ids as $i => $pid) {
                $q = str_replace(',', '', $qtys[$i]);
                $r = str_replace(',', '', $rates[$i]);
                $a = str_replace(',', '', $amounts[$i]);
                if ($q <= 0) continue;

                $stmt2->bind_param("iiddd", $id, $pid, $q, $r, $a);
                $stmt2->execute();

                $conn->query("UPDATE products SET stock_qty = stock_qty - $q WHERE id = $pid");
                $conn->query("UPDATE warehouse_stock SET stock_qty = GREATEST(stock_qty - $q, 0) WHERE warehouse_id = $warehouse_id AND product_id = $pid");
                $stmt3->bind_param("isiiii", $pid, $date, $id, $warehouse_id, $q, $pid);
                $stmt3->execute();
            }

            // 7) Customer ledger — new debit
            $stmt4 = $conn->prepare("INSERT INTO customer_ledger (customer_id, date, type, reference_id, debit, balance, notes)
                VALUES (?, ?, 'sale', ?, ?, (SELECT COALESCE(balance,0)+? FROM customers WHERE id=?), 'Sale - $invoice_no')");
            $stmt4->bind_param("isiddi", $customer_id, $date, $id, $grand_total, $grand_total, $customer_id);
            $stmt4->execute();
            $conn->query("UPDATE customers SET balance = balance + $grand_total WHERE id = $customer_id");

            // 8) Payment receipt if any
            if ($paid_amount > 0) {
                $conn->query("INSERT INTO customer_ledger (customer_id, date, type, reference_id, credit, balance, notes)
                    VALUES ($customer_id, '$date', 'receipt', $id, $paid_amount, (SELECT COALESCE(balance,0)-$paid_amount FROM customers WHERE id=$customer_id), 'Payment on sale - $invoice_no')");
                $conn->query("UPDATE customers SET balance = balance - $paid_amount WHERE id = $customer_id");
            }

            // 9) Journal entry
            $debits = [];
            $credits = [13 => $grand_total];
            if ($paid_amount > 0) $debits[2] = $paid_amount;
            $receivable = $grand_total - $paid_amount;
            if ($receivable > 0) $debits[4] = $receivable;
            autoJournalEntry($date, "Sale to customer (Inv: $invoice_no) [$delivery_type]", $debits, $credits, $_SESSION['user_id']);

            $conn->commit();
            $success = "Sale updated successfully.";
            // Reload sale data
            $sale = $conn->query("SELECT * FROM sales WHERE id = $id")->fetch_assoc();
            $sale_items = $conn->query("SELECT si.*, p.name as product_name FROM sale_items si JOIN products p ON si.product_id=p.id WHERE si.sale_id=$id");
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$customers = $conn->query("SELECT id, name, balance FROM customers ORDER BY name");
$products  = $conn->query("SELECT p.id, p.name, p.sale_price, p.cost_rate, p.stock_qty,
  (SELECT pi.rate_per_kg FROM production_items pi 
   JOIN productions pr ON pi.production_id = pr.id 
   WHERE pi.product_id = p.id 
   ORDER BY pr.date DESC, pr.id DESC LIMIT 1) as latest_cost_rate
  FROM products p WHERE status='active' AND name != 'Wheat (Gandam)' ORDER BY name");
$warehouses = $conn->query("SELECT id, name FROM warehouses WHERE status='active' AND type IN ('mill','finished') ORDER BY type, name");

// Build existing items JSON for JS
$existing_items = [];
$si_clone = $conn->query("SELECT si.*, p.name as product_name, p.stock_qty as prod_stock,
  (SELECT pi.rate_per_kg FROM production_items pi 
   JOIN productions pr ON pi.production_id = pr.id 
   WHERE pi.product_id = p.id 
   ORDER BY pr.date DESC, pr.id DESC LIMIT 1) as latest_cost_rate
  FROM sale_items si JOIN products p ON si.product_id=p.id WHERE si.sale_id=$id");
while ($ei = $si_clone->fetch_assoc()) {
    $existing_items[] = $ei;
}
?>
<style>
    .summary-card { border-left: 4px solid var(--gold); }
    .summary-card .sum-row { display:flex; justify-content:space-between; padding:8px 0; font-size:14px; }
    .summary-card .sum-row.grand { border-top:2px solid var(--navy); border-bottom:1px solid #dee2e6; background:#f8f9fc; padding:10px 0; font-weight:700; font-size:15px; }
    .summary-card .sum-row.prev  { background:#fff8e1; padding:10px 0; }
    .summary-card .sum-row.recv  { border-top:1px solid #dee2e6; padding:10px 0; }
    .summary-card .sum-row.rem   { border-top:2px solid var(--navy); padding:12px 0; font-weight:700; font-size:16px; }
    .delivery-toggle .btn { min-width:130px; font-weight:600; }
    .delivery-toggle .btn i { margin-right:6px; }
    .dchk { border:1px solid #d1d3e2; background:#fff; color:#555; font-size:13px; padding:6px 18px; cursor:pointer; transition:all .15s; border-radius:4px; }
    .dchk:hover { background:#f0f0f0; }
    .dchk.active-del { background:var(--gold); color:#fff; border-color:var(--gold); }
    .dchk.active-pick { background:#858796; color:#fff; border-color:#858796; }
    .transport-section { transition: all 0.3s ease; }
    #saleTable select, #saleTable input.form-control { height:38px !important; padding:6px 10px !important; font-size:14px !important; width:100% !important; box-sizing:border-box !important; }
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-edit mr-1"></i> Edit Sale — <?= htmlspecialchars($sale['invoice_no']) ?></h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Sales List</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<form method="POST" id="saleForm">
<input type="hidden" name="id" value="<?= $id ?>">

<!-- Row 1: Sale Details -->
<div class="card shadow mb-3">
    <div class="card-header"><h6 class="font-weight-bold m-0"><i class="fas fa-file-invoice mr-1"></i> Sale Details</h6></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Customer <span class="text-danger">*</span></label>
                    <select name="customer_id" id="customerSelect" class="form-control" required>
                        <option value="">Select Customer</option>
                        <?php while ($c = $customers->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" data-balance="<?= $c['balance'] ?>" <?= $c['id'] == $sale['customer_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Date <span class="text-danger">*</span></label>
                    <input type="date" name="date" class="form-control" value="<?= $sale['date'] ?>" required>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Invoice No.</label>
                    <input type="text" name="invoice_no" class="form-control" value="<?= htmlspecialchars($sale['invoice_no']) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Warehouse <span class="text-danger">*</span></label>
                    <select name="warehouse_id" class="form-control" required>
                        <option value="">Select Warehouse</option>
                        <?php while ($w = $warehouses->fetch_assoc()): ?>
                        <option value="<?= $w['id'] ?>" <?= $w['id'] == $sale['warehouse_id'] ? 'selected' : '' ?>><?= htmlspecialchars($w['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Delivery Type <span class="text-danger">*</span></label>
                    <div class="d-flex">
                        <label class="dchk <?= $sale['delivery_type'] === 'delivery' ? 'active-del' : '' ?> mr-2 mb-0 flex-fill text-center" id="deliveryLabel">
                            <input type="radio" name="delivery_type" id="deliveryRadio" value="delivery" autocomplete="off" <?= $sale['delivery_type'] === 'delivery' ? 'checked' : '' ?> class="d-none"> <i class="fas fa-truck fa-sm"></i> Delivery
                        </label>
                        <label class="dchk <?= $sale['delivery_type'] === 'pickup' ? 'active-pick' : '' ?> mb-0 flex-fill text-center" id="pickupLabel">
                            <input type="radio" name="delivery_type" id="pickupRadio" value="pickup" autocomplete="off" <?= $sale['delivery_type'] === 'pickup' ? 'checked' : '' ?> class="d-none"> <i class="fas fa-store fa-sm"></i> Pickup
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transport Section -->
<div class="card shadow mb-3" id="transportFields">
    <div class="card-header"><h6 class="font-weight-bold m-0"><i class="fas fa-truck mr-1"></i> Driver / Transport Details</h6></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Vehicle No.</label>
                    <input type="text" name="vehicle_no" id="vehicleNo" class="form-control" placeholder="e.g. LEH-1234" value="<?= htmlspecialchars($sale['vehicle_no'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Driver Name</label>
                    <input type="text" name="driver_name" id="driverName" class="form-control" placeholder="Driver name" value="<?= htmlspecialchars($sale['driver_name'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Driver Mobile</label>
                    <input type="text" name="driver_mobile" id="driverMobile" class="form-control" placeholder="03XX-XXXXXXX" oninput="this.value = this.value.replace(/[^0-9\-]/g,'')" value="<?= htmlspecialchars($sale['driver_mobile'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Products Section -->
<div class="card shadow mb-3">
    <div class="card-header">
        <h6 class="font-weight-bold m-0 d-inline"><i class="fas fa-boxes mr-1"></i> Products</h6>
        <button type="button" class="btn btn-sm btn-success float-right" onclick="addRow()"><i class="fas fa-plus"></i> Add Product</button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered mb-0" id="saleTable">
                <colgroup>
                    <col style="width:32%">
                    <col style="width:16%">
                    <col style="width:18%">
                    <col style="width:22%">
                    <col style="width:12%">
                </colgroup>
                <thead class="thead-dark">
                    <tr>
                        <th>Product</th>
                        <th class="text-right">Qty (KG)</th>
                        <th class="text-right">Rate/KG</th>
                        <th class="text-right">Amount</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                </tbody>
                <tfoot>
                    <tr class="table-active">
                        <th class="text-right" colspan="2">Products Total:</th>
                        <th class="text-right" id="productsTotal">0.000 KG</th>
                        <th class="text-right" id="productsTotalAmt">Rs 0.00</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Row: Notes + Summary -->
<div class="row">
    <div class="col-md-7">
        <div class="card shadow mb-4" style="height:calc(100% - 0px)">
            <div class="card-header"><h6 class="font-weight-bold m-0"><i class="fas fa-sticky-note mr-1"></i> Notes</h6></div>
            <div class="card-body">
                <textarea name="notes" class="form-control" rows="6" placeholder="Any additional notes for this sale..."><?= htmlspecialchars($sale['notes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card shadow mb-4 summary-card">
            <div class="card-header"><h6 class="font-weight-bold m-0"><i class="fas fa-calculator mr-1"></i> Invoice Summary</h6></div>
            <div class="card-body py-2">
                <div class="sum-row">
                    <span>Products Total</span>
                    <span id="sumProducts">Rs 0.00</span>
                </div>
                <div class="sum-row" style="align-items:center">
                    <span>Freight + Load</span>
                    <input type="text" name="freight_amount" id="freightInput" class="form-control form-control-sm text-right" style="width:160px" placeholder="0.00" value="<?= $sale['freight_amount'] ?? 0 ?>" oninput="calcTotals()">
                </div>
                <div class="sum-row grand">
                    <span>Grand Total</span>
                    <span id="sumGrand">Rs 0.00</span>
                </div>
                <div class="sum-row prev">
                    <span>Previous Balance <small class="text-muted">(Customer Due)</small></span>
                    <span id="sumPrev" class="font-weight-bold">Rs 0.00</span>
                </div>
                <div class="sum-row recv" style="align-items:center">
                    <span>Total Receiving</span>
                    <input type="text" name="paid_amount" id="totalReceiving" class="form-control form-control-sm text-right font-weight-bold" style="width:160px;border-color:var(--gold)" placeholder="0.00" value="<?= $sale['paid_amount'] ?>" oninput="calcTotals()">
                </div>
                <div class="sum-row rem">
                    <span>Remaining</span>
                    <span id="sumRemain">Rs 0.00</span>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg shadow" id="submitBtn">
            <i class="fas fa-save mr-1"></i> Update Sale
        </button>
    </div>
</div>

</form>

<script>
var productsData = '<?php $products->data_seek(0); while ($pr = $products->fetch_assoc()): ?><?= $pr["id"] ?>|<?= htmlspecialchars($pr["name"]) ?>|<?= ($pr["latest_cost_rate"] ?: $pr["cost_rate"]) ?: $pr["sale_price"] ?>|<?= $pr["stock_qty"] ?>|<?= qty($pr["stock_qty"]) ?>;<?php endwhile; ?>';

var existingItems = <?= json_encode($existing_items) ?>;

function buildProductOptions(selectedId) {
    var rows = productsData.split(';').filter(Boolean);
    var opts = '<option value="">Select Product</option>';
    rows.forEach(function(r) {
        var p = r.split('|');
        var sel = (p[0] == selectedId) ? ' selected' : '';
        opts += '<option value="'+p[0]+'" data-price="'+p[2]+'" data-stock="'+p[3]+'"'+sel+'>'+p[1]+' (Stock: '+p[4]+')</option>';
    });
    return opts;
}

function loadExistingItems() {
    var html = '';
    existingItems.forEach(function(it) {
        html += '<tr>' +
            '<td><select name="product_id[]" class="form-control" required onchange="setRate(this)">' + buildProductOptions(it.product_id) + '</select></td>' +
            '<td><input type="text" name="qty[]" class="form-control text-right" placeholder="0" value="' + parseFloat(it.qty).toFixed(3) + '" oninput="calcRow(this);calcTotals()"></td>' +
            '<td><input type="text" name="rate[]" class="form-control text-right" placeholder="0.00" value="' + parseFloat(it.rate).toFixed(2) + '" oninput="calcRow(this);calcTotals()"></td>' +
            '<td><input type="text" name="amount[]" class="form-control text-right" placeholder="0.00" value="' + parseFloat(it.amount).toFixed(2) + '" readonly style="background:#f0f0f0;font-weight:600"></td>' +
            '<td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'tr\').remove();calcTotals()"><i class="fas fa-times"></i></button></td>' +
            '</tr>';
    });
    $('#itemsBody').html(html);
    calcTotals();
}

function setRate(sel) {
    var price = $(sel).find(':selected').data('price');
    $(sel).closest('tr').find('input[name="rate[]"]').val(price || 0);
    calcRow(sel);
    calcTotals();
}

function calcRow(el) {
    var tr = $(el).closest('tr');
    var qty  = parseFloat(tr.find('input[name="qty[]"]').val()) || 0;
    var rate = parseFloat(tr.find('input[name="rate[]"]').val()) || 0;
    tr.find('input[name="amount[]"]').val((qty * rate).toFixed(2));
}

function calcTotals() {
    var totalQty = 0, totalAmt = 0;
    $('input[name="qty[]"]').each(function() { totalQty += parseFloat($(this).val()) || 0; });
    $('input[name="amount[]"]').each(function() { totalAmt += parseFloat($(this).val()) || 0; });

    var freight  = parseFloat($('#freightInput').val()) || 0;
    var grand    = totalAmt + freight;

    var custSel = $('#customerSelect');
    var prevBal = parseFloat(custSel.find(':selected').data('balance')) || 0;

    var receiving = parseFloat($('#totalReceiving').val()) || 0;
    var remain    = grand + prevBal - receiving;

    $('#productsTotal').text(totalQty.toFixed(3) + ' KG');
    $('#productsTotalAmt').text('Rs ' + totalAmt.toFixed(2));
    $('#sumProducts').text('Rs ' + totalAmt.toFixed(2));
    $('#sumGrand').text('Rs ' + grand.toFixed(2));
    $('#sumPrev').text('Rs ' + prevBal.toFixed(2));

    if (prevBal > 0) {
        $('#sumPrev').removeClass('text-success').addClass('text-danger font-weight-bold');
    } else if (prevBal < 0) {
        $('#sumPrev').removeClass('text-danger').addClass('text-success font-weight-bold');
    } else {
        $('#sumPrev').removeClass('text-danger text-success').addClass('font-weight-bold');
    }

    var remEl = $('#sumRemain');
    remEl.text('Rs ' + remain.toFixed(2));
    remEl.removeClass('text-success text-danger');
    remEl.addClass(remain > 0 ? 'text-danger' : 'text-success');
}

function addRow() {
    var rows = productsData.split(';').filter(Boolean);
    var opts = '<option value="">Select Product</option>';
    rows.forEach(function(r) {
        var p = r.split('|');
        opts += '<option value="'+p[0]+'" data-price="'+p[2]+'" data-stock="'+p[3]+'">'+p[1]+' (Stock: '+p[4]+')</option>';
    });
    var html = '<tr>' +
        '<td><select name="product_id[]" class="form-control" required onchange="setRate(this)">' + opts + '</select></td>' +
        '<td><input type="text" name="qty[]" class="form-control text-right" placeholder="0" oninput="calcRow(this);calcTotals()"></td>' +
        '<td><input type="text" name="rate[]" class="form-control text-right" placeholder="0.00" oninput="calcRow(this);calcTotals()"></td>' +
        '<td><input type="text" name="amount[]" class="form-control text-right" placeholder="0.00" readonly style="background:#f0f0f0;font-weight:600"></td>' +
        '<td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'tr\').remove();calcTotals()"><i class="fas fa-times"></i></button></td>' +
        '</tr>';
    $('#saleTable tbody').append(html);
}

// Delivery type toggle
$('input[name="delivery_type"]').on('change', function() {
    $('#deliveryLabel').removeClass('active-del');
    $('#pickupLabel').removeClass('active-pick');
    if ($(this).val() === 'delivery') {
        $('#deliveryLabel').addClass('active-del');
        $('#freightInput').prop('disabled', false);
    } else {
        $('#pickupLabel').addClass('active-pick');
        $('#freightInput').val(0).prop('disabled', true);
        calcTotals();
    }
});

$('#customerSelect').on('change', function() { calcTotals(); });

// Init
$(function() {
    loadExistingItems();
    // Trigger delivery type styling on load
    if ($('#pickupRadio').is(':checked')) {
        $('#freightInput').prop('disabled', true);
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
