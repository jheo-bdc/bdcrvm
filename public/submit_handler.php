<?php
require_once __DIR__ . '/../includes/functions.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/');
}

$token = trim($_POST['token'] ?? '');

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['submit_errors'] = ['Security token mismatch. Please reload and try again.'];
    redirect(BASE_URL . '/public/submit.php?token=' . urlencode($token));
}

$db   = get_db();
$stmt = $db->prepare("SELECT * FROM campaigns WHERE public_token = ? AND status = 'active'");
$stmt->execute([$token]);
$campaign = $stmt->fetch();
if (!$campaign) {
    redirect(BASE_URL . '/public/submit.php?token=' . urlencode($token));
}

$stmt = $db->prepare('SELECT * FROM campaign_slots WHERE campaign_id=? ORDER BY sort_order');
$stmt->execute([$campaign['id']]);
$slots = $stmt->fetchAll();

$stmt = $db->prepare('SELECT * FROM campaign_attestations WHERE campaign_id=? ORDER BY sort_order');
$stmt->execute([$campaign['id']]);
$attestations = $stmt->fetchAll();

// ── Identity validation ──────────────────────────────────────────────
$errors      = [];
$dealer_name = trim($_POST['dealer_name'] ?? '');
$dealership  = trim($_POST['dealership']  ?? '');
$email       = trim($_POST['email']       ?? '');
$phone       = trim($_POST['phone']       ?? '');
$title       = trim($_POST['title']       ?? '');
$sig_text    = trim($_POST['signature_text'] ?? '');
$signed_at   = trim($_POST['signed_at']   ?? '');

if (!$dealer_name)                              $errors[] = 'Full name is required.';
if (!$dealership)                               $errors[] = 'Dealership is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
if (!$phone)                                    $errors[] = 'Phone number is required.';
if (!$title)                                    $errors[] = 'Title is required.';
if (!$sig_text)                                 $errors[] = 'Signature is required.';

// ── Recording validation ─────────────────────────────────────────────
$allowed_mimes = ['audio/webm','video/webm','audio/ogg','audio/mp4','audio/mpeg','audio/wav','audio/x-wav'];
foreach ($slots as $slot) {
    $key = 'recording_' . $slot['id'];
    $file_ok = !empty($_FILES[$key]['name']) && $_FILES[$key]['error'] === UPLOAD_ERR_OK;
    if ($slot['required'] && !$file_ok) {
        $errors[] = 'Recording required for: ' . $slot['label'];
        continue;
    }
    if ($file_ok) {
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($_FILES[$key]['tmp_name']);
        if (!in_array($detected, $allowed_mimes, true)) {
            $errors[] = 'Invalid file type for slot: ' . $slot['label'];
        }
    }
}

// ── Attestation validation ───────────────────────────────────────────
foreach ($attestations as $att) {
    if ($att['required'] && empty($_POST['attestation_' . $att['id']])) {
        $errors[] = 'Required attestation must be checked: ' . mb_substr($att['text'], 0, 80) . '…';
    }
}

if ($errors) {
    $_SESSION['submit_errors'] = $errors;
    redirect(BASE_URL . '/public/submit.php?token=' . urlencode($token));
}

