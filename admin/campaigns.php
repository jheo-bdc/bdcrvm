<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$campaigns = get_db()->query(
    'SELECT c.*, u.email AS created_by_email,
     (SELECT COUNT(*) FROM submissions s WHERE s.campaign_id = c.id) AS submission_count
     FROM campaigns c
     LEFT JOIN users u ON u.id = c.created_by
     ORDER BY c.created_at DESC'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h2>Campaigns</h2>
  <a href="<?= e(BASE_URL) ?>/admin/campaign_create.php" class="btn btn-primary">+ New Campaign</a>
</div>

<?php if (empty($campaigns)): ?>
  <p class="text-muted">No campaigns yet. <a href="<?= e(BASE_URL) ?>/admin/campaign_create.php">Create the first one.</a></p>
<?php else: ?>
<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light">
    <tr><th>Name</th><th>Status</th><th>Submissions</th><th>Created</th><th class="text-end">Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach ($campaigns as $c):
      $badge = ['draft'=>'secondary','active'=>'success','closed'=>'dark'][$c['status']] ?? 'secondary';
    ?>
    <tr>
      <td>
        <a href="<?= e(BASE_URL) ?>/admin/campaign_view.php?id=<?= (int)$c['id'] ?>" class="fw-semibold text-decoration-none">
          <?= e($c['name']) ?>
        </a>
      </td>
      <td><span class="badge bg-<?= $badge ?>"><?= ucfirst(e($c['status'])) ?></span></td>
      <td><?= (int)$c['submission_count'] ?></td>
      <td><?= e(date('Y-m-d', strtotime($c['created_at']))) ?></td>
      <td class="text-end">
        <a href="<?= e(BASE_URL) ?>/admin/campaign_view.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
        <a href="<?= e(BASE_URL) ?>/admin/campaign_edit.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
