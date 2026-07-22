<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'sale_list';
$page_title = 'Sales List';
require_once '../../includes/db.php';
include '../../includes/header.php';

$result = $conn->query("SELECT s.*, c.name as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id=c.id ORDER BY s.date DESC, s.id DESC");
?>
<?php $flash = flashMessage(); if ($flash): ?>
<div class="alert alert-success alert-auto"><?= $flash ?></div>
<?php endif; ?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-cash-register mr-1"></i> Sales List</h1>
    <div>
        <a href="add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle mr-1"></i> New Sale</a>
        <a href="print_list.php" class="btn btn-sm btn-info" target="_blank"><i class="fas fa-print mr-1"></i> Print Register</a>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Sales</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th class="text-center">Type</th>
                        <th class="text-right">Qty (KG)</th>
                        <th class="text-right">Freight</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Due</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('d-m-Y', strtotime($row['date'])) ?></td>
                        <td><strong><?= htmlspecialchars($row['invoice_no']) ?></strong></td>
                        <td><a href="../customers/ledger.php?customer_id=<?= $row['customer_id'] ?>"><?= htmlspecialchars($row['customer_name']) ?></a></td>
                        <td class="text-center">
                            <?php if ($row['delivery_type'] === 'delivery'): ?>
                                <span class="badge badge-primary"><i class="fas fa-truck fa-sm"></i> Delivery</span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><i class="fas fa-store fa-sm"></i> Pickup</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?= qty($row['total_qty']) ?></td>
                        <td class="text-right"><?= ($row['freight_amount'] ?? 0) > 0 ? money($row['freight_amount']) : '-' ?></td>
                        <td class="text-right font-weight-bold"><?= money($row['total_amount']) ?></td>
                        <td class="text-right"><?= money($row['paid_amount']) ?></td>
                        <td class="text-right text-danger font-weight-bold"><?= money($row['total_amount'] - $row['paid_amount']) ?></td>
                        <td class="text-nowrap no-print">
                            <div class="btn-group btn-group-sm">
                                <a href="#" class="btn btn-info" title="View" onclick="viewSale(<?= $row['id'] ?>);return false"><i class="fas fa-eye"></i></a>
                                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-danger" title="Delete" onclick="return confirm('Delete this sale? All stock will be restored.')"><i class="fas fa-trash"></i></a>
                                <a href="print.php?id=<?= $row['id'] ?>" class="btn btn-secondary" title="Print" target="_blank"><i class="fas fa-print"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--navy);color:#fff">
                <h5 class="modal-title"><i class="fas fa-cash-register mr-1"></i> Sale Invoice</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <a href="#" class="btn btn-primary" id="modalPrintBtn" target="_blank"><i class="fas fa-print mr-1"></i> Print</a>
            </div>
        </div>
    </div>
</div>

<style>
.btn-group-sm > .btn { padding: 4px 8px !important; font-size: 12px !important; }
#viewModal .detail-box { background: #f8f9fc; border-radius: 6px; padding: 15px; margin-bottom: 15px; }
#viewModal .detail-box h6 { border-bottom: 2px solid var(--gold); padding-bottom: 6px; margin-bottom: 12px; color: #1B2A4A; font-weight: 700; }
#viewModal .detail-label { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
#viewModal .detail-value { font-size: 15px; font-weight: 600; color: #1B2A4A; }
#viewModal .financial-row { border-bottom: 1px solid #e3e6f0; padding: 6px 0; }
#viewModal .financial-row:last-child { border-bottom: none; font-size: 16px; }
</style>

