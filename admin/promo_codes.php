<?php
require_once __DIR__ . '/../includes/init.php';
requireAdmin();
$pageTitle = 'Promo Codes';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $code     = strtoupper(trim(sanitize($_POST['code'] ?? '')));
        $discount = (int)($_POST['discount_percent'] ?? 0);
        $maxUses  = (int)($_POST['max_uses'] ?? 0);
        $expires  = sanitize($_POST['expires_at'] ?? '');

        if (!$code || $discount < 1 || $discount > 100) {
            flash('error', 'Please enter a valid code and discount (1-100%).');
        } elseif (DB::fetch("SELECT id FROM promo_codes WHERE code=?", [$code])) {
            flash('error', 'Code already exists.');
        } else {
            DB::query(
                "INSERT INTO promo_codes (code, discount_percent, max_uses, expires_at) VALUES (?,?,?,?)",
                [$code, $discount, $maxUses, $expires ?: null]
            );
            flash('success', "Promo code '$code' created.");
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        DB::query("UPDATE promo_codes SET is_active=!is_active WHERE id=?", [$id]);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        DB::query("DELETE FROM promo_codes WHERE id=?", [$id]);
        flash('success', 'Promo code deleted.');
    }
    redirect(SITE_URL . '/admin/promo_codes.php');
}

$codes = DB::fetchAll("SELECT * FROM promo_codes ORDER BY created_at DESC");
include '_header.php';
?>
<div class="row g-3">
  <!-- Add Code -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-header"><i class="fas fa-plus text-primary me-2"></i>Create Promo Code</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Code *</label>
            <input type="text" name="code" class="form-control form-control-sm" placeholder="e.g. FREE100" required
              style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()">
            <div class="form-text">Alphanumeric, no spaces</div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Discount % *</label>
            <input type="number" name="discount_percent" class="form-control form-control-sm" min="1" max="100" placeholder="100" required>
            <div class="form-text">Use 100% to make setup fee completely free</div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Max Uses (0 = unlimited)</label>
            <input type="number" name="max_uses" class="form-control form-control-sm" value="0" min="0">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Expiry Date (optional)</label>
            <input type="date" name="expires_at" class="form-control form-control-sm">
          </div>
          <button type="submit" class="btn btn-primary btn-sm w-100">
            <i class="fas fa-tag me-2"></i>Create Code
          </button>
        </form>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-body bg-info bg-opacity-10 border border-info rounded-3">
        <h6 class="text-info fw-bold mb-2"><i class="fas fa-info-circle me-2"></i>How It Works</h6>
        <p class="small text-muted mb-0">
          When a new reseller registers, they can enter a promo code on the setup fee payment page.
          <br><br>
          A <strong>100% discount</strong> code makes the setup fee completely free — the reseller's account is activated immediately without any payment.
          <br><br>
          Share codes privately with trusted partners or use for testing.
        </p>
      </div>
    </div>
  </div>

  <!-- Codes List -->
  <div class="col-md-8">
    <div class="card">
      <div class="card-header"><i class="fas fa-tags text-primary me-2"></i>All Promo Codes (<?= count($codes) ?>)</div>
      <div class="card-body p-0">
        <?php if (empty($codes)): ?>
          <div class="p-5 text-center text-muted">
            <i class="fas fa-tags mb-3" style="font-size:40px;opacity:.3"></i>
            <p>No promo codes yet. Create one to get started.</p>
          </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr><th>Code</th><th>Discount</th><th>Uses</th><th>Expires</th><th>Status</th><th>Created</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($codes as $c): ?>
            <?php
              $isExpired = $c['expires_at'] && strtotime($c['expires_at']) < time();
              $maxReached = $c['max_uses'] > 0 && $c['used_count'] >= $c['max_uses'];
            ?>
            <tr class="<?= (!$c['is_active'] || $isExpired || $maxReached) ? 'table-secondary' : '' ?>">
              <td>
                <code class="fw-bold fs-6 text-primary"><?= sanitize($c['code']) ?></code>
                <?php if ($isExpired): ?><span class="badge bg-danger ms-1">Expired</span><?php endif; ?>
                <?php if ($maxReached): ?><span class="badge bg-warning text-dark ms-1">Used Up</span><?php endif; ?>
              </td>
              <td>
                <span class="badge bg-<?= $c['discount_percent'] == 100 ? 'success' : 'info' ?> fs-6">
                  <?= $c['discount_percent'] ?>%
                  <?= $c['discount_percent'] == 100 ? ' (FREE)' : '' ?>
                </span>
              </td>
              <td>
                <span class="fw-semibold"><?= $c['used_count'] ?></span>
                <?php if ($c['max_uses'] > 0): ?>
                  <span class="text-muted">/ <?= $c['max_uses'] ?></span>
                <?php else: ?>
                  <span class="text-muted">/ ∞</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($c['expires_at']): ?>
                  <span class="<?= $isExpired ? 'text-danger' : 'text-muted' ?> small">
                    <?= date('d/m/Y', strtotime($c['expires_at'])) ?>
                  </span>
                <?php else: ?>
                  <span class="text-muted small">Never</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge bg-<?= $c['is_active'] && !$isExpired && !$maxReached ? 'success' : 'secondary' ?>">
                  <?= $c['is_active'] && !$isExpired && !$maxReached ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td class="small text-muted"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
              <td>
                <div class="d-flex gap-1">
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <button class="btn btn-xs btn-outline-secondary" style="font-size:11px;padding:2px 8px" title="Toggle">
                      <i class="fas fa-<?= $c['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                    </button>
                  </form>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete this promo code?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <button class="btn btn-xs btn-danger" style="font-size:11px;padding:2px 8px">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include '_footer.php'; ?>
