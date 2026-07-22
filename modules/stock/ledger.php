<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'stock_ledger';
$page_title = 'Stock Ledger';
require_once '../../includes/db.php';
include '../../includes/header.php';

$pid = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$products = $conn->query("SELECT id, name FROM products WHERE status='active' ORDER BY name");

$product_name = '';
if ($pid > 0) {
    $p = $conn->query("SELECT name FROM products WHERE id=$pid")->fetch_assoc();
    $product_name = $p['name'] ?? '';
}

$result = $pid > 0
    ? $conn->query("SELECT sl.*, pr.name as product_name FROM stock_ledger sl LEFT JOIN products pr ON sl.product_id=pr.id WHERE sl.product_id=$pid ORDER BY sl.date ASC, sl.id ASC")
    : $conn->query("SELECT sl.*, pr.name as product_name FROM stock_ledger sl LEFT JOIN products pr ON sl.product_id=pr.id ORDER BY sl.product_id, sl.date ASC, sl.id ASC");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-1"></i> Stock Ledger</h1>
    <div>
        <a href="adjustment.php" class="btn btn-sm btn-warning"><i class="fas fa-sliders-h mr-1"></i> Adjustment</a>
        <a href="print_ledger.php<?= $pid ? '?product_id='.$pid : '' ?>" class="btn btn-sm btn-info ml-1" target="_blank"><i class="fas fa-print mr-1"></i> Print</a>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header">
        <form method="GET" class="form-inline">
            <label class="mr-2">Select Product:</label>
            <select name="product_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="">All Products</option>
                <?php while ($p = $products->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>" <?= $pid==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option>
                <?php endwhile; ?>
            </select>
            <?php if ($pid > 0): ?>
                <span class="ml-2 font-weight-bold text-primary">Current Stock: <?php $st = $conn->query("SELECT stock_qty FROM products WHERE id=$pid")->fetch_assoc(); echo qty($st['stock_qty']); ?> KG</span>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th class="text-right">In (KG)</th>
                        <th class="text-right">Out (KG)</th>
                        <th class="text-right">Balance (KG)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['date'] ?></td>
                        <td><?= htmlspecialchars($row['product_name']) ?></td>
                        <td><span class="badge badge-<?= $row['type']=='production'||$row['type']=='sale_return'||$row['type']=='opening'?'success':($row['type']=='sale'?'danger':'warning') ?>"><?= ucfirst(str_replace('_',' ',$row['type'])) ?></span></td>
                        <td class="text-right text-success font-weight-bold"><?= $row['qty_in'] > 0 ? qty($row['qty_in']) : '-' ?></td>
                        <td class="text-right text-danger font-weight-bold"><?= $row['qty_out'] > 0 ? qty($row['qty_out']) : '-' ?></td>
                        <td class="text-right font-weight-bold"><?= qty($row['balance_qty']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
