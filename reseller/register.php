<?php
require_once __DIR__ . '/../includes/init.php';
if (isResellerLoggedIn()) redirect(SITE_URL . '/reseller/dashboard.php');

$error = '';
$otpEnabled = DB::setting('otp_enabled') === '1';

// Step 1: Basic info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === '1') {
    $name  = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $biz   = sanitize($_POST['business_name'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (!$name || !$email || !$phone || !$pass) {
        $error = 'Please fill in all required fields.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (DB::fetch("SELECT id FROM resellers WHERE email=?", [$email])) {
        $error = 'Email already in use.';
    } elseif (DB::fetch("SELECT id FROM resellers WHERE phone=?", [$phone])) {
        $error = 'Phone number already in use.';
    } else {
        if (!$otpEnabled) {
            // OTP disabled: register directly
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $id   = DB::insert(
                "INSERT INTO resellers (name, email, phone, password, business_name, phone_verified, status) VALUES (?,?,?,?,?,1,'pending')",
                [$name, $email, $phone, $hash, $biz]
            );

            $setupFeeEnabled = DB::setting('setup_fee_enabled');
            $setupAmount     = (float)(DB::setting('setup_fee_amount') ?? SETUP_FEE);

            if ($setupFeeEnabled && $setupAmount > 0) {
                $_SESSION['pending_setup_reseller_id'] = $id;
                redirect(SITE_URL . '/reseller/pay_setup.php');
            } else {
                DB::query("UPDATE resellers SET status='active', subscription_expires=DATE_ADD(NOW(), INTERVAL 1 MONTH) WHERE id=?", [$id]);
                flash('success', 'Account created! Please login.');
                redirect(SITE_URL . '/reseller/login.php');
            }
        } else {
            // OTP enabled: save data and go to step 2
            $_SESSION['reg_data'] = compact('name','email','phone','biz','pass');
            $otpResult = BeemSMS::generateLocalOTP($phone, 'registration');
            if ($otpResult['success']) {
                $_SESSION['reg_step'] = 2;
                redirect(SITE_URL . '/reseller/register.php');
            } else {
                $error = 'Failed to send OTP. Please try again.';
            }
        }
    }
}

// Step 2: Verify OTP (only when OTP enabled)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === '2') {
    $code  = trim($_POST['otp'] ?? '');
    $data  = $_SESSION['reg_data'] ?? [];
    $phone = $data['phone'] ?? '';

    if (BeemSMS::verifyOTP($phone, $code, 'registration')) {
        $hash   = password_hash($data['pass'], PASSWORD_BCRYPT);
        $id     = DB::insert(
            "INSERT INTO resellers (name, email, phone, password, business_name, phone_verified, status) VALUES (?,?,?,?,?,1,'pending')",
            [$data['name'], $data['email'], $data['phone'], $hash, $data['biz']]
        );

        $setupFeeEnabled = DB::setting('setup_fee_enabled');
        $setupAmount     = (float)(DB::setting('setup_fee_amount') ?? SETUP_FEE);

        unset($_SESSION['reg_step'], $_SESSION['reg_data']);

        if ($setupFeeEnabled && $setupAmount > 0) {
            $_SESSION['pending_setup_reseller_id'] = $id;
            redirect(SITE_URL . '/reseller/pay_setup.php');
        } else {
            DB::query("UPDATE resellers SET status='active', subscription_expires=DATE_ADD(NOW(), INTERVAL 1 MONTH) WHERE id=?", [$id]);
            flash('success', 'Account created! Please login.');
            redirect(SITE_URL . '/reseller/login.php');
        }
    } else {
        $error = 'OTP is invalid or expired.';
    }
}

