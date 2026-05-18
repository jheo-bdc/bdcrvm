<?php
require_once __DIR__ . '/../includes/functions.php';

start_session();

$token     = trim($_GET['token'] ?? '');
$campaign  = null;
$slots     = [];
$attestations = [];

if ($token) {
    $db   = get_db();
    $stmt = $db->prepare("SELECT * FROM campaigns WHERE public_token = ? AND status = 'active'");
    $stmt->execute([$token]);
    $campaign = $stmt->fetch();

    if ($campaign) {
        $s = $db->prepare('SELECT * FROM campaign_slots WHERE campaign_id=? ORDER BY sort_order');
        $s->execute([$campaign['id']]);
        $slots = $s->fetchAll();

        $a = $db->prepare('SELECT * FROM campaign_attestations WHERE campaign_id=? ORDER BY sort_order');
        $a->execute([$campaign['id']]);
        $attestations = $a->fetchAll();

        // Record when the dealer first opened the form
        $key = 'started_at_' . $campaign['id'];
        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = date('Y-m-d H:i:s');
        }
    }
}

$submit_errors = $_SESSION['submit_errors'] ?? [];
unset($_SESSION['submit_errors']);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $campaign ? e($campaign['name']) . ' — ' : '' ?><?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body>
<div class="container py-4" style="max-width:760px">

<?php if (!$campaign): ?>
  <div class="text-center py-5">
    <h2 class="mb-3">Campaign Not Available</h2>
    <p class="text-muted lead">This submission link is not currently active.<br>Please contact your representative for assistance.</p>
  </div>
<?php else: ?>

<h2 class="mb-1"><?= e($campaign['name']) ?></h2>
<?php if ($campaign['intro_copy']): ?>
  <p class="lead text-muted mb-4"><?= nl2br(e($campaign['intro_copy'])) ?></p>
<?php else: ?>
  <hr class="mb-4">
<?php endif; ?>

