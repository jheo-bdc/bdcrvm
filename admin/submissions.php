<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$db          = get_db();
$campaign_id = (int)($_GET['campaign_id'] ?? 0);
$campaigns   = $db->query('SELECT id, name FROM campaigns ORDER BY name')->fetchAll();

$where  = $campaign_id ? 'WHERE s.campaign_id = ?' : '';
$params = $campaign_id ? [$campaign_id] : [];

$stmt = $db->prepare(
    "SELECT s.*, c.name AS campaign_name
     FROM submissions s
     JOIN campaigns c ON c.id = s.campaign_id
     $where
     ORDER BY s.submitted_at DESC"
);
$stmt->execute($params);
$submissions = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h2>Submissions</h2>
  <form method="get" class="d-flex gap-2 align-items-center">
    <select name="campaign_id" class="form-select form-select-sm" style="min-width:180px">
      <option value="">All Campaigns</option>
      <?php foreach ($campaigns as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $campaign_id === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
    <?php if ($campaign_id): ?><a href="submissions.php" class="btn btn-sm btn-link">Clear</a><?php endif; ?>
  </form>
</div>

<?php if (empty($submissions)): ?>
  <p class="text-muted">No submissions found.</p>
<?php else: ?>
<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light">
    <tr>
      <th>#</th><th>Dealer</th><th>Dealership</th><th>Campaign</th>
      <th>Submitted</th><th>Status</th><th class="text-end">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($submissions as $sub): ?>
    <tr>
      <td class="text-muted"><?= (int)$sub['id'] ?></td>
      <td><?= e($sub['dealer_name']) ?></td>
      <td><?= e($sub['dealership']) ?></td>
      <td><?= e($sub['campaign_name']) ?></td>
      <td><?= e(date('Y-m-d H:i', strtotime($sub['submitted_at']))) ?></td>
      <?php
        $badge = match($sub['status']) {
            'reviewed' => 'bg-success',
            'archived' => 'bg-dark',
            default    => 'bg-warning text-dark',
        };
      ?>
      <td><span class="badge <?= $badge ?>"><?= e($sub['status']) ?></span></td>
      <td class="text-end">
        <a href="<?= e(BASE_URL) ?>/admin/submission_view.php?id=<?= (int)$sub['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
