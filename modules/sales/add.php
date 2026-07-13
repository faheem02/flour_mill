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
    $customer_id = (int)$_POST['customer_id'];
    $date = $_POST['date'];
    $invoice_no = sanitize($_POST['invoice_no']);
    $paid_amount = str_replace(',', '', $_POST['paid_amount']);
    $warehouse_id = (int)$_POST['warehouse_id'];
    $vehicle_no = sanitize($_POST['vehicle_no'] ?? '');
    $driver_name = sanitize($_POST['driver_name'] ?? '');
    $driver_mobile = sanitize($_POST['driver_mobile'] ?? '');

    $product_ids = $_POST['product_id'];
    $qtys = $_POST['qty'];
    $rates = $_POST['rate'];
    $amounts = $_POST['amount'];

    $total_qty = 0; $total_amount = 0;
    foreach ($qtys as $i => $q) {
        $q_val = str_replace(',', '', $q);
        $total_qty += $q_val;
        $total_amount += str_replace(',', '', $amounts[$i]);
    }

    if ($total_qty <= 0) { $error = "At least one product is required."; }
    elseif ($warehouse_id <= 0) { $error = "Please select a warehouse."; }
    else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO sales (customer_id, date, invoice_no, total_qty, total_amount, paid_amount, vehicle_no, driver_name, driver_mobile) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issdddsss", $customer_id, $date, $invoice_no, $total_qty, $total_amount, $paid_amount, $vehicle_no, $driver_name, $driver_mobile);
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

                // Deduct from warehouse stock
                $conn->query("UPDATE warehouse_stock SET stock_qty = GREATEST(stock_qty - $q, 0) WHERE warehouse_id = $warehouse_id AND product_id = $pid");

                $stmt3->bind_param("isiiii", $pid, $date, $sale_id, $warehouse_id, $q, $pid);
                $stmt3->execute();
            }

            // Customer ledger
            $stmt4 = $conn->prepare("INSERT INTO customer_ledger (customer_id, date, type, reference_id, debit, balance, notes)
                VALUES (?, ?, 'sale', ?, ?, (SELECT COALESCE(balance,0)+? FROM customers WHERE id=?), 'Sale - $invoice_no')");
            $stmt4->bind_param("isiddi", $customer_id, $date, $sale_id, $total_amount, $total_amount, $customer_id);
            $stmt4->execute();
            $conn->query("UPDATE customers SET balance = balance + $total_amount WHERE id = $customer_id");

            if ($paid_amount > 0) {
                $conn->query("INSERT INTO customer_ledger (customer_id, date, type, reference_id, credit, balance, notes)
                    VALUES ($customer_id, '$date', 'receipt', $sale_id, $paid_amount, (SELECT COALESCE(balance,0)-$paid_amount FROM customers WHERE id=$customer_id), 'Payment on sale - $invoice_no')");
                $conn->query("UPDATE customers SET balance = balance - $paid_amount WHERE id = $customer_id");
            }

            $cash_amt = $paid_amount > 0 ? $paid_amount : 0;
            $receivable_amt = $total_amount - $cash_amt;
            $debits = [];
            $credits = [13 => $total_amount];
            if ($cash_amt > 0) $debits[2] = $cash_amt;
            if ($receivable_amt > 0) $debits[5] = $receivable_amt;
            autoJournalEntry($date, "Sale to customer (Inv: $invoice_no)", $debits, $credits, $_SESSION['user_id']);

            $conn->commit();
            $success = "Sale recorded successfully. Invoice: $invoice_no";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$customers = $conn->query("SELECT id, name FROM customers ORDER BY name");
$products = $conn->query("SELECT p.id, p.name, p.sale_price, p.cost_rate, p.stock_qty,
  (SELECT pi.rate_per_kg FROM production_items pi 
   JOIN productions pr ON pi.production_id = pr.id 
   WHERE pi.product_id = p.id 
   ORDER BY pr.date DESC, pr.id DESC LIMIT 1) as latest_cost_rate
  FROM products p WHERE status='active' AND name != 'Wheat (Gandam)' AND stock_qty > 0 ORDER BY name");
$warehouses = $conn->query("SELECT id, name FROM warehouses WHERE status='active' AND type IN ('mill','finished') ORDER BY type, name");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-cash-register mr-1"></i> New Sale</h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Sales List</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Sale Invoice</h6></div>
    <div class="card-body">
        <form method="POST" id="saleForm">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-control" required>
                            <option value="">Select Customer</option>
                            <?php while ($c = $customers->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
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

            <div class="card bg-light mb-3">
                <div class="card-header"><strong>Transport</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Vehicle No.</label>
                                <input type="text" name="vehicle_no" class="form-control" placeholder="e.g. LEH-1234">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Driver Name</label>
                                <input type="text" name="driver_name" class="form-control" placeholder="Driver name">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Driver Mobile</label>
                                <input type="text" name="driver_mobile" class="form-control" placeholder="03XX-XXXXXXX" oninput="this.value = this.value.replace(/[^0-9\-]/g,'')">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-light mb-3">
                <div class="card-header"><strong>Products</strong> <button type="button" class="btn btn-sm btn-success ml-2" onclick="addRow()"><i class="fas fa-plus"></i> Add Product</button></div>
                <div class="card-body">
                    <style>
                        #saleTable { table-layout: fixed; }
                        #saleTable th, #saleTable td { vertical-align: middle; }
                        #saleTable select, #saleTable input.form-control {
                            height: 38px !important;
                            padding: 6px 10px !important;
                            font-size: 14px !important;
                            width: 100% !important;
                            box-sizing: border-box !important;
                        }
                    </style>
                    <table class="table table-bordered" id="saleTable">
                        <colgroup>
                            <col style="width:30%">
                            <col style="width:18%">
                            <col style="width:18%">
                            <col style="width:22%">
                            <col style="width:12%">
                        </colgroup>
                        <thead>
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
                                    <select name="product_id[]" class="form-control" required onchange="setRate(this)">
                                        <option value="">Select</option>
                                        <?php $products->data_seek(0); while ($pr = $products->fetch_assoc()): ?>
                                        <option value="<?= $pr['id'] ?>" data-price="<?= ($pr['latest_cost_rate'] ?: $pr['cost_rate']) ?: $pr['sale_price'] ?>" data-stock="<?= $pr['stock_qty'] ?>"><?= htmlspecialchars($pr['name']) ?> (Stock: <?= qty($pr['stock_qty']) ?>)</option>
                                        <?php endwhile; ?>
                                    </select>
                                </td>
                                <td><input type="text" name="qty[]" class="form-control text-right" placeholder="0" oninput="calcRow(this);calcTotal()"></td>
                                <td><input type="text" name="rate[]" class="form-control text-right" placeholder="0.00" oninput="calcRow(this);calcTotal()"></td>
                                <td><input type="text" name="amount[]" class="form-control text-right" placeholder="0.00" readonly style="background:#f5f5f5;font-weight:bold"></td>
                                <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();calcTotal()"><i class="fas fa-times"></i></button></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="table-active">
                                <th colspan="2" class="text-right">Total:</th>
                                <th class="text-right" id="totalQty">0.000 KG</th>
                                <th class="text-right" id="totalAmount">0.00</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Paid Amount (Cash)</label>
                        <input type="text" name="paid_amount" class="form-control" placeholder="0.00" oninput="this.value = this.value.replace(/[^0-9.]/g,'')">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Sale</button>
        </form>
    </div>
</div>

<script>
function setRate(sel) {
    var price = $(sel).find(':selected').data('price');
    $(sel).closest('tr').find('input[name="rate[]"]').val(price || 0);
    calcRow(sel);
    calcTotal();
}

function calcRow(el) {
    var tr = $(el).closest('tr');
    var qty = parseFloat(tr.find('input[name="qty[]"]').val()) || 0;
    var rate = parseFloat(tr.find('input[name="rate[]"]').val()) || 0;
    tr.find('input[name="amount[]"]').val((qty * rate).toFixed(2));
}

function calcTotal() {
    var totalQty = 0, totalAmt = 0;
    $('input[name="qty[]"]').each(function() {
        totalQty += parseFloat($(this).val()) || 0;
    });
    $('input[name="amount[]"]').each(function() {
        totalAmt += parseFloat($(this).val()) || 0;
    });
    $('#totalQty').text(totalQty.toFixed(3) + ' KG');
    $('#totalAmount').text(totalAmt.toFixed(2));
}

function addRow() {
    var options = '<?php $products->data_seek(0); while ($pr = $products->fetch_assoc()): $pr_rate = ($pr["latest_cost_rate"] ?: $pr["cost_rate"]) ?: $pr["sale_price"]; ?><option value="<?= $pr["id"] ?>" data-price="<?= $pr_rate ?>" data-stock="<?= $pr["stock_qty"] ?>"><?= htmlspecialchars($pr["name"]) ?> (Stock: <?= qty($pr["stock_qty"]) ?>)</option><?php endwhile; ?>';
    var html = '<tr>' +
        '<td><select name="product_id[]" class="form-control" required onchange="setRate(this)"><option value="">Select</option>' + options + '</select></td>' +
        '<td><input type="text" name="qty[]" class="form-control text-right" placeholder="0" oninput="calcRow(this);calcTotal()"></td>' +
        '<td><input type="text" name="rate[]" class="form-control text-right" placeholder="0.00" oninput="calcRow(this);calcTotal()"></td>' +
        '<td><input type="text" name="amount[]" class="form-control text-right" placeholder="0.00" readonly style="background:#f5f5f5;font-weight:bold"></td>' +
        '<td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'tr\').remove();calcTotal()"><i class="fas fa-times"></i></button></td>' +
        '</tr>';
    $('#saleTable tbody').append(html);
}
</script>

<?php include '../../includes/footer.php'; ?>
