<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$db              = get_db();
$campaign_count  = $db->query('SELECT COUNT(*) FROM campaigns')->fetchColumn();
$active_count    = $db->query("SELECT COUNT(*) FROM campaigns WHERE status = 'active'")->fetchColumn();
$submission_count= $db->query('SELECT COUNT(*) FROM submissions')->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>
<h2 class="mb-4">Dashboard</h2>
<div class="row g-4 mb-4">
  <div class="col-sm-4">
    <div class="card text-center shadow-sm">
      <div class="card-body py-4">
        <div class="display-4 fw-bold text-primary"><?= (int)$campaign_count ?></div>
        <div class="text-muted mt-1">Total Campaigns</div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card text-center shadow-sm">
      <div class="card-body py-4">
        <div class="display-4 fw-bold text-success"><?= (int)$active_count ?></div>
        <div class="text-muted mt-1">Active Campaigns</div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card text-center shadow-sm">
      <div class="card-body py-4">
        <div class="display-4 fw-bold text-info"><?= (int)$submission_count ?></div>
        <div class="text-muted mt-1">Total Submissions</div>
      </div>
    </div>
  </div>
</div>
<div class="d-flex gap-2 flex-wrap">
  <a href="<?= e(BASE_URL) ?>/admin/campaign_create.php" class="btn btn-primary">+ New Campaign</a>
  <a href="<?= e(BASE_URL) ?>/admin/campaigns.php" class="btn btn-outline-secondary">View Campaigns</a>
  <a href="<?= e(BASE_URL) ?>/admin/submissions.php" class="btn btn-outline-secondary">View Submissions</a>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
