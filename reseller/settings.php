<?php
require_once __DIR__ . '/../includes/init.php';
requireReseller();
$pageTitle = 'Settings ya Account';
$rid = (int)$_SESSION['reseller_id'];

$reseller = DB::fetch("SELECT * FROM resellers WHERE id=?", [$rid]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $biz   = sanitize($_POST['business_name'] ?? '');
        $logo  = sanitize($_POST['logo_url'] ?? '');
        DB::query("UPDATE resellers SET business_name=?, logo_url=? WHERE id=?", [$biz, $logo, $rid]);
        $_SESSION['business_name'] = $biz;
        flash('success', 'Profile saved.');
    } elseif ($action === 'password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $c   = $_POST['confirm_password'] ?? '';
        if (!password_verify($old, $reseller['password'])) {
            flash('error', 'Old password is incorrect.');
        } elseif ($new !== $c || strlen($new) < 6) {
            flash('error', 'New passwords do not match or are too short.');
        } else {
            DB::query("UPDATE resellers SET password=? WHERE id=?", [password_hash($new, PASSWORD_BCRYPT), $rid]);
            flash('success', 'Password changed.');
        }
    } elseif ($action === 'portal') {
        $theme  = sanitize($_POST['portal_theme'] ?? 'modern');
        $color  = sanitize($_POST['portal_color'] ?? '#16a34a');
        $bgcol  = sanitize($_POST['portal_bg_color'] ?? '#f0fdf4');
        $wname  = sanitize($_POST['portal_wifi_name'] ?? '');
        $footer = sanitize($_POST['portal_footer_text'] ?? '');
        $showV  = (int)($_POST['portal_show_voucher'] ?? 1);
        $showP  = (int)($_POST['portal_show_packages'] ?? 1);
        DB::query(
            "UPDATE resellers SET portal_theme=?, portal_color=?, portal_bg_color=?, portal_wifi_name=?, portal_footer_text=?, portal_show_voucher=?, portal_show_packages=? WHERE id=?",
            [$theme, $color, $bgcol, $wname, $footer, $showV, $showP, $rid]
        );
        flash('success', 'Captive portal settings saved.');
    } elseif ($action === 'gateway') {
        $own    = (int)($_POST['use_own_gateway'] ?? 0);
        $apiKey = sanitize($_POST['palmpesa_api_key'] ?? '');
        DB::query("UPDATE resellers SET use_own_gateway=?, palmpesa_api_key=? WHERE id=?", [$own, $apiKey, $rid]);
        flash('success', 'Settings ya malipo imehifadhiwa.');
    }
    redirect(SITE_URL . '/reseller/settings.php');
}

include '_header.php';
?>
<div class="row g-3">
  <!-- Profile -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><i class="fas fa-user-edit text-primary me-2"></i>Wasifu wa Business</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="profile">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Email</label>
            <input type="text" class="form-control form-control-sm" value="<?= sanitize($reseller['email']) ?>" disabled>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Phone</label>
            <input type="text" class="form-control form-control-sm" value="<?= sanitize($reseller['phone']) ?>" disabled>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Name la Business</label>
            <input type="text" name="business_name" class="form-control form-control-sm" value="<?= sanitize($reseller['business_name'] ?? '') ?>" placeholder="Mfano: Juma WiFi Zone">
            <div class="form-text">This name will appear on your captive portal</div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Logo URL (optional)</label>
            <input type="url" name="logo_url" class="form-control form-control-sm" value="<?= sanitize($reseller['logo_url'] ?? '') ?>" placeholder="https://...">
          </div>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-2"></i>Save</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Password -->
  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header"><i class="fas fa-lock text-primary me-2"></i>Change Password</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="password">
          <div class="mb-2">
            <label class="form-label small fw-semibold">Old Password</label>
            <input type="password" name="old_password" class="form-control form-control-sm" required>
          </div>
          <div class="mb-2">
            <label class="form-label small fw-semibold">New Password</label>
            <input type="password" name="new_password" class="form-control form-control-sm" required minlength="6">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Verify New Password</label>
            <input type="password" name="confirm_password" class="form-control form-control-sm" required>
          </div>
          <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-key me-2"></i>Change</button>
        </form>
      </div>
    </div>

    <!-- Payment Gateway -->
    <div class="card">
      <div class="card-header"><i class="fas fa-credit-card text-primary me-2"></i>Gateway ya Payments</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="gateway">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Type ya Gateway</label>
            <select name="use_own_gateway" class="form-select form-select-sm" id="gatewaySelect">
              <option value="0" <?= !$reseller['use_own_gateway']?'selected':'' ?>>Tumia Gateway ya System (KADILI NET inakusanya)</option>
              <option value="1" <?= $reseller['use_own_gateway']?'selected':'' ?>>Own Gateway (Payments go directly to you)</option>
            </select>
          </div>
          <div id="ownKeySection" class="<?= !$reseller['use_own_gateway']?'d-none':'' ?>">
            <div class="mb-3">
              <label class="form-label small fw-semibold">Your PalmPesa API Key</label>
              <input type="text" name="palmpesa_api_key" class="form-control form-control-sm" value="<?= sanitize($reseller['palmpesa_api_key'] ?? '') ?>" placeholder="Bearer token ya PalmPesa">
            </div>
          </div>
          <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save me-2"></i>Save</button>
        </form>
      </div>
    </div>
  </div>
