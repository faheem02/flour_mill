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
    $wheat_qty = str_replace(',', '', $_POST['wheat_qty']);
    $wheat_purchase_id = (int)$_POST['wheat_purchase_id'];
    $notes = sanitize($_POST['notes']);

    $product_ids = $_POST['product_id'];
    $qtys = $_POST['qty'];
    $rates = $_POST['rate'];

    if ($wheat_qty <= 0) { $error = "Wheat quantity is required."; }
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
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sidddds", $date, $wheat_qty, $wheat_purchase_id, $total_output, $extraction, $wastage, $notes);
            $stmt->execute();
            $prod_id = $conn->insert_id;

            $stmt2 = $conn->prepare("INSERT INTO production_items (production_id, product_id, qty, rate_per_kg) VALUES (?, ?, ?, ?)");
            $stmt3 = $conn->prepare("INSERT INTO stock_ledger (product_id, date, type, reference_id, qty_in, balance_qty, notes)
                VALUES (?, ?, 'production', ?, ?, (SELECT COALESCE(stock_qty,0) FROM products WHERE id=?), ?)");

            foreach ($product_ids as $i => $pid) {
                $q = str_replace(',', '', $qtys[$i]);
                $r = str_replace(',', '', $rates[$i]);
                if ($q <= 0) continue;

                $stmt2->bind_param("iidd", $prod_id, $pid, $q, $r);
                $stmt2->execute();

                // Update product stock
                $conn->query("UPDATE products SET stock_qty = stock_qty + $q WHERE id = $pid");

                // Update product cost rate
                if ($r > 0) {
                    $conn->query("UPDATE products SET cost_rate = $r WHERE id = $pid");
                }

                // Stock ledger entry
                $notes_sl = "Production #$prod_id";
                $stmt3->bind_param("isiiis", $pid, $date, $prod_id, $q, $pid, $notes_sl);
                $stmt3->execute();
            }

            // Auto journal entry: Debit Stock (products), Credit COGS reduction / Wheat consumed
            // Transfer wheat cost to product cost
            if ($wheat_purchase_id > 0) {
                autoJournalEntry($date, "Production #$prod_id - Wheat crush ($wheat_qty KG)",
                    [5 => $total_output * 40], // Stock inventory (estimated cost)
                    [17 => $total_output * 40], // COGS / Wheat consumption
                    $_SESSION['user_id']
                );
            }

            $conn->commit();
            $success = "Production recorded successfully! Extraction rate: $extraction%";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$products = $conn->query("SELECT id, name FROM products WHERE status='active' ORDER BY name");
$purchases = $conn->query("SELECT id, date, total_qty, invoice_no FROM purchases ORDER BY date DESC LIMIT 50");
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
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Wheat Quantity (KG) <span class="text-danger">*</span></label>
                        <input type="text" name="wheat_qty" class="form-control" placeholder="0" required oninput="calcExtraction()">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Wheat Purchase (Optional)</label>
                        <select name="wheat_purchase_id" class="form-control">
                            <option value="">-- Select --</option>
                            <?php while ($p = $purchases->fetch_assoc()): ?>
                            <option value="<?= $p['id'] ?>"><?= $p['date'] ?> - <?= htmlspecialchars($p['invoice_no']) ?> (<?= qty($p['total_qty']) ?> KG)</option>
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
    var wheat = parseFloat($('input[name="wheat_qty"]').val()) || 0;
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
