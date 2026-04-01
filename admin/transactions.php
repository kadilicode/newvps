<?php
require_once __DIR__ . '/../includes/init.php';
requireAdmin();
$pageTitle = 'Payments';

$transactions = DB::fetchAll("
  SELECT t.*, r.name as reseller_name, r.business_name
  FROM transactions t
  JOIN resellers r ON r.id = t.reseller_id
  ORDER BY t.created_at DESC LIMIT 200
");

$totalCompleted = DB::fetch("SELECT COALESCE(SUM(amount),0) as s FROM transactions WHERE payment_status='completed'")['s'];
$totalPending   = DB::fetch("SELECT COUNT(*) as c FROM transactions WHERE payment_status='pending'")['c'];

include '_header.php';
?>
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div class="text-muted small">Total Revenue</div>
      <div class="fw-bold text-success"><?= formatTZS((float)$totalCompleted) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div class="text-muted small">Payments Pending</div>
      <div class="fw-bold text-warning"><?= $totalPending ?></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header py-3"><i class="fas fa-money-bill-wave text-primary me-2"></i>Payments View All</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr><th>Date</th><th>Reseller</th><th>Customer</th><th>Amount</th><th>PalmPesa ID</th><th>Type</th><th>Status</th><th>SMS</th></tr>
        </thead>
        <tbody>
        <?php foreach ($transactions as $t): ?>
        <tr>
          <td style="white-space:nowrap"><?= date('d/m H:i', strtotime($t['created_at'])) ?></td>
          <td><?= sanitize($t['business_name'] ?: $t['reseller_name']) ?></td>
          <td><?= sanitize($t['customer_phone']) ?></td>
          <td class="fw-bold text-success"><?= formatTZS((float)$t['amount']) ?></td>
          <td><small class="text-muted"><?= sanitize($t['palmpesa_order_id'] ?: '—') ?></small></td>
          <td><span class="badge bg-info"><?= sanitize($t['type']) ?></span></td>
          <td>
            <?php $sc = ['completed'=>'success','pending'=>'warning','failed'=>'danger','cancelled'=>'secondary']; ?>
            <span class="badge bg-<?= $sc[$t['payment_status']] ?>"><?= ucfirst($t['payment_status']) ?></span>
          </td>
          <td>
            <?php if ($t['sms_sent']): ?>
              <i class="fas fa-check-circle text-success"></i>
            <?php else: ?>
              <i class="fas fa-times-circle text-muted"></i>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '_footer.php'; ?>
