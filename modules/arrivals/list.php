<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'arrival_list';
$page_title = 'Arrival Register';
require_once '../../includes/db.php';
include '../../includes/header.php';

$result = $conn->query("SELECT a.*, w.name as warehouse_name, b.booking_no, f.name as farmer_name, d.name as driver_name, bt.name as bag_type_name
    FROM wheat_arrivals a
    LEFT JOIN warehouses w ON a.warehouse_id = w.id
    LEFT JOIN bookings b ON a.booking_id = b.id
    LEFT JOIN farmers f ON b.farmer_id = f.id
    LEFT JOIN drivers d ON a.driver_id = d.id
    LEFT JOIN bag_types bt ON a.bag_type_id = bt.id
    ORDER BY a.date DESC, a.id DESC");
?>
<?php $flash = flashMessage(); if ($flash): ?>
<div class="alert alert-success alert-auto"><?= $flash ?></div>
<?php endif; ?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-truck-loading mr-1"></i> Arrival Register</h1>
    <div>
        <a href="add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle mr-1"></i> New Arrival</a>
        <a href="print_list.php" class="btn btn-sm btn-info" target="_blank"><i class="fas fa-print mr-1"></i> Print Register</a>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="font-weight-bold m-0 text-primary"><i class="fas fa-list mr-1"></i> All Arrivals</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Booking</th>
                        <th>Farmer</th>
                        <th>Vehicle#</th>
                        <th>Bags</th>
                        <th class="text-right">Net KG</th>
                        <th class="text-right">Actual KG</th>
                        <th class="text-right">Diff</th>
                        <th class="text-right">Gross</th>
                        <th class="text-right">Charges</th>
                        <th class="text-right">Net Amt</th>
                        <th class="text-right">Paid</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $total_bags = $total_net = $total_actual = $total_gross = $total_charges = $total_net_amt = $total_paid = 0; ?>
                    <?php while ($row = $result->fetch_assoc()):
                        $charges = ($row['bag_amount']??0) + ($row['labour_charges']??0) + ($row['transport_charges']??0) + ($row['other_charges']??0);
                        $total_bags += $row['num_bags'];
                        $total_net += $row['net_weight'];
                        $total_actual += $row['actual_weight'];
                        $total_gross += $row['gross_amount'];
                        $total_charges += $charges;
                        $total_net_amt += $row['net_amount'];
                        $total_paid += ($row['payment_now'] ?? 0);
                    ?>
                    <tr>
                        <td class="text-nowrap"><?= date('d-m-Y', strtotime($row['date'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['booking_no'] ?? '-') ?></strong>
                            <?php if ($row['moisture_pct']): ?>
                                <br><small class="text-info">Moist: <?= $row['moisture_pct'] ?>%</small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['farmer_name'] ?? '-') ?></td>
                        <td>
                            <?= htmlspecialchars($row['vehicle_no'] ?? '-') ?>
                            <?php if ($row['driver_name']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($row['driver_name']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $row['num_bags'] ?>
                            <?php if ($row['bag_type_name']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($row['bag_type_name']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?= qty($row['net_weight']) ?></td>
                        <td class="text-right"><?= qty($row['actual_weight']) ?></td>
                        <td class="text-right <?= ($row['weight_diff'] ?? 0) < 0 ? 'text-danger font-weight-bold' : 'text-success' ?>">
                            <?= ($row['weight_diff'] ?? 0) != 0 ? number_format($row['weight_diff'], 3) : '-' ?>
                        </td>
                        <td class="text-right"><?= money($row['gross_amount']) ?></td>
                        <td class="text-right" style="font-size:12px;">
                            <?php if ($charges > 0): ?>
                                <?= money($charges) ?>
                                <br><small class="text-muted">
                                    <?php $parts = []; if ($row['bag_amount']>0) $parts[]='Bag:'.money($row['bag_amount']); if ($row['labour_charges']>0) $parts[]='Lab:'.money($row['labour_charges']); if ($row['transport_charges']>0) $parts[]='Trns:'.money($row['transport_charges']); if ($row['other_charges']>0) $parts[]='Oth:'.money($row['other_charges']); echo implode(' | ', $parts); ?>
                                </small>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="text-right font-weight-bold text-success"><?= money($row['net_amount']) ?></td>
                        <td class="text-right font-weight-bold <?= ($row['payment_now'] ?? 0) > 0 ? 'text-primary' : '' ?>"><?= ($row['payment_now'] ?? 0) > 0 ? money($row['payment_now']) : '-' ?></td>
                        <td class="text-nowrap no-print">
                            <div class="btn-group btn-group-sm">
                                <a href="#" class="btn btn-info btn-action" title="View" onclick="viewArrival(<?= $row['id'] ?>);return false"><i class="fas fa-eye"></i></a>
                                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-action" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-action" title="Delete" onclick="return confirm('Delete this arrival?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot class="table-info font-weight-bold">
                    <tr>
                        <td colspan="4" class="text-right">Totals:</td>
                        <td><?= $total_bags ?></td>
                        <td class="text-right"><?= qty($total_net) ?></td>
                        <td class="text-right"><?= qty($total_actual) ?></td>
                        <td class="text-right"><?= qty($total_actual - $total_net) ?></td>
                        <td class="text-right"><?= money($total_gross) ?></td>
                        <td class="text-right"><?= money($total_charges) ?></td>
                        <td class="text-right"><?= money($total_net_amt) ?></td>
                        <td class="text-right"><?= money($total_paid) ?></td>
                        <td class="no-print"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title"><i class="fas fa-truck-loading mr-1"></i> Arrival Slip</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
            </div>
        </div>
    </div>
</div>

<style>
.btn-action { padding: 4px 10px !important; font-size: 12px !important; }
#viewModal .detail-box { background: #f8f9fc; border-radius: 6px; padding: 15px; margin-bottom: 15px; }
#viewModal .detail-box h6 { border-bottom: 2px solid var(--gold); padding-bottom: 6px; margin-bottom: 12px; color: #1B2A4A; font-weight: 700; }
#viewModal .detail-label { font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
#viewModal .detail-value { font-size: 15px; font-weight: 600; color: #1B2A4A; }
#viewModal .financial-row { border-bottom: 1px solid #e3e6f0; padding: 6px 0; }
#viewModal .financial-row:last-child { border-bottom: none; font-size: 16px; }
</style>

<script>
function viewArrival(id) {
    $('#modalBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
    $('#viewModal').modal('show');
    $.get('arrival_json.php?id=' + id, function(d) {
        if (d.error) { $('#modalBody').html('<div class="alert alert-danger">' + d.error + '</div>'); return; }

        var h = '';

        // Header
        h += '<div class="text-center mb-3">';
        h += '<h4 class="font-weight-bold" style="color:#1B2A4A;"><i class="fas fa-truck-loading mr-1"></i> Arrival Slip</h4>';
        h += '<span class="text-muted">' + d.date + ' | ' + (d.booking_no || '-') + '</span>';
        h += '</div>';

        // Section 1: Booking & Farmer
        h += '<div class="row">';
        h += '<div class="col-md-6"><div class="detail-box">';
        h += '<h6><i class="fas fa-file-invoice mr-1"></i> Booking Info</h6>';
        h += '<div class="row"><div class="col-5 detail-label">Booking No</div><div class="col-7 detail-value">' + (d.booking_no || '-') + '</div></div>';
        h += '<div class="row"><div class="col-5 detail-label">Farmer</div><div class="col-7 detail-value">' + (d.farmer_name || '-') + '</div></div>';
        h += '<div class="row"><div class="col-5 detail-label">Slip No</div><div class="col-7 detail-value">' + (d.weight_slip_no || '-') + '</div></div>';
        h += '</div></div>';

        h += '<div class="col-md-6"><div class="detail-box">';
        h += '<h6><i class="fas fa-truck mr-1"></i> Vehicle & Warehouse</h6>';
        h += '<div class="row"><div class="col-5 detail-label">Vehicle No</div><div class="col-7 detail-value">' + (d.vehicle_no || '-') + '</div></div>';
        h += '<div class="row"><div class="col-5 detail-label">Driver</div><div class="col-7 detail-value">' + (d.driver_name || '-') + '</div></div>';
        h += '<div class="row"><div class="col-5 detail-label">Warehouse</div><div class="col-7 detail-value">' + (d.warehouse_name || '-') + '</div></div>';
        h += '<div class="row"><div class="col-5 detail-label">Bag Type</div><div class="col-7 detail-value">' + (d.bag_type_name || '-') + '</div></div>';
        h += '</div></div>';
        h += '</div>';

        // Section 2: Weight Details
        h += '<div class="detail-box">';
        h += '<h6><i class="fas fa-weight mr-1"></i> Weight Details</h6>';
        h += '<div class="row">';

        h += '<div class="col-md-3 text-center border-right">';
        h += '<div class="detail-label">Bags</div>';
        h += '<div class="detail-value" style="font-size:22px;">' + d.num_bags + '</div>';
        h += '</div>';

        h += '<div class="col-md-3 text-center border-right">';
        h += '<div class="detail-label">Wheat KG</div>';
        h += '<div class="detail-value" style="font-size:22px;">' + parseFloat(d.gross_weight).toLocaleString(undefined, {minimumFractionDigits:3}) + '</div>';
        h += '<small class="text-muted">(' + d.num_bags + ' × 50)</small>';
        h += '</div>';

        h += '<div class="col-md-3 text-center border-right">';
        h += '<div class="detail-label">+ Katt</div>';
        h += '<div class="detail-value" style="font-size:22px;">' + parseFloat(d.katt_applied).toLocaleString(undefined, {minimumFractionDigits:3}) + '</div>';
        h += '</div>';

        h += '<div class="col-md-3 text-center">';
        h += '<div class="detail-label">Net Weight</div>';
        h += '<div class="detail-value" style="font-size:22px;color:#27ae60;">' + parseFloat(d.net_weight).toLocaleString(undefined, {minimumFractionDigits:3}) + ' KG</div>';
        h += '</div>';

        h += '</div></div>';

        // Section 3: Actual Weight
        h += '<div class="detail-box">';
        h += '<h6><i class="fas fa-balance-scale mr-1"></i> Actual Weighment</h6>';
        h += '<div class="row">';
        h += '<div class="col-3 text-center border-right"><div class="detail-label">Actual Weight</div><div class="detail-value" style="font-size:20px;">' + parseFloat(d.actual_weight).toLocaleString(undefined, {minimumFractionDigits:3}) + ' KG</div></div>';
        h += '<div class="col-3 text-center border-right"><div class="detail-label">Net Weight</div><div class="detail-value">' + parseFloat(d.net_weight).toLocaleString(undefined, {minimumFractionDigits:3}) + ' KG</div></div>';
        var diffCls = parseFloat(d.weight_diff) < 0 ? 'text-danger' : 'text-success';
        h += '<div class="col-3 text-center border-right"><div class="detail-label">Difference</div><div class="detail-value ' + diffCls + '">' + (parseFloat(d.weight_diff) >= 0 ? '+' : '') + parseFloat(d.weight_diff).toLocaleString(undefined, {minimumFractionDigits:3}) + ' KG</div></div>';
        h += '<div class="col-3 text-center"><div class="detail-label">Moisture</div><div class="detail-value">' + (d.moisture_pct ? d.moisture_pct + '%' : '-') + '</div></div>';
        h += '</div></div>';

        // Section 4: Financial
        h += '<div class="detail-box">';
        h += '<h6><i class="fas fa-money-bill-wave mr-1"></i> Financial Summary</h6>';
        h += '<div class="row">';
        h += '<div class="col-md-6">';
        h += '<div class="financial-row d-flex justify-content-between"><span>Gross Amount</span><strong>' + parseFloat(d.gross_amount).toLocaleString(undefined, {minimumFractionDigits:2}) + '</strong></div>';
        h += '<div class="financial-row d-flex justify-content-between"><span>Bag Amount</span><strong>' + parseFloat(d.bag_amount).toLocaleString(undefined, {minimumFractionDigits:2}) + '</strong></div>';
        h += '<div class="financial-row d-flex justify-content-between"><span>Labour Charges</span><strong>' + parseFloat(d.labour_charges).toLocaleString(undefined, {minimumFractionDigits:2}) + '</strong></div>';
        h += '</div>';
        h += '<div class="col-md-6">';
        h += '<div class="financial-row d-flex justify-content-between"><span>Transport Charges</span><strong>' + parseFloat(d.transport_charges).toLocaleString(undefined, {minimumFractionDigits:2}) + '</strong></div>';
        h += '<div class="financial-row d-flex justify-content-between"><span>Other Charges</span><strong>' + parseFloat(d.other_charges).toLocaleString(undefined, {minimumFractionDigits:2}) + '</strong></div>';
        var netCls = parseFloat(d.net_amount) >= 0 ? 'text-success' : 'text-danger';
        h += '<div class="financial-row d-flex justify-content-between" style="border-top:2px solid #1B2A4A;padding-top:8px;margin-top:4px;"><span class="font-weight-bold" style="font-size:16px;">Net Amount</span><span class="font-weight-bold ' + netCls + '" style="font-size:18px;">' + parseFloat(d.net_amount).toLocaleString(undefined, {minimumFractionDigits:2}) + '</span></div>';
        var paidNow = parseFloat(d.payment_now || 0);
        if (paidNow > 0) {
            h += '<div class="financial-row d-flex justify-content-between" style="padding-top:6px;"><span class="font-weight-bold" style="font-size:14px;">Paid on Arrival</span><span class="font-weight-bold text-primary" style="font-size:16px;">' + paidNow.toLocaleString(undefined, {minimumFractionDigits:2}) + '</span></div>';
        }
        h += '</div>';
        h += '</div></div>';

        // Notes
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
