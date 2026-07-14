<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$page_title = 'Sale Invoice';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$id = (int)$_GET['id'];
$row = $conn->query("SELECT s.*, c.name as customer_name, c.phone as cust_phone, c.address as cust_address,
    w.name as warehouse_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN warehouses w ON s.warehouse_id = w.id
    WHERE s.id = $id")->fetch_assoc();

if (!$row) { die("Sale not found"); }

$items = $conn->query("SELECT si.*, p.name as product_name FROM sale_items si JOIN products p ON si.product_id=p.id WHERE si.sale_id=$id");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sale Invoice - <?= $row['invoice_no'] ?></title>
    <link href="<?= $asset_path ?>vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Courier New', monospace; font-size: 13px; padding: 15px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 12px; }
        .header h3 { margin: 0; color: #1B2A4A; }
        .header small { color: #666; }
        .type-badge { display:inline-block; padding:2px 10px; border-radius:4px; font-size:11px; font-weight:700; color:#fff; }
        .type-delivery { background:#4e73df; }
        .type-pickup { background:#858796; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        td, th { padding: 5px 8px; border: 1px solid #ccc; }
        th { background: #f0f0f0; font-weight: bold; }
        .label { font-weight: bold; width: 180px; }
        .text-right { text-align: right; }
        .section-title { background: #f0f0f0; font-weight: bold; text-align: center; padding: 5px; margin-top: 10px; margin-bottom: 5px; border: 1px solid #000; }
        .total { font-weight: bold; border-top: 2px solid #000 !important; }
        .grand { font-size: 15px; font-weight: bold; }
        .financial-row td { border-bottom: 1px solid #e3e6f0; }
        .footer-note { margin-top: 15px; font-size: 11px; color: #666; border-top: 1px dashed #999; padding-top: 8px; }
        @media print {
            body { padding: 0; font-size: 12px; }
            .no-print { display: none !important; }
            .header { border-bottom-color: #000; }
        }
    </style>
</head>
<body>
    <div class="no-print mb-3">
        <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
        <a href="list.php" class="btn btn-secondary btn-sm">Back to List</a>
    </div>

    <div class="header">
        <h3>FLOUR MILL - SALES INVOICE</h3>
        <small>Invoice #<?= htmlspecialchars($row['invoice_no']) ?> | Date: <?= date('d-m-Y', strtotime($row['date'])) ?></small><br>
        <span class="type-badge type-<?= $row['delivery_type'] ?>"><?= ucfirst($row['delivery_type']) ?></span>
    </div>

    <table>
        <tr>
            <td class="label">Customer:</td><td><?= htmlspecialchars($row['customer_name']) ?></td>
            <td class="label">Warehouse:</td><td><?= htmlspecialchars($row['warehouse_name']) ?></td>
        </tr>
        <?php if ($row['vehicle_no'] || $row['driver_name']): ?>
        <tr>
            <td class="label">Vehicle No:</td><td><?= htmlspecialchars($row['vehicle_no'] ?? '-') ?></td>
            <td class="label">Driver:</td><td><?= htmlspecialchars($row['driver_name'] ?? '-') ?> <?= $row['driver_mobile'] ? '(' . htmlspecialchars($row['driver_mobile']) . ')' : '' ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="section-title">PRODUCTS</div>
    <table>
        <thead>
            <tr><th>#</th><th>Product</th><th class="text-right">Qty (KG)</th><th class="text-right">Rate/KG</th><th class="text-right">Amount</th></tr>
        </thead>
        <tbody>
            <?php $i = 1; $total_qty = 0; $products_total = 0; while ($it = $items->fetch_assoc()): $total_qty += $it['qty']; $products_total += $it['amount']; ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($it['product_name']) ?></td>
                <td class="text-right"><?= qty($it['qty']) ?></td>
                <td class="text-right"><?= money($it['rate']) ?></td>
                <td class="text-right"><?= money($it['amount']) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr class="total">
                <th colspan="2">Total</th>
                <th class="text-right"><?= qty($total_qty) ?> KG</th>
                <th></th>
                <th class="text-right"><?= money($products_total) ?></th>
            </tr>
        </tfoot>
    </table>

    <div class="section-title">FINANCIAL SUMMARY</div>
    <table>
        <tr><td class="label">Products Total:</td><td class="text-right"><?= money($products_total) ?></td></tr>
        <?php if ($row['freight_amount'] > 0): ?>
        <tr><td class="label">Freight + Load:</td><td class="text-right"><?= money($row['freight_amount']) ?></td></tr>
        <?php endif; ?>
        <tr class="grand total"><td class="label">Grand Total:</td><td class="text-right"><?= money($row['total_amount']) ?></td></tr>
        <tr><td class="label">Paid Amount:</td><td class="text-right text-success"><?= money($row['paid_amount']) ?></td></tr>
        <?php $due = $row['total_amount'] - $row['paid_amount']; ?>
        <tr class="grand"><td class="label">Balance Due:</td><td class="text-right <?= $due > 0 ? 'text-danger' : 'text-success' ?>"><?= money($due) ?></td></tr>
    </table>

    <?php if ($row['notes']): ?>
    <div class="section-title">NOTES</div>
    <p><?= nl2br(htmlspecialchars($row['notes'])) ?></p>
    <?php endif; ?>

    <div class="footer-note">
        This is a computer-generated invoice. | Printed on <?= date('d-m-Y H:i:s') ?>
    </div>
</body>
</html>
