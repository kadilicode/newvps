<?php
require_once __DIR__ . '/../includes/init.php';
requireAdmin();
$pageTitle = 'Resellers';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'activate') {
        DB::query("UPDATE resellers SET status='active' WHERE id=?", [$id]);
        flash('success', 'Reseller amewezeshwa.');
    } elseif ($action === 'suspend') {
        DB::query("UPDATE resellers SET status='suspended' WHERE id=?", [$id]);
        flash('success', 'Reseller amesimamishwa.');
    } elseif ($action === 'delete') {
        DB::query("DELETE FROM resellers WHERE id=?", [$id]);
        flash('success', 'Reseller amefutwa.');
    } elseif ($action === 'extend') {
        $months = (int)($_POST['months'] ?? 1);
        $reseller = DB::fetch("SELECT subscription_expires FROM resellers WHERE id=?", [$id]);
        $base = ($reseller && $reseller['subscription_expires'] && strtotime($reseller['subscription_expires']) > time())
            ? $reseller['subscription_expires'] : date('Y-m-d');
        $newExpiry = date('Y-m-d', strtotime("+$months months", strtotime($base)));
        DB::query("UPDATE resellers SET subscription_expires=?, status='active' WHERE id=?", [$newExpiry, $id]);
        flash('success', "Subscription umepanuliwa hadi $newExpiry.");
    }
    redirect(SITE_URL . '/admin/resellers.php');
}

$search = sanitize($_GET['s'] ?? '');
$params = [];
$where = '';
if ($search) {
    $where = "WHERE r.name LIKE ? OR r.email LIKE ? OR r.phone LIKE ? OR r.business_name LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

$resellers = DB::fetchAll("
  SELECT r.*, 
    (SELECT COUNT(*) FROM routers WHERE reseller_id=r.id) as router_count,
    (SELECT COUNT(*) FROM transactions WHERE reseller_id=r.id AND payment_status='completed') as tx_count,
    (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE reseller_id=r.id AND payment_status='completed' AND type='voucher_sale') as total_sales
  FROM resellers r $where ORDER BY r.created_at DESC
", $params);

include '_header.php';
?>
<div class="card">
  <div class="card-header py-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <span><i class="fas fa-users text-primary me-2"></i>Resellers (<?= count($resellers) ?>)</span>
    <form class="d-flex gap-2" method="GET">
      <input type="text" name="s" class="form-control form-control-sm" placeholder="Search..." value="<?= $search ?>">
      <button class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
    </form>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr><th>Reseller</th><th>Phone</th><th>Business</th><th>Status</th><th>Subscription</th><th>Routers</th><th>Sales</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($resellers as $r): ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= sanitize($r['name']) ?></div>
            <div class="text-muted small"><?= sanitize($r['email']) ?></div>
          </td>
          <td><?= sanitize($r['phone']) ?></td>
          <td><?= sanitize($r['business_name'] ?: '—') ?></td>
          <td>
            <?php $sc = ['active'=>'success','pending'=>'warning','suspended'=>'danger']; ?>
            <span class="badge bg-<?= $sc[$r['status']] ?>"><?= ucfirst($r['status']) ?></span>
            <?php if (!$r['phone_verified']): ?>
              <span class="badge bg-secondary">OTP</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($r['subscription_expires']): ?>
              <?php $expired = strtotime($r['subscription_expires']) < time(); ?>
              <span class="badge bg-<?= $expired?'danger':'info' ?>"><?= date('d/m/Y', strtotime($r['subscription_expires'])) ?></span>
            <?php else: ?>
              <span class="text-muted small">—</span>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-primary"><?= $r['router_count'] ?></span></td>
          <td class="fw-semibold text-success"><?= formatTZS((float)$r['total_sales']) ?></td>
          <td>
            <div class="d-flex gap-1 flex-wrap">
              <!-- Extend subscription -->
              <button class="btn btn-xs btn-outline-info" style="font-size:11px;padding:2px 8px" 
                onclick="extendSub(<?= $r['id'] ?>)">
                <i class="fas fa-calendar-plus"></i>
              </button>
              <?php if ($r['status'] !== 'active'): ?>
              <form method="POST" class="d-inline">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <input type="hidden" name="action" value="activate">
                <button class="btn btn-xs btn-success" style="font-size:11px;padding:2px 8px">
                  <i class="fas fa-check"></i>
                </button>
              </form>
              <?php else: ?>
              <form method="POST" class="d-inline">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <input type="hidden" name="action" value="suspend">
                <button class="btn btn-xs btn-warning" style="font-size:11px;padding:2px 8px">
                  <i class="fas fa-pause"></i>
                </button>
              </form>
              <?php endif; ?>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete this reseller?')">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button class="btn btn-xs btn-danger" style="font-size:11px;padding:2px 8px">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($resellers)): ?>
        <tr><td colspan="8" class="text-center py-4 text-muted">No resellers found</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Extend Modal -->
<div class="modal fade" id="extendModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Extend Subscription</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="action" value="extend">
          <input type="hidden" name="id" id="extendId">
          <label class="form-label small fw-semibold">Months</label>
          <select name="months" class="form-select">
            <option value="1">1 Month - <?= formatTZS(MONTHLY_FEE) ?></option>
            <option value="3">3 Months - <?= formatTZS(MONTHLY_FEE*3) ?></option>
            <option value="6">6 Months - <?= formatTZS(MONTHLY_FEE*6) ?></option>
            <option value="12">12 Months - <?= formatTZS(MONTHLY_FEE*12) ?></option>
          </select>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary btn-sm">Extend</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = <<<JS
<script>
function extendSub(id) {
  document.getElementById('extendId').value = id;
  new bootstrap.Modal(document.getElementById('extendModal')).show();
}
</script>
JS;
include '_footer.php';
?>
