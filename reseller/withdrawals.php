<?php
require_once __DIR__ . '/../includes/init.php';
requireReseller();
$pageTitle = 'Withdraw';
$rid = (int)$_SESSION['reseller_id'];

$reseller = DB::fetch("SELECT * FROM resellers WHERE id=?", [$rid]);
$balance  = (float)$reseller['balance'];
$minWithdraw = (float)(DB::setting('withdrawal_min') ?? WITHDRAWAL_MIN);
$feePercent  = (float)(DB::setting('withdrawal_fee_percent') ?? WITHDRAWAL_FEE_PERCENT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $phone  = sanitize($_POST['phone'] ?? $reseller['phone']);

    if ($amount < $minWithdraw) {
        flash('error', "Amount cha chini ni " . formatTZS($minWithdraw));
    } elseif ($amount > $balance) {
        flash('error', "Balance lako ni " . formatTZS($balance) . ".");
    } else {
        $fee    = $amount * ($feePercent / 100);
        $net    = $amount - $fee;
        DB::query(
            "INSERT INTO withdrawals (reseller_id, amount, fee, net_amount, phone) VALUES (?,?,?,?,?)",
            [$rid, $amount, $fee, $net, $phone]
        );
        flash('success', "Ombi la kuondoa " . formatTZS($amount) . " limetumwa. Utapokea " . formatTZS($net) . " baada ya ada ya " . formatTZS($fee));
    }
    redirect(SITE_URL . '/reseller/withdrawals.php');
}

$withdrawals = DB::fetchAll("SELECT * FROM withdrawals WHERE reseller_id=? ORDER BY created_at DESC", [$rid]);
include '_header.php';
?>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card mb-3" style="background:linear-gradient(135deg,#28a745,#1e7e34)">
      <div class="card-body text-white text-center p-4">
        <div class="mb-1 small opacity-75">Your Balance Sasa</div>
        <div class="h3 fw-bold"><?= formatTZS($balance) ?></div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="fas fa-paper-plane text-primary me-2"></i>Request Withdrawal</div>
      <div class="card-body">
        <?php if ($balance < $minWithdraw): ?>
          <div class="alert alert-warning small">
            Your balance is too low. Amount cha chini cha kuondoa ni <strong><?= formatTZS($minWithdraw) ?></strong>
          </div>
        <?php else: ?>
        <form method="POST">
          <div class="mb-2">
            <label class="form-label small fw-semibold">Amount (TZS)</label>
            <input type="number" name="amount" class="form-control form-control-sm" min="<?= $minWithdraw ?>" max="<?= $balance ?>" step="100" placeholder="<?= $minWithdraw ?>" required>
            <div class="form-text">Min: <?= formatTZS($minWithdraw) ?> | Fee: <?= $feePercent ?>%</div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Nambari ya Phone ya Kupokea</label>
            <input type="tel" name="phone" class="form-control form-control-sm" value="<?= sanitize($reseller['phone']) ?>" required>
          </div>
          <div class="alert alert-info py-2 small" id="feeCalc">
            Enter amount to see calculation
          </div>
          <button type="submit" class="btn btn-primary btn-sm w-100"
            onclick="return confirm('Send withdrawal request?')">
            <i class="fas fa-paper-plane me-2"></i>Send Request
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card">
      <div class="card-header"><i class="fas fa-history text-primary me-2"></i>Historia ya Withdrawals</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Date</th><th>Amount</th><th>Fee</th><th>Net Received</th><th>Phone</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($withdrawals as $w): ?>
              <tr>
                <td><?= date('d/m/Y H:i', strtotime($w['created_at'])) ?></td>
                <td><?= formatTZS((float)$w['amount']) ?></td>
                <td class="text-danger">-<?= formatTZS((float)$w['fee']) ?></td>
                <td class="fw-bold text-success"><?= formatTZS((float)$w['net_amount']) ?></td>
                <td><?= sanitize($w['phone']) ?></td>
                <td>
                  <?php $sc=['pending'=>'warning','approved'=>'success','rejected'=>'danger']; ?>
                  <span class="badge bg-<?= $sc[$w['status']] ?>"><?= ucfirst($w['status']) ?></span>
                  <?php if ($w['admin_note']): ?>
                    <div class="text-muted" style="font-size:10px"><?= sanitize($w['admin_note']) ?></div>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($withdrawals)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No history</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$extraJs = <<<JS
<script>
document.querySelector('input[name="amount"]')?.addEventListener('input', function() {
  const amount = parseFloat(this.value) || 0;
  const fee = amount * {$feePercent} / 100;
  const net = amount - fee;
  const el = document.getElementById('feeCalc');
  if (amount > 0) {
    el.innerHTML = `Amount: <b>TZS ${amount.toLocaleString()}</b> − Fee (${$feePercent}%): <b>TZS ${fee.toLocaleString()}</b> = Unapokea: <b class="text-success">TZS ${net.toLocaleString()}</b>`;
  }
});
</script>
JS;
include '_footer.php';
?>
