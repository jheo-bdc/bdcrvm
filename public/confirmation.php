<?php
require_once __DIR__ . '/../includes/functions.php';

start_session();

$id = (int)($_GET['id'] ?? 0);
$sub = null;
if ($id) {
    $stmt = get_db()->prepare(
        'SELECT s.dealer_name, c.name AS campaign_name
         FROM submissions s
         JOIN campaigns c ON c.id = s.campaign_id
         WHERE s.id = ?'
    );
    $stmt->execute([$id]);
    $sub = $stmt->fetch() ?: null;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Submission Confirmed — <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-6">
      <div class="card shadow-sm text-center p-5">
        <div class="mb-3" style="font-size:3.5rem;color:#198754">&#10003;</div>
        <h2 class="mb-2">Thank You<?= $sub ? ', ' . e($sub['dealer_name']) : '' ?>!</h2>
        <p class="text-muted">
          Your recording submission<?= $sub ? ' for <strong>' . e($sub['campaign_name']) . '</strong>' : '' ?> has been received and securely recorded.
        </p>
        <?php if ($id): ?>
          <p class="text-muted">
            Submission reference: <strong>#<?= (int)$id ?></strong>
          </p>
        <?php endif; ?>
        <hr>
        <p class="text-muted small mb-0">
          If you have any questions, please contact your representative.
        </p>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
