<?php
require_once __DIR__ . '/../includes/init.php';

$resellerId = (int)($_GET['r'] ?? 1);
$routerId   = (int)($_GET['router'] ?? 0);
$userIp     = $_SERVER['REMOTE_ADDR'] ?? '';
$userMac    = sanitize($_GET['mac'] ?? '');

$reseller = DB::fetch("SELECT * FROM resellers WHERE id=? AND status='active'", [$resellerId]);
if (!$reseller) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
    <body style="font-family:sans-serif;text-align:center;padding:60px 20px;background:#f0fdf4">
    <div style="font-size:48px;margin-bottom:16px">⚠️</div>
    <h2 style="color:#dc2626">Portal not found</h2>
    <p style="color:#6b7280">Please contact your WiFi administrator.</p>
    </body></html>');
}

$bizName    = $reseller['business_name'] ?: 'WiFi Hotspot';
$themeColor = $reseller['portal_color'] ?? '#16a34a';
$bgColor    = $reseller['portal_bg_color'] ?? '#f0fdf4';
$showVoucher = (int)($reseller['portal_show_voucher'] ?? 1);
$showAuto    = (int)($reseller['portal_show_packages'] ?? 1);
$footerText  = $reseller['portal_footer_text'] ?? '';

$packages = DB::fetchAll(
    "SELECT p.*, r.name as router_name FROM packages p
     JOIN routers r ON r.id=p.router_id
     WHERE p.reseller_id=? AND p.status='active'"
    . ($routerId ? " AND p.router_id=$routerId" : "")
    . " ORDER BY p.price ASC",
    [$resellerId]
);

