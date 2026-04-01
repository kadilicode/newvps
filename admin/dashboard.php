<?php
require_once __DIR__ . '/../includes/init.php';
requireAdmin();
$pageTitle = 'Dashboard';

// Stats
$totalResellers  = DB::fetch("SELECT COUNT(*) as c FROM resellers")['c'];
$activeResellers = DB::fetch("SELECT COUNT(*) as c FROM resellers WHERE status='active'")['c'];
$totalRevenue    = DB::fetch("SELECT COALESCE(SUM(amount),0) as s FROM transactions WHERE payment_status='completed' AND type='voucher_sale'")['s'];
$todaySales      = DB::fetch("SELECT COALESCE(SUM(amount),0) as s FROM transactions WHERE payment_status='completed' AND DATE(created_at)=CURDATE()")['s'];
$totalVouchers   = DB::fetch("SELECT COUNT(*) as c FROM vouchers WHERE status='used'")['c'];
$totalRouters    = DB::fetch("SELECT COUNT(*) as c FROM routers")['c'];
$pendingWithdraw = DB::fetch("SELECT COUNT(*) as c FROM withdrawals WHERE status='pending'")['c'];

// Last 7 days sales chart data
$chartData = DB::fetchAll("
  SELECT DATE(created_at) as d, COALESCE(SUM(amount),0) as total
  FROM transactions
  WHERE payment_status='completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
  GROUP BY DATE(created_at)
  ORDER BY d ASC
");
$chartLabels = json_encode(array_column($chartData, 'd'));
$chartValues = json_encode(array_column($chartData, 'total'));

// Recent transactions
$recentTx = DB::fetchAll("
  SELECT t.*, r.name as reseller_name, r.business_name
  FROM transactions t
  JOIN resellers r ON r.id = t.reseller_id
  ORDER BY t.created_at DESC LIMIT 10
");

// Announcements
$announcements = DB::fetchAll("SELECT * FROM announcements WHERE is_active=1 ORDER BY created_at DESC LIMIT 3");

include '_header.php';
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card card h-100" style="background:linear-gradient(135deg,#007bff,#0056b3)">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-white-50 small">Resellers Wote</div>
          <div class="h3 fw-bold text-white mb-0"><?= number_format($totalResellers) ?></div>
          <div class="text-white-50" style="font-size:11px"><?= $activeResellers ?> active</div>
        </div>
        <div class="icon bg-white bg-opacity-25"><i class="fas fa-users text-white"></i></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card card h-100" style="background:linear-gradient(135deg,#28a745,#1e7e34)">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-white-50 small">Total Revenue</div>
          <div class="h5 fw-bold text-white mb-0"><?= formatTZS((float)$totalRevenue) ?></div>
          <div class="text-white-50" style="font-size:11px">Leo: <?= formatTZS((float)$todaySales) ?></div>
        </div>
        <div class="icon bg-white bg-opacity-25"><i class="fas fa-money-bill-wave text-white"></i></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card card h-100" style="background:linear-gradient(135deg,#fd7e14,#e36209)">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-white-50 small">Vouchers Used</div>
          <div class="h3 fw-bold text-white mb-0"><?= number_format($totalVouchers) ?></div>
          <div class="text-white-50" style="font-size:11px">Total</div>
        </div>
        <div class="icon bg-white bg-opacity-25"><i class="fas fa-ticket-alt text-white"></i></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card card h-100" style="background:linear-gradient(135deg,#6f42c1,#59359a)">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="text-white-50 small">Routers</div>
          <div class="h3 fw-bold text-white mb-0"><?= number_format($totalRouters) ?></div>
          <div class="text-white-50" style="font-size:11px">
            <?php if ($pendingWithdraw > 0): ?>
              <span class="badge bg-warning text-dark"><?= $pendingWithdraw ?> withdrawal pending</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="icon bg-white bg-opacity-25"><i class="fas fa-router text-white"></i></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Sales Chart -->
  <div class="col-md-8">
    <div class="card">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <span><i class="fas fa-chart-line text-primary me-2"></i>Sales - Days 7 Zilizopita</span>
      </div>
      <div class="card-body">
        <canvas id="salesChart" height="100"></canvas>
      </div>
    </div>
  </div>

  <!-- Announcements -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <span><i class="fas fa-bullhorn text-warning me-2"></i>Announcements</span>
        <a href="announcements.php" class="btn btn-sm btn-outline-primary">+ Add</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($announcements)): ?>
          <div class="p-4 text-center text-muted small">No announcements</div>
        <?php else: ?>
          <?php foreach ($announcements as $ann): ?>
          <div class="border-bottom p-3">
            <div class="d-flex align-items-start gap-2">
              <span class="badge bg-<?= $ann['type'] ?> mt-1"><?= ucfirst($ann['type']) ?></span>
              <div>
                <div class="fw-semibold small"><?= sanitize($ann['title']) ?></div>
                <div class="text-muted" style="font-size:11px"><?= date('d M Y', strtotime($ann['created_at'])) ?></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Transactions -->
  <div class="col-12">
    <div class="card">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <span><i class="fas fa-history text-primary me-2"></i>Payments ya Hivi Karibuni</span>
        <a href="transactions.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Date</th><th>Reseller</th><th>Phone</th><th>Amount</th><th>Type</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($recentTx as $tx): ?>
              <tr>
                <td><?= date('d/m H:i', strtotime($tx['created_at'])) ?></td>
                <td><div class="fw-semibold"><?= sanitize($tx['business_name'] ?: $tx['reseller_name']) ?></div></td>
                <td><?= sanitize($tx['customer_phone']) ?></td>
                <td class="fw-bold text-success"><?= formatTZS((float)$tx['amount']) ?></td>
                <td><span class="badge bg-info"><?= ucfirst($tx['type']) ?></span></td>
                <td>
                  <?php $statusColors = ['completed'=>'success','pending'=>'warning','failed'=>'danger','cancelled'=>'secondary']; ?>
                  <span class="badge bg-<?= $statusColors[$tx['payment_status']] ?? 'secondary' ?>"><?= ucfirst($tx['payment_status']) ?></span>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($recentTx)): ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">No payments yet</td></tr>
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
  type: 'line',
  data: {
    labels: {$chartLabels},
    datasets: [{
      label: 'Sales (TZS)',
      data: {$chartValues},
      borderColor: '#007bff',
      backgroundColor: 'rgba(0,123,255,0.1)',
      borderWidth: 2,
      fill: true,
      tension: 0.4,
      pointBackgroundColor: '#007bff',
      pointRadius: 5
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
