<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'sale_return';
$page_title = 'Sale Return';
require_once '../../includes/db.php';
include '../../includes/header.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sale_id = (int)$_POST['sale_id'];
    $product_id = (int)$_POST['product_id'];
    $qty = str_replace(',', '', $_POST['qty']);
    $amount = str_replace(',', '', $_POST['amount']);
    $date = $_POST['date'];
    $reason = sanitize($_POST['reason']);

    $sale = $conn->query("SELECT * FROM sales WHERE id=$sale_id")->fetch_assoc();
    if (!$sale) { $error = "Sale not found."; }
    elseif ($qty <= 0) { $error = "Qty must be greater than zero."; }
    else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO sale_returns (sale_id, customer_id, date, product_id, qty, amount, reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisidds", $sale_id, $sale['customer_id'], $date, $product_id, $qty, $amount, $reason);
            $stmt->execute();

            // Increase stock
            $conn->query("UPDATE products SET stock_qty = stock_qty + $qty WHERE id = $product_id");

            // Stock ledger
            $conn->query("INSERT INTO stock_ledger (product_id, date, type, reference_id, qty_in, balance_qty, notes)
                VALUES ($product_id, '$date', 'sale_return', $sale_id, $qty, (SELECT COALESCE(stock_qty,0) FROM products WHERE id=$product_id), 'Return from sale #$sale_id')");

            // Customer ledger: credit (reduce their debit)
            $conn->query("INSERT INTO customer_ledger (customer_id, date, type, reference_id, credit, balance, notes)
                VALUES ({$sale['customer_id']}, '$date', 'return', $sale_id, $amount, (SELECT COALESCE(balance,0)-$amount FROM customers WHERE id={$sale['customer_id']}), 'Sale return - $reason')");
            $conn->query("UPDATE customers SET balance = balance - $amount WHERE id = {$sale['customer_id']}");

            // Reverse journal entry
            autoJournalEntry($date, "Sale return from customer (Sale #$sale_id)", [13 => $amount], [5 => $amount], $_SESSION['user_id']);

            $conn->commit();
            $success = "Return processed successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$sales = $conn->query("SELECT s.id, s.date, s.invoice_no, c.name as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id WHERE s.status='completed' ORDER BY s.date DESC LIMIT 100");
$products = $conn->query("SELECT id, name FROM products WHERE status='active'");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-undo-alt mr-1"></i> Sale Return</h1>
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-auto"><?= $success ?></div><?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Return Product from Customer</h6></div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Sale Invoice <span class="text-danger">*</span></label>
                        <select name="sale_id" class="form-control" required>
                            <option value="">Select Sale</option>
                            <?php while ($s = $sales->fetch_assoc()): ?>
                            <option value="<?= $s['id'] ?>"><?= $s['date'] ?> - <?= htmlspecialchars($s['invoice_no']) ?> (<?= htmlspecialchars($s['customer_name']) ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-control" required>
                            <option value="">Select Product</option>
                            <?php while ($p = $products->fetch_assoc()): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Qty (KG) <span class="text-danger">*</span></label>
                        <input type="text" name="qty" class="form-control" placeholder="0" required oninput="this.value = this.value.replace(/[^0-9.]/g,'')">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Amount <span class="text-danger">*</span></label>
                        <input type="text" name="amount" class="form-control" placeholder="0.00" required oninput="this.value = this.value.replace(/[^0-9.]/g,'')">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Reason</label>
                        <input type="text" name="reason" class="form-control" placeholder="Reason for return">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-warning"><i class="fas fa-undo-alt mr-1"></i> Process Return</button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
