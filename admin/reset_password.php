<?php
// ============================================
// KADILI NET - Admin Password Reset
// Run once then DELETE this file!
// URL: /admin/reset_password.php?key=KadiliReset2024
// ============================================

$secret = $_GET['key'] ?? '';
if ($secret !== 'KadiliReset2024' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
    http_response_code(403);
    die('<h3 style="font-family:sans-serif;color:red;text-align:center;margin-top:100px">403 Forbidden — DELETE this file after use!</h3>');
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if ($email && $pass) {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $exists = DB::fetch("SELECT id FROM admins WHERE email=?", [$email]);
        if ($exists) {
            DB::query("UPDATE admins SET password=? WHERE email=?", [$hash, $email]);
            $msg = "✅ Password updated for $email";
        } else {
            DB::insert("INSERT INTO admins (name, email, password) VALUES (?,?,?)", ['Admin', $email, $hash]);
            $msg = "✅ Admin created: $email";
        }
    }
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Reset Admin Password - KADILI NET</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="bg-light">
<div class="container" style="max-width:400px;margin-top:80px">
  <div class="card p-4 shadow">
    <h5 class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>Admin Password Reset</h5>
    <div class="alert alert-warning small">⚠️ DELETE this file immediately after use!</div>
    <?php if ($msg): ?><div class="alert alert-success small"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="POST">
      <div class="mb-3">
        <label class="form-label small fw-semibold">Admin Email</label>
        <input type="email" name="email" class="form-control" value="kadiliy17@gmail.com" required>
      </div>
      <div class="mb-3">
        <label class="form-label small fw-semibold">New Password</label>
        <input type="password" name="password" class="form-control" placeholder="Kadili@123" required>
      </div>
      <button type="submit" class="btn btn-danger w-100">Set Password</button>
    </form>
    <div class="mt-3 text-center">
      <a href="../admin/login.php" class="btn btn-primary btn-sm">Go to Login</a>
    </div>
  </div>
</div>
</body></html>