// Resend OTP
$step = $_SESSION['reg_step'] ?? 1;
if (isset($_GET['resend']) && $step === 2) {
    $data  = $_SESSION['reg_data'] ?? [];
    $phone = $data['phone'] ?? '';
    if ($phone) BeemSMS::generateLocalOTP($phone, 'registration');
    redirect(SITE_URL . '/reseller/register.php?resent=1');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register - KADILI NET</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
body { background: linear-gradient(135deg, #f8f9fa, #e3f2fd); min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
.reg-card { border: none; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,123,255,0.15); max-width: 500px; }
.reg-header { background: linear-gradient(135deg, #007bff, #0056b3); border-radius: 20px 20px 0 0; padding: 30px; text-align: center; }
.reg-header img { width: 60px; margin-bottom: 8px; }
.reg-header h5 { color: #fff; margin: 0; font-weight: 800; letter-spacing: 2px; }
.step-badge { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; font-size: 12px; font-weight: 700; }
.form-control { border-radius: 10px; border: 2px solid #e9ecef; padding: 11px 15px; font-size: 13px; }
.form-control:focus { border-color: #007bff; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.1); }
.btn-reg { background: linear-gradient(135deg, #007bff, #0056b3); border: none; border-radius: 10px; padding: 12px; font-weight: 600; }
.otp-input { font-size: 28px; text-align: center; letter-spacing: 10px; font-weight: 700; }
</style>
</head>
<body>
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-6">
      <div class="reg-card card">
        <div class="reg-header">
          <img src="<?= SITE_LOGO ?>" alt="KADILI NET">
          <h5>KADILI NET</h5>
          <?php if ($otpEnabled): ?>
          <div class="d-flex justify-content-center gap-3 mt-3">
            <div class="d-flex align-items-center gap-2">
              <span class="step-badge <?= $step==1?'bg-white text-primary':'bg-white bg-opacity-25 text-white' ?>">1</span>
              <span class="text-white-75 small">Details</span>
            </div>
            <div class="text-white-50">→</div>
            <div class="d-flex align-items-center gap-2">
              <span class="step-badge <?= $step==2?'bg-white text-primary':'bg-white bg-opacity-25 text-white' ?>">2</span>
              <span class="text-white-75 small">Verify</span>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <div class="card-body p-4">
          <?php if ($error): ?>
            <div class="alert alert-danger small rounded-3"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div>
          <?php endif; ?>
          <?php if (isset($_GET['resent'])): ?>
            <div class="alert alert-success small rounded-3"><i class="fas fa-check-circle me-2"></i>New OTP sent.</div>
          <?php endif; ?>

          <?php if (!$otpEnabled || $step === 1): ?>
          <!-- Registration form -->
          <h6 class="fw-bold mb-3 text-primary">Create New Account</h6>
          <form method="POST">
            <input type="hidden" name="step" value="1">
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Full Name *</label>
                <input type="text" name="name" class="form-control" placeholder="Your full name" required value="<?= sanitize($_SESSION['reg_data']['name'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Phone *</label>
                <input type="tel" name="phone" class="form-control" placeholder="0712345678" required value="<?= sanitize($_SESSION['reg_data']['phone'] ?? '') ?>">
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Email *</label>
                <input type="email" name="email" class="form-control" placeholder="email@example.com" required value="<?= sanitize($_SESSION['reg_data']['email'] ?? '') ?>">
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Business Name (optional)</label>
                <input type="text" name="business_name" class="form-control" placeholder="e.g. John WiFi" value="<?= sanitize($_SESSION['reg_data']['biz'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Password *</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required minlength="6">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Confirm Password *</label>
                <input type="password" name="password2" class="form-control" placeholder="••••••••" required>
              </div>
            </div>
            <div class="alert alert-info mt-3 small rounded-3">
              <i class="fas fa-info-circle me-2"></i>
              <?php $fee = (float)(DB::setting('setup_fee_amount') ?? SETUP_FEE); ?>
              <?php if (DB::setting('setup_fee_enabled')): ?>
                Setup fee: <strong><?= formatTZS($fee) ?></strong> + <?= formatTZS(MONTHLY_FEE) ?>/month
              <?php else: ?>
                Monthly fee: <strong><?= formatTZS(MONTHLY_FEE) ?></strong>
              <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-reg btn-primary w-100 mt-2">
              <?php if ($otpEnabled): ?>
                <i class="fas fa-arrow-right me-2"></i>Continue - Verify Phone
              <?php else: ?>
                <i class="fas fa-user-plus me-2"></i>Create Account
              <?php endif; ?>
            </button>
          </form>

          <?php elseif ($step === 2): ?>
          <!-- Step 2: OTP verification -->
          <?php $phone = $_SESSION['reg_data']['phone'] ?? ''; ?>
          <div class="text-center mb-4">
            <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:70px;height:70px">
              <i class="fas fa-mobile-alt text-primary" style="font-size:28px"></i>
            </div>
            <h6 class="fw-bold">Verify Phone Number</h6>
            <p class="text-muted small">A verification code (OTP) was sent to <strong><?= htmlspecialchars(substr($phone,0,4).'***'.substr($phone,-3)) ?></strong></p>
          </div>
          <form method="POST">
            <input type="hidden" name="step" value="2">
            <div class="mb-3">
              <input type="text" name="otp" class="form-control otp-input" placeholder="0000" maxlength="4" required autocomplete="one-time-code" inputmode="numeric">
            </div>
            <button type="submit" class="btn btn-reg btn-primary w-100">
              <i class="fas fa-shield-alt me-2"></i>Verify OTP
            </button>
          </form>
          <div class="text-center mt-3">
            <a href="?resend=1" class="text-muted small">Didn't receive OTP? <span class="text-primary">Resend</span></a>
          </div>
          <?php endif; ?>

          <div class="text-center mt-4">
            <span class="text-muted small">Already have an account? <a href="login.php" class="text-primary fw-semibold">Login</a></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
