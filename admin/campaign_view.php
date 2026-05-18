<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM campaigns WHERE id = ?');
$stmt->execute([$id]);
$campaign = $stmt->fetch();
if (!$campaign) {
    flash_error('Campaign not found.');
    redirect(BASE_URL . '/admin/campaigns.php');
}

$stmt = $db->prepare('SELECT * FROM campaign_slots WHERE campaign_id=? ORDER BY sort_order');
$stmt->execute([$id]);
$slots = $stmt->fetchAll();

$stmt = $db->prepare('SELECT * FROM campaign_attestations WHERE campaign_id=? ORDER BY sort_order');
$stmt->execute([$id]);
$attestations = $stmt->fetchAll();

$stmt = $db->prepare(
    'SELECT * FROM submissions WHERE campaign_id=? ORDER BY submitted_at DESC LIMIT 25'
);
$stmt->execute([$id]);
$submissions = $stmt->fetchAll();

$public_url = BASE_URL . '/public/submit.php?token=' . urlencode($campaign['public_token']);
$badge = ['draft'=>'secondary','active'=>'success','closed'=>'dark'][$campaign['status']] ?? 'secondary';

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
  <div>
    <h2 class="mb-1">
      <?= e($campaign['name']) ?>
      <span class="badge bg-<?= $badge ?> fs-6"><?= ucfirst(e($campaign['status'])) ?></span>
    </h2>
    <?php if ($campaign['client_context']): ?>
      <p class="text-muted mb-0"><?= e($campaign['client_context']) ?></p>
    <?php endif; ?>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= e(BASE_URL) ?>/admin/campaign_edit.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary">Edit</a>
    <a href="<?= e(BASE_URL) ?>/admin/campaigns.php" class="btn btn-outline-secondary">← Back</a>
  </div>
</div>

<?php if ($campaign['status'] === 'active'): ?>
<div class="alert alert-success">
  <strong>Public Submission URL:</strong><br>
  <a href="<?= e($public_url) ?>" target="_blank"><?= e($public_url) ?></a>
  <button class="btn btn-sm btn-outline-success ms-2"
    onclick="navigator.clipboard.writeText(<?= json_encode($public_url) ?>); this.textContent='Copied!'">
    Copy URL
  </button>
</div>
<?php else: ?>
<div class="alert alert-warning">
  This campaign is <strong><?= e($campaign['status']) ?></strong>.
  Set status to <strong>active</strong> to enable the public submission link.
</div>
<?php endif; ?>

<?php if ($campaign['intro_copy']): ?>
<div class="card mb-4">
  <div class="card-header">Intro Copy</div>
  <div class="card-body"><p class="mb-0"><?= nl2br(e($campaign['intro_copy'])) ?></p></div>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header">Recording Slots (<?= count($slots) ?>)</div>
      <div class="card-body">
        <?php if (empty($slots)): ?>
          <p class="text-muted mb-0">No slots defined.</p>
        <?php else: ?>
          <?php foreach ($slots as $i => $slot): ?>
            <div class="mb-3">
              <strong><?= $i+1 ?>. <?= e($slot['label']) ?></strong>
              <?php if (!$slot['required']): ?> <span class="badge bg-light text-muted border">optional</span><?php endif; ?>
              <?php if ($slot['max_seconds']): ?> <span class="badge bg-light text-muted border"><?= (int)$slot['max_seconds'] ?>s max</span><?php endif; ?>
              <?php if ($slot['prompt_text']): ?><br><small class="text-muted"><?= e($slot['prompt_text']) ?></small><?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header">Attestations (<?= count($attestations) ?>)</div>
      <div class="card-body">
        <?php if (empty($attestations)): ?>
          <p class="text-muted mb-0">No attestations defined.</p>
        <?php else: ?>
          <ol class="mb-0 ps-3">
            <?php foreach ($attestations as $att): ?>
              <li class="mb-1">
                <?= e($att['text']) ?>
                <?php if (!$att['required']): ?> <span class="badge bg-light text-muted border">optional</span><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">Recent Submissions (<?= count($submissions) ?>)</h5>
  <a href="<?= e(BASE_URL) ?>/admin/submissions.php?campaign_id=<?= (int)$id ?>" class="btn btn-sm btn-outline-secondary">View all</a>
</div>

<?php if (empty($submissions)): ?>
  <p class="text-muted">No submissions yet.</p>
<?php else: ?>
<div class="table-responsive">
<table class="table table-sm table-hover">
  <thead class="table-light">
    <tr><th>Dealer</th><th>Dealership</th><th>Submitted</th><th></th></tr>
  </thead>
  <tbody>
    <?php foreach ($submissions as $sub): ?>
    <tr>
      <td><?= e($sub['dealer_name']) ?></td>
      <td><?= e($sub['dealership']) ?></td>
      <td><?= e(date('Y-m-d H:i', strtotime($sub['submitted_at']))) ?></td>
      <td>
        <a href="<?= e(BASE_URL) ?>/admin/submission_view.php?id=<?= (int)$sub['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
