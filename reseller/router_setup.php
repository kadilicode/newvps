<?php
require_once __DIR__ . '/../includes/init.php';
requireReseller();
$pageTitle = 'Router Setup';
$rid = (int)$_SESSION['reseller_id'];

// Enforce ONE router per reseller
$existingRouter = DB::fetch("SELECT * FROM routers WHERE reseller_id=?", [$rid]);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ─── Step 1: Initialize setup ─────────────────────────
    if ($action === 'init_setup') {
        if ($existingRouter) {
            flash('error', 'You already have a router on this account. Delete it first to add a new one.');
            redirect(SITE_URL . '/reseller/routers.php');
        }
        $name  = sanitize($_POST['name'] ?? 'My Router');
        $user  = sanitize($_POST['username'] ?? 'admin');
        $pass  = sanitize($_POST['password'] ?? '');
        $token = bin2hex(random_bytes(16));

        $id = DB::insert(
            "INSERT INTO routers (reseller_id, name, host, port, username, password, vpn_type, setup_step, vpn_token, status)
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$rid, $name, '0.0.0.0', 8728, $user, $pass, 'direct', 'step1', $token, 'connecting']
        );
        redirect(SITE_URL . '/reseller/router_setup.php?router_id=' . $id . '&step=1');
    }

    // ─── Set router IP (Step 1 form) ──────────────────────
    if ($action === 'set_ip') {
        $routerId = (int)($_POST['router_id'] ?? 0);
        $ip       = sanitize($_POST['router_ip'] ?? '');
        $r        = DB::fetch("SELECT * FROM routers WHERE id=? AND reseller_id=?", [$routerId, $rid]);
        if ($r && $ip) {
            DB::query("UPDATE routers SET host=? WHERE id=? AND reseller_id=?", [$ip, $routerId, $rid]);
        }
        redirect(SITE_URL . '/reseller/router_setup.php?router_id=' . $routerId . '&step=1');
    }


    // ─── Step 2: Save bridge config ───────────────────────
    if ($action === 'save_bridge') {
        $routerId   = (int)($_POST['router_id'] ?? 0);
        $router     = DB::fetch("SELECT * FROM routers WHERE id=? AND reseller_id=?", [$routerId, $rid]);
        if (!$router) die('Router not found.');

        $wanIface   = sanitize($_POST['wan_interface'] ?? 'ether1');
        $gatewayIp  = sanitize($_POST['gateway_ip'] ?? '192.168.88.1');
        $subnetSize = sanitize($_POST['subnet_size'] ?? '/24');
        $enableHS   = (int)($_POST['enable_hotspot'] ?? 1);

        DB::query(
            "UPDATE routers SET wan_interface=?, gateway_ip=?, subnet_size=?, setup_step='step2' WHERE id=? AND reseller_id=?",
            [$wanIface, $gatewayIp, $subnetSize, $routerId, $rid]
        );

        // Move to step 3 — deploy
        redirect(SITE_URL . '/reseller/router_setup.php?router_id=' . $routerId . '&step=3');
    }

    // ─── Step 3: Trigger full deployment ──────────────────
    if ($action === 'deploy') {
        $routerId = (int)($_POST['router_id'] ?? 0);
        $router   = DB::fetch("SELECT * FROM routers WHERE id=? AND reseller_id=?", [$routerId, $rid]);
        if (!$router) die('Router not found.');

        $reseller    = DB::fetch("SELECT * FROM resellers WHERE id=?", [$rid]);
        $lanIfaces   = $_POST['lan_interfaces'] ?? [];
        $bridgeName  = 'KADILI-BRIDGE';
        $hotspotDns  = 'wifi.com';

        $result = MikroTik::deployHotspot(
            $router,
            $router['wan_interface'] ?? 'ether1',
            $lanIfaces,
            $bridgeName,
            $router['gateway_ip'] ?? '192.168.88.1',
            $router['subnet_size'] ?? '/24',
            $hotspotDns,
            $reseller['business_name'] ?? 'KADILI WiFi'
        );

        $logText = implode("\n", $result['log']);
        DB::query(
            "UPDATE routers SET setup_step=?, bridge_name=?, hotspot_dns=?, deploy_log=?, status=? WHERE id=? AND reseller_id=?",
            [
                $result['success'] ? 'complete' : 'step2',
                $bridgeName, $hotspotDns, $logText,
                $result['success'] ? 'online' : 'offline',
                $routerId, $rid
            ]
        );

        if ($result['success']) {
            flash('success', '🎉 Router setup complete! Your hotspot is live.');
            redirect(SITE_URL . '/reseller/router_setup.php?router_id=' . $routerId . '&step=done');
        } else {
            flash('error', 'Deployment had errors. Check the log below.');
            redirect(SITE_URL . '/reseller/router_setup.php?router_id=' . $routerId . '&step=3&log=1');
        }
    }

    // ─── Reset/Delete router ──────────────────────────────
    if ($action === 'delete_router') {
        $routerId = (int)($_POST['router_id'] ?? 0);
        DB::query("DELETE FROM routers WHERE id=? AND reseller_id=?", [$routerId, $rid]);
        flash('success', 'Router removed. You can now add a new one.');
        redirect(SITE_URL . '/reseller/routers.php');
    }

    redirect(SITE_URL . '/reseller/router_setup.php');
}

