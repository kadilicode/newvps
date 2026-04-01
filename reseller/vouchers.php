<?php
require_once __DIR__ . '/../includes/init.php';
requireReseller();
$pageTitle = 'Vouchers';
$rid = (int)$_SESSION['reseller_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $packageId = (int)$_POST['package_id'];
        $count     = min((int)($_POST['count'] ?? 10), 100);
        $package   = DB::fetch("SELECT * FROM packages WHERE id=? AND reseller_id=?", [$packageId, $rid]);

        if ($package && $count > 0) {
            $batchId = 'BATCH_' . time();
            $generated = 0;
            for ($i = 0; $i < $count; $i++) {
                $code = generateVoucherCode(8);
                // Add to MikroTik
                $router = DB::fetch("SELECT * FROM routers WHERE id=?", [$package['router_id']]);
                if ($router) {
                    $seconds = durationToSeconds($package['duration_value'], $package['duration_unit']);
                    MikroTik::addVoucher($router, $code, $package['profile_name'], $seconds);
                }
                DB::query(
                    "INSERT INTO vouchers (reseller_id, router_id, package_id, code, profile_name, batch_id) VALUES (?,?,?,?,?,?)",
                    [$rid, $package['router_id'], $packageId, $code, $package['profile_name'], $batchId]
                );
                $generated++;
            }
            flash('success', "$generated vouchers generated. Batch: $batchId");
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        DB::query("DELETE FROM vouchers WHERE id=? AND reseller_id=? AND status='unused'", [$id, $rid]);
        flash('success', 'Voucher deleted.');
    }
    redirect(SITE_URL . '/reseller/vouchers.php');
}

// Filter
$batchFilter = sanitize($_GET['batch'] ?? '');
$packages    = DB::fetchAll("SELECT p.*, r.name as router_name FROM packages p JOIN routers r ON r.id=p.router_id WHERE p.reseller_id=? AND p.status='active'", [$rid]);
$batches     = DB::fetchAll("SELECT DISTINCT batch_id, COUNT(*) as cnt, MIN(created_at) as created FROM vouchers WHERE reseller_id=? AND batch_id IS NOT NULL GROUP BY batch_id ORDER BY created DESC LIMIT 20", [$rid]);

$vouchersQ = $batchFilter
    ? DB::fetchAll("SELECT v.*, p.name as package_name, p.price FROM vouchers v LEFT JOIN packages p ON p.id=v.package_id WHERE v.reseller_id=? AND v.batch_id=? ORDER BY v.created_at DESC", [$rid, $batchFilter])
    : DB::fetchAll("SELECT v.*, p.name as package_name, p.price FROM vouchers v LEFT JOIN packages p ON p.id=v.package_id WHERE v.reseller_id=? ORDER BY v.created_at DESC LIMIT 50", [$rid]);

