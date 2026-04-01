<?php
require_once __DIR__ . '/../includes/init.php';
requireReseller();
$pageTitle = 'Packages';
$rid = (int)$_SESSION['reseller_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        DB::query(
            "INSERT INTO packages (reseller_id, router_id, name, price, duration_value, duration_unit, speed_limit, profile_name) VALUES (?,?,?,?,?,?,?,?)",
            [
                $rid,
                (int)$_POST['router_id'],
                sanitize($_POST['name']),
                (float)$_POST['price'],
                (int)$_POST['duration_value'],
                sanitize($_POST['duration_unit']),
                sanitize($_POST['speed_limit'] ?? ''),
                sanitize($_POST['profile_name']),
            ]
        );
        flash('success', 'Package added.');
    } elseif ($action === 'delete') {
        DB::query("DELETE FROM packages WHERE id=? AND reseller_id=?", [(int)$_POST['id'], $rid]);
        flash('success', 'Package deleted.');
    } elseif ($action === 'toggle') {
        DB::query("UPDATE packages SET status=IF(status='active','inactive','active') WHERE id=? AND reseller_id=?", [(int)$_POST['id'], $rid]);
    }
    redirect(SITE_URL . '/reseller/packages.php');
}

$routerFilter = (int)($_GET['router_id'] ?? 0);
$routers  = DB::fetchAll("SELECT * FROM routers WHERE reseller_id=?", [$rid]);
$packages = DB::fetchAll(
    "SELECT p.*, r.name as router_name FROM packages p JOIN routers r ON r.id=p.router_id WHERE p.reseller_id=?" . ($routerFilter?" AND p.router_id=$routerFilter":"") . " ORDER BY p.created_at DESC",
    [$rid]
);

include '_header.php';
?>
<div class="row g-3">
  <!-- Add Package -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header"><i class="fas fa-plus text-primary me-2"></i>Add Package</div>
      <div class="card-body">
        <?php if (empty($routers)): ?>
          <div class="alert alert-warning small"><i class="fas fa-exclamation-triangle me-2"></i>Add router kwanza.</div>
        <?php else: ?>
        <form method="POST">
          <input type="hidden" name="action" value="add">
          <div class="mb-2">
            <label class="form-label small fw-semibold">Router *</label>
            <select name="router_id" class="form-select form-select-sm" required>
              <?php foreach ($routers as $r): ?>
                <option value="<?= $r['id'] ?>" <?= $routerFilter===$r['id']?'selected':'' ?>><?= sanitize($r['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label small fw-semibold">Package Name *</label>
            <input type="text" name="name" class="form-control form-control-sm" placeholder="Mfano: Hours 2" required>
          </div>
          <div class="mb-2">
            <label class="form-label small fw-semibold">Price (TZS) *</label>
            <input type="number" name="price" class="form-control form-control-sm" placeholder="500" min="0" required>
          </div>
          <div class="mb-2 row g-1">
            <div class="col-5">
              <label class="form-label small fw-semibold">Duration *</label>
              <input type="number" name="duration_value" class="form-control form-control-sm" placeholder="2" min="1" required>
            </div>
            <div class="col-7">
              <label class="form-label small fw-semibold">&nbsp;</label>
              <select name="duration_unit" class="form-select form-select-sm">
                <option value="minutes">Minutes</option>
                <option value="hours" selected>Hours</option>
                <option value="days">Days</option>
                <option value="weeks">Weeks</option>
                <option value="months">Months</option>
              </select>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label small fw-semibold">Speed (optional)</label>
            <input type="text" name="speed_limit" class="form-control form-control-sm" placeholder="2M/2M">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">MikroTik Profile *</label>
            <input type="text" name="profile_name" class="form-control form-control-sm" placeholder="default" required>
            <div class="form-text">Profile name as set on your router</div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-plus me-2"></i>Add</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Packages List -->
  <div class="col-md-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-box text-primary me-2"></i>All Packages (<?= count($packages) ?>)</span>
        <?php if ($routerFilter): ?>
          <a href="packages.php" class="btn btn-xs btn-outline-secondary" style="font-size:11px;padding:2px 8px">× Clear Filter</a>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <?php if (empty($packages)): ?>
          <div class="p-5 text-center text-muted">No packages yet.</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Name</th><th>Router</th><th>Price</th><th>Duration</th><th>Profile</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($packages as $p): ?>
              <tr>
                <td class="fw-semibold"><?= sanitize($p['name']) ?></td>
                <td><span class="badge bg-light text-dark border"><?= sanitize($p['router_name']) ?></span></td>
                <td class="fw-bold text-success"><?= formatTZS((float)$p['price']) ?></td>
                <td><?= $p['duration_value'] . ' ' . $p['duration_unit'] ?></td>
                <td><code><?= sanitize($p['profile_name']) ?></code></td>
                <td>
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <button class="badge bg-<?= $p['status']==='active'?'success':'secondary' ?> border-0" style="cursor:pointer">
                      <?= $p['status'] === 'active'?'Active':'Disabled' ?>
                    </button>
                  </form>
                </td>
                <td>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete package?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <button class="btn btn-xs btn-danger" style="font-size:11px;padding:2px 7px"><i class="fas fa-trash"></i></button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include '_footer.php'; ?>