$skey   = 'portal_' . $resellerId;
$step   = $_SESSION[$skey . '_step'] ?? 'home';
$error  = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Voucher login ──────────────────────────
    if ($action === 'voucher_login') {
        $code = strtoupper(trim(sanitize($_POST['voucher_code'] ?? '')));
        if (!$code) { $error = 'Please enter your voucher code.'; }
        else {
            $voucher = DB::fetch(
                "SELECT v.*, r.host, r.port, r.username, r.password FROM vouchers v
                 JOIN routers r ON r.id=v.router_id
                 WHERE v.code=? AND v.reseller_id=? AND v.status='unused'",
                [$code, $resellerId]
            );
            if (!$voucher) {
                $error = 'Invalid or already used voucher code.';
            } else {
                // Mark used
                DB::query("UPDATE vouchers SET status='used', used_at=NOW(), used_by_phone='manual' WHERE id=?", [$voucher['id']]);
                // Log the user in via MikroTik
                MikroTik::loginUser($voucher, $userIp, $code, $code);
                $_SESSION[$skey . '_step']    = 'voucher_success';
                $_SESSION[$skey . '_vcode']   = $code;
                $_SESSION[$skey . '_vpkg']    = $voucher['package_name'] ?? '';
                redirect($_SERVER['REQUEST_URI']);
            }
        }
    }

    // ── Select package (auto payment) ──────────
    if ($action === 'select_package') {
        $packageId = (int)$_POST['package_id'];
        $pkg = DB::fetch("SELECT * FROM packages WHERE id=? AND reseller_id=?", [$packageId, $resellerId]);
        if ($pkg) {
            $_SESSION[$skey . '_pkg'] = $pkg;
            $_SESSION[$skey . '_step'] = 'enter_phone';
        }
        redirect($_SERVER['REQUEST_URI']);
    }

    // ── Enter phone for payment ────────────────
    if ($action === 'enter_phone') {
        $phone = sanitize($_POST['phone'] ?? '');
        $name  = sanitize($_POST['name'] ?? 'Customer');
        if (!$phone || strlen(preg_replace('/\D/', '', $phone)) < 9) {
            $error = 'Please enter a valid phone number.';
        } else {
            $pkg  = $_SESSION[$skey . '_pkg'] ?? null;
            if (!$pkg) { $_SESSION[$skey . '_step'] = 'home'; redirect($_SERVER['REQUEST_URI']); }

            $txId   = 'WIFI_' . $resellerId . '_' . time();
            $txDbId = DB::insert(
                "INSERT INTO transactions (reseller_id, package_id, customer_phone, customer_name, amount, type, payment_status)
                 VALUES (?,?,?,?,?,'voucher_sale','pending')",
                [$resellerId, $pkg['id'], $phone, $name, $pkg['price']]
            );
            $_SESSION[$skey . '_tx']    = $txDbId;
            $_SESSION[$skey . '_phone'] = $phone;
            $_SESSION[$skey . '_name']  = $name;

            $apiKey = ($reseller['use_own_gateway'] && $reseller['palmpesa_api_key'])
                    ? $reseller['palmpesa_api_key'] : PALMPESA_API_KEY;

            $palmResult = PalmPesa::initiate([
                'name'           => $name,
                'email'          => preg_replace('/\D/', '', $phone) . '@wifi.local',
                'phone'          => $phone,
                'amount'         => (int)$pkg['price'],
                'transaction_id' => $txId,
                'address'        => 'Tanzania',
                'postcode'       => '00000',
                'callback_url'   => SITE_URL . '/api/palmpesa_callback.php?reseller_id=' . $resellerId
                                    . '&tx_id=' . $txDbId . '&mac=' . urlencode($userMac) . '&ip=' . urlencode($userIp),
            ], $apiKey);

            if ($palmResult['success']) {
                $_SESSION[$skey . '_order'] = $palmResult['order_id'];
                $_SESSION[$skey . '_step']  = 'waiting_payment';
            } else {
                $error = 'Payment initiation failed. Please try again.';
                $_SESSION[$skey . '_step'] = 'enter_phone';
            }
            redirect($_SERVER['REQUEST_URI']);
        }
    }

    // ── Poll payment status ────────────────────
    if ($action === 'check_payment') {
        $txDbId = $_SESSION[$skey . '_tx'] ?? null;
        if ($txDbId) {
            $tx = DB::fetch("SELECT * FROM transactions WHERE id=? AND payment_status='completed'", [$txDbId]);
            if ($tx) { $_SESSION[$skey . '_step'] = 'pay_success'; redirect($_SERVER['REQUEST_URI']); }
        }
        echo json_encode(['paid' => false]); exit;
    }

    // ── Back / reset ───────────────────────────
    if ($action === 'back' || $action === 'reset') {
        unset(
            $_SESSION[$skey . '_step'], $_SESSION[$skey . '_pkg'],
            $_SESSION[$skey . '_tx'],   $_SESSION[$skey . '_phone'],
            $_SESSION[$skey . '_order']
        );
        redirect($_SERVER['REQUEST_URI']);
    }
}

$step = $_SESSION[$skey . '_step'] ?? 'home';
$currentPkg = $_SESSION[$skey . '_pkg'] ?? null;

