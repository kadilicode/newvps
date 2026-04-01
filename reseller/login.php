<?php
require_once __DIR__ . '/../includes/init.php';
if (isResellerLoggedIn()) redirect(SITE_URL . '/reseller/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $reseller = DB::fetch("SELECT * FROM resellers WHERE email=?", [$email]);

    if ($reseller && password_verify($password, $reseller['password'])) {
        if ($reseller['status'] === 'suspended') {
            $error = 'Your account has been suspended. Please contact admin.';
        } elseif ($reseller['status'] === 'pending') {
            $error = 'Your account is not yet activated. Please complete setup first.';
        } else {
            loginReseller($reseller);
            redirect(SITE_URL . '/reseller/dashboard.php');
        }
    } else {
        $error = 'Invalid email or password.';
    }
}
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - KADILI NET</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
body { background: linear-gradient(135deg,#f8f9fa,#e3f2fd); min-height:100vh; display:flex; align-items:center; font-family:'Segoe UI',sans-serif; }
.card { border:none; border-radius:20px; box-shadow:0 20px 60px rgba(0,123,255,.15); max-width:420px; }
.card-header-custom { background:linear-gradient(135deg,#007bff,#0056b3); border-radius:20px 20px 0 0; padding:35px 30px 25px; text-align:center; }
.card-header-custom img { width:70px; margin-bottom:8px; }
.card-header-custom h5 { color:#fff; font-weight:800; letter-spacing:2px; margin:0; }
.form-control { border-radius:10px; border:2px solid #e9ecef; padding:12px 15px; font-size:13px; }
.form-control:focus { border-color:#007bff; box-shadow:0 0 0 .2rem rgba(0,123,255,.1); }
.btn-login { background:linear-gradient(135deg,#007bff,#0056b3); border:none; border-radius:10px; padding:12px; font-weight:700; }
</style>
</head>
<body>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
      <div class="card">
        <div class="card-header-custom">
          <img src="<?= SITE_LOGO ?>" alt="KADILI NET">
          <h5>KADILI NET</h5>
          <small class="text-white-50">Reseller Dashboard</small>
        </div>
        <div class="p-4">
          <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type']==='error'?'danger':$flash['type'] ?> small rounded-3"><?= sanitize($flash['message']) ?></div>
          <?php endif; ?>
          <?php if ($error): ?>
            <div class="alert alert-danger small rounded-3"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div>
          <?php endif; ?>
          <?php if (isset($_GET['error']) && $_GET['error'] === 'suspended'): ?>
            <div class="alert alert-warning small rounded-3">Your account has been suspended.</div>
          <?php endif; ?>

          <form method="POST">
            <div class="mb-3">
              <label class="form-label small fw-semibold">Email</label>
              <input type="email" name="email" class="form-control" placeholder="email@example.com" required value="<?= sanitize($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-4">
              <label class="form-label small fw-semibold">Password</label>
              <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-login btn-primary w-100">
              <i class="fas fa-sign-in-alt me-2"></i>LOGIN
            </button>
          </form>

          <div class="text-center mt-4">
            <span class="text-muted small">Don't have an account? <a href="register.php" class="text-primary fw-semibold">Register</a></span>
          </div>
          <div class="text-center mt-2">
            <small class="text-muted">kadilihotspot.online &copy; <?= date('Y') ?></small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