<script>
function viewSale(id) {
    $('#modalBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
    $('#modalPrintBtn').attr('href', 'print.php?id=' + id);
    $('#viewModal').modal('show');
    $.get('sale_json.php?id=' + id, function(d) {
        if (d.error) { $('#modalBody').html('<div class="alert alert-danger">' + d.error + '</div>'); return; }

        var h = '';
        h += '<div class="text-center mb-3">';
        h += '<h4 class="font-weight-bold" style="color:#1B2A4A;"><i class="fas fa-cash-register mr-1"></i> Sale Invoice</h4>';
        h += '<span class="text-muted">' + d.date + ' | ' + (d.invoice_no || '-') + '</span>';
        h += '</div>';

        h += '<div class="row">';
        h += '<div class="col-md-6"><div class="detail-box">';
        h += '<h6><i class="fas fa-user mr-1"></i> Customer Info</h6>';
        h += '<div class="row"><div class="col-5 detail-label">Customer</div><div class="col-7 detail-value">' + (d.customer_name || '-') + '</div></div>';
        h += '<div class="row"><div class="col-5 detail-label">Type</div><div class="col-7 detail-value">' + (d.delivery_type === 'delivery' ? '<span class="badge badge-primary"><i class="fas fa-truck fa-sm"></i> Delivery</span>' : '<span class="badge badge-secondary"><i class="fas fa-store fa-sm"></i> Pickup</span>') + '</div></div>';
        h += '<div class="row"><div class="col-5 detail-label">Warehouse</div><div class="col-7 detail-value">' + (d.warehouse_name || '-') + '</div></div>';
        h += '</div></div>';

        h += '<div class="col-md-6"><div class="detail-box">';
        h += '<h6><i class="fas fa-truck mr-1"></i> Transport</h6>';
        h += '<div class="row"><div class="col-5 detail-label">Vehicle No</div><div class="col-7 detail-value">' + (d.vehicle_no || '-') + '</div></div>';
        h += '<div class="row"><div class="col-5 detail-label">Driver</div><div class="col-7 detail-value">' + (d.driver_name || '-') + '</div></div>';
        h += '<div class="row"><div class="col-5 detail-label">Mobile</div><div class="col-7 detail-value">' + (d.driver_mobile || '-') + '</div></div>';
        h += '</div></div>';
        h += '</div>';

        h += '<div class="detail-box">';
        h += '<h6><i class="fas fa-boxes mr-1"></i> Products</h6>';
        h += '<table class="table table-sm table-bordered mb-0"><thead class="thead-dark"><tr><th>Product</th><th class="text-right">Qty</th><th class="text-right">Rate</th><th class="text-right">Amount</th></tr></thead><tbody>';
        if (d.items && d.items.length) {
            d.items.forEach(function(it) {
                h += '<tr><td>' + it.name + '</td><td class="text-right">' + parseFloat(it.qty).toLocaleString(undefined, {minimumFractionDigits:3}) + '</td><td class="text-right">' + parseFloat(it.rate).toLocaleString(undefined, {minimumFractionDigits:2}) + '</td><td class="text-right font-weight-bold">' + parseFloat(it.amount).toLocaleString(undefined, {minimumFractionDigits:2}) + '</td></tr>';
            });
        }
        h += '</tbody></table></div>';

        h += '<div class="detail-box">';
        h += '<h6><i class="fas fa-money-bill-wave mr-1"></i> Financial Summary</h6>';
        h += '<div class="financial-row d-flex justify-content-between"><span>Products Total</span><strong>' + parseFloat(d.products_total).toLocaleString(undefined, {minimumFractionDigits:2}) + '</strong></div>';
        h += '<div class="financial-row d-flex justify-content-between"><span>Freight + Load</span><strong>' + parseFloat(d.freight_amount || 0).toLocaleString(undefined, {minimumFractionDigits:2}) + '</strong></div>';
        h += '<div class="financial-row d-flex justify-content-between" style="border-top:2px solid #1B2A4A;padding-top:8px;margin-top:4px;"><span class="font-weight-bold" style="font-size:16px;">Grand Total</span><span class="font-weight-bold" style="font-size:18px;color:#1B2A4A;">' + parseFloat(d.total_amount).toLocaleString(undefined, {minimumFractionDigits:2}) + '</span></div>';
        h += '<div class="financial-row d-flex justify-content-between"><span>Paid Amount</span><strong class="text-success">' + parseFloat(d.paid_amount).toLocaleString(undefined, {minimumFractionDigits:2}) + '</strong></div>';
        var due = parseFloat(d.total_amount) - parseFloat(d.paid_amount);
        h += '<div class="financial-row d-flex justify-content-between"><span class="font-weight-bold">Balance Due</span><span class="font-weight-bold ' + (due > 0 ? 'text-danger' : 'text-success') + '" style="font-size:16px;">' + due.toLocaleString(undefined, {minimumFractionDigits:2}) + '</span></div>';
        h += '</div>';

        if (d.notes) {
            h += '<div class="detail-box"><h6><i class="fas fa-sticky-note mr-1"></i> Notes</h6><p class="mb-0">' + d.notes + '</p></div>';
        }

        $('#modalBody').html(h);
    }, 'json').fail(function() {
        $('#modalBody').html('<div class="alert alert-danger">Failed to load data.</div>');
    });
}
</script>

<?php include '../../includes/footer.php'; ?>