// Duration label helper
function durationLabel(int $val, string $unit): string {
    $map = ['minutes'=>'min','hours'=>'hr','days'=>'day','weeks'=>'wk','months'=>'mo'];
    $short = $map[$unit] ?? $unit;
    return $val . ' ' . ($val > 1 ? $short . 's' : $short);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title><?= htmlspecialchars($bizName) ?> — WiFi</title>
<style>
:root {
  --brand: <?= htmlspecialchars($themeColor) ?>;
  --brand-light: <?= htmlspecialchars($bgColor) ?>;
  --brand-dark: color-mix(in srgb, <?= htmlspecialchars($themeColor) ?> 80%, black);
}
* { box-sizing:border-box; margin:0; padding:0; }
html, body { min-height:100vh; background:var(--brand-light); font-family:'Segoe UI',system-ui,sans-serif; }

.portal-wrap {
  min-height:100vh;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:flex-start;
  padding:16px 12px 40px;
  background: radial-gradient(ellipse at top, color-mix(in srgb, var(--brand) 15%, white) 0%, var(--brand-light) 70%);
}

/* ── Header ── */
.portal-header {
  text-align:center;
  margin-bottom:20px;
  padding-top:12px;
}
.wifi-icon {
  width:64px;
  height:64px;
  background:var(--brand);
  border-radius:50%;
  display:flex;
  align-items:center;
  justify-content:center;
  margin:0 auto 10px;
  box-shadow:0 4px 20px color-mix(in srgb, var(--brand) 40%, transparent);
}
.wifi-icon svg { width:36px; height:36px; fill:none; stroke:#fff; stroke-width:2.2; stroke-linecap:round; }
.portal-header h1 { font-size:26px; font-weight:800; color:var(--brand); letter-spacing:-0.5px; }
.portal-header p { color:#6b7280; font-size:14px; margin-top:2px; }

/* ── Card ── */
.card {
  background:#fff;
  border-radius:20px;
  box-shadow:0 8px 32px rgba(0,0,0,.10);
  width:100%;
  max-width:440px;
  overflow:hidden;
}

/* ── Tabs ── */
.tabs {
  display:flex;
  background:#f8f9fa;
  border-bottom:1px solid #e9ecef;
}
.tab {
  flex:1;
  padding:14px 8px;
  text-align:center;
  font-size:13px;
  font-weight:600;
  color:#9ca3af;
  cursor:pointer;
  border:none;
  background:none;
  transition:all .2s;
  border-bottom:3px solid transparent;
}
.tab.active {
  color:var(--brand);
  border-bottom-color:var(--brand);
  background:#fff;
}
.tab i { display:block; font-size:18px; margin-bottom:3px; }

/* ── Voucher section ── */
.voucher-section { padding:24px; }
.voucher-input-row { display:flex; gap:8px; margin-bottom:10px; }
.voucher-input {
  flex:1;
  border:2px solid #e5e7eb;
  border-radius:10px;
  padding:12px 14px;
  font-size:16px;
  font-family:'Courier New',monospace;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:2px;
  outline:none;
  transition:border .2s;
}
.voucher-input:focus { border-color:var(--brand); }
.btn-voucher {
  background:var(--brand);
  color:#fff;
  border:none;
  border-radius:10px;
  padding:12px 20px;
  font-weight:700;
  font-size:14px;
  cursor:pointer;
  white-space:nowrap;
  transition:opacity .2s;
}
.btn-voucher:active { opacity:.8; }
.find-link { color:var(--brand); font-size:13px; text-align:center; text-decoration:none; display:block; margin-top:4px; }

/* ── Package list ── */
.packages-section { padding:16px; }
.packages-label { font-size:13px; color:#6b7280; text-align:center; margin-bottom:12px; font-weight:500; }
.pkg-item {
  display:flex;
  align-items:center;
  padding:14px 16px;
  border-radius:12px;
  border:1.5px solid #e5e7eb;
  margin-bottom:8px;
  transition:all .2s;
  cursor:pointer;
  background:#fff;
}
.pkg-item:hover { border-color:var(--brand); background:var(--brand-light); }
.pkg-info { flex:1; }
.pkg-name { font-size:15px; font-weight:700; color:#111827; }
.pkg-desc { font-size:12px; color:#9ca3af; margin-top:2px; }
.pkg-price { font-size:16px; font-weight:800; color:var(--brand); margin:0 12px; white-space:nowrap; }
.btn-buy {
  background:var(--brand);
  color:#fff;
  border:none;
  border-radius:8px;
  padding:8px 16px;
  font-weight:700;
  font-size:13px;
  cursor:pointer;
  transition:opacity .2s;
}
.btn-buy:active { opacity:.8; }

/* ── Phone entry ── */
.phone-section { padding:24px; }
.pkg-badge {
  background:var(--brand-light);
  border:1.5px solid var(--brand);
  border-radius:10px;
  padding:12px 16px;
  margin-bottom:20px;
  display:flex;
  align-items:center;
  gap:12px;
}
.pkg-badge-icon { font-size:28px; }
.pkg-badge-detail .name { font-weight:700; font-size:15px; }
.pkg-badge-detail .price { color:var(--brand); font-weight:800; font-size:18px; }
.field-label { font-size:13px; font-weight:600; color:#374151; margin-bottom:5px; display:block; }
.field-input {
  width:100%;
  border:2px solid #e5e7eb;
  border-radius:10px;
  padding:12px 14px;
  font-size:16px;
  outline:none;
  margin-bottom:12px;
  transition:border .2s;
}
.field-input:focus { border-color:var(--brand); }
.btn-pay {
  width:100%;
  background:var(--brand);
  color:#fff;
  border:none;
  border-radius:12px;
  padding:14px;
  font-size:16px;
  font-weight:800;
  cursor:pointer;
  margin-top:4px;
  transition:opacity .2s;
}
.btn-pay:active { opacity:.8; }
.btn-back { background:none; border:none; color:var(--brand); font-size:13px; cursor:pointer; margin-top:12px; display:block; width:100%; text-align:center; }

/* ── Waiting payment ── */
.waiting-section { padding:28px 24px; text-align:center; }
.stk-icon { font-size:64px; margin-bottom:12px; }
.stk-title { font-size:20px; font-weight:800; margin-bottom:6px; }
.stk-sub { color:#6b7280; font-size:14px; line-height:1.5; }
.pulse { animation:pulse 1.5s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
.stk-steps { background:#f8f9fa; border-radius:12px; padding:16px; margin:16px 0; text-align:left; }
.stk-step { display:flex; gap:10px; align-items:flex-start; margin-bottom:10px; font-size:13px; }
.stk-step:last-child { margin-bottom:0; }
.stk-num { width:22px; height:22px; background:var(--brand); color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; flex-shrink:0; margin-top:1px; }

/* ── Success ── */
.success-section { padding:28px 24px; text-align:center; }
.success-icon { font-size:64px; margin-bottom:12px; }

/* ── Error banner ── */
.error-msg { background:#fef2f2; border:1.5px solid #fecaca; color:#dc2626; border-radius:10px; padding:10px 14px; font-size:13px; margin-bottom:14px; }

/* ── Footer ── */
.portal-footer { text-align:center; color:#9ca3af; font-size:12px; margin-top:20px; }

/* ── Speed badge ── */
.speed-badge { font-size:11px; background:#eff6ff; color:#3b82f6; border-radius:6px; padding:2px 6px; font-weight:600; display:inline-block; margin-top:3px; }
</style>
</head>
<body>
<div class="portal-wrap">

  <!-- Header -->
  <div class="portal-header">
    <div class="wifi-icon">
      <svg viewBox="0 0 24 24"><path d="M5 12.55a11 11 0 0114.08 0M1.42 9a16 16 0 0121.16 0M8.53 16.11a6 6 0 016.95 0M12 20h.01"/></svg>
    </div>
    <h1><?= htmlspecialchars($bizName) ?></h1>
    <p>Select a plan or enter your voucher to connect</p>
  </div>

  <!-- Main card -->
  <div class="card">

    <?php if ($step === 'home'): ?>

    <!-- Tabs -->
    <?php if ($showAuto && $showVoucher): ?>
    <div class="tabs">
      <?php if ($showAuto): ?><button class="tab active" id="tabPkg" onclick="switchTab('pkg')"><i class="fas fa-wifi"></i>Buy Package</button><?php endif; ?>
      <?php if ($showVoucher): ?><button class="tab <?= !$showAuto?'active':'' ?>" id="tabVoucher" onclick="switchTab('voucher')"><i class="fas fa-ticket-alt"></i>Use Voucher</button><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Packages pane -->
    <?php if ($showAuto): ?>
    <div id="panePkg" class="packages-section">
      <?php if ($error): ?><div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <div class="packages-label">Select a package and pay with Mobile Money</div>

      <?php if (empty($packages)): ?>
      <div style="text-align:center;padding:30px;color:#9ca3af">
        <div style="font-size:40px;margin-bottom:8px">📦</div>
        <div>No packages available yet.</div>
      </div>
      <?php else: ?>
      <?php foreach ($packages as $pkg): ?>
      <form method="POST">
        <input type="hidden" name="action" value="select_package">
        <input type="hidden" name="package_id" value="<?= $pkg['id'] ?>">
        <button type="submit" class="pkg-item w-100 border-0" style="text-align:left">
          <div class="pkg-info">
            <div class="pkg-name"><?= htmlspecialchars($pkg['name']) ?></div>
            <div class="pkg-desc"><?= durationLabel($pkg['duration_value'], $pkg['duration_unit']) ?></div>
            <?php if ($pkg['speed_limit']): ?>
            <span class="speed-badge">⚡ <?= htmlspecialchars($pkg['speed_limit']) ?></span>
            <?php endif; ?>
          </div>
          <div class="pkg-price">TZS <?= number_format((float)$pkg['price']) ?></div>
          <button type="submit" class="btn-buy" onclick="event.stopPropagation()">BUY</button>
        </button>
      </form>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Voucher pane -->
    <?php if ($showVoucher): ?>
    <div id="paneVoucher" class="voucher-section" style="<?= $showAuto?'display:none':'' ?>">
      <?php if ($error): ?><div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="voucher_login">
        <div class="voucher-input-row">
          <input type="text" name="voucher_code" class="voucher-input" placeholder="Enter voucher code" autocomplete="off" autocorrect="off" spellcheck="false" maxlength="12">
          <button type="submit" class="btn-voucher"><i class="fas fa-sign-in-alt me-1"></i>Login</button>
        </div>
      </form>
      <a href="#" class="find-link"><i class="fas fa-search me-1"></i>Already bought? Find My Voucher</a>
    </div>
    <?php endif; ?>

    <?php elseif ($step === 'enter_phone' && $currentPkg): ?>

    <!-- Phone Entry -->
    <div class="phone-section">
      <?php if ($error): ?><div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <div class="pkg-badge">
        <div class="pkg-badge-icon">📦</div>
        <div class="pkg-badge-detail">
          <div class="name"><?= htmlspecialchars($currentPkg['name']) ?></div>
          <div class="price">TZS <?= number_format((float)$currentPkg['price']) ?></div>
        </div>
      </div>

      <form method="POST">
        <input type="hidden" name="action" value="enter_phone">
        <label class="field-label">Your Name <span style="color:#9ca3af;font-weight:400">(optional)</span></label>
        <input type="text" name="name" class="field-input" placeholder="e.g. John Doe">

        <label class="field-label">Mobile Number <span style="color:#dc2626">*</span></label>
        <input type="tel" name="phone" class="field-input" placeholder="e.g. 0712345678" required inputmode="tel">

        <div style="font-size:12px;color:#9ca3af;margin-bottom:12px">
          You will receive an STK Push to pay <strong>TZS <?= number_format((float)$currentPkg['price']) ?></strong> on this number.
        </div>

        <button type="submit" class="btn-pay">
          <i class="fas fa-mobile-alt me-2"></i>Send Payment Request
        </button>
      </form>

      <form method="POST">
        <input type="hidden" name="action" value="back">
        <button type="submit" class="btn-back">← Back to packages</button>
      </form>
    </div>

    <?php elseif ($step === 'waiting_payment' && $currentPkg): ?>

    <!-- Waiting for Payment -->
    <div class="waiting-section">
      <div class="stk-icon pulse">📲</div>
      <div class="stk-title">Check Your Phone!</div>
      <div class="stk-sub">We sent a payment request of <strong>TZS <?= number_format((float)$currentPkg['price']) ?></strong> to <strong><?= htmlspecialchars($_SESSION[$skey . '_phone'] ?? '') ?></strong></div>

      <div class="stk-steps">
        <div class="stk-step"><div class="stk-num">1</div><div>A payment prompt has been sent to your phone</div></div>
        <div class="stk-step"><div class="stk-num">2</div><div>Enter your Mobile Money PIN to confirm</div></div>
        <div class="stk-step"><div class="stk-num">3</div><div>You will be connected automatically after payment</div></div>
      </div>

      <div style="font-size:13px;color:#9ca3af;margin-bottom:16px">This page checks automatically...</div>

      <form method="POST">
        <input type="hidden" name="action" value="back">
        <button type="submit" class="btn-back">✕ Cancel</button>
      </form>
    </div>

    <script>
    let checks = 0;
    function checkPay() {
      if (checks++ > 60) return;
      fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=check_payment' })
        .then(r => r.json())
        .then(d => { if (d.paid) location.reload(); else setTimeout(checkPay, 4000); })
        .catch(() => setTimeout(checkPay, 4000));
    }
    setTimeout(checkPay, 4000);
    </script>

    <?php elseif ($step === 'pay_success'): ?>

    <!-- Payment Success -->
    <div class="success-section">
      <div class="success-icon">🎉</div>
      <div style="font-size:22px;font-weight:800;margin-bottom:6px;color:#16a34a">Payment Successful!</div>
      <div style="color:#6b7280;font-size:14px;margin-bottom:20px">
        Your internet access for <strong><?= htmlspecialchars($currentPkg['name'] ?? '') ?></strong> is active.
        <?php if ($_SESSION[$skey . '_phone'] ?? ''): ?>
        Your voucher code has been sent to <strong><?= htmlspecialchars($_SESSION[$skey . '_phone']) ?></strong>.
        <?php endif; ?>
      </div>
      <div style="background:var(--brand-light);border:2px solid var(--brand);border-radius:12px;padding:14px;margin-bottom:20px">
        <div style="font-size:12px;color:#6b7280;margin-bottom:4px">Duration</div>
        <div style="font-size:20px;font-weight:800;color:var(--brand)"><?= durationLabel($currentPkg['duration_value'], $currentPkg['duration_unit']) ?></div>
      </div>
      <div style="font-size:13px;color:#6b7280">You are now connected. Enjoy your browsing! 🌐</div>
      <form method="POST" style="margin-top:16px">
        <input type="hidden" name="action" value="reset">
        <button type="submit" class="btn-back">← Buy another package</button>
      </form>
    </div>

    <?php elseif ($step === 'voucher_success'): ?>

    <!-- Voucher Success -->
    <div class="success-section">
      <div class="success-icon">✅</div>
      <div style="font-size:22px;font-weight:800;margin-bottom:6px;color:#16a34a">Connected!</div>
      <div style="color:#6b7280;font-size:14px;margin-bottom:20px">Your voucher has been activated successfully.</div>
      <div style="background:#1e293b;color:#86efac;font-family:monospace;font-size:24px;font-weight:900;letter-spacing:4px;border-radius:12px;padding:16px;margin-bottom:20px">
        <?= htmlspecialchars($_SESSION[$skey . '_vcode'] ?? '') ?>
      </div>
      <div style="font-size:13px;color:#6b7280">You are now connected to the internet. Enjoy! 🌐</div>
      <form method="POST" style="margin-top:16px">
        <input type="hidden" name="action" value="reset">
        <button type="submit" class="btn-back">← Use a different voucher</button>
      </form>
    </div>

    <?php endif; ?>

  </div>

  <!-- Footer -->
  <div class="portal-footer">
    <?php if ($footerText): ?><div><?= htmlspecialchars($footerText) ?></div><?php endif; ?>
    <div>Powered by <strong>KADILI NET</strong></div>
  </div>

</div>

<?php if ($showAuto && $showVoucher): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script>
function switchTab(tab) {
  const showPkg = tab === 'pkg';
  document.getElementById('panePkg').style.display = showPkg ? '' : 'none';
  document.getElementById('paneVoucher').style.display = showPkg ? 'none' : '';
  document.getElementById('tabPkg').classList.toggle('active', showPkg);
  document.getElementById('tabVoucher').classList.toggle('active', !showPkg);
}
</script>
<?php else: ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php endif; ?>
</body>
</html>
