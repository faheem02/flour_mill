<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'sale_add';
$page_title = 'New Sale';
require_once '../../includes/db.php';
include '../../includes/header.php';

$error = $success = '';

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
        $stock_errors = [];
        foreach ($product_ids as $i => $pid) {
            $q = str_replace(',', '', $qtys[$i]);
            if ($q <= 0) continue;
            $wh_stock = $conn->query("SELECT COALESCE(stock_qty,0) AS s FROM warehouse_stock WHERE warehouse_id = $warehouse_id AND product_id = $pid")->fetch_assoc();
            $avail = $wh_stock ? (float)$wh_stock['s'] : 0;
            if ($q > $avail) {
                $pname = $conn->query("SELECT name FROM products WHERE id = $pid")->fetch_assoc()['name'] ?? "Product #$pid";
                $stock_errors[] = "$pname: requested " . qty($q) . " KG, available " . qty($avail) . " KG";
            }
        }
        if (!empty($stock_errors)) {
            $error = "Insufficient stock in selected warehouse:<br>" . implode("<br>", $stock_errors);
        } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO sales (customer_id, warehouse_id, date, invoice_no, total_qty, total_amount, paid_amount, delivery_type, freight_amount, vehicle_no, driver_name, driver_mobile, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisddddsdssss", $customer_id, $warehouse_id, $date, $invoice_no, $total_qty, $grand_total, $paid_amount, $delivery_type, $freight_amount, $vehicle_no, $driver_name, $driver_mobile, $notes);
            $stmt->execute();
            $sale_id = $conn->insert_id;

            $stmt2 = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, qty, rate, amount) VALUES (?, ?, ?, ?, ?)");
            $stmt3 = $conn->prepare("INSERT INTO stock_ledger (product_id, date, type, reference_id, warehouse_id, qty_out, balance_qty, notes)
                VALUES (?, ?, 'sale', ?, ?, ?, (SELECT COALESCE(stock_qty,0) FROM products WHERE id=?), 'Sale - $invoice_no')");

            foreach ($product_ids as $i => $pid) {
                $q = str_replace(',', '', $qtys[$i]);
                $r = str_replace(',', '', $rates[$i]);
                $a = str_replace(',', '', $amounts[$i]);
                if ($q <= 0) continue;

                $stmt2->bind_param("iiddd", $sale_id, $pid, $q, $r, $a);
                $stmt2->execute();

                $conn->query("UPDATE products SET stock_qty = stock_qty - $q WHERE id = $pid");
                $conn->query("UPDATE warehouse_stock SET stock_qty = stock_qty - $q WHERE warehouse_id = $warehouse_id AND product_id = $pid");
                $stmt3->bind_param("isiiii", $pid, $date, $sale_id, $warehouse_id, $q, $pid);
                $stmt3->execute();
            }

            $stmt4 = $conn->prepare("INSERT INTO customer_ledger (customer_id, date, type, reference_id, debit, balance, notes)
                VALUES (?, ?, 'sale', ?, ?, (SELECT COALESCE(balance,0)+? FROM customers WHERE id=?), 'Sale - $invoice_no')");
            $stmt4->bind_param("isiddi", $customer_id, $date, $sale_id, $grand_total, $grand_total, $customer_id);
            $stmt4->execute();
            $conn->query("UPDATE customers SET balance = balance + $grand_total WHERE id = $customer_id");

            if ($paid_amount > 0) {
                $conn->query("INSERT INTO customer_ledger (customer_id, date, type, reference_id, credit, balance, notes)
                    VALUES ($customer_id, '$date', 'receipt', $sale_id, $paid_amount, (SELECT COALESCE(balance,0)-$paid_amount FROM customers WHERE id=$customer_id), 'Payment on sale - $invoice_no')");
                $conn->query("UPDATE customers SET balance = balance - $paid_amount WHERE id = $customer_id");
            }

            $debits = [];
            $credits = [13 => $grand_total];
            if ($paid_amount > 0) $debits[2] = $paid_amount;
            $receivable = $grand_total - $paid_amount;
            if ($receivable > 0) $debits[4] = $receivable;
            autoJournalEntry($date, "Sale to customer (Inv: $invoice_no) [$delivery_type]", $debits, $credits, $_SESSION['user_id']);

            $conn->commit();
            $success = "Sale recorded successfully. Invoice: $invoice_no";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
        }
    }
}

