<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare(
    'SELECT s.*, c.name AS campaign_name, c.client_context, c.public_token
     FROM submissions s
     JOIN campaigns c ON c.id = s.campaign_id
     WHERE s.id = ?'
);
$stmt->execute([$id]);
$sub = $stmt->fetch();
if (!$sub) {
    http_response_code(404);
    exit('Submission not found.');
}

if (!class_exists('ZipArchive')) {
    exit('ZipArchive extension is not available on this server.');
}

$stmt = $db->prepare(
    'SELECT sr.*, cs.label AS slot_label
     FROM submission_recordings sr
     JOIN campaign_slots cs ON cs.id = sr.slot_id
     WHERE sr.submission_id = ?
     ORDER BY cs.sort_order'
);
$stmt->execute([$id]);
$recordings = $stmt->fetchAll();

$stmt = $db->prepare('SELECT * FROM submission_attestations WHERE submission_id = ? ORDER BY id');
$stmt->execute([$id]);
$attestations = $stmt->fetchAll();

$stmt = $db->prepare('SELECT * FROM audit_events WHERE submission_id = ? ORDER BY created_at');
$stmt->execute([$id]);
$audit_events = $stmt->fetchAll();

$zip_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'evidence_' . $id . '_' . time() . '.zip';
$zip      = new ZipArchive();
$zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

// Add audio files
foreach ($recordings as $rec) {
    $full = __DIR__ . '/../' . $rec['file_path'];
    if (file_exists($full)) {
        $zip->addFile($full, 'recordings/' . basename($rec['file_path']));
    }
}

// manifest.json
$manifest = [
    'generated_at' => date('c'),
    'submission'   => [
        'id'                    => $sub['id'],
        'campaign_name'         => $sub['campaign_name'],
        'client_context'        => $sub['client_context'],
        'public_token_snapshot' => $sub['public_token_snapshot'],
        'dealer_name'           => $sub['dealer_name'],
        'dealership'            => $sub['dealership'],
        'email'                 => $sub['email'],
        'phone'                 => $sub['phone'],
        'title'                 => $sub['title'],
        'signature_text'        => $sub['signature_text'],
        'signed_at'             => $sub['signed_at'],
        'ip_address'            => $sub['ip_address'],
        'user_agent'            => $sub['user_agent'],
        'started_at'            => $sub['started_at'],
        'submitted_at'          => $sub['submitted_at'],
        'status'                => $sub['status'],
    ],
    'recordings'   => array_map(fn($r) => [
        'slot_label'        => $r['slot_label'],
        'filename'          => basename($r['file_path']),
        'original_filename' => $r['original_filename'],
        'mime_type'         => $r['mime_type'],
        'size_bytes'        => (int)$r['size_bytes'],
        'duration_seconds'  => $r['duration_seconds'] !== null ? (float)$r['duration_seconds'] : null,
        'sha256'            => $r['sha256'],
        'created_at'        => $r['created_at'],
    ], $recordings),
    'attestations' => array_map(fn($a) => [
        'text_snapshot' => $a['attestation_text_snapshot'],
        'checked_at'    => $a['checked_at'],
        'created_at'    => $a['created_at'],
    ], $attestations),
    'audit_events' => array_map(fn($e) => [
        'event_type' => $e['event_type'],
        'payload'    => $e['payload_json'] ? json_decode($e['payload_json'], true) : null,
        'ip_address' => $e['ip_address'],
        'user_agent' => $e['user_agent'],
        'created_at' => $e['created_at'],
    ], $audit_events),
];
$zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// manifest.txt (human-readable)
$line = str_repeat('-', 60);
$txt  = "EVIDENCE PACKAGE — RVM Recording Authorization Portal\n";
$txt .= "Generated : " . date('Y-m-d H:i:s') . "\n";
$txt .= "$line\n\n";
$txt .= "SUBMISSION #{$sub['id']}\n";
$txt .= "Campaign   : {$sub['campaign_name']}\n";
$txt .= "Token snap : " . substr($sub['public_token_snapshot'], 0, 16) . "...\n";
$txt .= "Submitted  : {$sub['submitted_at']}\n\n";
$txt .= "DEALER IDENTITY\n$line\n";
$txt .= "Name       : {$sub['dealer_name']}\n";
$txt .= "Dealership : {$sub['dealership']}\n";
$txt .= "Email      : {$sub['email']}\n";
$txt .= "Phone      : {$sub['phone']}\n";
$txt .= "Title      : {$sub['title']}\n";
$txt .= "IP Address : {$sub['ip_address']}\n";
$txt .= "User Agent : " . ($sub['user_agent'] ?? '') . "\n\n";
$txt .= "SIGNATURE\n$line\n";
$txt .= "{$sub['signature_text']}\nSigned at: {$sub['signed_at']}\n\n";
$txt .= "RECORDINGS\n$line\n";
foreach ($recordings as $i => $r) {
    $txt .= ($i+1) . ". {$r['slot_label']}\n";
    $txt .= "   File     : " . basename($r['file_path']) . "\n";
    $txt .= "   MIME     : {$r['mime_type']}\n";
    $txt .= "   Size     : {$r['size_bytes']} bytes\n";
    if ($r['duration_seconds'] !== null) {
        $txt .= "   Duration : " . number_format((float)$r['duration_seconds'], 1) . "s\n";
    }
    $txt .= "   SHA-256  : {$r['sha256']}\n\n";
}
$txt .= "ATTESTATIONS\n$line\n";
foreach ($attestations as $i => $a) {
    $txt .= ($i+1) . ". {$a['attestation_text_snapshot']}\n";
    $txt .= "   Checked at: {$a['checked_at']}\n\n";
}
$txt .= "AUDIT TRAIL\n$line\n";
foreach ($audit_events as $ev) {
    $txt .= "[{$ev['created_at']}] {$ev['event_type']}";
    if ($ev['payload_json']) $txt .= "  " . $ev['payload_json'];
    $txt .= "\n";
}
$zip->addFromString('manifest.txt', $txt);
$zip->close();

$filename = 'evidence_submission_' . $id . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($zip_path));
header('Pragma: no-cache');
readfile($zip_path);
unlink($zip_path);
exit;
