<?php
require_once __DIR__ . '/../includes/init.php';
requireAdmin();
$pageTitle = 'Announcements';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        DB::query(
            "INSERT INTO announcements (title, body, type) VALUES (?,?,?)",
            [sanitize($_POST['title']), sanitize($_POST['body']), sanitize($_POST['type'])]
        );
        // Send SMS to all active resellers if requested
        if (!empty($_POST['send_sms'])) {
            $resellers = DB::fetchAll("SELECT phone FROM resellers WHERE status='active'");
            foreach ($resellers as $r) {
                BeemSMS::send($r['phone'], sanitize($_POST['title']) . ': ' . sanitize($_POST['body']));
            }
        }
        flash('success', 'Announcement added.');
    } elseif ($action === 'delete') {
        DB::query("DELETE FROM announcements WHERE id=?", [(int)$_POST['id']]);
        flash('success', 'Announcement deleted.');
    } elseif ($action === 'toggle') {
        DB::query("UPDATE announcements SET is_active = !is_active WHERE id=?", [(int)$_POST['id']]);
    }
    redirect(SITE_URL . '/admin/announcements.php');
}

$announcements = DB::fetchAll("SELECT * FROM announcements ORDER BY created_at DESC");
include '_header.php';
?>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card">
      <div class="card-header"><i class="fas fa-plus text-primary me-2"></i>New Announcement</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Title</label>
            <input type="text" name="title" class="form-control form-control-sm" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Message</label>
            <textarea name="body" class="form-control form-control-sm" rows="4" required></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Type</label>
            <select name="type" class="form-select form-select-sm">
              <option value="info">Info (Buluu)</option>
              <option value="success">Success (Kijani)</option>
              <option value="warning">Warning (Njano)</option>
              <option value="danger">Danger (Nyekundu)</option>
            </select>
          </div>
          <div class="mb-3">
            <div class="form-check">
              <input type="checkbox" name="send_sms" class="form-check-input" id="sendSms">
              <label class="form-check-label small" for="sendSms">Send SMS to all resellers</label>
            </div>
          </div>
          <button class="btn btn-primary btn-sm w-100"><i class="fas fa-paper-plane me-2"></i>Publish</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card">
      <div class="card-header"><i class="fas fa-list text-primary me-2"></i>Announcements View All</div>
      <div class="card-body p-0">
        <?php if (empty($announcements)): ?>
          <div class="p-4 text-center text-muted">No announcements</div>
        <?php else: ?>
        <?php foreach ($announcements as $ann): ?>
        <div class="border-bottom p-3">
          <div class="d-flex justify-content-between align-items-start">
            <div class="flex-grow-1">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-<?= $ann['type'] ?>"><?= ucfirst($ann['type']) ?></span>
                <strong class="small"><?= sanitize($ann['title']) ?></strong>
                <?php if (!$ann['is_active']): ?>
                  <span class="badge bg-secondary">Disabled</span>
                <?php endif; ?>
              </div>
              <p class="mb-1 small text-muted"><?= nl2br(sanitize($ann['body'])) ?></p>
              <small class="text-muted"><?= date('d M Y H:i', strtotime($ann['created_at'])) ?></small>
            </div>
            <div class="d-flex gap-1 ms-2">
              <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $ann['id'] ?>">
                <button class="btn btn-xs btn-outline-secondary" style="font-size:11px;padding:2px 8px">
                  <i class="fas fa-<?= $ann['is_active']?'eye-slash':'eye' ?>"></i>
                </button>
              </form>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete announcement?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $ann['id'] ?>">
                <button class="btn btn-xs btn-danger" style="font-size:11px;padding:2px 8px">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include '_footer.php'; ?>
