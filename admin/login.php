<?php
require_once __DIR__ . '/../includes/init.php';

if (isAdminLoggedIn()) redirect(SITE_URL . '/admin/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $admin    = DB::fetch("SELECT * FROM admins WHERE email = ?", [$email]);
    if ($admin && password_verify($password, $admin['password'])) {
        loginAdmin($admin);
        redirect(SITE_URL . '/admin/dashboard.php');
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login - KADILI NET</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
body { background: linear-gradient(135deg, #f8f9fa 0%, #e3f2fd 100%); min-height: 100vh; display: flex; align-items: center; font-family: 'Segoe UI', sans-serif; }
.login-card { border: none; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,123,255,0.15); max-width: 420px; width: 100%; }
.login-header { background: linear-gradient(135deg, #007bff, #0056b3); border-radius: 20px 20px 0 0; padding: 40px 30px 30px; text-align: center; }
.login-header img { width: 80px; margin-bottom: 10px; }
.login-header h4 { color: #fff; margin: 0; font-weight: 700; letter-spacing: 2px; }
.login-header p { color: rgba(255,255,255,0.8); font-size: 12px; margin: 4px 0 0; }
.login-body { padding: 35px 30px; }
.form-control { border-radius: 10px; border: 2px solid #e9ecef; padding: 12px 15px; font-size: 14px; }
.form-control:focus { border-color: #007bff; box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.1); }
.btn-login { background: linear-gradient(135deg, #007bff, #0056b3); border: none; border-radius: 10px; padding: 12px; font-weight: 600; letter-spacing: 1px; width: 100%; }
.btn-login:hover { transform: translateY(-1px); box-shadow: 0 5px 20px rgba(0,123,255,0.4); }
.input-group-text { border-radius: 10px 0 0 10px; background: #f8f9fa; border: 2px solid #e9ecef; border-right: none; }
.form-control { border-radius: 0 10px 10px 0 !important; }
</style>
</head>
<body>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
      <div class="login-card card">
        <div class="login-header">
          <img src="<?= SITE_LOGO ?>" alt="KADILI NET">
          <h4>KADILI NET</h4>
          <p>Admin Control Panel</p>
        </div>
        <div class="login-body">
          <?php if ($error): ?>
            <div class="alert alert-danger rounded-3 py-2 small"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div>
          <?php endif; ?>
          <form method="POST">
            <div class="mb-3">
              <label class="form-label fw-semibold small">Email</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-envelope text-primary"></i></span>
                <input type="email" name="email" class="form-control" placeholder="admin@kadilihotspot.online" required value="<?= sanitize($_POST['email'] ?? '') ?>">
              </div>
            </div>
            <div class="mb-4">
              <label class="form-label fw-semibold small">Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock text-primary"></i></span>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
              </div>
            </div>
            <button type="submit" class="btn btn-primary btn-login">
              <i class="fas fa-sign-in-alt me-2"></i>LOGIN
            </button>
          </form>
          <div class="text-center mt-4">
            <small class="text-muted">KADILI NET &copy; <?= date('Y') ?> | kadilihotspot.online</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
