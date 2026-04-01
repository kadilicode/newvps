<?php
// reseller/transactions.php
require_once __DIR__ . '/../includes/init.php';
requireReseller();
$pageTitle = 'Payments Yangu';
$rid = (int)$_SESSION['reseller_id'];

$transactions = DB::fetchAll("SELECT * FROM transactions WHERE reseller_id=? ORDER BY created_at DESC LIMIT 100", [$rid]);
$total = DB::fetch("SELECT COALESCE(SUM(amount),0) as s FROM transactions WHERE reseller_id=? AND payment_status='completed'", [$rid])['s'];

include '_header.php';
?>
<div class="card">
  <div class="card-header d-flex justify-content-between">
    <span><i class="fas fa-receipt text-primary me-2"></i>Payments Yangu</span>
    <span class="badge bg-success fs-6"><?= formatTZS((float)$total) ?></span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Date</th><th>Customer</th><th>Amount</th><th>Order ID</th><th>Type</th><th>Status</th><th>SMS</th></tr></thead>
        <tbody>
        <?php foreach ($transactions as $t): ?>
        <tr>
          <td><?= date('d/m H:i', strtotime($t['created_at'])) ?></td>
          <td><?= sanitize($t['customer_phone']) ?></td>
          <td class="fw-bold text-success"><?= formatTZS((float)$t['amount']) ?></td>
          <td><small class="text-muted"><?= sanitize($t['palmpesa_order_id'] ?: '—') ?></small></td>
          <td><span class="badge bg-info"><?= sanitize($t['type']) ?></span></td>
          <td>
            <?php $sc=['completed'=>'success','pending'=>'warning','failed'=>'danger','cancelled'=>'secondary']; ?>
            <span class="badge bg-<?= $sc[$t['payment_status']] ?>"><?= ucfirst($t['payment_status']) ?></span>
          </td>
          <td><?= $t['sms_sent'] ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-muted"></i>' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($transactions)): ?>
        <tr><td colspan="7" class="text-center py-4 text-muted">No payments yet</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include '_footer.php'; ?>
