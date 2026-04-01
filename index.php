<?php
// kadilihotspot.online - Main Entry Point
require_once __DIR__ . '/includes/init.php';

if (isAdminLoggedIn()) {
    redirect(SITE_URL . '/admin/dashboard.php');
} elseif (isResellerLoggedIn()) {
    redirect(SITE_URL . '/reseller/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>KADILI NET - WiFi SaaS Platform Tanzania</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
body { font-family: 'Poppins', sans-serif; background: #fff; }
.hero { background: linear-gradient(135deg, #007bff 0%, #004a99 100%); min-height: 100vh; display: flex; align-items: center; padding: 80px 0 60px; position: relative; overflow: hidden; }
.hero::before { content:''; position:absolute; top:-30%; right:-10%; width:500px; height:500px; background:rgba(255,255,255,.05); border-radius:50%; }
.hero::after  { content:''; position:absolute; bottom:-20%; left:-10%; width:400px; height:400px; background:rgba(255,255,255,.04); border-radius:50%; }
.hero-title { font-size: clamp(32px, 5vw, 52px); font-weight: 900; color: #fff; line-height: 1.2; }
.hero-sub { color: rgba(255,255,255,.85); font-size: 17px; margin: 20px 0 35px; }
.btn-hero { border-radius: 50px; padding: 14px 35px; font-weight: 700; font-size: 15px; }
.feature-card { border: none; border-radius: 16px; box-shadow: 0 5px 30px rgba(0,0,0,.06); padding: 30px; height: 100%; transition: transform .2s; }
.feature-card:hover { transform: translateY(-5px); }
.feature-icon { width: 55px; height: 55px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 15px; }
.navbar { position: fixed; top: 0; width: 100%; z-index: 1000; background: transparent; transition: background .3s; }
.navbar.scrolled { background: rgba(0,86,179,.95) !important; backdrop-filter: blur(10px); }
.stat-item { text-align: center; }
.stat-num { font-size: 36px; font-weight: 900; color: #007bff; }
.stat-lbl { color: #666; font-size: 14px; }
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark" id="mainNav">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="#">
      <img src="<?= SITE_LOGO ?>" style="height:38px;width:38px;object-fit:contain;background:rgba(255,255,255,.15);border-radius:10px;padding:4px" alt="KADILI NET">
      <span class="fw-800" style="font-weight:800;letter-spacing:1px">KADILI NET</span>
    </a>
    <div class="ms-auto d-flex gap-2">
      <a href="reseller/login.php" class="btn btn-outline-light btn-sm rounded-pill px-3">Login</a>
      <a href="reseller/register.php" class="btn btn-light btn-sm rounded-pill px-3 text-primary fw-bold">Register</a>
    </div>
  </div>
</nav>

<!-- Hero -->
<section class="hero">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6">
        <div class="hero-title">
          System Bora wa<br><span style="color:#7eb8ff">WiFi Vouchers</span><br>Tanzania
        </div>
        <p class="hero-sub">Manage routers za MikroTik, uza vouchers kiotomatiki kupitia PalmPesa, na udhibiti wasambazaji wako kwa urahisi.</p>
        <div class="d-flex flex-wrap gap-3">
          <a href="reseller/register.php" class="btn btn-light btn-hero text-primary">
            <i class="fas fa-rocket me-2"></i>Anza Sasa - Bure
          </a>
          <a href="portal/?r=1" class="btn btn-outline-light btn-hero">
            <i class="fas fa-eye me-2"></i>Ona Demo Portal
          </a>
        </div>
        <div class="d-flex flex-wrap gap-4 mt-4 text-white-75">
          <div><i class="fas fa-check-circle me-2 text-light"></i>Bila Contract</div>
          <div><i class="fas fa-check-circle me-2 text-light"></i>Setup dakika 5</div>
          <div><i class="fas fa-check-circle me-2 text-light"></i>SMS Otomatiki</div>
        </div>
      </div>
      <div class="col-lg-6 d-none d-lg-block text-center">
        <div style="background:rgba(255,255,255,.1);border-radius:24px;padding:30px">
          <img src="<?= SITE_LOGO ?>" style="width:120px;margin-bottom:20px" alt="KADILI NET">
          <div style="color:#fff;font-size:14px;opacity:.8">SaaS Platform for MikroTik Resellers</div>
          <div class="row mt-4 g-3">
            <?php $feats = [
              ['fas fa-router','Routers za MikroTik'],
              ['fas fa-ticket-alt','Vouchers Otomatiki'],
              ['fas fa-mobile-alt','PalmPesa STK Push'],
              ['fas fa-sms','Beem SMS/OTP'],
            ]; foreach($feats as $f): ?>
            <div class="col-6">
              <div style="background:rgba(255,255,255,.15);border-radius:12px;padding:12px;font-size:12px;color:#fff">
                <i class="<?= $f[0] ?> mb-1" style="font-size:20px"></i><br><?= $f[1] ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Features -->
<section class="py-5">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="fw-800" style="font-weight:800">Kwa Nini KADILI NET?</h2>
      <p class="text-muted">Zana zote unazohitaji kusimamia biashara yako ya WiFi</p>
    </div>
    <div class="row g-4">
      <?php $features = [
        ['bg-primary bg-opacity-10','fas fa-router text-primary','MikroTik API','Unganisha routers bila IP ya umma kupitia VPN tunnel (SSTP/L2TP) na RouterOS API.'],
        ['bg-success bg-opacity-10','fas fa-money-bill-wave text-success','PalmPesa Payments','Payments ya M-Pesa, Airtel, Tigo, Halo kwa STK push moja kwa moja kwenye simu za wateja.'],
        ['bg-warning bg-opacity-10','fas fa-sms text-warning','SMS Otomatiki','Wateja wanapata credentials kwa SMS moja kwa moja baada ya kulipa. Kikumbusha dakika 30 kabla ya kumalizika.'],
        ['bg-info bg-opacity-10','fas fa-chart-line text-info','Dashboard ya Sales','Charts, takwimu za wakati halisi, usimamizi wa wasambazaji na maombi ya kuondoa pesa.'],
        ['bg-danger bg-opacity-10','fas fa-ticket-alt text-danger','Voucher Generator','Zalisha vouchers kwa wingi, chapisha PDF za kitaalamu (vouchers 30 kwa ukurasa).'],
        ['bg-purple bg-opacity-10','fas fa-shield-alt text-secondary','OTP Verification','Subscription salama kupitia Beem OTP verification kwa nambari za simu za Tanzania.'],
      ]; foreach ($features as $f): ?>
      <div class="col-md-4">
        <div class="feature-card card">
          <div class="feature-icon <?= $f[0] ?>"><i class="<?= $f[1] ?>"></i></div>
          <h5 class="fw-700" style="font-weight:700"><?= $f[2] ?></h5>
          <p class="text-muted small mb-0"><?= $f[3] ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Pricing -->
<section class="py-5 bg-light">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="fw-800" style="font-weight:800">Price ya Subscription</h2>
    </div>
    <div class="row justify-content-center g-4">
      <div class="col-md-5">
        <div class="card border-0 rounded-4 shadow-sm p-4 text-center">
          <div class="badge bg-primary mb-3" style="font-size:13px">Reseller</div>
          <div class="h1 fw-900" style="font-weight:900;color:#007bff">TZS <?= number_format(MONTHLY_FEE) ?></div>
          <div class="text-muted mb-4">/mwezi</div>
          <?php if (DB::setting('setup_fee_enabled')): ?>
          <div class="alert alert-light border rounded-3 small">Fee ya kwanza (mara moja): <strong><?= formatTZS(SETUP_FEE) ?></strong></div>
          <?php endif; ?>
          <ul class="list-unstyled text-start mb-4">
            <?php $perks = ['Routers zisizo na kikomo','Packages visivyo na kikomo','Vouchers za kiotomatiki','PalmPesa integration','Beem SMS notifications','Dashboard na charts']; ?>
            <?php foreach ($perks as $p): ?>
            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i><?= $p ?></li>
            <?php endforeach; ?>
          </ul>
          <a href="reseller/register.php" class="btn btn-primary rounded-pill py-3 fw-bold">
            <i class="fas fa-rocket me-2"></i>Anza Sasa
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="bg-dark text-white py-4">
  <div class="container text-center">
    <img src="<?= SITE_LOGO ?>" style="height:35px;margin-bottom:10px;filter:brightness(0) invert(1)" alt="KADILI NET">
    <p class="mb-1 small opacity-75">KADILI NET &copy; <?= date('Y') ?> — kadilihotspot.online</p>
    <p class="small opacity-50">Created by KADILI DEV | Email: kadiliy17@gmail.com | 0618240534</p>
    <div class="mt-2 d-flex justify-content-center gap-3">
      <a href="admin/login.php" class="text-white-50 small">Admin</a>
      <a href="reseller/login.php" class="text-white-50 small">Reseller</a>
      <a href="portal/" class="text-white-50 small">Portal Demo</a>
    </div>
  </div>
</footer>

<script>
window.addEventListener('scroll', () => {
  document.getElementById('mainNav').classList.toggle('scrolled', window.scrollY > 50);
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
