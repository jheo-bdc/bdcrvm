<?php
require_once __DIR__ . '/../includes/functions.php';

function require_login(): void {
    start_session();
    if (empty($_SESSION['user_id'])) {
        redirect(BASE_URL . '/admin/login.php');
    }
}

function current_user(): ?array {
    start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = get_db()->prepare('SELECT id, email, role FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function login(string $email, string $password): bool {
    start_session();
    $stmt = get_db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if ($row && password_verify($password, $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $row['id'];
        return true;
    }
    return false;
}

function logout(): void {
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
