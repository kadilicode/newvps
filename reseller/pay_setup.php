<?php
require_once __DIR__ . '/../includes/init.php';

$resellerId = $_SESSION['pending_setup_reseller_id'] ?? null;
if (!$resellerId) redirect(SITE_URL . '/reseller/login.php');

$reseller   = DB::fetch("SELECT * FROM resellers WHERE id=?", [$resellerId]);
if (!$reseller) redirect(SITE_URL . '/reseller/login.php');

$setupAmount = (float)(DB::setting('setup_fee_amount') ?? SETUP_FEE);
$error = '';

// Handle promo code apply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_code') {
    $code = strtoupper(trim($_POST['promo_code'] ?? ''));
    $promo = DB::fetch("SELECT * FROM promo_codes WHERE code=? AND is_active=1 AND (expires_at IS NULL OR expires_at > NOW()) AND (used_count < max_uses OR max_uses=0)", [$code]);
    if ($promo) {
        $_SESSION['promo_code_id']   = $promo['id'];
        $_SESSION['promo_code']      = $promo['code'];
        $_SESSION['promo_discount']  = $promo['discount_percent'];
        flash('success', "Promo code applied! You get {$promo['discount_percent']}% off.");
    } else {
        flash('error', 'Invalid or expired promo code.');
    }
    redirect(SITE_URL . '/reseller/pay_setup.php');
}

// Handle remove promo
if (isset($_GET['remove_promo'])) {
    unset($_SESSION['promo_code_id'], $_SESSION['promo_code'], $_SESSION['promo_discount']);
    redirect(SITE_URL . '/reseller/pay_setup.php');
}

// Calculate amount with promo
$discount = (float)($_SESSION['promo_discount'] ?? 0);
$discountAmount = $setupAmount * ($discount / 100);
$finalAmount = $setupAmount - $discountAmount;

