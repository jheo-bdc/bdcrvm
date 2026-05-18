<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        flash_error('Invalid request.');
    } else {
        $new_status = $_POST['status'];
        if (in_array($new_status, ['pending', 'reviewed', 'archived'], true)) {
            $db->prepare('UPDATE submissions SET status = ? WHERE id = ?')->execute([$new_status, $id]);
            flash_success('Status updated.');
        }
    }
    redirect(BASE_URL . '/admin/submission_view.php?id=' . $id);
}

$stmt = $db->prepare(
    'SELECT s.*, c.name AS campaign_name, c.id AS campaign_id_val, c.signature_prompt
     FROM submissions s
     JOIN campaigns c ON c.id = s.campaign_id
     WHERE s.id = ?'
);
$stmt->execute([$id]);
$sub = $stmt->fetch();
if (!$sub) {
    flash_error('Submission not found.');
    redirect(BASE_URL . '/admin/submissions.php');
}

$stmt = $db->prepare(
    'SELECT sr.*, cs.label AS slot_label
     FROM submission_recordings sr
     JOIN campaign_slots cs ON cs.id = sr.slot_id
     WHERE sr.submission_id = ?
     ORDER BY cs.sort_order'
);
$stmt->execute([$id]);
$recordings = $stmt->fetchAll();

$stmt = $db->prepare('SELECT * FROM submission_attestations WHERE submission_id = ? ORDER BY id');
$stmt->execute([$id]);
$attestations = $stmt->fetchAll();

$stmt = $db->prepare('SELECT * FROM audit_events WHERE submission_id = ? ORDER BY created_at');
$stmt->execute([$id]);
$audit_events = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
  <div>
    <h2 class="mb-1">Submission #<?= (int)$sub['id'] ?></h2>
    <p class="text-muted mb-0">Campaign: <a href="<?= e(BASE_URL) ?>/admin/campaign_view.php?id=<?= (int)$sub['campaign_id_val'] ?>"><?= e($sub['campaign_name']) ?></a></p>
  </div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <form method="post" class="d-flex gap-2 align-items-center">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <select name="status" class="form-select form-select-sm" style="min-width:130px">
        <option value="pending"  <?= $sub['status'] === 'pending'  ? 'selected' : '' ?>>Pending</option>
        <option value="reviewed" <?= $sub['status'] === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
        <option value="archived" <?= $sub['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
      </select>
      <button type="submit" class="btn btn-sm btn-outline-primary">Update Status</button>
    </form>
    <a href="<?= e(BASE_URL) ?>/admin/download_evidence.php?id=<?= (int)$id ?>" class="btn btn-success">Download Evidence Package</a>
    <a href="<?= e(BASE_URL) ?>/admin/submissions.php" class="btn btn-outline-secondary">← Back</a>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header fw-semibold">Dealer Identity</div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-5">Full Name</dt>   <dd class="col-sm-7"><?= e($sub['dealer_name']) ?></dd>
          <dt class="col-sm-5">Dealership</dt>  <dd class="col-sm-7"><?= e($sub['dealership']) ?></dd>
          <dt class="col-sm-5">Email</dt>        <dd class="col-sm-7"><?= e($sub['email']) ?></dd>
          <dt class="col-sm-5">Phone</dt>        <dd class="col-sm-7"><?= e($sub['phone']) ?></dd>
          <dt class="col-sm-5">Title</dt>        <dd class="col-sm-7"><?= e($sub['title']) ?></dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header fw-semibold">Submission Metadata</div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-5">Submitted At</dt> <dd class="col-sm-7"><?= e($sub['submitted_at']) ?></dd>
          <dt class="col-sm-5">Started At</dt>   <dd class="col-sm-7"><?= e($sub['started_at']) ?></dd>
          <dt class="col-sm-5">Signed At</dt>    <dd class="col-sm-7"><?= e($sub['signed_at']) ?></dd>
        </dl>
      </div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header fw-semibold">Signature</div>
  <div class="card-body">
    <?php if ($sub['signature_prompt']): ?>
      <p class="text-muted small mb-2"><?= e($sub['signature_prompt']) ?></p>
    <?php endif; ?>
    <p class="fs-5 fw-semibold mb-1"><?= e($sub['signature_text']) ?></p>
    <small class="text-muted">Signed at: <?= e($sub['signed_at']) ?></small>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header fw-semibold">Recordings (<?= count($recordings) ?>)</div>
  <div class="card-body">
    <?php if (empty($recordings)): ?>
      <p class="text-muted mb-0">No recordings on file.</p>
    <?php else: ?>
      <?php foreach ($recordings as $rec): ?>
        <?php $rec_url = e(BASE_URL . '/' . ltrim(str_replace('\\', '/', $rec['file_path']), '/')); ?>
        <div class="mb-4 pb-3 border-bottom">
          <div class="fw-semibold mb-1"><?= e($rec['slot_label']) ?></div>
          <div class="text-muted small mb-2">
            MP3 &middot;
            <?= number_format($rec['size_bytes'] / 1024, 1) ?> KB
            <?php if ($rec['duration_seconds']): ?>&middot; <?= number_format((float)$rec['duration_seconds'], 1) ?>s<?php endif; ?>
          </div>
          <audio controls class="d-block mb-2" src="<?= $rec_url ?>"></audio>
          <a href="<?= $rec_url ?>"
             download="<?= e(preg_replace('/[^a-z0-9_\-]/i', '_', $rec['slot_label'])) ?>.mp3"
             class="btn btn-sm btn-outline-secondary">
            ↓ Download MP3
          </a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header fw-semibold">Attestations (<?= count($attestations) ?>)</div>
  <div class="card-body">
    <?php if (empty($attestations)): ?>
      <p class="text-muted mb-0">No attestations recorded.</p>
    <?php else: ?>
      <ol class="mb-0 ps-3">
        <?php foreach ($attestations as $att): ?>
          <li class="mb-2">
            <?= e($att['attestation_text_snapshot']) ?>
            <br><small class="text-muted">Checked at: <?= e($att['checked_at']) ?></small>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header fw-semibold">Audit Trail (<?= count($audit_events) ?> events)</div>
  <div class="card-body p-0">
    <?php if (empty($audit_events)): ?>
      <p class="text-muted p-3 mb-0">No audit events.</p>
    <?php else: ?>
      <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr><th>Time</th><th>Event</th></tr>
        </thead>
        <tbody>
          <?php foreach ($audit_events as $ev): ?>
          <tr>
            <td><small class="text-muted"><?= e($ev['created_at']) ?></small></td>
            <td><code><?= e($ev['event_type']) ?></code></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
