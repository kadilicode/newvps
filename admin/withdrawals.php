<?php
require_once __DIR__ . '/../includes/init.php';
requireAdmin();
$pageTitle = 'Withdrawals';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $note   = sanitize($_POST['note'] ?? '');

    if ($action === 'approve') {
        $w = DB::fetch("SELECT * FROM withdrawals WHERE id=? AND status='pending'", [$id]);
        if ($w) {
            DB::query("UPDATE withdrawals SET status='approved', admin_note=?, processed_at=NOW() WHERE id=?", [$note, $id]);
            DB::query("UPDATE resellers SET balance = balance - ? WHERE id=?", [$w['amount'], $w['reseller_id']]);
            $r = DB::fetch("SELECT phone, name FROM resellers WHERE id=?", [$w['reseller_id']]);
            if ($r) BeemSMS::send($r['phone'], "Hujambo {$r['name']}, ombi lako la kuondoa " . formatTZS($w['net_amount']) . " limeidhinishwa. KADILI NET");
            flash('success', 'Request approved.');
        }
    } elseif ($action === 'reject') {
        DB::query("UPDATE withdrawals SET status='rejected', admin_note=?, processed_at=NOW() WHERE id=?", [$note, $id]);
        $w = DB::fetch("SELECT reseller_id FROM withdrawals WHERE id=?", [$id]);
        if ($w) {
            $r = DB::fetch("SELECT phone, name FROM resellers WHERE id=?", [$w['reseller_id']]);
            if ($r) BeemSMS::send($r['phone'], "Hujambo {$r['name']}, ombi lako la kuondoa pesa limekataliwa. Sababu: $note. KADILI NET");
        }
        flash('info', 'Request rejected.');
    }
    redirect(SITE_URL . '/admin/withdrawals.php');
}

$withdrawals = DB::fetchAll("
  SELECT w.*, r.name as reseller_name, r.phone as reseller_phone, r.business_name
  FROM withdrawals w
  JOIN resellers r ON r.id = w.reseller_id
  ORDER BY w.created_at DESC
");

include '_header.php';
?>
<div class="card">
  <div class="card-header py-3"><i class="fas fa-hand-holding-usd text-primary me-2"></i>Maombi ya Withdrawals</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr><th>Date</th><th>Reseller</th><th>Phone</th><th>Amount</th><th>Fee</th><th>Amount Statussi</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($withdrawals as $w): ?>
        <tr>
          <td><?= date('d/m/Y H:i', strtotime($w['created_at'])) ?></td>
          <td>
            <div class="fw-semibold"><?= sanitize($w['business_name'] ?: $w['reseller_name']) ?></div>
          </td>
          <td><?= sanitize($w['reseller_phone']) ?></td>
          <td><?= formatTZS((float)$w['amount']) ?></td>
          <td class="text-danger"><?= formatTZS((float)$w['fee']) ?></td>
          <td class="fw-bold text-success"><?= formatTZS((float)$w['net_amount']) ?></td>
          <td>
            <?php $sc = ['pending'=>'warning','approved'=>'success','rejected'=>'danger']; ?>
            <span class="badge bg-<?= $sc[$w['status']] ?>"><?= ucfirst($w['status']) ?></span>
            <?php if ($w['admin_note']): ?>
              <div class="text-muted" style="font-size:10px"><?= sanitize($w['admin_note']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($w['status'] === 'pending'): ?>
            <div class="d-flex gap-1">
              <button class="btn btn-xs btn-success" style="font-size:11px;padding:2px 8px"
                onclick="processWithdrawal(<?= $w['id'] ?>, 'approve')">
                <i class="fas fa-check"></i> Approve
              </button>
              <button class="btn btn-xs btn-danger" style="font-size:11px;padding:2px 8px"
                onclick="processWithdrawal(<?= $w['id'] ?>, 'reject')">
                <i class="fas fa-times"></i> Reject
              </button>
            </div>
            <?php else: ?>
              <span class="text-muted small"><?= date('d/m H:i', strtotime($w['processed_at'])) ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($withdrawals)): ?>
        <tr><td colspan="8" class="text-center py-4 text-muted">No requests</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Process Modal -->
<div class="modal fade" id="processModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="processModalTitle">Approve / Reject</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="id" id="processId">
          <input type="hidden" name="action" id="processAction">
          <label class="form-label small fw-semibold">Notes (optional)</label>
          <textarea name="note" class="form-control form-control-sm" rows="3" placeholder="Reason or notes..."></textarea>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary btn-sm" id="processBtn">Verify</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = <<<JS
<script>
function processWithdrawal(id, action) {
  document.getElementById('processId').value = id;
  document.getElementById('processAction').value = action;
  document.getElementById('processModalTitle').textContent = action === 'approve' ? 'Approve Request' : 'Reject Request';
  document.getElementById('processBtn').className = 'btn btn-sm btn-' + (action === 'approve' ? 'success' : 'danger');
  document.getElementById('processBtn').textContent = action === 'approve' ? 'Approve' : 'Reject';
  new bootstrap.Modal(document.getElementById('processModal')).show();
}
</script>
JS;
include '_footer.php';
?>
