<?php
$flash = getFlash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$reseller = DB::fetch("SELECT * FROM resellers WHERE id=?", [$_SESSION['reseller_id']]);
$subExpired = isset($_SESSION['sub_expired']) && $_SESSION['sub_expired'];
$announcements = DB::fetchAll("SELECT * FROM announcements WHERE is_active=1 ORDER BY created_at DESC LIMIT 5");
$activeUsersCount = DB::fetch("SELECT COUNT(*) as c FROM hotspot_users WHERE reseller_id=? AND status='active' AND expires_at > NOW()", [$_SESSION['reseller_id']])['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ?? 'Dashboard' ?> - KADILI NET</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root { --primary:#007bff; --sidebar-w:250px; }
body { background:#f8f9fa; font-family:'Segoe UI',sans-serif; }
.sidebar { width:var(--sidebar-w); min-height:100vh; background:linear-gradient(180deg,#007bff 0%,#004a99 100%); position:fixed; top:0; left:0; z-index:1000; overflow-y:auto; transition:all .3s; }
.sidebar-brand { padding:18px 15px; border-bottom:1px solid rgba(255,255,255,.1); text-align:center; }
.sidebar-brand img { width:45px; margin-bottom:6px; }
.sidebar-brand h6 { color:#fff; font-weight:800; letter-spacing:2px; margin:0; font-size:13px; }
.sidebar-brand small { color:rgba(255,255,255,.6); font-size:10px; }
.nav-section { padding:8px 15px 3px; color:rgba(255,255,255,.4); font-size:10px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; }
.sidebar .nav-link { color:rgba(255,255,255,.85); padding:9px 15px; border-radius:8px; margin:2px 8px; font-size:13px; display:flex; align-items:center; gap:9px; transition:all .2s; }
.sidebar .nav-link:hover, .sidebar .nav-link.active { background:rgba(255,255,255,.18); color:#fff; }
.sidebar .nav-link i { width:16px; text-align:center; }
.main-content { margin-left:var(--sidebar-w); min-height:100vh; }
.topbar { background:#fff; border-bottom:1px solid #e9ecef; padding:10px 22px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; box-shadow:0 2px 10px rgba(0,0,0,.05); }
.page-content { padding:22px; }
.card { border:none; border-radius:12px; box-shadow:0 2px 15px rgba(0,0,0,.05); }
.card-header { background:none; border-bottom:1px solid #f0f0f0; font-weight:600; font-size:14px; }
.stat-card { border:none; border-radius:15px; padding:18px; }
.table { font-size:13px; }
.table thead th { background:#f8f9fa; border:none; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#6c757d; }
.btn { border-radius:8px; font-size:13px; }
.badge { font-size:11px; }
.balance-badge { background:linear-gradient(135deg,#28a745,#1e7e34); color:#fff; border-radius:10px; padding:5px 12px; font-size:13px; font-weight:600; }
@media (max-width:768px) {
  .sidebar { transform:translateX(-100%); }
  .sidebar.show { transform:translateX(0); }
  .main-content { margin-left:0; }
}
</style>
</head>
<body>
<div class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <img src="<?= SITE_LOGO ?>" alt="KADILI NET">
    <h6>KADILI NET</h6>
    <small><?= sanitize($reseller['business_name'] ?: 'Reseller') ?></small>
  </div>
  <nav class="mt-2">
    <div class="nav-section">Main</div>
    <a href="<?= SITE_URL ?>/reseller/dashboard.php" class="nav-link <?= $currentPage==='dashboard'?'active':'' ?>"><i class="fas fa-home"></i> Dashboard</a>
    <a href="<?= SITE_URL ?>/reseller/routers.php" class="nav-link <?= in_array($currentPage,['routers','router_setup'])?'active':'' ?>">
      <i class="fas fa-router"></i> Router
    </a>
    <a href="<?= SITE_URL ?>/reseller/packages.php" class="nav-link <?= $currentPage==='packages'?'active':'' ?>"><i class="fas fa-box"></i> Packages</a>

    <div class="nav-section">Sales</div>
    <a href="<?= SITE_URL ?>/reseller/vouchers.php" class="nav-link <?= $currentPage==='vouchers'?'active':'' ?>"><i class="fas fa-ticket-alt"></i> Vouchers</a>
    <a href="<?= SITE_URL ?>/reseller/users.php" class="nav-link <?= $currentPage==='users'?'active':'' ?>" style="position:relative">
      <i class="fas fa-users"></i> Active Users
      <?php if ($activeUsersCount > 0): ?><span style="position:absolute;right:12px;background:#ff4444;color:#fff;border-radius:10px;padding:1px 6px;font-size:10px;font-weight:700"><?= $activeUsersCount ?></span><?php endif; ?>
    </a>
    <a href="<?= SITE_URL ?>/reseller/transactions.php" class="nav-link <?= $currentPage==='transactions'?'active':'' ?>"><i class="fas fa-receipt"></i> Payments</a>
    <a href="<?= SITE_URL ?>/reseller/withdrawals.php" class="nav-link <?= $currentPage==='withdrawals'?'active':'' ?>"><i class="fas fa-wallet"></i> Withdraw</a>

    <div class="nav-section">Account</div>
    <a href="<?= SITE_URL ?>/reseller/subscription.php" class="nav-link <?= $currentPage==='subscription'?'active':'' ?>"><i class="fas fa-calendar-check"></i> Subscription</a>
    <a href="<?= SITE_URL ?>/reseller/settings.php" class="nav-link <?= $currentPage==='settings'?'active':'' ?>"><i class="fas fa-cog"></i> Settings</a>
    <a href="<?= SITE_URL ?>/reseller/logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </nav>
</div>

<div class="main-content">
  <div class="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-outline-secondary d-md-none" onclick="document.getElementById('sidebar').classList.toggle('show')">
        <i class="fas fa-bars"></i>
      </button>
      <span class="fw-semibold text-primary"><?= $pageTitle ?? 'Dashboard' ?></span>
    </div>
    <div class="d-flex align-items-center gap-3">
      <?php if ($reseller): ?>
      <span class="balance-badge d-none d-md-block">
        <i class="fas fa-wallet me-1"></i><?= formatTZS((float)$reseller['balance']) ?>
      </span>
      <?php endif; ?>
      <?php if ($subExpired): ?>
      <a href="<?= SITE_URL ?>/reseller/subscription.php" class="btn btn-sm btn-danger">
        <i class="fas fa-exclamation-triangle me-1"></i>Subscription Expired
      </a>
      <?php endif; ?>
      <div class="d-flex align-items-center gap-2">
        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:34px;height:34px">
          <i class="fas fa-user text-white" style="font-size:13px"></i>
        </div>
        <div class="d-none d-md-block">
          <div class="fw-semibold" style="font-size:13px"><?= sanitize($_SESSION['reseller_name'] ?? '') ?></div>
          <div class="text-muted" style="font-size:11px">Reseller</div>
        </div>
      </div>
    </div>
  </div>

  <div class="page-content">
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type']==='error'?'danger':$flash['type'] ?> alert-dismissible fade show rounded-3">
      <?= sanitize($flash['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php foreach ($announcements as $ann): ?>
    <div class="alert alert-<?= $ann['type'] ?> alert-dismissible fade show rounded-3 py-2 small">
      <strong><?= sanitize($ann['title']) ?>:</strong> <?= sanitize($ann['body']) ?>
      <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
    </div>
    <?php endforeach; ?>