// ── Persist to database ──────────────────────────────────────────────
$db->beginTransaction();
try {
    $ip         = get_client_ip();
    $ua         = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $started_at = $_SESSION['started_at_' . $campaign['id']] ?? date('Y-m-d H:i:s');
    $signed_ts  = $signed_at ? date('Y-m-d H:i:s', strtotime($signed_at) ?: time()) : date('Y-m-d H:i:s');

    $db->prepare(
        'INSERT INTO submissions
         (campaign_id, public_token_snapshot, dealer_name, dealership, email, phone, title,
          signature_text, signed_at, ip_address, user_agent, started_at, submitted_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
    )->execute([$campaign['id'], $token, $dealer_name, $dealership, $email, $phone, $title,
                $sig_text, $signed_ts, $ip, $ua, $started_at]);

    $submission_id = (int)$db->lastInsertId();

    // Storage directory for this submission
    $storage_dir = STORAGE_PATH . DIRECTORY_SEPARATOR . $submission_id;
    if (!is_dir($storage_dir)) {
        mkdir($storage_dir, 0755, true);
    }

    // Move recordings
    $rec_stmt = $db->prepare(
        'INSERT INTO submission_recordings
         (submission_id, slot_id, file_path, original_filename, duration_seconds, mime_type, size_bytes, sha256)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    foreach ($slots as $slot) {
        $key     = 'recording_' . $slot['id'];
        $file_ok = !empty($_FILES[$key]['name']) && $_FILES[$key]['error'] === UPLOAD_ERR_OK;
        if (!$file_ok) continue;

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mime     = $finfo->file($_FILES[$key]['tmp_name']);
        $ext      = ext_from_mime($mime);
        $filename = 'slot_' . $slot['id'] . '_' . time() . '.' . $ext;
        $dest     = $storage_dir . DIRECTORY_SEPARATOR . $filename;
        $rel_path = 'storage/recordings/' . $submission_id . '/' . $filename;

        move_uploaded_file($_FILES[$key]['tmp_name'], $dest);

        $sha256   = hash_file('sha256', $dest);
        $size     = filesize($dest);
        $duration = ($_POST['duration_' . $slot['id']] ?? '') !== ''
                    ? (float)$_POST['duration_' . $slot['id']] : null;

        $rec_stmt->execute([
            $submission_id, $slot['id'], $rel_path, $_FILES[$key]['name'],
            $duration, $mime, $size, $sha256,
        ]);
    }

    // Attestations (with text snapshot)
    $sa_stmt = $db->prepare(
        'INSERT INTO submission_attestations
         (submission_id, attestation_id, attestation_text_snapshot, checked_at)
         VALUES (?,?,?,?)'
    );
    foreach ($attestations as $att) {
        if (empty($_POST['attestation_' . $att['id']])) continue;
        $checked_raw = trim($_POST['att_checked_at_' . $att['id']] ?? '');
        $checked_ts  = $checked_raw
                       ? date('Y-m-d H:i:s', strtotime($checked_raw) ?: time())
                       : date('Y-m-d H:i:s');
        $sa_stmt->execute([$submission_id, $att['id'], $att['text'], $checked_ts]);
    }

    // Client-side audit events
    $client_events = json_decode($_POST['audit_events_json'] ?? '[]', true);
    if (!is_array($client_events)) $client_events = [];
    $ae_stmt = $db->prepare(
        'INSERT INTO audit_events
         (submission_id, campaign_id, event_type, payload_json, ip_address, user_agent, created_at)
         VALUES (?,?,?,?,?,?,?)'
    );
    foreach ($client_events as $ev) {
        $ev_type    = preg_replace('/[^a-z0-9_]/', '', $ev['event_type'] ?? 'unknown');
        $ev_created = !empty($ev['ts'])
                      ? date('Y-m-d H:i:s', strtotime($ev['ts']) ?: time())
                      : date('Y-m-d H:i:s');
        $ev_payload = isset($ev['payload']) && $ev['payload'] ? json_encode($ev['payload']) : null;
        $ae_stmt->execute([$submission_id, $campaign['id'], $ev_type, $ev_payload, $ip, $ua, $ev_created]);
    }

    // Server-side form_submitted event
    $db->prepare(
        'INSERT INTO audit_events (submission_id, campaign_id, event_type, ip_address, user_agent)
         VALUES (?,?,?,?,?)'
    )->execute([$submission_id, $campaign['id'], 'form_submitted', $ip, $ua]);

    $db->commit();

    unset($_SESSION['started_at_' . $campaign['id']], $_SESSION['submit_errors']);
    redirect(BASE_URL . '/public/confirmation.php?id=' . $submission_id);

} catch (Throwable $ex) {
    $db->rollBack();
    error_log('[RVM] submit_handler error: ' . $ex->getMessage());
    $_SESSION['submit_errors'] = ['A server error occurred. Please try again.'];
    redirect(BASE_URL . '/public/submit.php?token=' . urlencode($token));
}