// ─── GET: determine current state ─────────────────────────
$routerId = (int)($_GET['router_id'] ?? ($existingRouter['id'] ?? 0));
$step     = $_GET['step'] ?? '0';
$router   = $routerId ? DB::fetch("SELECT * FROM routers WHERE id=? AND reseller_id=?", [$routerId, $rid]) : null;

// Auto-determine step from router state
if ($router && $step === '0') {
    $stepMap = ['none'=>'0','step1'=>'1','step2'=>'2','step3'=>'3','complete'=>'done'];
    $step    = $stepMap[$router['setup_step']] ?? '0';
}

// If step1, try to detect connection via direct test
$isConnected = false;
if ($router && $step === '1') {
    $isConnected = MikroTik::testConnection($router);
    if ($isConnected) {
        // Auto-advance
        if ($router['setup_step'] === 'step1') {
            DB::query("UPDATE routers SET status='online', last_heartbeat=NOW(), setup_step='step2' WHERE id=?", [$router['id']]);
            $router = DB::fetch("SELECT * FROM routers WHERE id=?", [$router['id']]);
        }
        $step = '2';
    }
}

// Scan interfaces for step 2
$interfaces = [];
if ($router && $step === '2') {
    $interfaces = MikroTik::scanInterfaces($router);
}

include '_header.php';

// Helper: generate the install script
function generateInstallScript(array $router, string $siteUrl): string {
    $token = $router['vpn_token'] ?? '';
    $routerId = $router['id'];
    $heartbeatUrl = $siteUrl . '/api/router_heartbeat.php?token=' . $token . '&router_id=' . $routerId;
    return '/tool fetch url="' . $heartbeatUrl . '" mode=https; :delay 1s; /ip service set api disabled=no address=0.0.0.0/0';
}
?>

