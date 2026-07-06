<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . $base_url . "auth/login.php"); exit; }
$active_page = 'trial_balance';
$page_title = 'Trial Balance';
require_once '../../includes/db.php';
include '../../includes/header.php';

$accounts = $conn->query("SELECT code, name, type, opening_balance, balance FROM chart_of_accounts WHERE parent_id IS NOT NULL AND status='active' ORDER BY code");
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-balance-scale mr-1"></i> Trial Balance</h1>
    <button class="btn btn-sm btn-secondary no-print" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
</div>

<div class="card shadow mb-4">
    <div class="card-header"><h6 class="font-weight-bold m-0">As at <?= date('d F Y') ?></h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>Code</th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th class="text-right">Debit (Dr)</th>
                        <th class="text-right">Credit (Cr)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $total_dr=0; $total_cr=0; while ($row = $accounts->fetch_assoc()):
                        $bal = $row['balance'];
                        if (in_array($row['type'], ['asset', 'expense'])) {
                            $dr = $bal > 0 ? $bal : 0;
                            $cr = $bal < 0 ? abs($bal) : 0;
                        } else {
                            $cr = $bal > 0 ? $bal : 0;
                            $dr = $bal < 0 ? abs($bal) : 0;
                        }
                        $total_dr += $dr; $total_cr += $cr;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['code']) ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><span class="badge badge-<?= $row['type']=='asset'?'primary':($row['type']=='liability'?'warning':($row['type']=='income'?'success':'danger')) ?>"><?= ucfirst($row['type']) ?></span></td>
                        <td class="text-right"><?= $dr > 0 ? money($dr) : '-' ?></td>
                        <td class="text-right"><?= $cr > 0 ? money($cr) : '-' ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot class="table-active">
                    <tr>
                        <th colspan="3" class="text-right">Total</th>
                        <th class="text-right"><?= money($total_dr) ?></th>
                        <th class="text-right"><?= money($total_cr) ?></th>
                    </tr>
                    <?php if (abs($total_dr - $total_cr) > 0.01): ?>
                    <tr class="table-danger">
                        <th colspan="3" class="text-right">Difference</th>
                        <th colspan="2" class="text-center text-danger font-weight-bold">Rs <?= money(abs($total_dr - $total_cr)) ?></th>
                    </tr>
                    <?php else: ?>
                    <tr class="table-success">
                        <th colspan="5" class="text-center text-success font-weight-bold"><i class="fas fa-check-circle"></i> Balanced</th>
                    </tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
