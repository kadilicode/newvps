<?php
require_once __DIR__ . '/../includes/init.php';
requireReseller();
$pageTitle = 'Dashboard';
$rid = (int)$_SESSION['reseller_id'];

$totalSales    = DB::fetch("SELECT COALESCE(SUM(amount),0) as s FROM transactions WHERE reseller_id=? AND payment_status='completed' AND type='voucher_sale'", [$rid])['s'];
$todaySales    = DB::fetch("SELECT COALESCE(SUM(amount),0) as s FROM transactions WHERE reseller_id=? AND payment_status='completed' AND DATE(created_at)=CURDATE()", [$rid])['s'];
$totalVouchers = DB::fetch("SELECT COUNT(*) as c FROM vouchers WHERE reseller_id=? AND status='used'", [$rid])['c'];
$activeUsers   = DB::fetch("SELECT COUNT(*) as c FROM hotspot_users WHERE reseller_id=? AND status='active' AND expires_at > NOW()", [$rid])['c'];
$totalRouters  = DB::fetch("SELECT COUNT(*) as c FROM routers WHERE reseller_id=?", [$rid])['c'];
$balance       = DB::fetch("SELECT balance FROM resellers WHERE id=?", [$rid])['balance'];
$subExpires    = DB::fetch("SELECT subscription_expires FROM resellers WHERE id=?", [$rid])['subscription_expires'];

// Chart - last 7 days
$chartData = DB::fetchAll("
  SELECT DATE(created_at) as d, COALESCE(SUM(amount),0) as total
  FROM transactions WHERE reseller_id=? AND payment_status='completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
  GROUP BY DATE(created_at) ORDER BY d ASC
", [$rid]);
$chartLabels = json_encode(array_column($chartData, 'd'));
$chartValues = json_encode(array_column($chartData, 'total'));

// Recent transactions
$recentTx = DB::fetchAll("SELECT * FROM transactions WHERE reseller_id=? ORDER BY created_at DESC LIMIT 8", [$rid]);

// Router status
$routers = DB::fetchAll("SELECT * FROM routers WHERE reseller_id=?", [$rid]);

include '_header.php';
?>

<!-- Stat cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card card h-100" style="background:linear-gradient(135deg,#007bff,#004a99)">
      <div class="text-white-50 small">Sales Jumla</div>
      <div class="h5 fw-bold text-white mb-0"><?= formatTZS((float)$totalSales) ?></div>
      <div class="text-white-50 small">Leo: <?= formatTZS((float)$todaySales) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card card h-100" style="background:linear-gradient(135deg,#28a745,#1a6e2e)">
      <div class="text-white-50 small">Your Balance</div>
      <div class="h5 fw-bold text-white mb-0"><?= formatTZS((float)$balance) ?></div>
      <div class="text-white-50 small"><a href="withdrawals.php" class="text-white-50">Withdraw</a></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card card h-100" style="background:linear-gradient(135deg,#fd7e14,#b85a00)">
      <div class="text-white-50 small">Vouchers Used</div>
      <div class="h3 fw-bold text-white mb-0"><?= number_format($totalVouchers) ?></div>
      <div class="text-white-50 small"><?= $activeUsers ?> active now</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card card h-100" style="background:linear-gradient(135deg,#6f42c1,#4a2a8a)">
      <div class="text-white-50 small">Routers</div>
      <div class="h3 fw-bold text-white mb-0"><?= $totalRouters ?></div>
      <?php if ($subExpires): ?>
      <div class="text-white-50 small">Subscription: <?= date('d/m/Y', strtotime($subExpires)) ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Sales Chart -->
  <div class="col-md-8">
    <div class="card">
      <div class="card-header py-3"><i class="fas fa-chart-bar text-primary me-2"></i>Sales - Days 7 Zilizopita</div>
      <div class="card-body"><canvas id="salesChart" height="120"></canvas></div>
    </div>
  </div>

  <!-- Router Status -->
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header py-3 d-flex justify-content-between">
        <span><i class="fas fa-router text-primary me-2"></i>Router Status</span>
        <a href="routers.php" class="btn btn-xs btn-outline-primary" style="font-size:11px;padding:2px 8px">Manage</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($routers)): ?>
          <div class="p-4 text-center">
            <i class="fas fa-router text-muted mb-2" style="font-size:30px"></i>
            <p class="text-muted small mb-2">No routers yet</p>
            <a href="routers.php" class="btn btn-primary btn-sm">+ Add Router</a>
          </div>
        <?php else: ?>
          <?php foreach ($routers as $r): ?>
          <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
            <div>
              <div class="fw-semibold small"><?= sanitize($r['name']) ?></div>
              <div class="text-muted" style="font-size:11px"><?= sanitize($r['host']) ?></div>
            </div>
            <?php $statusC = ['online'=>'success','offline'=>'danger','unknown'=>'secondary']; ?>
            <span class="badge bg-<?= $statusC[$r['status']] ?>">
              <i class="fas fa-circle me-1" style="font-size:8px"></i><?= ucfirst($r['status']) ?>
            </span>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Transactions -->
  <div class="col-12">
    <div class="card">
      <div class="card-header py-3 d-flex justify-content-between">
        <span><i class="fas fa-history text-primary me-2"></i>Payments ya Hivi Karibuni</span>
        <a href="transactions.php" class="btn btn-xs btn-outline-primary" style="font-size:11px;padding:2px 8px">View All</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Date</th><th>Phone ya Customer</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($recentTx as $t): ?>
              <tr>
                <td><?= date('d/m H:i', strtotime($t['created_at'])) ?></td>
                <td><?= sanitize($t['customer_phone']) ?></td>
                <td class="fw-bold text-success"><?= formatTZS((float)$t['amount']) ?></td>
                <td>
                  <?php $sc=['completed'=>'success','pending'=>'warning','failed'=>'danger','cancelled'=>'secondary']; ?>
                  <span class="badge bg-<?= $sc[$t['payment_status']] ?>"><?= ucfirst($t['payment_status']) ?></span>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($recentTx)): ?>
                <tr><td colspan="4" class="text-center py-3 text-muted">No payments yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$extraJs = <<<JS
<script>
const ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: {$chartLabels},
    datasets: [{
      label: 'Sales (TZS)',
      data: {$chartValues},
      backgroundColor: 'rgba(0,123,255,0.8)',
      borderRadius: 8
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
      x: { grid: { display: false } }
    }
  }
});
</script>
JS;
include '_footer.php';
?>
