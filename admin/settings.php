<?php
require_once __DIR__ . '/../includes/init.php';
requireAdmin();
$pageTitle = 'Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['setup_fee_enabled','setup_fee_amount','monthly_fee','sms_enabled','otp_enabled',
             'palmpesa_api_key','beem_sms_api_key','beem_sms_secret','beem_otp_api_key','beem_otp_secret',
             'sender_id','withdrawal_min','withdrawal_fee_percent'];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            DB::query("UPDATE settings SET setting_value=? WHERE setting_key=?", [sanitize($_POST[$key]), $key]);
        }
    }
    flash('success', 'Settings imehifadhiwa.');
    redirect(SITE_URL . '/admin/settings.php');
}

$settings = [];
foreach (DB::fetchAll("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

function s($key) use ($settings) { return htmlspecialchars($settings[$key] ?? ''); }

// Check Beem balance
$beemBalance = null;
try { $beemBalance = BeemSMS::checkBalance(); } catch(Exception $e) {}

include '_header.php';
?>
<div class="row g-3">
  <div class="col-12">
    <div class="alert alert-info rounded-3">
      <i class="fas fa-info-circle me-2"></i>Settings changes will affect the entire system immediately.
    </div>
  </div>

  <form method="POST" class="col-12">
  <div class="row g-3">
    <!-- Business Settings -->
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header"><i class="fas fa-building text-primary me-2"></i>Settings ya Business</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Fee ya Usanidi (Kuwezeshwa)</label>
            <select name="setup_fee_enabled" class="form-select form-select-sm">
              <option value="1" <?= s('setup_fee_enabled')==='1'?'selected':'' ?>>Yes</option>
              <option value="0" <?= s('setup_fee_enabled')==='0'?'selected':'' ?>>No</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Fee ya Usanidi (TZS)</label>
            <input type="number" name="setup_fee_amount" class="form-control form-control-sm" value="<?= s('setup_fee_amount') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Fee ya Kila Mwezi (TZS)</label>
            <input type="number" name="monthly_fee" class="form-control form-control-sm" value="<?= s('monthly_fee') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Amount cha Chini cha Kuondoa (TZS)</label>
            <input type="number" name="withdrawal_min" class="form-control form-control-sm" value="<?= s('withdrawal_min') ?>">
          </div>
          <div class="mb-0">
            <label class="form-label small fw-semibold">Asilimia ya Fee ya Kuondoa (%)</label>
            <input type="number" name="withdrawal_fee_percent" class="form-control form-control-sm" step="0.1" value="<?= s('withdrawal_fee_percent') ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- SMS Settings -->
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fas fa-sms text-success me-2"></i>Settings ya SMS / OTP</span>
          <?php if ($beemBalance !== null): ?>
            <span class="badge bg-info">Balance: <?= number_format($beemBalance) ?> credits</span>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold">SMS Enabled</label>
            <select name="sms_enabled" class="form-select form-select-sm">
              <option value="1" <?= s('sms_enabled')==='1'?'selected':'' ?>>Yes</option>
              <option value="0" <?= s('sms_enabled')==='0'?'selected':'' ?>>No</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">OTP Enabled</label>
            <select name="otp_enabled" class="form-select form-select-sm">
              <option value="1" <?= s('otp_enabled')==='1'?'selected':'' ?>>Yes</option>
              <option value="0" <?= s('otp_enabled')==='0'?'selected':'' ?>>No</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Sender ID</label>
            <input type="text" name="sender_id" class="form-control form-control-sm" value="<?= s('sender_id') ?>" maxlength="11">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Beem SMS API Key</label>
            <input type="text" name="beem_sms_api_key" class="form-control form-control-sm" value="<?= s('beem_sms_api_key') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Beem SMS Secret Key</label>
            <input type="text" name="beem_sms_secret" class="form-control form-control-sm" value="<?= s('beem_sms_secret') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Beem OTP API Key</label>
            <input type="text" name="beem_otp_api_key" class="form-control form-control-sm" value="<?= s('beem_otp_api_key') ?>">
          </div>
          <div class="mb-0">
            <label class="form-label small fw-semibold">Beem OTP Secret Key</label>
            <input type="text" name="beem_otp_secret" class="form-control form-control-sm" value="<?= s('beem_otp_secret') ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- PalmPesa -->
    <div class="col-12">
      <div class="card">
        <div class="card-header"><i class="fas fa-credit-card text-warning me-2"></i>PalmPesa API (System)</div>
        <div class="card-body">
          <div class="mb-0">
            <label class="form-label small fw-semibold">PalmPesa API Key (Admin/System)</label>
            <input type="text" name="palmpesa_api_key" class="form-control form-control-sm" value="<?= s('palmpesa_api_key') ?>">
            <div class="form-text">Used to collect payments from resellers using the system gateway.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-2"></i>Save Settings
      </button>
    </div>
  </div>
  </form>
</div>

<?php include '_footer.php'; ?>
