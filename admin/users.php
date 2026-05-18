<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = in_array($_POST['role'] ?? '', ['admin','viewer']) ? $_POST['role'] : 'admin';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        flash_error('Valid email and password (min 8 chars) required.');
    } else {
        try {
            $stmt = $db->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
            $stmt->execute([$email, password_hash($password, PASSWORD_BCRYPT), $role]);
            flash_success('User created.');
        } catch (PDOException $e) {
            flash_error('Email already exists.');
        }
    }
    redirect(BASE_URL . '/admin/users.php');
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
    $id       = (int)($_POST['user_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    if (strlen($password) < 8) {
        flash_error('Password must be at least 8 characters.');
    } else {
        $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_BCRYPT), $id]);
        flash_success('Password updated.');
    }
    redirect(BASE_URL . '/admin/users.php');
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
    $id = (int)($_POST['user_id'] ?? 0);
    $me = current_user();
    if ($id === (int)$me['id']) {
        flash_error('You cannot delete your own account.');
    } else {
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        flash_success('User deleted.');
    }
    redirect(BASE_URL . '/admin/users.php');
}

$users = $db->query('SELECT id, email, role, created_at FROM users ORDER BY created_at ASC')->fetchAll();
$me    = current_user();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h2>Users</h2>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">+ New User</button>
</div>

<div class="table-responsive">
<table class="table table-hover align-middle">
  <thead class="table-light">
    <tr><th>Email</th><th>Role</th><th>Created</th><th class="text-end">Actions</th></tr>
  </thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><?= e($u['email']) ?> <?= $u['id'] == $me['id'] ? '<span class="badge bg-secondary">you</span>' : '' ?></td>
      <td><span class="badge bg-<?= $u['role'] === 'admin' ? 'dark' : 'secondary' ?>"><?= e($u['role']) ?></span></td>
      <td class="text-muted small"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-secondary"
          data-bs-toggle="modal" data-bs-target="#pwModal"
          data-uid="<?= $u['id'] ?>" data-email="<?= e($u['email']) ?>">
          Change Password
        </button>
        <?php if ($u['id'] != $me['id']): ?>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete <?= e($u['email']) ?>?')">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
          <button class="btn btn-sm btn-outline-danger">Delete</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- Create user modal -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="create">
        <div class="modal-header"><h5 class="modal-title">New User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password <span class="text-muted">(min 8 chars)</span></label>
            <input type="password" name="password" class="form-control" required minlength="8">
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select">
              <option value="admin">Admin</option>
              <option value="viewer">Viewer</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Change password modal -->
<div class="modal fade" id="pwModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="user_id" id="pwUserId">
        <div class="modal-header"><h5 class="modal-title">Change Password — <span id="pwEmail"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">New Password <span class="text-muted">(min 8 chars)</span></label>
            <input type="password" name="password" class="form-control" required minlength="8">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('pwModal').addEventListener('show.bs.modal', function(e) {
  var btn = e.relatedTarget;
  document.getElementById('pwUserId').value = btn.dataset.uid;
  document.getElementById('pwEmail').textContent = btn.dataset.email;
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
