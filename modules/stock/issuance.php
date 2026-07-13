<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'issuance';
$page_title = 'Issuance / Transfer';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$error = $success = '';

$wheat = $conn->query("SELECT id FROM products WHERE name = 'Wheat (Gandam)' LIMIT 1")->fetch_assoc();
$wheat_pid = $wheat ? $wheat['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_stock'])) {
    $from_wh = (int)$_POST['from_warehouse_id'];
    $to_wh   = (int)$_POST['to_warehouse_id'];
    $bag_qty = (int)str_replace(',', '', $_POST['bag_qty'] ?? 0);
    $qty  = str_replace(',', '', $_POST['qty']);
    $date = $_POST['date'];
    $notes = sanitize($_POST['notes']);

    if ($bag_qty > 0 && ($qty <= 0 || empty($_POST['qty']))) {
        $qty = $bag_qty * 50;
    }

    if ($from_wh <= 0 || $to_wh <= 0 || $wheat_pid <= 0 || $qty <= 0) {
        $error = "Please fill all required fields.";
    } elseif ($from_wh === $to_wh) {
        $error = "Source and destination warehouse cannot be the same.";
    } else {
        $stock = $conn->query("SELECT COALESCE(stock_qty,0) AS s FROM warehouse_stock WHERE warehouse_id = $from_wh AND product_id = $wheat_pid")->fetch_assoc();
        if (!$stock || $stock['s'] < $qty) {
            $error = "Insufficient stock. Available: " . qty($stock['s'] ?? 0) . " KG";
        } else {
            $conn->begin_transaction();
            try {
                $conn->query("INSERT INTO warehouse_transfers (date, from_warehouse_id, to_warehouse_id, product_id, qty, notes)
                    VALUES ('$date', $from_wh, $to_wh, $wheat_pid, $qty, '$notes')");
                $transfer_id = $conn->insert_id;

                $conn->query("UPDATE warehouse_stock SET stock_qty = stock_qty - $qty WHERE warehouse_id = $from_wh AND product_id = $wheat_pid");
                $conn->query("INSERT INTO warehouse_stock (warehouse_id, product_id, stock_qty)
                    VALUES ($to_wh, $wheat_pid, $qty) ON DUPLICATE KEY UPDATE stock_qty = stock_qty + $qty");

                $conn->query("INSERT INTO stock_ledger (product_id, date, type, reference_id, warehouse_id, qty_out, balance_qty, notes)
                    VALUES ($wheat_pid, '$date', 'transfer', $transfer_id, $from_wh, $qty,
                        (SELECT COALESCE(stock_qty,0) FROM warehouse_stock WHERE warehouse_id=$from_wh AND product_id=$wheat_pid),
                        'Issued to other warehouse')");
                $conn->query("INSERT INTO stock_ledger (product_id, date, type, reference_id, warehouse_id, qty_in, balance_qty, notes)
                    VALUES ($wheat_pid, '$date', 'transfer', $transfer_id, $to_wh, $qty,
                        (SELECT COALESCE(stock_qty,0) FROM warehouse_stock WHERE warehouse_id=$to_wh AND product_id=$wheat_pid),
                        'Received from other warehouse')");

                $conn->commit();
                $success = "Stock transferred successfully.";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

$warehouses = $conn->query("SELECT id, name, type FROM warehouses WHERE status='active' ORDER BY name");

$wh_stock = $conn->query("
    SELECT ws.warehouse_id, ws.product_id, ws.stock_qty,
           w.name AS wh_name, p.name AS prod_name
    FROM warehouse_stock ws
    JOIN warehouses w ON w.id = ws.warehouse_id
    JOIN products p ON p.id = ws.product_id
    WHERE ws.stock_qty > 0
    ORDER BY w.name, p.name
");

$transfers = $conn->query("
    SELECT wt.*, w1.name AS from_name, w2.name AS to_name, p.name AS prod_name
    FROM warehouse_transfers wt
    JOIN warehouses w1 ON w1.id = wt.from_warehouse_id
    JOIN warehouses w2 ON w2.id = wt.to_warehouse_id
    JOIN products p ON p.id = wt.product_id
    ORDER BY wt.date DESC, wt.id DESC
    LIMIT 50
");

$wh_stock_map = [];
if ($wheat_pid > 0) {
    $ws = $conn->query("SELECT warehouse_id, COALESCE(stock_qty,0) AS s FROM warehouse_stock WHERE product_id = $wheat_pid");
    while ($r = $ws->fetch_assoc()) {
        $wh_stock_map[$r['warehouse_id']] = $r['s'];
    }
}

include '../../includes/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-exchange-alt mr-1"></i> Wheat Transfer</h1>
    <a href="warehouse_stock.php" class="btn btn-sm btn-info"><i class="fas fa-boxes mr-1"></i> Warehouse Stock</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<div class="row">
    <!-- Transfer Form -->
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header"><h6 class="font-weight-bold m-0"><i class="fas fa-paper-plane mr-1"></i> New Transfer</h6></div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>From Warehouse <span class="text-danger">*</span></label>
                        <select name="from_warehouse_id" id="fromWh" class="form-control" required>
                            <option value="">Select Source</option>
                            <?php $warehouses->data_seek(0); while ($w = $warehouses->fetch_assoc()): ?>
                            <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?> (<?= ucfirst($w['type']) ?>)</option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted" id="availableStock"></small>
                    </div>
                    <div class="form-group">
                        <label>To Warehouse <span class="text-danger">*</span></label>
                        <select name="to_warehouse_id" id="toWh" class="form-control" required>
                            <option value="">Select Destination</option>
                            <?php $warehouses->data_seek(0); while ($w = $warehouses->fetch_assoc()): ?>
                            <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?> (<?= ucfirst($w['type']) ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label>Bag Quantity</label>
                                <input type="number" name="bag_qty" id="bagQtyInput" class="form-control" placeholder="0" min="0" oninput="calcFromBags()">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label>Quantity (KG) <span class="text-danger">*</span></label>
                                <input type="text" name="qty" id="qtyInput" class="form-control" placeholder="0" required oninput="this.value = this.value.replace(/[^0-9.]/g,'')">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional"></textarea>
                    </div>
                    <button type="submit" name="transfer_stock" class="btn btn-primary btn-block"><i class="fas fa-exchange-alt mr-1"></i> Transfer Wheat</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Current Stock + Transfer History -->
    <div class="col-lg-8">
        <!-- Current Stock Summary -->
        <div class="card shadow mb-4">
            <div class="card-header"><h6 class="font-weight-bold m-0"><i class="fas fa-boxes mr-1"></i> Current Warehouse Stock</h6></div>
            <div class="card-body">
                <?php if ($wh_stock->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm" width="100%">
                        <thead class="thead-dark">
                            <tr>
                                <th>Warehouse</th>
                                <th>Product</th>
                                <th class="text-right">Stock (KG)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($s = $wh_stock->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['wh_name']) ?></td>
                                <td><?= htmlspecialchars($s['prod_name']) ?></td>
                                <td class="text-right font-weight-bold text-success"><?= qty($s['stock_qty']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No stock in any warehouse.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Transfer History -->
        <div class="card shadow mb-4">
            <div class="card-header"><h6 class="font-weight-bold m-0"><i class="fas fa-history mr-1"></i> Transfer History</h6></div>
            <div class="card-body">
                <?php if ($transfers->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%">
                        <thead class="thead-dark">
                            <tr>
                                <th>Date</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Product</th>
                                <th class="text-right">Qty (KG)</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($t = $transfers->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d-M-Y', strtotime($t['date'])) ?></td>
                                <td><span class="badge badge-danger"><?= htmlspecialchars($t['from_name']) ?></span></td>
                                <td><span class="badge badge-success"><?= htmlspecialchars($t['to_name']) ?></span></td>
                                <td><?= htmlspecialchars($t['prod_name']) ?></td>
                                <td class="text-right font-weight-bold"><?= qty($t['qty']) ?></td>
                                <td><?= htmlspecialchars($t['notes'] ?? '') ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No transfers yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
var whStockMap = <?= json_encode($wh_stock_map) ?>;

function calcFromBags() {
    var bagQty = parseFloat($('#bagQtyInput').val()) || 0;
    if (bagQty > 0) {
        $('#qtyInput').val((bagQty * 50).toFixed(3));
    }
}

$(document).ready(function() {
    function checkStock() {
        var wh = $('#fromWh').val();
        if (wh && whStockMap[wh] !== undefined) {
            var s = parseFloat(whStockMap[wh]) || 0;
            $('#availableStock').html(s > 0
                ? '<span class="text-success">Available: ' + s.toLocaleString() + ' KG</span>'
                : '<span class="text-danger">No wheat stock in this warehouse</span>');
        } else if (wh) {
            $('#availableStock').html('<span class="text-danger">No wheat stock in this warehouse</span>');
        } else {
            $('#availableStock').html('');
        }
    }
    $('#fromWh').on('change', checkStock);
    checkStock();
});
</script>

<?php include '../../includes/footer.php'; ?>