$customers = $conn->query("SELECT id, name, balance FROM customers ORDER BY name");
$warehouses = $conn->query("SELECT w.id, w.name, w.type, COALESCE(SUM(ws.stock_qty),0) as total_stock
    FROM warehouses w
    LEFT JOIN warehouse_stock ws ON ws.warehouse_id = w.id
    WHERE w.status='active' AND w.type IN ('mill','finished')
    GROUP BY w.id, w.name, w.type
    ORDER BY w.type, w.name");
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
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-cash-register mr-1"></i> New Sale</h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Sales List</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<form method="POST" id="saleForm">

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
                        <option value="<?= $c['id'] ?>" data-balance="<?= $c['balance'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Date <span class="text-danger">*</span></label>
                    <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Invoice No.</label>
                    <input type="text" name="invoice_no" class="form-control" value="<?= generateInvoiceNo() ?>">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Warehouse <span class="text-danger">*</span></label>
                    <select name="warehouse_id" id="warehouseSelect" class="form-control" required>
                        <option value="">Select Warehouse</option>
                        <?php while ($w = $warehouses->fetch_assoc()): ?>
                        <option value="<?= $w['id'] ?>" <?= $w['total_stock'] <= 0 ? 'disabled' : '' ?>><?= htmlspecialchars($w['name']) ?> (Stock: <?= qty($w['total_stock']) ?> KG)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Delivery Type <span class="text-danger">*</span></label>
                    <div class="d-flex">
                        <label class="dchk active-del mr-2 mb-0 flex-fill text-center" id="deliveryLabel">
                            <input type="radio" name="delivery_type" id="deliveryRadio" value="delivery" autocomplete="off" checked class="d-none"> <i class="fas fa-truck fa-sm"></i> Delivery
                        </label>
                        <label class="dchk mb-0 flex-fill text-center" id="pickupLabel">
                            <input type="radio" name="delivery_type" id="pickupRadio" value="pickup" autocomplete="off" class="d-none"> <i class="fas fa-store fa-sm"></i> Pickup
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
                    <input type="text" name="vehicle_no" id="vehicleNo" class="form-control" placeholder="e.g. LEH-1234">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Driver Name</label>
                    <input type="text" name="driver_name" id="driverName" class="form-control" placeholder="Driver name">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Driver Mobile</label>
                    <input type="text" name="driver_mobile" id="driverMobile" class="form-control" placeholder="03XX-XXXXXXX" oninput="this.value = this.value.replace(/[^0-9\-]/g,'')">
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
                <tbody>
                    <tr>
                        <td>
                            <select name="product_id[]" class="form-control product-select" required onchange="setRate(this)">
                                <option value="">Select Warehouse First</option>
                            </select>
                        </td>
                        <td><input type="text" name="qty[]" class="form-control text-right" placeholder="0" oninput="calcRow(this);calcTotals()"></td>
                        <td><input type="text" name="rate[]" class="form-control text-right" placeholder="0.00" oninput="calcRow(this);calcTotals()"></td>
                        <td><input type="text" name="amount[]" class="form-control text-right" placeholder="0.00" readonly style="background:#f0f0f0;font-weight:600"></td>
                        <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();calcTotals()"><i class="fas fa-times"></i></button></td>
                    </tr>
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
                <textarea name="notes" class="form-control" rows="6" placeholder="Any additional notes for this sale..."></textarea>
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
                    <input type="text" name="freight_amount" id="freightInput" class="form-control form-control-sm text-right" style="width:160px" placeholder="0.00" value="0" oninput="calcTotals()">
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
                    <input type="text" name="paid_amount" id="totalReceiving" class="form-control form-control-sm text-right font-weight-bold" style="width:160px;border-color:var(--gold)" placeholder="0.00" value="0" oninput="calcTotals()">
                </div>
                <div class="sum-row rem">
                    <span>Remaining</span>
                    <span id="sumRemain">Rs 0.00</span>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg shadow" id="submitBtn">
            <i class="fas fa-save mr-1"></i> Save Sale
        </button>
    </div>
</div>

</form>

<script>
var productsData = '';

function buildProductOptions() {
    var rows = productsData.split(';').filter(Boolean);
    var opts = '<option value="">Select Product</option>';
    rows.forEach(function(r) {
        var p = r.split('|');
        opts += '<option value="'+p[0]+'" data-price="'+p[2]+'" data-stock="'+p[3]+'">'+p[1]+' (Stock: '+p[4]+')</option>';
    });
    return opts;
}

function refreshProductDropdowns() {
    var opts = buildProductOptions();
    $('.product-select').each(function() {
        var val = $(this).val();
        $(this).html(opts);
        if (val && $(this).find('option[value="'+val+'"]').length) {
            $(this).val(val);
        }
    });
}

$('#warehouseSelect').on('change', function() {
    var wh = $(this).val();
    if (!wh) {
        productsData = '';
        refreshProductDropdowns();
        return;
    }
    $.get('get_warehouse_products.php', { warehouse_id: wh }, function(data) {
        productsData = '';
        data.forEach(function(p) {
            var rate = p.rate || p.sale_price || 0;
            var stockStr = p.stock_qty.toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:3});
            productsData += p.id + '|' + p.name + '|' + rate + '|' + p.stock_qty + '|' + stockStr + ';';
        });
        refreshProductDropdowns();
    });
});

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
    var opts = buildProductOptions();
    var html = '<tr>' +
        '<td><select name="product_id[]" class="form-control product-select" required onchange="setRate(this)">' + opts + '</select></td>' +
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

// Customer change — update previous balance
$('#customerSelect').on('change', function() { calcTotals(); });

// Init
calcTotals();
</script>

<?php include '../../includes/footer.php'; ?>
