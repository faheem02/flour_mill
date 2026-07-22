<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'farmer_ledger';
$page_title = 'Farmer Ledger';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$farmer_id = (int)($_GET['id'] ?? 0);
$farmer = $farmer_id ? $conn->query("SELECT * FROM farmers WHERE id = $farmer_id")->fetch_assoc() : null;

include '../../includes/header.php';

// Build ledger entries if farmer selected
$ledger = [];
if ($farmer) {
    $opening = (float)($farmer['opening_balance'] ?? 0);

    // Arrivals (wheat delivered — farmer gave us goods = CREDIT / our liability increases)
    $arrivals = $conn->query("
        SELECT a.id, a.date, a.net_amount, a.payment_now, b.booking_no
        FROM wheat_arrivals a
        LEFT JOIN bookings b ON a.booking_id = b.id
        WHERE b.farmer_id = $farmer_id
        ORDER BY a.date ASC, a.id ASC
    ");
    while ($a = $arrivals->fetch_assoc()) {
        $ledger[] = [
            'date'    => $a['date'],
            'type'    => 'arrival',
            'ref'     => $a['booking_no'] ?? '-',
            'desc'    => 'Wheat Arrival',
            'debit'   => 0,
            'credit'  => (float)$a['net_amount'],
            'payment' => (float)($a['payment_now'] ?? 0),
        ];
    }

    // Payments (we paid farmer = DEBIT / our liability decreases)
    $payments = $conn->query("
        SELECT p.id, p.date, p.amount, p.type, p.payment_mode, p.notes, b.booking_no
        FROM farmer_payments p
        LEFT JOIN bookings b ON p.booking_id = b.id
        WHERE p.farmer_id = $farmer_id
        ORDER BY p.date ASC, p.id ASC
    ");
    while ($p = $payments->fetch_assoc()) {
        $ledger[] = [
            'date'    => $p['date'],
            'type'    => $p['type'],
            'ref'     => $p['booking_no'] ?? '-',
            'desc'    => ucfirst($p['type']) . ($p['payment_mode'] ? " ({$p['payment_mode']})" : '') . ($p['notes'] ? " - {$p['notes']}" : ''),
            'debit'   => (float)$p['amount'],
            'credit'  => 0,
            'payment' => 0,
        ];
    }

    // Sort by date, then type (openings first, then by id)
    usort($ledger, function($a, $b) {
        $d = strcmp($a['date'], $b['date']);
        return $d !== 0 ? $d : 0;
    });
}
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-book mr-1"></i> Farmer Ledger</h1>
    <div>
        <a href="list.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Farmer List</a>
        <?php if ($farmer): ?>
        <a href="print_ledger.php?id=<?= $farmer['id'] ?>" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-print mr-1"></i> Print</a>
        <?php else: ?>
        <button class="btn btn-sm btn-primary" disabled><i class="fas fa-print mr-1"></i> Print</button>
        <?php endif; ?>
        <?php if ($farmer): ?>
        <a href="payment.php?farmer_id=<?= $farmer['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-money-bill-wave mr-1"></i> Make Payment</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$farmer): ?>
<?php
$farmers = $conn->query("SELECT * FROM farmers ORDER BY name");
?>
<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">Select a Farmer</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Village</th>
                        <th>City</th>
                        <th class="text-right">Balance</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($f = $farmers->fetch_assoc()): ?>
                    <tr>
                        <td><?= $f['id'] ?></td>
                        <td><strong><?= htmlspecialchars($f['name']) ?></strong></td>
                        <td><?= htmlspecialchars($f['phone']) ?></td>
                        <td><?= htmlspecialchars($f['village']) ?></td>
                        <td><?= htmlspecialchars($f['city']) ?></td>
                        <td class="text-right"><?= money($f['balance']) ?></td>
                        <td><a href="ledger.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-primary">View Ledger</a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>

<!-- Farmer Info -->
<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0"><?= htmlspecialchars($farmer['name']) ?> — Ledger</h6></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-2">
                <small class="text-muted d-block">Phone</small>
                <strong><?= htmlspecialchars($farmer['phone'] ?: '-') ?></strong>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Location</small>
                <strong><?= htmlspecialchars($farmer['village'] ?: '-') ?><?= $farmer['city'] ? ', ' . htmlspecialchars($farmer['city']) : '' ?></strong>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Opening Balance</small>
                <strong>Rs <?= money($farmer['opening_balance'] ?? 0) ?></strong>
            </div>
            <div class="col-md-2">
                <small class="text-muted d-block">Current Balance</small>
                <strong class="text-<?= $farmer['balance'] > 0 ? 'success' : ($farmer['balance'] < 0 ? 'danger' : 'muted') ?>">Rs <?= money($farmer['balance']) ?></strong>
            </div>
            <div class="col-md-4 text-right">
                <a href="payment.php?farmer_id=<?= $farmer['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-money-bill-wave mr-1"></i> Pay</a>
            </div>
        </div>
    </div>
</div>

<!-- Ledger Table -->
<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0"><i class="fas fa-scroll mr-1"></i> Ledger</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" style="font-size:13px;">
                <thead class="thead-dark">
                    <tr>
                        <th style="width:90px">Date</th>
                        <th style="width:90px">Type</th>
                        <th>Ref / Description</th>
                        <th class="text-right" style="width:130px">Credit (+)</th>
                        <th class="text-right" style="width:130px">Debit (-)</th>
                        <th class="text-right" style="width:150px">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $balance = $opening;
                    $total_debit = 0;
                    $total_credit = 0;
                    ?>

                    <!-- Opening Balance Row -->
                    <tr style="background:#e8f0fe; font-weight:bold;">
                        <td></td>
                        <td><span class="badge badge-secondary">Opening</span></td>
                        <td>Opening Balance b/f</td>
                        <td class="text-right"><strong>Rs <?= money($balance) ?></strong></td>
                        <td class="text-right"></td>
                        <td class="text-right"><strong>Rs <?= money($balance) ?></strong></td>
                    </tr>

                    <?php foreach ($ledger as $entry): ?>
                    <?php
                        if ($entry['type'] === 'arrival') {
                            // Arrival: credit increases balance (farmer gave goods, our liability grows)
                            $balance += $entry['credit'];
                            $total_credit += $entry['credit'];
                        } else {
                            // Payment: debit decreases balance (we paid farmer, our liability shrinks)
                            $balance -= $entry['debit'];
                            $total_debit += $entry['debit'];
                        }
                    ?>
                    <tr>
                        <td><?= date('d-M-Y', strtotime($entry['date'])) ?></td>
                        <td>
                            <?php if ($entry['type'] === 'arrival'): ?>
                                <span class="badge badge-primary">Arrival</span>
                            <?php elseif ($entry['type'] === 'advance'): ?>
                                <span class="badge badge-info">Advance</span>
                            <?php else: ?>
                                <span class="badge badge-success">Payment</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($entry['desc']) ?>
                            <?php if ($entry['ref'] !== '-'): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($entry['ref']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-right <?= $entry['credit'] > 0 ? 'font-weight-bold text-primary' : '' ?>">
                            <?= $entry['credit'] > 0 ? 'Rs ' . money($entry['credit']) : '' ?>
                        </td>
                        <td class="text-right <?= $entry['debit'] > 0 ? 'font-weight-bold text-danger' : '' ?>">
                            <?= $entry['debit'] > 0 ? 'Rs ' . money($entry['debit']) : '' ?>
                        </td>
                        <td class="text-right font-weight-bold">Rs <?= money($balance) ?></td>
                    </tr>

                    <?php endforeach; ?>
                </tbody>
                <tfoot class="font-weight-bold" style="font-size:14px;">
                    <tr style="background:#1B2A4A; color:#fff;">
                        <td colspan="3" class="text-right">Total</td>
                        <td class="text-right">Rs <?= money($total_credit) ?></td>
                        <td class="text-right">Rs <?= money($total_debit) ?></td>
                        <td class="text-right">Rs <?= money($balance) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Bookings Detail -->
<?php
$bookings = $conn->query("
    SELECT b.*,
        COALESCE((SELECT quantity FROM booking_bags WHERE booking_id = b.id LIMIT 1), 0) AS bag_qty,
        COALESCE((SELECT ownership FROM booking_bags WHERE booking_id = b.id LIMIT 1), 'company') AS bag_ownership,
        COALESCE((SELECT bag_rate FROM booking_bags WHERE booking_id = b.id LIMIT 1), 0) AS bag_rate,
        COALESCE((SELECT SUM(amount) FROM farmer_payments WHERE booking_id = b.id AND type='payment'), 0) AS extra_paid
    FROM bookings b
    WHERE b.farmer_id = $farmer_id
    ORDER BY b.date DESC, b.id DESC
");
?>
<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0"><i class="fas fa-file-signature mr-1"></i> Bookings</h6></div>
    <div class="card-body">
        <?php if ($bookings->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered datatable">
                <thead class="thead-dark">
                    <tr>
                        <th>Booking No</th>
                        <th>Date</th>
                        <th class="text-right">Qty (KG)</th>
                        <th class="text-right">Rate/man(40kg)</th>
                        <th class="text-right">Wheat</th>
                        <th class="text-right">Bag Chg</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Advance</th>
                        <th class="text-right">Paid</th>
                        <th class="text-right">Remaining</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($b = $bookings->fetch_assoc()):
                        $mans = ($b['bag_qty'] * 50) / 40;
                        $bag_rate_total = ($b['bag_ownership'] === 'farmer' && $b['bag_rate'] > 0) ? ($b['bag_qty'] * $b['bag_rate']) : 0;
                        $wheat_value = $mans * $b['rate'];
                        $total_value = $wheat_value + $bag_rate_total;
                        $total_paid = $b['advance_amount'] + $b['extra_paid'];
                        $remaining = max(0, $total_value - $total_paid);
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($b['booking_no']) ?></strong></td>
                        <td><?= $b['date'] ?></td>
                        <td class="text-right"><?= qty($b['booked_qty']) ?></td>
                        <td class="text-right"><?= money($b['rate']) ?></td>
                        <td class="text-right"><?= money($wheat_value) ?></td>
                        <td class="text-right"><?= $bag_rate_total > 0 ? money($bag_rate_total) : '-' ?></td>
                        <td class="text-right"><strong><?= money($total_value) ?></strong></td>
                        <td class="text-right"><?= money($b['advance_amount']) ?></td>
                        <td class="text-right"><?= money($total_paid) ?></td>
                        <td class="text-right"><?= money($remaining) ?></td>
                        <td><span class="badge badge-<?= match($b['status']) { 'completed' => 'success', 'partial' => 'warning', 'cancelled' => 'danger', default => 'secondary' } ?>"><?= ucfirst($b['status']) ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0">No bookings for this farmer.</p>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
