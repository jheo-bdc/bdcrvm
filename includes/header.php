<?php
require_once __DIR__ . '/../includes/auth.php';

$flash        = get_flash();
$_is_admin    = (basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'admin');
$_admin_user  = $_is_admin ? current_user() : null;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body>
<?php if ($_is_admin && $_admin_user): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand" href="<?= e(BASE_URL) ?>/admin/index.php"><?= e(APP_NAME) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= e(BASE_URL) ?>/admin/campaigns.php">Campaigns</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(BASE_URL) ?>/admin/submissions.php">Submissions</a></li>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item"><span class="navbar-text me-3 text-muted"><?= e($_admin_user['email']) ?></span></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(BASE_URL) ?>/admin/logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<?php endif; ?>
<div class="container py-3">
<?php if ($flash['error']): ?>
  <div class="alert alert-danger alert-dismissible">
    <?= e($flash['error']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if ($flash['success']): ?>
  <div class="alert alert-success alert-dismissible">
    <?= e($flash['success']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
