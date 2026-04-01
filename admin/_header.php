<?php
$flash = getFlash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ?? 'Dashboard' ?> - KADILI NET Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root { --primary: #007bff; --sidebar-w: 260px; }
body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
.sidebar { width: var(--sidebar-w); min-height: 100vh; background: linear-gradient(180deg, #0056b3 0%, #003d82 100%); position: fixed; top: 0; left: 0; z-index: 1000; transition: all .3s; overflow-y: auto; }
.sidebar-brand { padding: 20px 15px; border-bottom: 1px solid rgba(255,255,255,0.1); text-align: center; }
.sidebar-brand img { width: 50px; margin-bottom: 8px; }
.sidebar-brand h6 { color: #fff; font-weight: 800; letter-spacing: 2px; margin: 0; font-size: 14px; }
.sidebar-brand small { color: rgba(255,255,255,0.6); font-size: 10px; }
.nav-section { padding: 10px 15px 5px; color: rgba(255,255,255,0.4); font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; }
.sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 10px 15px; border-radius: 8px; margin: 2px 8px; font-size: 13px; display: flex; align-items: center; gap: 10px; transition: all .2s; }
.sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.15); color: #fff; }
.sidebar .nav-link i { width: 18px; text-align: center; }
.main-content { margin-left: var(--sidebar-w); min-height: 100vh; }
.topbar { background: #fff; border-bottom: 1px solid #e9ecef; padding: 12px 25px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
.page-content { padding: 25px; }
.stat-card { border: none; border-radius: 15px; padding: 20px; margin-bottom: 20px; transition: transform .2s; }
.stat-card:hover { transform: translateY(-3px); }
.stat-card .icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.card { border: none; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
.card-header { background: none; border-bottom: 1px solid #f0f0f0; font-weight: 600; }
.table { font-size: 13px; }
.table thead th { background: #f8f9fa; border: none; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; }
.badge { font-size: 11px; }
.btn { border-radius: 8px; font-size: 13px; }
.sidebar-toggle { display: none; }
@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .sidebar.show { transform: translateX(0); }
  .main-content { margin-left: 0; }
  .sidebar-toggle { display: block; }
}
</style>
</head>
<body>
<div class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <img src="<?= SITE_LOGO ?>" alt="KADILI NET">
    <h6>KADILI NET</h6>
    <small>Admin Panel</small>
  </div>
  <nav class="mt-2">
    <div class="nav-section">Main</div>
    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="nav-link <?= $currentPage==='dashboard'?'active':'' ?>"><i class="fas fa-home"></i> Dashboard</a>
    <a href="<?= SITE_URL ?>/admin/resellers.php" class="nav-link <?= $currentPage==='resellers'?'active':'' ?>"><i class="fas fa-users"></i> Resellers</a>
    <a href="<?= SITE_URL ?>/admin/transactions.php" class="nav-link <?= $currentPage==='transactions'?'active':'' ?>"><i class="fas fa-money-bill-wave"></i> Payments</a>
    <a href="<?= SITE_URL ?>/admin/withdrawals.php" class="nav-link <?= $currentPage==='withdrawals'?'active':'' ?>"><i class="fas fa-hand-holding-usd"></i> Withdrawals</a>

    <div class="nav-section">Marketing</div>
    <a href="<?= SITE_URL ?>/admin/promo_codes.php" class="nav-link <?= $currentPage==='promo_codes'?'active':'' ?>"><i class="fas fa-tags"></i> Promo Codes</a>
    <a href="<?= SITE_URL ?>/admin/announcements.php" class="nav-link <?= $currentPage==='announcements'?'active':'' ?>"><i class="fas fa-bullhorn"></i> Announcements</a>
    <a href="<?= SITE_URL ?>/admin/sms_blast.php" class="nav-link <?= $currentPage==='sms_blast'?'active':'' ?>"><i class="fas fa-sms"></i> Bulk SMS</a>

    <div class="nav-section">System</div>
    <a href="<?= SITE_URL ?>/admin/settings.php" class="nav-link <?= $currentPage==='settings'?'active':'' ?>"><i class="fas fa-cog"></i> Settings</a>
    <a href="<?= SITE_URL ?>/admin/logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </nav>
</div>

<div class="main-content">
  <div class="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-outline-secondary sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('show')">
        <i class="fas fa-bars"></i>
      </button>
      <span class="fw-semibold text-primary"><?= $pageTitle ?? 'Dashboard' ?></span>
    </div>
    <div class="d-flex align-items-center gap-3">
      <small class="text-muted d-none d-md-block"><i class="fas fa-clock me-1"></i><?= date('d M Y, H:i') ?></small>
      <div class="d-flex align-items-center gap-2">
        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:35px;height:35px">
          <i class="fas fa-user-shield text-white" style="font-size:14px"></i>
        </div>
        <div class="d-none d-md-block">
          <div class="fw-semibold" style="font-size:13px"><?= sanitize($_SESSION['admin_name'] ?? 'Admin') ?></div>
          <div class="text-muted" style="font-size:11px">Administrator</div>
        </div>
      </div>
    </div>
  </div>

  <div class="page-content">
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show rounded-3">
      <?= sanitize($flash['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
