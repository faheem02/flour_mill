<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'production_add';
$page_title = 'New Production (Crush)';
require_once '../../includes/db.php';
include '../../includes/header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $bag_qty = (int)str_replace(',', '', $_POST['bag_qty']);
    $wheat_qty = str_replace(',', '', $_POST['wheat_qty']);
    $notes = sanitize($_POST['notes']);
    $mill_warehouse_id = (int)$_POST['mill_warehouse_id'];
    $bag_type_id = (int)$_POST['bag_type_id'];

    $product_ids = $_POST['product_id'];
    $qtys = $_POST['qty'];
    $rates = $_POST['rate'];

    if ($wheat_qty <= 0) { $error = "Wheat quantity is required."; }
    elseif ($mill_warehouse_id <= 0) { $error = "Please select mill warehouse."; }
    else {
        $total_output = 0;
        foreach ($qtys as $k => $q) {
            $q_val = str_replace(',', '', $q);
            $total_output += $q_val;
        }
        $wastage = $wheat_qty - $total_output;
        $extraction = $wheat_qty > 0 ? round(($total_output / $wheat_qty) * 100, 2) : 0;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO productions (date, wheat_qty, wheat_purchase_id, total_output, extraction_rate, wastage_qty, notes)
                VALUES (?, ?, NULL, ?, ?, ?, ?)");
            $stmt->bind_param("sdddds", $date, $wheat_qty, $total_output, $extraction, $wastage, $notes);
            $stmt->execute();
            $prod_id = $conn->insert_id;

            $stmt2 = $conn->prepare("INSERT INTO production_items (production_id, product_id, qty, rate_per_kg) VALUES (?, ?, ?, ?)");

            // Deduct wheat from mill warehouse
            $wheat = $conn->query("SELECT id FROM products WHERE name = 'Wheat (Gandam)' LIMIT 1")->fetch_assoc();
            if ($wheat) {
                $wpid = $wheat['id'];
                $conn->query("UPDATE warehouse_stock SET stock_qty = GREATEST(stock_qty - $wheat_qty, 0) WHERE warehouse_id = $mill_warehouse_id AND product_id = $wpid");
                $conn->query("INSERT INTO stock_ledger (product_id, date, type, reference_id, warehouse_id, qty_out, balance_qty, notes)
                    VALUES ($wpid, '$date', 'production', $prod_id, $mill_warehouse_id, $wheat_qty, (SELECT COALESCE(stock_qty,0) FROM products WHERE id=$wpid), 'Issued to Production #$prod_id')");
                $conn->query("UPDATE products SET stock_qty = GREATEST(stock_qty - $wheat_qty, 0) WHERE id = $wpid");
            }

            foreach ($product_ids as $i => $pid) {
                $q = str_replace(',', '', $qtys[$i]);
                $r = str_replace(',', '', $rates[$i]);
                if ($q <= 0) continue;

                $stmt2->bind_param("iidd", $prod_id, $pid, $q, $r);
                $stmt2->execute();

                $conn->query("UPDATE products SET stock_qty = stock_qty + $q WHERE id = $pid");

                // Add output to mill warehouse stock
                $conn->query("INSERT INTO warehouse_stock (warehouse_id, product_id, stock_qty)
                    VALUES ($mill_warehouse_id, $pid, $q)
                    ON DUPLICATE KEY UPDATE stock_qty = stock_qty + $q");
                $conn->query("INSERT INTO stock_ledger (product_id, date, type, reference_id, warehouse_id, qty_in, balance_qty, notes)
                    VALUES ($pid, '$date', 'production', $prod_id, $mill_warehouse_id, $q, (SELECT COALESCE(stock_qty,0) FROM products WHERE id=$pid), 'Produced in Production #$prod_id')");

                if ($r > 0) {
                    $conn->query("UPDATE products SET cost_rate = $r WHERE id = $pid");
                }
            }

            autoJournalEntry($date, "Production #$prod_id - Wheat crush ($wheat_qty KG)",
                [5 => $total_output * 40],
                [17 => $total_output * 40],
                $_SESSION['user_id']
            );

            $conn->commit();
            $success = "Production recorded successfully! Extraction rate: $extraction%";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$products = $conn->query("SELECT id, name FROM products WHERE status='active' AND name != 'Wheat (Gandam)' ORDER BY name");
$mill_warehouses = $conn->query("SELECT w.id, w.name, COALESCE(ws.stock_qty,0) as stock_qty
    FROM warehouses w
    LEFT JOIN warehouse_stock ws ON ws.warehouse_id = w.id
    LEFT JOIN products p ON ws.product_id = p.id AND p.name = 'Wheat (Gandam)'
    WHERE w.status='active' AND w.type='mill'
    ORDER BY w.name");
$bag_types = $conn->query("SELECT id, name, bag_weight_kg FROM bag_types WHERE status='active' ORDER BY name");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-industry mr-1"></i> New Production (Gandam Crush)</h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> History</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Crush Details</h6></div>
    <div class="card-body">
        <form method="POST" id="prodForm">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Mill Warehouse <span class="text-danger">*</span></label>
                        <select name="mill_warehouse_id" class="form-control" required>
                            <option value="">Select Mill</option>
                            <?php while ($mw = $mill_warehouses->fetch_assoc()): ?>
                            <option value="<?= $mw['id'] ?>" <?= $mw['stock_qty'] <= 0 ? 'disabled' : '' ?>><?= htmlspecialchars($mw['name']) ?> (Wheat: <?= qty($mw['stock_qty']) ?> KG)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Bag Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="bag_qty" id="bagQty" class="form-control" placeholder="0" min="0" required oninput="calcWheatQty()">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Wheat Quantity (KG)</label>
                        <input type="text" id="wheatQtyDisplay" class="form-control" value="0" readonly style="background:#f5f5f5;font-weight:bold">
                        <input type="hidden" name="wheat_qty" id="wheatQtyHidden">
                    </div>
                </div>

            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Bag Type</label>
                        <select name="bag_type_id" id="bagTypeId" class="form-control" onchange="calcWheatQty()">
                            <option value="">Select</option>
                            <?php $bag_types->data_seek(0); while ($b = $bag_types->fetch_assoc()): ?>
                            <option value="<?= $b['id'] ?>" data-weight="<?= $b['bag_weight_kg'] ?? 50 ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card bg-light mb-3">
                <div class="card-header"><strong>Output Products</strong> <button type="button" class="btn btn-sm btn-success ml-2" onclick="addRow()"><i class="fas fa-plus"></i> Add Product</button></div>
                <div class="card-body">
                    <table class="table table-bordered" id="prodTable">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-right">Qty (KG)</th>
                                <th class="text-right">Rate/KG</th>
                                <th width="50">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <select name="product_id[]" class="form-control form-control-sm" required>
                                        <option value="">Select</option>
                                        <?php $products->data_seek(0); while ($pr = $products->fetch_assoc()): ?>
                                        <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['name']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </td>
                                <td><input type="text" name="qty[]" class="form-control form-control-sm text-right" placeholder="0" oninput="calcExtraction()"></td>
                                <td><input type="text" name="rate[]" class="form-control form-control-sm text-right" placeholder="0.00"></td>
                                <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();calcExtraction()"><i class="fas fa-times"></i></button></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="table-active">
                                <th class="text-right">Total Output:</th>
                                <th class="text-right" id="totalOutput">0.000 KG</th>
                                <th></th>
                                <th></th>
                            </tr>
                            <tr class="table-info">
                                <th class="text-right">Extraction Rate:</th>
                                <th class="text-right" id="extractionRate">0.00%</th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Production</button>
        </form>
    </div>
</div>

<script>
function calcWheatQty() {
    var bagQty = parseFloat($('#bagQty').val()) || 0;
    var bagWeight = parseFloat($('#bagTypeId option:selected').data('weight')) || 50;
    var wheat = bagQty * bagWeight;
    $('#wheatQtyDisplay').val(wheat.toFixed(3));
    $('#wheatQtyHidden').val(wheat.toFixed(3));
    calcExtraction();
}

function addRow() {
    var html = '<tr>' +
        '<td><select name="product_id[]" class="form-control form-control-sm" required><option value="">Select</option><?php $products->data_seek(0); while ($pr = $products->fetch_assoc()): ?><option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['name']) ?></option><?php endwhile; ?></select></td>' +
        '<td><input type="text" name="qty[]" class="form-control form-control-sm text-right" placeholder="0" oninput="calcExtraction()"></td>' +
        '<td><input type="text" name="rate[]" class="form-control form-control-sm text-right" placeholder="0.00"></td>' +
        '<td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'tr\').remove();calcExtraction()"><i class="fas fa-times"></i></button></td>' +
        '</tr>';
    $('#prodTable tbody').append(html);
}

function calcExtraction() {
    var wheat = parseFloat($('#wheatQtyHidden').val()) || 0;
    var total = 0;
    $('input[name="qty[]"]').each(function() {
        total += parseFloat($(this).val()) || 0;
    });
    $('#totalOutput').text(total.toFixed(3) + ' KG');
    var ext = wheat > 0 ? ((total / wheat) * 100).toFixed(2) : '0.00';
    $('#extractionRate').text(ext + '%');
}
</script>

<?php include '../../includes/footer.php'; ?>
