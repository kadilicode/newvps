<?php
require_once __DIR__ . '/../includes/init.php';
requireReseller();
$pageTitle = 'My Router';
$rid = (int)$_SESSION['reseller_id'];

// One router per reseller
$router = DB::fetch(
    "SELECT r.*, res.business_name FROM routers r
     JOIN resellers res ON res.id=r.reseller_id
     WHERE r.reseller_id=?",
    [$rid]
);

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'test' && $router) {
        $online = MikroTik::testConnection($router);
        DB::query("UPDATE routers SET status=?, last_checked=NOW() WHERE id=?",
                  [$online ? 'online' : 'offline', $router['id']]);
        flash($online ? 'success' : 'warning', $online ? '✅ Router is online!' : '⚠️ Router did not respond.');
        redirect(SITE_URL . '/reseller/routers.php');
    }

    if ($action === 'delete' && $router) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$router['id']) {
            DB::query("DELETE FROM routers WHERE id=? AND reseller_id=?", [$id, $rid]);
            flash('success', 'Router removed. You can now set up a new one.');
        }
        redirect(SITE_URL . '/reseller/routers.php');
    }

    if ($action === 'update_host' && $router) {
        $host = sanitize($_POST['host'] ?? '');
        $user = sanitize($_POST['username'] ?? 'admin');
        $pass = sanitize($_POST['password'] ?? '');
        $port = (int)($_POST['port'] ?? 8728);
        DB::query(
            "UPDATE routers SET host=?, username=?, password=?, port=? WHERE id=? AND reseller_id=?",
            [$host, $user, $pass, $port, $router['id'], $rid]
        );
        flash('success', 'Router connection details updated.');
        redirect(SITE_URL . '/reseller/routers.php');
    }
}

// Refresh router data
$router = DB::fetch("SELECT * FROM routers WHERE reseller_id=?", [$rid]);
$reseller = DB::fetch("SELECT * FROM resellers WHERE id=?", [$rid]);

$activeUsers = $router ? MikroTik::getActiveUsers($router) : [];
$resourceInfo = $router ? MikroTik::getResourceInfo($router) : [];

include '_header.php';
?>

