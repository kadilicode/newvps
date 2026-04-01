<?php
require_once __DIR__ . '/../includes/init.php';
requireReseller();
$pageTitle = 'Active Users';
$rid = (int)$_SESSION['reseller_id'];

// Handle kick user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'kick') {
    $userId = (int)$_POST['id'];
    $user   = DB::fetch("SELECT hu.*, r.host, r.port, r.username as r_user, r.password as r_pass FROM hotspot_users hu JOIN routers r ON r.id=hu.router_id WHERE hu.id=? AND hu.reseller_id=?", [$userId, $rid]);
    if ($user) {
        $router = ['host'=>$user['host'],'port'=>$user['port'],'username'=>$user['r_user'],'password'=>$user['r_pass']];
        MikroTik::removeHotspotUser($router, $user['username']);
        DB::query("UPDATE hotspot_users SET status='expired' WHERE id=?", [$userId]);
        DB::query("UPDATE vouchers SET status='expired' WHERE code=?", [$user['username']]);
        flash('success', 'User ametolewa.');
    }
    redirect(SITE_URL . '/reseller/users.php');
}

$users = DB::fetchAll("
    SELECT hu.*, p.name as package_name, r.name as router_name
    FROM hotspot_users hu
    LEFT JOIN packages p ON p.id = hu.package_id
    LEFT JOIN routers r ON r.id = hu.router_id
    WHERE hu.reseller_id = ?
    ORDER BY hu.created_at DESC LIMIT 100
", [$rid]);

$activeCount  = DB::fetch("SELECT COUNT(*) as c FROM hotspot_users WHERE reseller_id=? AND status='active' AND expires_at > NOW()", [$rid])['c'];
$expiredCount = DB::fetch("SELECT COUNT(*) as c FROM hotspot_users WHERE reseller_id=? AND status='expired'", [$rid])['c'];

include '_header.php';
?>
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div class="text-muted small">Active Sasa</div>
      <div class="fw-bold text-success h4 mb-0"><?= $activeCount ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div class="text-muted small">Expired</div>
      <div class="fw-bold text-secondary h4 mb-0"><?= $expiredCount ?></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header py-3"><i class="fas fa-users text-primary me-2"></i>Hotspot Users</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr><th>User</th><th>Phone</th><th>Package</th><th>Router</th><th>Expires</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <?php
        $isActive = $u['status'] === 'active' && $u['expires_at'] && strtotime($u['expires_at']) > time();
        $minsLeft = $u['expires_at'] ? max(0, (int)((strtotime($u['expires_at']) - time()) / 60)) : 0;
        ?>
        <tr>
          <td><code class="fw-bold"><?= sanitize($u['username']) ?></code></td>
          <td><?= sanitize($u['phone'] ?? '—') ?></td>
          <td><?= sanitize($u['package_name'] ?? '—') ?></td>
          <td><span class="badge bg-light text-dark border"><?= sanitize($u['router_name'] ?? '—') ?></span></td>
          <td>
            <?php if ($u['expires_at']): ?>
              <div style="font-size:12px"><?= date('d/m H:i', strtotime($u['expires_at'])) ?></div>
              <?php if ($isActive && $minsLeft < 60): ?>
                <span class="badge bg-warning text-dark"><?= $minsLeft ?>min</span>
              <?php endif; ?>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td>
            <span class="badge bg-<?= $isActive?'success':'secondary' ?>">
              <?= $isActive?'Active':'Expired' ?>
            </span>
          </td>
          <td>
            <?php if ($isActive): ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Remove this user?')">
              <input type="hidden" name="action" value="kick">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-xs btn-danger" style="font-size:11px;padding:2px 7px">
                <i class="fas fa-user-times"></i>
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
          <tr><td colspan="7" class="text-center py-4 text-muted">No users yet</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '_footer.php'; ?>