// Handle free (100% promo)
if ($finalAmount <= 0) {
    DB::query("UPDATE resellers SET status='active', setup_fee_paid=1, subscription_expires=DATE_ADD(NOW(), INTERVAL 1 MONTH) WHERE id=?", [$resellerId]);
    if (!empty($_SESSION['promo_code_id'])) {
        DB::query("UPDATE promo_codes SET used_count=used_count+1 WHERE id=?", [$_SESSION['promo_code_id']]);
    }
    unset($_SESSION['pending_setup_reseller_id'], $_SESSION['promo_code_id'], $_SESSION['promo_code'], $_SESSION['promo_discount']);
    flash('success', 'Account activated with promo code! Please login.');
    redirect(SITE_URL . '/reseller/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'apply_code') {
    $phone = sanitize($_POST['phone'] ?? $reseller['phone']);
    $txId  = 'SETUP_' . $resellerId . '_' . time();

    DB::query(
        "INSERT INTO transactions (reseller_id, customer_phone, customer_name, amount, type, payment_status) VALUES (?,?,?,?,'setup_fee','pending')",
        [$resellerId, $phone, $reseller['name'], $finalAmount]
    );

    $result = PalmPesa::initiate([
        'name'         => $reseller['name'],
        'email'        => $reseller['email'],
        'phone'        => $phone,
        'amount'       => $finalAmount,
        'transaction_id' => $txId,
        'address'      => 'Tanzania',
        'postcode'     => '00000',
        'callback_url' => SITE_URL . '/api/palmpesa_callback.php',
    ]);

    if ($result['success']) {
        $_SESSION['setup_order_id'] = $result['order_id'];
        redirect(SITE_URL . '/reseller/pay_setup.php?waiting=1');
    } else {
        $error = 'Failed to initiate payment. Please try again.';
    }
}

$waiting = isset($_GET['waiting']);
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup Fee - KADILI NET</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
body { background:linear-gradient(135deg,#f8f9fa,#e3f2fd); min-height:100vh; display:flex; align-items:center; font-family:'Segoe UI',sans-serif; }
.card { border:none; border-radius:20px; box-shadow:0 20px 60px rgba(0,123,255,.15); max-width:480px; }
.card-header-custom { background:linear-gradient(135deg,#007bff,#0056b3); border-radius:20px 20px 0 0; padding:25px; text-align:center; }
.spinner-ring { width:60px; height:60px; border:4px solid #e3f2fd; border-top:4px solid #007bff; border-radius:50%; animation:spin 1s linear infinite; margin:0 auto; }
@keyframes spin { to { transform:rotate(360deg); } }
.promo-badge { background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:8px; padding:6px 12px; font-size:13px; }
</style>
</head>
<body>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6">
      <div class="card">
        <div class="card-header-custom">
          <img src="<?= SITE_LOGO ?>" style="width:55px" alt="KADILI NET">
          <h5 class="text-white fw-bold mt-2 mb-0">KADILI NET</h5>
          <small class="text-white-50">Setup Fee</small>
        </div>
        <div class="p-4">
          <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type']==='error'?'danger':$flash['type'] ?> small rounded-3"><?= sanitize($flash['message']) ?></div>
          <?php endif; ?>

          <?php if ($waiting): ?>
            <div class="text-center py-3">
              <div class="spinner-ring mb-4"></div>
              <h5 class="fw-bold text-primary">Waiting for Payment...</h5>
              <p class="text-muted small">Check your phone (<strong><?= sanitize($reseller['phone']) ?></strong>) and confirm the payment of <strong><?= formatTZS($finalAmount) ?></strong> via M-Pesa/Airtel/Tigo.</p>
              <div class="alert alert-info small">Order ID: <strong><?= sanitize($_SESSION['setup_order_id'] ?? '') ?></strong></div>
              <a href="pay_setup.php?check=1" class="btn btn-primary"><i class="fas fa-sync me-2"></i>Check Payment Status</a>
              <br><small class="text-muted mt-2 d-block">Click the button above after paying</small>
            </div>
          <?php elseif (isset($_GET['check'])): ?>
            <?php
            $tx = DB::fetch(
                "SELECT * FROM transactions WHERE reseller_id=? AND type='setup_fee' AND payment_status='completed' ORDER BY id DESC LIMIT 1",
                [$resellerId]
            );
            if ($tx) {
                DB::query("UPDATE resellers SET status='active', setup_fee_paid=1, subscription_expires=DATE_ADD(NOW(), INTERVAL 1 MONTH) WHERE id=?", [$resellerId]);
                if (!empty($_SESSION['promo_code_id'])) {
                    DB::query("UPDATE promo_codes SET used_count=used_count+1 WHERE id=?", [$_SESSION['promo_code_id']]);
                }
                unset($_SESSION['pending_setup_reseller_id'], $_SESSION['setup_order_id'], $_SESSION['promo_code_id'], $_SESSION['promo_code'], $_SESSION['promo_discount']);
                flash('success', 'Payment confirmed! Your account is now active.');
                redirect(SITE_URL . '/reseller/login.php');
            }
            ?>
            <div class="text-center py-3">
              <i class="fas fa-clock text-warning" style="font-size:40px"></i>
              <h6 class="mt-3 fw-bold">Payment Not Yet Confirmed</h6>
              <p class="text-muted small">Please wait 1-2 minutes then try again.</p>
              <a href="pay_setup.php?waiting=1" class="btn btn-warning btn-sm">Go Back to Waiting</a>
            </div>
          <?php else: ?>
            <h6 class="fw-bold text-primary mb-3"><i class="fas fa-credit-card me-2"></i>Pay Setup Fee</h6>
            <div class="alert alert-warning small rounded-3 mb-3">
              Welcome, <strong><?= sanitize($reseller['name']) ?></strong>!<br>
              To complete your registration, pay a one-time setup fee.
            </div>

            <!-- Price breakdown -->
            <div class="card bg-light border-0 mb-3 p-3">
              <div class="d-flex justify-content-between small mb-1">
                <span>Setup Fee:</span><span><?= formatTZS($setupAmount) ?></span>
              </div>
              <?php if ($discount > 0): ?>
              <div class="d-flex justify-content-between small text-success mb-1">
                <span>Discount (<?= $discount ?>%):</span><span>-<?= formatTZS($discountAmount) ?></span>
              </div>
              <?php endif; ?>
              <hr class="my-1">
              <div class="d-flex justify-content-between fw-bold">
                <span>Total to Pay:</span><span class="text-primary"><?= formatTZS($finalAmount) ?></span>
              </div>
            </div>

            <!-- Promo code section -->
            <?php if (!empty($_SESSION['promo_code'])): ?>
            <div class="promo-badge d-flex justify-content-between align-items-center mb-3">
              <span><i class="fas fa-tag me-2"></i>Code: <strong><?= sanitize($_SESSION['promo_code']) ?></strong> — <?= $discount ?>% off</span>
              <a href="?remove_promo=1" class="text-danger small"><i class="fas fa-times"></i></a>
            </div>
            <?php else: ?>
            <div class="mb-3">
              <form method="POST" class="d-flex gap-2">
                <input type="hidden" name="action" value="apply_code">
                <input type="text" name="promo_code" class="form-control form-control-sm" placeholder="Have a promo code?" style="text-transform:uppercase">
                <button type="submit" class="btn btn-outline-secondary btn-sm" style="white-space:nowrap">Apply</button>
              </form>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
              <div class="alert alert-danger small"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
              <div class="mb-3">
                <label class="form-label small fw-semibold">Payment Phone Number (STK Push)</label>
                <input type="tel" name="phone" class="form-control" value="<?= sanitize($reseller['phone']) ?>" required>
                <div class="form-text">This number will receive the payment prompt</div>
              </div>
              <div class="d-flex gap-2 justify-content-center mb-3">
                <img src="https://i.ibb.co/5Xmzv2kq/M-pesa-logo-removebg-preview.webp" style="height:30px" alt="M-Pesa">
                <img src="https://i.ibb.co/zVJrmYn1/images-removebg-preview.webp" style="height:30px" alt="Airtel">
                <img src="https://i.ibb.co/FLQ2MVxQ/mixx-logo-removebg-preview.webp" style="height:30px" alt="Tigo">
                <img src="https://i.ibb.co/S4mp6TbX/applications-system-removebg-preview.webp" style="height:30px" alt="Halo">
              </div>
              <button type="submit" class="btn btn-primary w-100 fw-bold">
                <i class="fas fa-mobile-alt me-2"></i>Pay <?= formatTZS($finalAmount) ?> Now
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($waiting): ?>
<script>setTimeout(() => location.href='pay_setup.php?check=1', 30000);</script>
<?php endif; ?>
</body>
</html>