<style>
.router-hero { background:linear-gradient(135deg,#16a34a,#15803d); border-radius:16px; padding:24px; color:#fff; margin-bottom:20px; }
.status-pill { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:20px; font-size:13px; font-weight:600; }
.status-pill.online { background:rgba(255,255,255,.2); color:#fff; }
.status-pill.offline { background:rgba(255,0,0,.2); color:#fca5a5; }
.status-pill.connecting { background:rgba(255,200,0,.2); color:#fde68a; }
.status-pill .dot { width:8px; height:8px; border-radius:50%; background:currentColor; }
.stat-tile { text-align:center; background:rgba(255,255,255,.15); border-radius:12px; padding:12px; }
.stat-tile .val { font-size:24px; font-weight:800; }
.stat-tile .lbl { font-size:11px; opacity:.8; margin-top:2px; }
.info-row { display:flex; align-items:center; padding:10px 0; border-bottom:1px solid #f3f4f6; font-size:14px; }
.info-row:last-child { border:none; }
.info-row .lbl { color:#9ca3af; width:140px; font-size:13px; }
.info-row .val { font-weight:600; }
.setup-steps { display:flex; gap:8px; flex-wrap:wrap; }
.setup-badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.setup-badge.complete { background:#dcfce7; color:#16a34a; }
.setup-badge.pending { background:#fef3c7; color:#d97706; }
</style>

<?php if (!$router): ?>
<!-- ═══ NO ROUTER YET ═══ -->
<div class="card p-0 overflow-hidden">
  <div style="background:linear-gradient(135deg,#16a34a,#15803d);padding:40px 32px;text-align:center;color:#fff">
    <div style="font-size:56px;margin-bottom:16px">🛡️</div>
    <h3 class="fw-bold mb-2">Connect Your MikroTik Router</h3>
    <p style="opacity:.8;margin-bottom:0">One account = one router. Set it up once and manage everything from here.</p>
  </div>
  <div class="p-4">
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="text-center p-3" style="background:#f0fdf4;border-radius:12px">
          <div style="font-size:32px;margin-bottom:6px">🛡️</div>
          <div class="fw-bold text-success small">Step 1</div>
          <div class="text-muted small">Run one command in MikroTik terminal</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="text-center p-3" style="background:#eff6ff;border-radius:12px">
          <div style="font-size:32px;margin-bottom:6px">🔌</div>
          <div class="fw-bold text-primary small">Step 2</div>
          <div class="text-muted small">We scan ports — you pick WAN</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="text-center p-3" style="background:#fdf4ff;border-radius:12px">
          <div style="font-size:32px;margin-bottom:6px">🚀</div>
          <div class="fw-bold small" style="color:#9333ea">Step 3</div>
          <div class="text-muted small">Full hotspot deployed automatically</div>
        </div>
      </div>
    </div>

    <div class="text-center">
      <a href="<?= SITE_URL ?>/reseller/router_setup.php" class="btn btn-success btn-lg px-5">
        <i class="fas fa-play me-2"></i>Start Setup Wizard
      </a>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ═══ ROUTER EXISTS ═══ -->

<?php
$statusColor = match($router['status']) {
  'online'     => 'online',
  'offline'    => 'offline',
  'connecting' => 'connecting',
  default      => 'offline',
};
$stepDone = $router['setup_step'] === 'complete';
?>

<!-- Hero card -->
<div class="router-hero">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
      <div class="d-flex align-items-center gap-3 mb-2">
        <h4 class="mb-0 fw-bold"><?= htmlspecialchars($router['name']) ?></h4>
        <div class="status-pill <?= $statusColor ?>">
          <div class="dot"></div>
          <?= ucfirst($router['status']) ?>
        </div>
      </div>
      <div style="opacity:.8;font-size:14px">
        <i class="fas fa-server me-1"></i><?= htmlspecialchars($router['host']) ?>:<?= $router['port'] ?>
        &nbsp;|&nbsp;<i class="fas fa-user me-1"></i><?= htmlspecialchars($router['username']) ?>
      </div>
      <?php if ($router['last_checked']): ?>
      <div style="opacity:.6;font-size:12px;margin-top:4px">Last checked: <?= date('d M H:i', strtotime($router['last_checked'])) ?></div>
      <?php endif; ?>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <form method="POST" class="d-inline">
        <input type="hidden" name="action" value="test">
        <button class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4)">
          <i class="fas fa-plug me-1"></i>Test
        </button>
      </form>

      <?php if ($stepDone): ?>
      <a href="<?= SITE_URL ?>/reseller/packages.php" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4)">
        <i class="fas fa-box me-1"></i>Packages
      </a>
      <?php else: ?>
      <a href="<?= SITE_URL ?>/reseller/router_setup.php?router_id=<?= $router['id'] ?>" class="btn btn-sm" style="background:rgba(255,255,0,.2);color:#fff;border:1px solid rgba(255,255,0,.5)">
        <i class="fas fa-wrench me-1"></i>Continue Setup
      </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($resourceInfo): ?>
  <div class="row g-2 mt-3">
    <?php
    $uptime = $resourceInfo['uptime'] ?? '?';
    $cpu    = $resourceInfo['cpu-load'] ?? '?';
    $memFree = isset($resourceInfo['free-memory']) ? round((int)$resourceInfo['free-memory'] / 1024 / 1024) . 'MB' : '?';
    $board  = $resourceInfo['board-name'] ?? '?';
    ?>
    <div class="col-3"><div class="stat-tile"><div class="val"><?= $cpu ?>%</div><div class="lbl">CPU</div></div></div>
    <div class="col-3"><div class="stat-tile"><div class="val"><?= $memFree ?></div><div class="lbl">Free RAM</div></div></div>
    <div class="col-3"><div class="stat-tile"><div class="val"><?= count($activeUsers) ?></div><div class="lbl">Active</div></div></div>
    <div class="col-3"><div class="stat-tile"><div class="val" style="font-size:12px;padding-top:4px"><?= $board ?></div><div class="lbl">Board</div></div></div>
  </div>
  <?php endif; ?>
</div>

<div class="row g-3">

  <!-- Setup status + Config info -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header">
        <i class="fas fa-info-circle text-success me-2"></i>Configuration
        <?php if ($stepDone): ?>
        <span class="badge bg-success ms-2" style="font-size:10px">✓ Complete</span>
        <?php else: ?>
        <span class="badge bg-warning ms-2" style="font-size:10px">Setup Pending</span>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <div class="px-3 py-2">
          <div class="info-row"><div class="lbl">WAN Port</div><div class="val"><?= htmlspecialchars($router['wan_interface'] ?? '—') ?></div></div>
          <div class="info-row"><div class="lbl">Bridge</div><div class="val"><?= htmlspecialchars($router['bridge_name'] ?? '—') ?></div></div>
          <div class="info-row"><div class="lbl">Gateway IP</div><div class="val"><?= htmlspecialchars($router['gateway_ip'] ?? '—') ?><?= htmlspecialchars($router['subnet_size'] ?? '') ?></div></div>
          <div class="info-row"><div class="lbl">Hotspot DNS</div><div class="val"><?= htmlspecialchars($router['hotspot_dns'] ?? '—') ?></div></div>
          <div class="info-row"><div class="lbl">VPN Type</div><div class="val"><?= strtoupper($router['vpn_type'] ?? 'direct') ?></div></div>
          <?php if ($router['last_heartbeat']): ?>
          <div class="info-row"><div class="lbl">Last Heartbeat</div><div class="val" style="font-size:12px"><?= date('d M H:i:s', strtotime($router['last_heartbeat'])) ?></div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Update connection -->
    <div class="card mt-3">
      <div class="card-header"><i class="fas fa-edit text-primary me-2"></i>Update Connection Details</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="update_host">
          <div class="row g-2">
            <div class="col-8">
              <input type="text" name="host" class="form-control form-control-sm" placeholder="IP Address" value="<?= htmlspecialchars($router['host']) ?>">
            </div>
            <div class="col-4">
              <input type="number" name="port" class="form-control form-control-sm" placeholder="Port" value="<?= $router['port'] ?>">
            </div>
            <div class="col-6">
              <input type="text" name="username" class="form-control form-control-sm" placeholder="Username" value="<?= htmlspecialchars($router['username']) ?>">
            </div>
            <div class="col-6">
              <input type="password" name="password" class="form-control form-control-sm" placeholder="Password">
            </div>
          </div>
          <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-sm btn-primary flex-fill">Save</button>
            <a href="<?= SITE_URL ?>/reseller/router_setup.php?router_id=<?= $router['id'] ?>&step=2" class="btn btn-sm btn-outline-success flex-fill">Re-scan Ports</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Active users + danger zone -->
  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header"><i class="fas fa-users text-success me-2"></i>Active Sessions (<?= count($activeUsers) ?>)</div>
      <div class="card-body p-0" style="max-height:200px;overflow-y:auto">
        <?php if (empty($activeUsers)): ?>
        <div class="p-4 text-center text-muted small">No active sessions right now.</div>
        <?php else: ?>
        <?php foreach (array_slice($activeUsers, 0, 20) as $u): ?>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom" style="font-size:12px">
          <div>
            <span class="fw-semibold"><?= htmlspecialchars($u['user'] ?? $u['name'] ?? '?') ?></span>
            <span class="text-muted ms-2"><?= htmlspecialchars($u['address'] ?? '') ?></span>
          </div>
          <div class="text-muted"><?= htmlspecialchars($u['uptime'] ?? '') ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Captive portal link -->
    <?php if ($stepDone): ?>
    <div class="card mb-3">
      <div class="card-header"><i class="fas fa-globe text-success me-2"></i>Captive Portal</div>
      <div class="card-body">
        <div class="text-muted small mb-2">Share this link with customers, or it opens automatically when they connect to your WiFi.</div>
        <?php $portalUrl = SITE_URL . '/portal/?r=' . $rid . '&router=' . $router['id']; ?>
        <div class="input-group input-group-sm">
          <input type="text" class="form-control" value="<?= $portalUrl ?>" id="portalUrl" readonly>
          <button class="btn btn-outline-success" onclick="navigator.clipboard.writeText(document.getElementById('portalUrl').value);this.textContent='✓ Copied'">Copy</button>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Danger zone -->
    <div class="card border-danger">
      <div class="card-header text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</div>
      <div class="card-body">
        <p class="small text-muted mb-3">Removing this router will delete all its packages and data. You can add a new one after.</p>
        <form method="POST" onsubmit="return confirm('Are you sure? This will remove your router and all its packages!')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $router['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger w-100">
            <i class="fas fa-trash me-2"></i>Remove Router & Reset
          </button>
        </form>
      </div>
    </div>
  </div>

</div>

<?php endif; ?>

<?php include '_footer.php'; ?>
