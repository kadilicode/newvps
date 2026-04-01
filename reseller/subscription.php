<?php
require_once __DIR__ . '/../includes/init.php';
requireReseller();
$pageTitle = 'Lipa Subscription';
$rid = (int)$_SESSION['reseller_id'];
$reseller = DB::fetch("SELECT * FROM resellers WHERE id=?", [$rid]);
$monthlyFee = (float)(DB::setting('monthly_fee') ?? MONTHLY_FEE);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $months = (int)($_POST['months'] ?? 1);
    $phone  = sanitize($_POST['phone'] ?? $reseller['phone']);
    $amount = $months * $monthlyFee;
    $txId   = 'SUB_' . $rid . '_' . time();

    DB::query(
        "INSERT INTO transactions (reseller_id, customer_phone, amount, type, payment_status) VALUES (?,?,?,'subscription','pending')",
        [$rid, $phone, $amount]
    );

    $apiKey = $reseller['use_own_gateway'] && $reseller['palmpesa_api_key'] ? $reseller['palmpesa_api_key'] : PALMPESA_API_KEY;
    $result = PalmPesa::initiate([
        'name'         => $reseller['name'],
        'email'        => $reseller['email'],
        'phone'        => $phone,
        'amount'       => $amount,
        'transaction_id' => $txId,
        'address'      => 'Tanzania',
        'postcode'     => '00000',
        'callback_url' => SITE_URL . '/api/palmpesa_callback.php?type=subscription&reseller_id=' . $rid . '&months=' . $months,
    ], $apiKey);

    if ($result['success']) {
        $_SESSION['sub_order_id'] = $result['order_id'];
        flash('info', 'Check your phone for the payment STK push.');
    } else {
        flash('error', 'Failed to initiate payment. Please try again.');
    }
    redirect(SITE_URL . '/reseller/subscription.php');
}

include '_header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><i class="fas fa-calendar-check text-primary me-2"></i>Renew Subscription</div>
      <div class="card-body">
        <?php if ($reseller['subscription_expires']): ?>
        <div class="alert alert-<?= strtotime($reseller['subscription_expires']) < time()?'danger':'info' ?> rounded-3 small mb-3">
          <i class="fas fa-calendar me-2"></i>
          Subscription wako <?= strtotime($reseller['subscription_expires']) < time()?'expired on':'expires on' ?>:
          <strong><?= date('d M Y', strtotime($reseller['subscription_expires'])) ?></strong>
        </div>
        <?php endif; ?>

        <form method="POST">
          <div class="mb-3">
            <label class="form-label fw-semibold small">Select Duration</label>
            <div class="row g-2">
              <?php
              $plans = [
                ['months'=>1, 'label'=>'1 Month'],
                ['months'=>3, 'label'=>'3 Months'],
                ['months'=>6, 'label'=>'6 Months'],
                ['months'=>12,'label'=>'12 Months (Year)'],
              ];
              ?>
              <?php foreach ($plans as $plan): ?>
              <div class="col-6">
                <input type="radio" class="btn-check" name="months" id="m<?= $plan['months'] ?>" value="<?= $plan['months'] ?>" <?= $plan['months']===1?'checked':'' ?>>
                <label class="btn btn-outline-primary w-100" for="m<?= $plan['months'] ?>">
                  <div class="fw-bold"><?= $plan['label'] ?></div>
                  <div class="small"><?= formatTZS($plan['months'] * $monthlyFee) ?></div>
                </label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Phone ya Kulipa (STK Push)</label>
            <input type="tel" name="phone" class="form-control" value="<?= sanitize($reseller['phone']) ?>" required>
          </div>
          <div class="d-flex gap-2 justify-content-center mb-3">
            <img src="https://i.ibb.co/5Xmzv2kq/M-pesa-logo-removebg-preview.webp" style="height:25px" alt="M-Pesa">
            <img src="https://i.ibb.co/zVJrmYn1/images-removebg-preview.webp" style="height:25px" alt="Airtel">
            <img src="https://i.ibb.co/FLQ2MVxQ/mixx-logo-removebg-preview.webp" style="height:25px" alt="Tigo">
            <img src="https://i.ibb.co/S4mp6TbX/applications-system-removebg-preview.webp" style="height:25px" alt="Halo">
          </div>
          <button type="submit" class="btn btn-primary w-100 fw-bold">
            <i class="fas fa-mobile-alt me-2"></i>Lipa Subscription
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include '_footer.php'; ?>