<?php if ($submit_errors): ?>
  <div class="alert alert-danger">
    <strong>Please correct the following:</strong>
    <ul class="mb-0 mt-1"><?php foreach ($submit_errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
  </div>
<?php endif; ?>

<form id="submission-form" method="post"
      action="<?= e(BASE_URL) ?>/public/submit_handler.php"
      enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="token"      value="<?= e($token) ?>">
  <input type="hidden" name="audit_events_json" id="audit-events-json" value="[]">

  <!-- Identity -->
  <div class="card mb-4">
    <div class="card-header fw-semibold">Your Information</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="dealer_name" class="form-control" required autocomplete="name">
        </div>
        <div class="col-md-6">
          <label class="form-label">Dealership <span class="text-danger">*</span></label>
          <input type="text" name="dealership" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Email <span class="text-danger">*</span></label>
          <input type="email" name="email" class="form-control" required autocomplete="email">
        </div>
        <div class="col-md-6">
          <label class="form-label">Phone <span class="text-danger">*</span></label>
          <input type="tel" name="phone" class="form-control" required autocomplete="tel">
        </div>
        <div class="col-md-6">
          <label class="form-label">Title <span class="text-danger">*</span></label>
          <input type="text" name="title" class="form-control" required>
        </div>
      </div>
    </div>
  </div>

  <!-- Recording Slots -->
  <?php foreach ($slots as $slot): ?>
  <div class="card mb-4 recording-card"
       data-slot-id="<?= (int)$slot['id'] ?>"
       data-required="<?= (int)$slot['required'] ?>">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span class="fw-semibold"><?= e($slot['label']) ?></span>
      <span>
        <?php if (!$slot['required']): ?><span class="badge bg-light text-muted border me-1">Optional</span><?php endif; ?>
        <?php if ($slot['max_seconds']): ?><span class="badge bg-light text-muted border"><?= (int)$slot['max_seconds'] ?>s max</span><?php endif; ?>
      </span>
    </div>
    <div class="card-body">
      <?php if ($slot['prompt_text']): ?>
        <div class="alert alert-light border mb-3 py-2">
          <strong>Script:</strong> <?= nl2br(e($slot['prompt_text'])) ?>
        </div>
      <?php endif; ?>

      <div class="recorder-ui">
        <div class="d-flex gap-2 mb-2 flex-wrap">
          <button type="button" class="btn btn-danger btn-record">&#9679; Record</button>
          <button type="button" class="btn btn-secondary btn-stop d-none" disabled>&#9632; Stop</button>
          <button type="button" class="btn btn-outline-secondary btn-rerecord d-none">&#8635; Re-record</button>
          <button type="button" class="btn btn-success btn-accept d-none">&#10003; Accept</button>
        </div>
        <div class="timer-display text-muted small fw-bold d-none mb-1">00:00</div>
        <audio class="preview-audio d-none mb-2" controls style="max-width:100%;width:400px"></audio>
        <div class="status-msg text-muted small"></div>
        <?php if ($slot['max_seconds']): ?>
          <input type="hidden" class="max-seconds" value="<?= (int)$slot['max_seconds'] ?>">
        <?php endif; ?>
      </div>

      <!-- Hidden file input — populated by JS via DataTransfer -->
      <input type="file" name="recording_<?= (int)$slot['id'] ?>"
             class="recording-input visually-hidden" accept="audio/*" tabindex="-1">
      <input type="hidden" name="duration_<?= (int)$slot['id'] ?>" class="duration-input" value="">
      <div class="accepted-badge d-none text-success fw-semibold mt-2">&#10003; Recording accepted</div>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Attestations -->
  <?php if ($attestations): ?>
  <div class="card mb-4">
    <div class="card-header fw-semibold">Attestations</div>
    <div class="card-body">
      <?php foreach ($attestations as $att): ?>
        <div class="form-check mb-3">
          <input class="form-check-input attestation-check"
                 type="checkbox"
                 id="att_<?= (int)$att['id'] ?>"
                 name="attestation_<?= (int)$att['id'] ?>"
                 value="1"
                 data-att-id="<?= (int)$att['id'] ?>"
                 data-required="<?= (int)$att['required'] ?>"
                 <?= $att['required'] ? 'required' : '' ?>>
          <label class="form-check-label" for="att_<?= (int)$att['id'] ?>">
            <?= e($att['text']) ?>
            <?php if ($att['required']): ?><span class="text-danger">*</span><?php endif; ?>
          </label>
          <input type="hidden" name="att_checked_at_<?= (int)$att['id'] ?>" class="att-checked-at" value="">
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Signature -->
  <div class="card mb-4">
    <div class="card-header fw-semibold">Signature</div>
    <div class="card-body">
      <p class="text-muted small">
        <?= $campaign['signature_prompt']
            ? e($campaign['signature_prompt'])
            : 'By typing your full legal name below, you attest that the recordings submitted are accurate and authorized.' ?>
      </p>
      <div class="col-md-7">
        <input type="text" name="signature_text" id="signature-text"
               class="form-control form-control-lg" placeholder="Type your full name" required>
      </div>
      <input type="hidden" name="signed_at" id="signed-at" value="">
    </div>
  </div>

  <div id="submit-errors" class="alert alert-danger d-none"></div>

  <button type="submit" id="submit-btn" class="btn btn-primary btn-lg px-5">
    Submit Recording
  </button>
  <p class="text-muted small mt-2">All required fields and recordings must be complete before submitting.</p>
</form>

<?php endif; ?>
</div>

<script>
const auditEvents = [];
function pushAudit(type, payload) {
    auditEvents.push({ event_type: type, payload: payload || {}, ts: new Date().toISOString() });
    const el = document.getElementById('audit-events-json');
    if (el) el.value = JSON.stringify(auditEvents);
}
pushAudit('page_loaded', { token: <?= json_encode($token) ?> });

// Attestation timestamps
document.querySelectorAll('.attestation-check').forEach(cb => {
    cb.addEventListener('change', function () {
        const hidden = this.closest('.form-check').querySelector('.att-checked-at');
        if (this.checked) {
            const ts = new Date().toISOString();
            hidden.value = ts;
            pushAudit('attestation_checked', { attestation_id: this.dataset.attId, ts });
        } else {
            hidden.value = '';
        }
    });
});

// Signature timestamp (capture once, clear if blank)
const sigInput  = document.getElementById('signature-text');
const signedAt  = document.getElementById('signed-at');
if (sigInput) {
    sigInput.addEventListener('input', function () {
        if (this.value.trim() && !signedAt.value) {
            signedAt.value = new Date().toISOString();
            pushAudit('signature_entered');
        }
        if (!this.value.trim()) signedAt.value = '';
    });
}
</script>
<script src="<?= e(BASE_URL) ?>/assets/js/recorder.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
