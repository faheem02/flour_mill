<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'arrival_list';
$page_title = 'Arrival Register';
require_once '../../includes/db.php';
include '../../includes/header.php';

$result = $conn->query("SELECT a.*, w.name as warehouse_name, b.booking_no, f.name as farmer_name
    FROM wheat_arrivals a
    LEFT JOIN warehouses w ON a.warehouse_id = w.id
    LEFT JOIN bookings b ON a.booking_id = b.id
    LEFT JOIN farmers f ON b.farmer_id = f.id
    ORDER BY a.date DESC, a.id DESC");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-list mr-1"></i> Arrival Register</h1>
    <div>
        <a href="add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle mr-1"></i> New Arrival</a>
        <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
</div>

<style>
.btn-action { padding: 4px 10px !important; font-size: 12px !important; }
</style>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">All Arrivals</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>Date</th>
                        <th>Booking</th>
                        <th>Farmer</th>
                        <th>Warehouse</th>
                        <th class="text-right">Bags</th>
                        <th class="text-right">Gross KG</th>
                        <th class="text-right">Net KG</th>
                        <th>Moisture</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['date'] ?></td>
                        <td><?= htmlspecialchars($row['booking_no'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['farmer_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['warehouse_name']) ?></td>
                        <td class="text-right"><?= $row['num_bags'] ?></td>
                        <td class="text-right"><?= qty($row['gross_weight']) ?></td>
                        <td class="text-right"><?= qty($row['net_weight']) ?></td>
                        <td><?= $row['moisture_pct'] ? $row['moisture_pct'] . '%' : '-' ?></td>
                        <td class="text-nowrap">
                            <div class="btn-group btn-group-sm">
                                <a href="#" class="btn btn-info btn-action" title="View" onclick="viewArrival(<?= $row['id'] ?>);return false"><i class="fas fa-eye"></i></a>
                                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-action" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-action" title="Delete" onclick="return confirm('Delete this arrival?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title"><i class="fas fa-truck-loading mr-1"></i> Arrival Details</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewArrival(id) {
    $('#modalBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
    $('#viewModal').modal('show');
    $.get('arrival_json.php?id=' + id, function(data) {
        var h = '<div class="row mb-3">';
        h += '<div class="col-sm-6"><strong>Date:</strong> ' + data.date + '</div>';
        h += '<div class="col-sm-6"><strong>Booking No:</strong> ' + (data.booking_no || '-') + '</div>';
        h += '</div><div class="row mb-3">';
        h += '<div class="col-sm-6"><strong>Farmer:</strong> ' + (data.farmer_name || '-') + '</div>';
        h += '<div class="col-sm-6"><strong>Warehouse:</strong> ' + (data.warehouse_name || '-') + '</div>';
        h += '</div><hr><div class="row mb-3">';
        h += '<div class="col-sm-4"><strong>Bag Type:</strong> ' + (data.bag_type_name || '-') + '</div>';
        h += '<div class="col-sm-4"><strong>Bags:</strong> ' + data.num_bags + '</div>';
        h += '<div class="col-sm-4"><strong>Broker:</strong> ' + (data.broker_name || '-') + '</div>';
        h += '</div><div class="row mb-3">';
        h += '<div class="col-sm-3"><strong>Gross Weight:</strong> ' + parseFloat(data.gross_weight).toLocaleString() + ' KG</div>';
        h += '<div class="col-sm-3"><strong>Bag Weight:</strong> ' + parseFloat(data.bag_weight).toLocaleString() + ' KG</div>';
        h += '<div class="col-sm-3"><strong>Net Weight:</strong> <span class="text-success font-weight-bold">' + parseFloat(data.net_weight).toLocaleString() + ' KG</span></div>';
        h += '<div class="col-sm-3"><strong>Moisture:</strong> ' + (data.moisture_pct ? data.moisture_pct + '%' : '-') + '</div>';
        h += '</div><div class="row mb-3">';
        h += '<div class="col-sm-12"><strong>Notes:</strong> ' + (data.notes || '-') + '</div>';
        h += '</div>';
        $('#modalBody').html(h);
    }, 'json').fail(function() {
        $('#modalBody').html('<div class="alert alert-danger">Failed to load data.</div>');
    });
}
</script>

<?php include '../../includes/footer.php'; ?>
