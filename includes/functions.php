<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function generate_token(): string {
    return bin2hex(random_bytes(32));
}

function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generate_token();
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    start_session();
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function get_client_ip(): string {
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

function audit(?int $submission_id, ?int $campaign_id, string $event_type, array $payload = []): void {
    try {
        $db = get_db();
        $stmt = $db->prepare(
            'INSERT INTO audit_events (submission_id, campaign_id, event_type, payload_json, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $submission_id,
            $campaign_id,
            $event_type,
            empty($payload) ? null : json_encode($payload),
            get_client_ip(),
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    } catch (Throwable $e) {
        // audit failures are non-fatal
    }
}

function flash_error(string $msg): void {
    start_session();
    $_SESSION['flash_error'] = $msg;
}

function flash_success(string $msg): void {
    start_session();
    $_SESSION['flash_success'] = $msg;
}

function get_flash(): array {
    start_session();
    $out = [
        'error'   => $_SESSION['flash_error']   ?? null,
        'success' => $_SESSION['flash_success'] ?? null,
    ];
    unset($_SESSION['flash_error'], $_SESSION['flash_success']);
    return $out;
}

function allowed_audio_mime(string $mime): bool {
    return in_array($mime, ['audio/webm','video/webm','audio/ogg','audio/mp4','audio/mpeg','audio/wav','audio/x-wav'], true);
}

function ext_from_mime(string $mime): string {
    return [
        'audio/webm'  => 'webm',
        'audio/ogg'   => 'ogg',
        'audio/mp4'   => 'mp4',
        'audio/mpeg'  => 'mp3',
        'audio/wav'   => 'wav',
        'audio/x-wav' => 'wav',
    ][$mime] ?? 'webm';
}