</div>

  <!-- Captive Portal Settings -->
  <div class="col-12">
    <div class="card">
      <div class="card-header"><i class="fas fa-paint-brush text-primary me-2"></i>Captive Portal Customization</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="portal">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label small fw-semibold">WiFi Name (on portal)</label>
              <input type="text" name="portal_wifi_name" class="form-control form-control-sm"
                     value="<?= sanitize($reseller['portal_wifi_name'] ?? '') ?>" placeholder="e.g. Juma WiFi">
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-semibold">Brand Color</label>
              <div class="d-flex gap-2 align-items-center">
                <input type="color" name="portal_color" class="form-control form-control-sm form-control-color"
                       value="<?= sanitize($reseller['portal_color'] ?? '#16a34a') ?>" style="width:48px;height:32px">
                <input type="text" id="colorHex" class="form-control form-control-sm" style="max-width:90px"
                       value="<?= sanitize($reseller['portal_color'] ?? '#16a34a') ?>" placeholder="#16a34a">
              </div>
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-semibold">Background Color</label>
              <div class="d-flex gap-2 align-items-center">
                <input type="color" name="portal_bg_color" class="form-control form-control-sm form-control-color"
                       value="<?= sanitize($reseller['portal_bg_color'] ?? '#f0fdf4') ?>" style="width:48px;height:32px">
                <input type="text" class="form-control form-control-sm" style="max-width:90px"
                       value="<?= sanitize($reseller['portal_bg_color'] ?? '#f0fdf4') ?>" placeholder="#f0fdf4">
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Footer Text</label>
              <input type="text" name="portal_footer_text" class="form-control form-control-sm"
                     value="<?= sanitize($reseller['portal_footer_text'] ?? '') ?>" placeholder="e.g. Thank you for choosing us!">
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-semibold">Show on Portal</label>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="portal_show_packages" value="1" id="chkPkg"
                       <?= ($reseller['portal_show_packages'] ?? 1) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="chkPkg">Auto Packages (Buy)</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="portal_show_voucher" value="1" id="chkVoucher"
                       <?= ($reseller['portal_show_voucher'] ?? 1) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="chkVoucher">Voucher Login</label>
              </div>
            </div>
          </div>
          <div class="mt-3">
            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save me-2"></i>Save Portal Settings</button>
            <?php
            $r = DB::fetch("SELECT id FROM routers WHERE reseller_id=?", [$rid]);
            if ($r): ?>
            <a href="<?= SITE_URL ?>/portal/?r=<?= $rid ?>&router=<?= $r['id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm ms-2">
              <i class="fas fa-external-link-alt me-1"></i>Preview Portal
            </a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

<?php
$extraJs = <<<JS
<script>
document.getElementById('gatewaySelect').addEventListener('change', function() {
  document.getElementById('ownKeySection').classList.toggle('d-none', this.value !== '1');
});
</script>
JS;
include '_footer.php';
?>
