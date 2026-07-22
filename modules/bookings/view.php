<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'booking_list';
$page_title = 'Booking Detail';
require_once '../../includes/db.php';
include '../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$booking = $conn->query("
    SELECT b.*, f.name AS farmer_name, f.village, f.phone
    FROM bookings b
    JOIN farmers f ON b.farmer_id = f.id
    WHERE b.id = $id
")->fetch_assoc();

if (!$booking) {
    echo '<div class="alert alert-danger">Booking not found.</div>';
    include '../../includes/footer.php';
    exit;
}

$bag = $conn->query("
    SELECT bb.*, bt.name AS bag_type_name
    FROM booking_bags bb
    JOIN bag_types bt ON bb.bag_type_id = bt.id
    WHERE bb.booking_id = $id
")->fetch_assoc();

$farmer_wheat = ($bag ? $bag['quantity'] : 0) * 50;
$katt_total   = $bag ? ($bag['quantity'] * $booking['katt_per_bag']) : 0;
$net_qty      = $farmer_wheat + $katt_total;
$mans         = $farmer_wheat / 40;
$bag_rate_total = ($bag && $bag['ownership'] === 'farmer') ? ($bag['quantity'] * ($bag['bag_rate'] ?? 0)) : 0;
$wheat_value    = $mans * $booking['rate'];
$total_value    = $wheat_value + $bag_rate_total;
$total_paid   = $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM farmer_payments WHERE booking_id = $id")->fetch_assoc()['t'];
$remaining    = max(0, $total_value - $total_paid);
$badge        = match($booking['status']) {
    'completed' => 'success',
    'partial'   => 'warning',
    'cancelled' => 'danger',
    default     => 'secondary'
};
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-signature mr-1"></i> Booking Voucher</h1>
    <div>
        <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
        <button class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
</div>

<div class="voucher-wrapper">
    <!-- HEADER -->
    <div class="voucher-header">
        <div class="row">
            <div class="col-sm-8">
                <h2 class="voucher-title">Booking Voucher</h2>
                <p class="voucher-meta">#<?= htmlspecialchars($booking['booking_no']) ?></p>
            </div>
            <div class="col-sm-4 text-sm-right">
                <div class="voucher-status">
                    <span class="badge badge-<?= $badge ?>" style="font-size:14px;padding:6px 18px;"><?= ucfirst($booking['status']) ?></span>
                </div>
                <div class="voucher-date"><?= date('d-M-Y', strtotime($booking['date'])) ?></div>
            </div>
        </div>
    </div>

    <!-- FARMER + BOOKING INFO -->
    <div class="voucher-section">
        <div class="row">
            <div class="col-sm-6">
                <h5 class="section-title"><i class="fas fa-user mr-1"></i> Farmer Details</h5>
                <table class="table table-sm voucher-table">
                    <tr>
                        <th>Name</th>
                        <td><?= htmlspecialchars($booking['farmer_name']) ?></td>
                    </tr>
                    <tr>
                        <th>Address</th>
                        <td><?= htmlspecialchars($booking['village'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><?= htmlspecialchars($booking['phone'] ?? '-') ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-sm-6">
                <h5 class="section-title"><i class="fas fa-info-circle mr-1"></i> Booking Info</h5>
                <table class="table table-sm voucher-table">
                    <tr>
                        <th>Date</th>
                        <td><?= $booking['date'] ?></td>
                    </tr>
                    <?php if ($booking['expected_date']): ?>
                    <tr>
                        <th>Expected Delivery</th>
                        <td><?= $booking['expected_date'] ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Moisture</th>
                        <td><?= $booking['moisture_percent'] > 0 ? $booking['moisture_percent'] . '%' : '-' ?></td>
                    </tr>
                    <tr>
                        <th>Delivery Type</th>
                        <td><span class="badge badge-<?= ($booking['delivery_type'] ?? 'pickup') === 'delivery' ? 'info' : 'primary' ?>"><?= ($booking['delivery_type'] ?? 'pickup') === 'delivery' ? 'We Will Pickup' : 'Farmer Will Send' ?></span></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- BAG DETAILS -->
    <div class="voucher-section">
        <h5 class="section-title"><i class="fas fa-shopping-bag mr-1"></i> Bag Details</h5>
        <table class="table table-sm voucher-table">
            <thead>
                <tr>
                    <th>Bag Type</th>
                    <th class="text-center">Quantity</th>
                    <th class="text-center">Capacity</th>
                    <th class="text-center">Ownership</th>
                    <th class="text-right">Bag Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($bag): ?>
                <tr>
                    <td><?= htmlspecialchars($bag['bag_type_name']) ?></td>
                    <td class="text-center"><?= $bag['quantity'] ?></td>
                    <td class="text-center"><?= qty($bag['bag_capacity_kg']) ?> KG</td>
                    <td class="text-center"><?= $bag['ownership'] === 'company' ? 'Company' : 'Farmer' ?></td>
                    <td class="text-right"><?= $bag['ownership'] === 'farmer' && $bag['bag_rate'] > 0 ? money($bag['bag_rate']) : '-' ?></td>
                </tr>
                <?php else: ?>
                <tr><td colspan="5" class="text-muted text-center">No bag details recorded.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- QUANTITY & PRICING -->
    <div class="voucher-section">
        <div class="row">
            <div class="col-sm-7">
                <h5 class="section-title"><i class="fas fa-weight mr-1"></i> Quantity Breakdown</h5>
                <table class="table table-sm voucher-table">
                    <tr>
                        <th>Farmer's Wheat</th>
                        <td class="text-right"><?= qty($farmer_wheat) ?> KG <small class="text-muted d-block"><?= $bag ? $bag['quantity'] : 0 ?> bags × 50 KG</small></td>
                        <td class="text-muted small">Bag Qty × 50 KG</td>
                    </tr>
                    <tr>
                        <th>Katt @ <?= qty($booking['katt_per_bag']) ?> KG/bag</th>
                        <td class="text-right text-success">+ <?= qty($katt_total) ?> KG</td>
                        <td class="text-muted small"><?= $bag ? $bag['quantity'] : 0 ?> × <?= qty($booking['katt_per_bag']) ?></td>
                    </tr>
                    <tr class="table-active">
                        <th><strong>Net Qty (Stock)</strong></th>
                        <td class="text-right"><strong><?= qty($net_qty) ?> KG</strong> <small class="text-muted d-block"><?= $bag ? $bag['quantity'] : 0 ?> bags</small></td>
                        <td></td>
                    </tr>
                </table>
            </div>
            <div class="col-sm-5">
                <h5 class="section-title"><i class="fas fa-calculator mr-1"></i> Pricing</h5>
                <table class="table table-sm voucher-table">
                    <tr>
                        <th>Rate per Man (40 KG)</th>
                        <td class="text-right"><strong><?= money($booking['rate']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Mans</th>
                        <td class="text-right"><?= qty($mans) ?></td>
                    </tr>
                    <tr>
                        <th>Wheat Value</th>
                        <td class="text-right"><strong><?= money($wheat_value) ?></strong></td>
                    </tr>
                    <?php if ($bag_rate_total > 0): ?>
                    <tr>
                        <th>Bag Rate × <?= $bag['quantity'] ?> bags</th>
                        <td class="text-right"><?= money($bag_rate_total) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="table-active">
                        <th><strong>Grand Total</strong></th>
                        <td class="text-right"><strong><?= money($total_value) ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- PAYMENT SUMMARY -->
    <div class="voucher-footer">
        <div class="row">
            <div class="col-sm-3">
                <div class="footer-stat">
                    <div class="stat-label">Wheat Value</div>
                    <div class="stat-value"><?= money($wheat_value) ?></div>
                </div>
            </div>
            <?php if ($bag_rate_total > 0): ?>
            <div class="col-sm-3">
                <div class="footer-stat">
                    <div class="stat-label">Bag Charges</div>
                    <div class="stat-value"><?= money($bag_rate_total) ?></div>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-sm-3">
                <div class="footer-stat">
                    <div class="stat-label">Advance Paid</div>
                    <div class="stat-value stat-warning"><?= money($booking['advance_amount']) ?></div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="footer-stat">
                    <div class="stat-label">Total Paid</div>
                    <div class="stat-value stat-success"><?= money($total_paid) ?></div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="footer-stat">
                    <div class="stat-label">Grand Total</div>
                    <div class="stat-value"><?= money($total_value) ?></div>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="footer-stat">
                    <div class="stat-label">Remaining</div>
                    <div class="stat-value <?= $remaining > 0 ? 'stat-danger' : 'stat-success' ?>"><?= money($remaining) ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($booking['notes']): ?>
    <div class="voucher-section">
        <h5 class="section-title"><i class="fas fa-sticky-note mr-1"></i> Notes</h5>
        <p class="mb-0"><?= nl2br(htmlspecialchars($booking['notes'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<style>
.voucher-wrapper {
    background: #fff;
    border: 1px solid #d1d3e2;
    border-radius: 8px;
    padding: 35px 40px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    max-width: 1000px;
    margin: 0 auto;
}

.voucher-header {
    border-bottom: 3px double var(--gold);
    padding-bottom: 18px;
    margin-bottom: 25px;
}

.voucher-title {
    font-size: 24px;
    font-weight: 700;
    color: var(--navy);
    margin: 0;
    letter-spacing: 0.5px;
}

.voucher-meta {
    font-size: 16px;
    color: #666;
    margin: 4px 0 0 0;
    font-weight: 600;
}

.voucher-date {
    font-size: 14px;
    color: #888;
    margin-top: 8px;
}

.voucher-status {
    margin-bottom: 4px;
}

.voucher-section {
    padding: 18px 0;
    border-bottom: 1px solid #e8e8e8;
}

.voucher-section:last-of-type {
    border-bottom: none;
}

.section-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 12px;
    padding-bottom: 6px;
    border-bottom: 2px solid var(--gold-light);
}

.voucher-table {
    margin-bottom: 0;
    font-size: 14px;
}

.voucher-table th {
    font-weight: 600;
    color: #555;
    border-top: none !important;
    padding-left: 0 !important;
    width: 160px;
}

.voucher-table td {
    border-top: none !important;
    padding-right: 0 !important;
}

.voucher-table thead th {
    color: var(--navy);
    font-weight: 700;
    border-bottom: 2px solid #dee2e6 !important;
    padding: 6px 0 !important;
}

.voucher-table tbody td {
    padding: 8px 0 !important;
    border-bottom: 1px solid #f0f0f0;
}

.voucher-footer {
    background: #f8f9fc;
    border-radius: 6px;
    padding: 20px 24px;
    margin-top: 20px;
}

.footer-stat {
    text-align: center;
    padding: 8px 0;
}

.stat-label {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #888;
    font-weight: 600;
}

.stat-value {
    font-size: 20px;
    font-weight: 700;
    color: var(--navy);
    margin-top: 4px;
}

.stat-warning { color: #856404; }
.stat-success { color: #155724; }
.stat-danger { color: #721c24; }

@media print {
    .no-print { display: none !important; }
    .voucher-wrapper {
        border: none;
        box-shadow: none;
        padding: 20px;
        margin: 0;
    }
    .voucher-header { border-bottom-color: #000; }
    .voucher-section { page-break-inside: avoid; }
    .voucher-footer { border: 1px solid #ddd; }
}
</style>

<?php include '../../includes/footer.php'; ?>