<style>
.wizard-steps { display:flex; gap:0; margin-bottom:32px; counter-reset:step; }
.wizard-step { flex:1; text-align:center; position:relative; }
.wizard-step::after { content:''; position:absolute; top:20px; left:50%; width:100%; height:2px; background:#e5e7eb; z-index:0; }
.wizard-step:last-child::after { display:none; }
.step-circle { width:40px; height:40px; border-radius:50%; border:2px solid #d1d5db; background:#fff; color:#9ca3af; font-weight:700; font-size:15px; display:flex; align-items:center; justify-content:center; margin:0 auto 8px; position:relative; z-index:1; transition:all .3s; }
.step-circle.active { border-color:#16a34a; background:#16a34a; color:#fff; box-shadow:0 0 0 4px rgba(22,163,74,.2); }
.step-circle.done { border-color:#16a34a; background:#dcfce7; color:#16a34a; }
.step-label { font-size:12px; font-weight:600; color:#6b7280; }
.step-label.active { color:#16a34a; }
.wizard-card { background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.07); overflow:hidden; }
.wizard-card-header { background:linear-gradient(135deg,#16a34a,#15803d); color:#fff; padding:24px 28px; }
.wizard-card-body { padding:28px; }
.code-box { background:#1e293b; color:#86efac; border-radius:10px; padding:16px 20px; font-family:'Courier New',monospace; font-size:13px; word-break:break-all; line-height:1.6; position:relative; }
.copy-btn { position:absolute; top:10px; right:10px; background:rgba(255,255,255,.15); border:none; color:#fff; border-radius:6px; padding:4px 10px; font-size:12px; cursor:pointer; transition:background .2s; }
.copy-btn:hover { background:rgba(255,255,255,.25); }
.iface-card { border:2px solid #e5e7eb; border-radius:10px; padding:14px 16px; cursor:pointer; transition:all .2s; user-select:none; }
.iface-card:hover { border-color:#16a34a; background:#f0fdf4; }
.iface-card.selected { border-color:#16a34a; background:#dcfce7; }
.iface-card.wan-selected { border-color:#3b82f6; background:#eff6ff; }
.status-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
.status-dot.online { background:#22c55e; box-shadow:0 0 6px #22c55e; }
.status-dot.offline { background:#9ca3af; }
.pulse-ring { animation:pulse 1.5s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
.log-box { background:#0f172a; color:#94a3b8; font-family:monospace; font-size:12px; padding:16px; border-radius:10px; max-height:200px; overflow-y:auto; }
.log-box .ok { color:#86efac; }
.log-box .err { color:#f87171; }
</style>

<div class="wizard-steps">
  <?php
  $steps = [
    '1' => ['label'=>'Remote Access','icon'=>'shield-alt'],
    '2' => ['label'=>'Port Config','icon'=>'network-wired'],
    '3' => ['label'=>'Deploy','icon'=>'rocket'],
  ];
  foreach ($steps as $s => $info):
    $isActive = $step === $s || ($step==='done' && $s<='3');
    $isDone   = $step==='done' || ($step!=='0' && (int)$step > (int)$s);
  ?>
  <div class="wizard-step">
    <div class="step-circle <?= $isDone?'done':($isActive?'active':'') ?>">
      <?php if ($isDone): ?><i class="fas fa-check"></i><?php else: ?><?= $s ?><?php endif; ?>
    </div>
    <div class="step-label <?= $isActive?'active':'' ?>"><?= $info['label'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($step === '0'): // ═══ LANDING — no router yet ═══ ?>

<div class="wizard-card">
  <div class="wizard-card-header">
    <div class="d-flex align-items-center gap-3">
      <div style="width:48px;height:48px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px">🛡️</div>
      <div>
        <h4 class="mb-0 fw-bold">Router Setup Wizard</h4>
        <small style="opacity:.8">Connect your MikroTik router in 3 easy steps</small>
      </div>
    </div>
  </div>
  <div class="wizard-card-body">
    <div class="row g-4 mb-4">
      <div class="col-md-4">
        <div class="text-center p-3 rounded-3" style="background:#f0fdf4">
          <div style="font-size:32px;margin-bottom:8px">🛡️</div>
          <div class="fw-bold text-success small">Step 1</div>
          <div class="small text-muted">Paste one command into MikroTik terminal — we handle the rest</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="text-center p-3 rounded-3" style="background:#eff6ff">
          <div style="font-size:32px;margin-bottom:8px">🔌</div>
          <div class="fw-bold text-primary small">Step 2</div>
          <div class="small text-muted">We scan your ports. You pick WAN. Everything else is automatic.</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="text-center p-3 rounded-3" style="background:#fdf4ff">
          <div style="font-size:32px;margin-bottom:8px">🚀</div>
          <div class="fw-bold small" style="color:#9333ea">Step 3</div>
          <div class="small text-muted">One click deploys bridge, DHCP, NAT, hotspot & captive portal</div>
        </div>
      </div>
    </div>

    <div class="alert alert-info rounded-3 small py-2 mb-4">
      <i class="fas fa-info-circle me-2"></i>
      <strong>Before you start:</strong> Your MikroTik must already have internet on ether1 (DHCP client from ISP). Do not touch any other settings.
    </div>

    <form method="POST">
      <input type="hidden" name="action" value="init_setup">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold small">Router Name (for your reference)</label>
          <input type="text" name="name" class="form-control" placeholder="e.g. Main Router" value="My Router">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold small">MikroTik Username</label>
          <input type="text" name="username" class="form-control" value="admin">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold small">MikroTik Password</label>
          <input type="password" name="password" class="form-control" placeholder="Leave blank if none">
        </div>
      </div>
      <div class="mt-4 d-flex gap-2 align-items-center">
        <button type="submit" class="btn btn-success px-5">
          <i class="fas fa-play me-2"></i>Start Setup Wizard
        </button>
        <a href="<?= SITE_URL ?>/reseller/routers.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php elseif ($step === '1'): // ═══ STEP 1: Remote Access ═══ ?>

<?php
$script = generateInstallScript($router, SITE_URL);
// Try to detect IP from host field or use entered host
$routerHost = $router['host'] ?? '';
?>

<div class="wizard-card">
  <div class="wizard-card-header">
    <h5 class="mb-0 fw-bold"><i class="fas fa-shield-alt me-2"></i>Step 1: Secure Remote Access</h5>
    <small style="opacity:.8">Connect your MikroTik so our dashboard can control it</small>
  </div>
  <div class="wizard-card-body">

    <!-- Manual IP entry (for direct connection) -->
    <div class="alert alert-light border rounded-3 mb-4">
      <div class="fw-semibold mb-2"><i class="fas fa-server text-primary me-2"></i>Router IP Address</div>
      <form method="POST" class="d-flex gap-2">
        <input type="hidden" name="action" value="set_ip">
        <input type="hidden" name="router_id" value="<?= $router['id'] ?>">
        <input type="text" name="router_ip" class="form-control form-control-sm" placeholder="e.g. 192.168.88.1 or public IP" value="<?= $routerHost !== '0.0.0.0' ? htmlspecialchars($routerHost) : '' ?>" style="max-width:260px">
        <button type="submit" class="btn btn-sm btn-primary">Save IP</button>
      </form>
      <div class="text-muted small mt-1">Enter the IP address of your MikroTik router (local or public).</div>
    </div>

    <div class="fw-semibold mb-2">Remote Access Installation Script</div>
    <div class="text-muted small mb-3">Copy this command and paste it into your <strong>MikroTik RouterOS terminal</strong> (Winbox → New Terminal, or SSH).</div>

    <div class="code-box" id="scriptBox">
      <?= htmlspecialchars($script) ?>
      <button class="copy-btn" onclick="copyScript()"><i class="fas fa-copy me-1"></i>Copy</button>
    </div>

    <div class="alert alert-warning small rounded-3 mt-3 py-2">
      <i class="fas fa-exclamation-triangle me-1"></i>
      If you get <code>"not allowed by device mode"</code>, first run: <code>/system/device-mode/update mode=advanced</code>
    </div>

    <!-- Waiting indicator -->
    <div class="mt-4 p-4 rounded-3 text-center" style="background:#f8fafc;border:2px dashed #cbd5e1" id="waitingBox">
      <div class="pulse-ring mb-2" style="font-size:28px">⏳</div>
      <div class="fw-semibold text-muted">Waiting for router to connect...</div>
      <div class="text-muted small mt-1">This page checks automatically every 5 seconds.</div>
      <div class="mt-3">
        <a href="<?= SITE_URL ?>/reseller/router_setup.php?router_id=<?= $router['id'] ?>&step=1" class="btn btn-sm btn-outline-success">
          <i class="fas fa-sync me-1"></i>Check Connection
        </a>
      </div>
    </div>

    <!-- Already connected? Skip to step 2 -->
    <div class="mt-3 text-center">
      <small class="text-muted">Router already showing online?</small>
      <a href="<?= SITE_URL ?>/reseller/router_setup.php?router_id=<?= $router['id'] ?>&step=2" class="btn btn-sm btn-success ms-2">
        <i class="fas fa-arrow-right me-1"></i>Continue to Port Configuration
      </a>
    </div>

    <form method="POST" class="mt-3 text-end">
      <input type="hidden" name="action" value="delete_router">
      <input type="hidden" name="router_id" value="<?= $router['id'] ?>">
      <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancel and remove this router?')">
        <i class="fas fa-times me-1"></i>Cancel Setup
      </button>
    </form>
  </div>
</div>

<script>
function copyScript() {
  const text = document.getElementById('scriptBox').textContent.trim();
  navigator.clipboard.writeText(text).then(() => {
    const btn = document.querySelector('.copy-btn');
    btn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
    setTimeout(() => btn.innerHTML = '<i class="fas fa-copy me-1"></i>Copy', 2000);
  });
}

// Auto-poll every 5 seconds
let polls = 0;
function checkConnection() {
  if (polls++ > 60) return; // stop after 5 mins
  fetch('<?= SITE_URL ?>/api/router_heartbeat.php?check=1&router_id=<?= $router['id'] ?>')
    .then(r => r.json())
    .then(d => {
      if (d.connected) {
        window.location = '<?= SITE_URL ?>/reseller/router_setup.php?router_id=<?= $router['id'] ?>&step=2';
      } else {
        setTimeout(checkConnection, 5000);
      }
    })
    .catch(() => setTimeout(checkConnection, 5000));
}
setTimeout(checkConnection, 5000);
</script>

<?php elseif ($step === '2'): // ═══ STEP 2: Port Configuration ═══ ?>

<?php $wanDefault = 'ether1'; ?>

<div class="wizard-card">
  <div class="wizard-card-header">
    <h5 class="mb-0 fw-bold"><i class="fas fa-network-wired me-2"></i>Step 2: Port Configuration</h5>
    <small style="opacity:.8">We scanned your MikroTik. Configure your WAN and LAN ports below.</small>
  </div>
  <div class="wizard-card-body">

    <?php if (empty($interfaces)): ?>
    <div class="alert alert-warning rounded-3">
      <i class="fas fa-plug me-2"></i>Could not scan interfaces. Please check that the router IP is correct and API port 8728 is accessible.
      <div class="mt-2">
        <a href="<?= SITE_URL ?>/reseller/router_setup.php?router_id=<?= $router['id'] ?>&step=1" class="btn btn-sm btn-warning">← Back to Step 1</a>
      </div>
    </div>
    <?php else: ?>

    <form method="POST" id="bridgeForm">
      <input type="hidden" name="action" value="save_bridge">
      <input type="hidden" name="router_id" value="<?= $router['id'] ?>">
      <input type="hidden" name="wan_interface" id="wanInput" value="<?= $wanDefault ?>">

      <!-- Interface visual map -->
      <div class="mb-4">
        <div class="fw-semibold mb-1">Select WAN Port (Internet from ISP)</div>
        <div class="text-muted small mb-3">Click the port connected to your ISP/modem. Usually <strong>ether1</strong>.</div>
        <div class="row g-2">
          <?php foreach ($interfaces as $iface): ?>
          <div class="col-md-3 col-6">
            <div class="iface-card <?= $iface['name']===$wanDefault?'wan-selected':'' ?>"
                 onclick="selectWAN('<?= htmlspecialchars($iface['name']) ?>')"
                 id="iface_<?= htmlspecialchars($iface['name']) ?>">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="status-dot <?= $iface['running']?'online':'offline' ?>"></span>
                <strong style="font-size:13px"><?= htmlspecialchars($iface['name']) ?></strong>
              </div>
              <div class="small text-muted"><?= strtoupper($iface['type']) ?></div>
              <div style="font-size:10px;color:#94a3b8"><?= htmlspecialchars($iface['mac'] ?? '') ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <hr>

      <!-- Bridge configuration -->
      <div class="mb-4">
        <div class="fw-semibold mb-1">Bridge (Gateway) Configuration</div>
        <div class="text-muted small mb-3">All other ports will be added to the bridge. Adjust IP if needed.</div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Gateway IP Address</label>
            <input type="text" name="gateway_ip" class="form-control"
                   value="<?= htmlspecialchars($router['gateway_ip'] ?? '192.168.88.1') ?>"
                   placeholder="192.168.88.1">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Subnet Size</label>
            <select name="subnet_size" class="form-select">
              <option value="/28" <?= ($router['subnet_size']??'/24')==='/28'?'selected':'' ?>>/28 (14 Hosts)</option>
              <option value="/27" <?= ($router['subnet_size']??'/24')==='/27'?'selected':'' ?>>/27 (30 Hosts)</option>
              <option value="/26" <?= ($router['subnet_size']??'/24')==='/26'?'selected':'' ?>>/26 (62 Hosts)</option>
              <option value="/25" <?= ($router['subnet_size']??'/24')==='/25'?'selected':'' ?>>/25 (126 Hosts)</option>
              <option value="/24" <?= ($router['subnet_size']??'/24')==='/24'?'selected':'' ?>>/24 (254 Hosts) ✓</option>
              <option value="/23" <?= ($router['subnet_size']??'/24')==='/23'?'selected':'' ?>>/23 (510 Hosts)</option>
              <option value="/22" <?= ($router['subnet_size']??'/24')==='/22'?'selected':'' ?>>/22 (1022 Hosts)</option>
              <option value="/19" <?= ($router['subnet_size']??'/24')==='/19'?'selected':'' ?>>/19 (8190 Hosts)</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Bridge Services</label>
            <div class="p-2 border rounded-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="enable_hotspot" value="1" id="hsCheck" checked>
                <label class="form-check-label small" for="hsCheck">
                  <strong>Hotspot Server</strong> (Captive Portal)
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success px-5">
          <i class="fas fa-arrow-right me-2"></i>Save & Continue to Deployment
        </button>
        <a href="<?= SITE_URL ?>/reseller/router_setup.php?router_id=<?= $router['id'] ?>&step=1" class="btn btn-outline-secondary">← Back</a>
      </div>
    </form>

    <script>
    let selectedWAN = '<?= $wanDefault ?>';

    function selectWAN(name) {
      document.querySelectorAll('.iface-card').forEach(el => {
        el.classList.remove('wan-selected');
        el.classList.remove('selected');
      });
      document.getElementById('iface_' + name).classList.add('wan-selected');
      document.getElementById('wanInput').value = name;
      selectedWAN = name;
    }
    </script>

    <?php endif; ?>
  </div>
</div>

<?php elseif ($step === '3'): // ═══ STEP 3: Deploy ═══ ?>

<?php
// Get all interfaces except WAN for LAN selection
$wanIface   = $router['wan_interface'] ?? 'ether1';
$allIfaces  = MikroTik::scanInterfaces($router);
$lanIfaces  = array_filter($allIfaces, fn($i) => $i['name'] !== $wanIface);
$showLog    = isset($_GET['log']);
$deployLog  = $router['deploy_log'] ?? '';
?>

<div class="wizard-card">
  <div class="wizard-card-header">
    <h5 class="mb-0 fw-bold"><i class="fas fa-rocket me-2"></i>Step 3: Service Deployment</h5>
    <small style="opacity:.8">Review the configuration and click Deploy to set up everything automatically</small>
  </div>
  <div class="wizard-card-body">

    <!-- Summary -->
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="p-3 rounded-3 text-center" style="background:#f0fdf4">
          <div style="font-size:24px">🌐</div>
          <div class="small fw-semibold text-success mt-1">WAN (Internet)</div>
          <div class="fw-bold"><?= htmlspecialchars($wanIface) ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-3 rounded-3 text-center" style="background:#eff6ff">
          <div style="font-size:24px">🔌</div>
          <div class="small fw-semibold text-primary mt-1">Gateway IP</div>
          <div class="fw-bold"><?= htmlspecialchars($router['gateway_ip'] ?? '192.168.88.1') ?><?= htmlspecialchars($router['subnet_size'] ?? '/24') ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-3 rounded-3 text-center" style="background:#fdf4ff">
          <div style="font-size:24px">🔥</div>
          <div class="small fw-semibold mt-1" style="color:#9333ea">Hotspot DNS</div>
          <div class="fw-bold">wifi.com</div>
        </div>
      </div>
    </div>

    <!-- LAN ports to add -->
    <form method="POST" id="deployForm">
      <input type="hidden" name="action" value="deploy">
      <input type="hidden" name="router_id" value="<?= $router['id'] ?>">

      <div class="fw-semibold mb-2">LAN Ports (all non-WAN ports — will be added to bridge)</div>
      <div class="row g-2 mb-4">
        <?php if (empty($lanIfaces)): ?>
          <div class="col-12"><div class="alert alert-warning small py-2">No LAN interfaces detected. Step back and re-scan.</div></div>
        <?php else: ?>
          <?php foreach ($lanIfaces as $iface): ?>
          <div class="col-md-3 col-6">
            <label class="iface-card d-flex align-items-center gap-2" style="cursor:pointer">
              <input type="checkbox" name="lan_interfaces[]" value="<?= htmlspecialchars($iface['name']) ?>" checked style="accent-color:#16a34a">
              <div>
                <div class="fw-semibold" style="font-size:13px"><?= htmlspecialchars($iface['name']) ?></div>
                <div class="text-muted" style="font-size:11px"><?= strtoupper($iface['type']) ?></div>
              </div>
              <span class="status-dot ms-auto <?= $iface['running']?'online':'offline' ?>"></span>
            </label>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="alert alert-info rounded-3 small py-2 mb-4">
        <i class="fas fa-magic me-2"></i>
        <strong>What will be deployed:</strong> Bridge interface, IP address, DHCP server (IP Pool), NAT masquerade, Hotspot server with captive portal, DNS redirect to <strong>wifi.com</strong>, and Walled Garden for payment gateway.
      </div>

      <?php if ($showLog && $deployLog): ?>
      <div class="mb-4">
        <div class="fw-semibold mb-2 text-danger">Deployment Log (Previous Attempt):</div>
        <div class="log-box">
          <?php foreach (explode("\n", $deployLog) as $line): ?>
          <div class="<?= str_contains($line,'❌')?'err':'ok' ?>"><?= htmlspecialchars($line) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success px-5" id="deployBtn"
                onclick="this.disabled=true; this.innerHTML='<i class=\'fas fa-spinner fa-spin me-2\'></i>Deploying...'; this.form.submit();">
          <i class="fas fa-rocket me-2"></i>Deploy Everything Now
        </button>
        <a href="<?= SITE_URL ?>/reseller/router_setup.php?router_id=<?= $router['id'] ?>&step=2" class="btn btn-outline-secondary">← Back</a>
      </div>
    </form>
  </div>
</div>

<?php elseif ($step === 'done'): // ═══ DONE ═══ ?>

<div class="wizard-card text-center">
  <div class="wizard-card-body py-5">
    <div style="font-size:64px;margin-bottom:16px">🎉</div>
    <h3 class="fw-bold text-success">Hotspot is Live!</h3>
    <p class="text-muted mb-4">Your MikroTik router is fully configured. Customers can now connect to your WiFi and purchase packages.</p>

    <?php if ($router): ?>
    <div class="row g-3 justify-content-center mb-4">
      <div class="col-md-3">
        <div class="p-3 rounded-3" style="background:#f0fdf4">
          <div class="fw-bold text-success"><?= htmlspecialchars($router['gateway_ip'] ?? '') ?></div>
          <div class="small text-muted">Gateway IP</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="p-3 rounded-3" style="background:#eff6ff">
          <div class="fw-bold text-primary"><?= htmlspecialchars($router['bridge_name'] ?? 'KADILI-BRIDGE') ?></div>
          <div class="small text-muted">Bridge Name</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="p-3 rounded-3" style="background:#fdf4ff">
          <div class="fw-bold" style="color:#9333ea"><?= htmlspecialchars($router['hotspot_dns'] ?? 'wifi.com') ?></div>
          <div class="small text-muted">Portal DNS</div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2 justify-content-center">
      <a href="<?= SITE_URL ?>/reseller/packages.php" class="btn btn-success px-4">
        <i class="fas fa-box me-2"></i>Add Packages
      </a>
      <a href="<?= SITE_URL ?>/reseller/dashboard.php" class="btn btn-outline-secondary">
        <i class="fas fa-home me-2"></i>Dashboard
      </a>
    </div>

    <?php if ($router && $router['deploy_log']): ?>
    <details class="mt-4 text-start">
      <summary class="btn btn-sm btn-outline-secondary">View Deployment Log</summary>
      <div class="log-box mt-2">
        <?php foreach (explode("\n", $router['deploy_log']) as $line): ?>
        <div class="<?= str_contains($line,'❌')?'err':'ok' ?>"><?= htmlspecialchars($line) ?></div>
        <?php endforeach; ?>
      </div>
    </details>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>

<?php include '_footer.php'; ?>
