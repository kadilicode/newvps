<?php
require_once __DIR__ . '/../includes/init.php';
requireAdmin();
$pageTitle = 'Bulk SMS';

$sent = 0; $failed = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message  = sanitize($_POST['message'] ?? '');
    $target   = $_POST['target'] ?? 'all';
    if ($message) {
        $q = match($target) {
            'active'    => "SELECT phone FROM resellers WHERE status='active'",
            'expired'   => "SELECT phone FROM resellers WHERE subscription_expires < NOW()",
            default     => "SELECT phone FROM resellers"
        };
        $resellers = DB::fetchAll($q);
        foreach ($resellers as $r) {
            $res = BeemSMS::send($r['phone'], $message);
            $res['success'] ? $sent++ : $failed++;
        }
        flash('success', "SMS sent: $sent succeeded, $failed failed.");
    }
    redirect(SITE_URL . '/admin/sms_blast.php');
}

$resellerCount = DB::fetch("SELECT COUNT(*) as c FROM resellers")['c'];
$activeCount   = DB::fetch("SELECT COUNT(*) as c FROM resellers WHERE status='active'")['c'];

include '_header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-7">
    <div class="card">
      <div class="card-header"><i class="fas fa-sms text-primary me-2"></i>Send SMS Kwa Resellers</div>
      <div class="card-body">
        <form method="POST">
          <div class="mb-3">
            <label class="form-label fw-semibold small">Send To</label>
            <select name="target" class="form-select">
              <option value="all">Resellers Wote (<?= $resellerCount ?>)</option>
              <option value="active">Active tu (<?= $activeCount ?>)</option>
              <option value="expired">Expired Subscription</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold small">Message</label>
            <textarea name="message" class="form-control" rows="5" maxlength="160" required
              placeholder="Write your message here... (max 160 chars)"></textarea>
            <div class="form-text">Message mfupi (SMS moja = herufi 160)</div>
          </div>
          <button type="submit" class="btn btn-primary"
            onclick="return confirm('Are you sure you want to send SMS to resellers?')">
            <i class="fas fa-paper-plane me-2"></i>Send SMS
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include '_footer.php'; ?>