// PDF Export
if (isset($_GET['pdf']) && $batchFilter) {
    $vouchers = DB::fetchAll("SELECT v.*, p.name as package_name, p.price, p.duration_value, p.duration_unit FROM vouchers v LEFT JOIN packages p ON p.id=v.package_id WHERE v.reseller_id=? AND v.batch_id=?", [$rid, $batchFilter]);
    $reseller = DB::fetch("SELECT * FROM resellers WHERE id=?", [$rid]);
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><title>Vouchers - <?= $batchFilter ?></title>
    <style>
    body{font-family:Arial,sans-serif;margin:0;background:#fff;}
    .page{max-width:210mm;margin:0 auto;padding:10mm;}
    h2{text-align:center;color:#007bff;font-size:16px;margin-bottom:5px;}
    p.sub{text-align:center;font-size:11px;color:#666;margin-bottom:10px;}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
    .voucher{border:2px dashed #007bff;border-radius:10px;padding:12px;text-align:center;background:linear-gradient(135deg,#f0f8ff,#fff);}
    .code{font-size:18px;font-weight:900;color:#0056b3;letter-spacing:3px;font-family:monospace;}
    .info{font-size:10px;color:#333;margin-top:4px;}
    .price{font-size:13px;font-weight:bold;color:#28a745;}
    .logo{text-align:center;margin-bottom:8px;}
    @media print{.no-print{display:none}}
    </style></head>
    <body>
    <div class="page">
      <div class="logo"><strong style="font-size:18px;color:#007bff;">KADILI NET</strong></div>
      <h2><?= sanitize($reseller['business_name'] ?: $reseller['name']) ?></h2>
      <p class="sub">Batch: <?= $batchFilter ?> | Vouchers: <?= count($vouchers) ?></p>
      <div class="no-print" style="text-align:center;margin-bottom:10px">
        <button onclick="window.print()" style="background:#007bff;color:#fff;border:none;padding:8px 20px;border-radius:8px;cursor:pointer;font-size:13px">🖨️ Publish</button>
      </div>
      <div class="grid">
        <?php foreach ($vouchers as $v): ?>
        <div class="voucher">
          <div style="font-size:9px;color:#999;margin-bottom:2px;">KADILI NET WiFi</div>
          <div class="code"><?= $v['code'] ?></div>
          <div class="price"><?= formatTZS((float)$v['price']) ?></div>
          <div class="info"><?= sanitize($v['package_name']) ?> • <?= $v['duration_value'].' '.$v['duration_unit'] ?></div>
          <div class="info" style="color:#<?= $v['status']==='used'?'dc3545':'28a745' ?>"><?= strtoupper($v['status']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <p style="text-align:center;font-size:9px;color:#aaa;margin-top:15px;">kadilihotspot.online | Printed <?= date('d/m/Y H:i') ?></p>
    </div>
    </body></html>
    <?php exit;
}

include '_header.php';
?>
<div class="row g-3">
  <!-- Generate Panel -->
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header"><i class="fas fa-plus-circle text-primary me-2"></i>Generate Vouchers</div>
      <div class="card-body">
        <?php if (empty($packages)): ?>
          <div class="alert alert-warning small">Add vifurushi kwanza.</div>
        <?php else: ?>
        <form method="POST">
          <input type="hidden" name="action" value="generate">
          <div class="mb-2">
            <label class="form-label small fw-semibold">Package</label>
            <select name="package_id" class="form-select form-select-sm" required>
              <?php foreach ($packages as $p): ?>
                <option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?> - <?= formatTZS((float)$p['price']) ?> (<?= sanitize($p['router_name']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Quantity (max 100)</label>
            <input type="number" name="count" class="form-control form-control-sm" value="30" min="1" max="100" required>
          </div>
          <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-magic me-2"></i>Generate Vouchers</button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Batches -->
    <div class="card">
      <div class="card-header"><i class="fas fa-layer-group text-primary me-2"></i>Voucher Batches</div>
      <div class="card-body p-0">
        <?php foreach ($batches as $b): ?>
        <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
          <div>
            <div class="small fw-semibold"><?= $b['cnt'] ?> vouchers</div>
            <div class="text-muted" style="font-size:10px"><?= date('d/m H:i', strtotime($b['created'])) ?></div>
          </div>
          <div class="d-flex gap-1">
            <a href="?batch=<?= urlencode($b['batch_id']) ?>" class="btn btn-xs btn-outline-primary" style="font-size:11px;padding:2px 7px">View</a>
            <a href="?batch=<?= urlencode($b['batch_id']) ?>&pdf=1" target="_blank" class="btn btn-xs btn-success" style="font-size:11px;padding:2px 7px"><i class="fas fa-print"></i></a>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($batches)): ?>
          <div class="p-3 text-center text-muted small">No vouchers bado</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Vouchers Table -->
  <div class="col-md-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-ticket-alt text-primary me-2"></i>
          <?= $batchFilter ? "Batch: $batchFilter" : 'Recent Vouchers' ?>
          (<?= count($vouchersQ) ?>)
        </span>
        <?php if ($batchFilter): ?>
          <div class="d-flex gap-1">
            <a href="?batch=<?= urlencode($batchFilter) ?>&pdf=1" target="_blank" class="btn btn-sm btn-success">
              <i class="fas fa-print me-1"></i>Print PDF
            </a>
            <a href="vouchers.php" class="btn btn-sm btn-outline-secondary">× All</a>
          </div>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Code</th><th>Package</th><th>Price</th><th>Status</th><th>Date</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($vouchersQ as $v): ?>
              <tr>
                <td><code class="fs-6 fw-bold text-primary"><?= $v['code'] ?></code></td>
                <td><?= sanitize($v['package_name'] ?? '—') ?></td>
                <td><?= formatTZS((float)($v['price'] ?? 0)) ?></td>
                <td>
                  <?php $sc=['unused'=>'success','used'=>'secondary','expired'=>'danger']; ?>
                  <span class="badge bg-<?= $sc[$v['status']] ?>"><?= ucfirst($v['status']) ?></span>
                </td>
                <td><small><?= date('d/m/Y', strtotime($v['created_at'])) ?></small></td>
                <td>
                  <?php if ($v['status'] === 'unused'): ?>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete voucher?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $v['id'] ?>">
                    <button class="btn btn-xs btn-danger" style="font-size:11px;padding:2px 7px"><i class="fas fa-trash"></i></button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($vouchersQ)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No vouchers</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include '_footer.php'; ?>
