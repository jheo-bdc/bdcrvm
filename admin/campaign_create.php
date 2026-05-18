<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        $name             = trim($_POST['name'] ?? '');
        $client_context   = trim($_POST['client_context'] ?? '');
        $intro_copy       = trim($_POST['intro_copy'] ?? '');
        $signature_prompt = trim($_POST['signature_prompt'] ?? '');
        $status           = $_POST['status'] ?? 'draft';

        if (!$name) $errors[] = 'Campaign name is required.';
        if (!in_array($status, ['draft','active','closed'], true)) $errors[] = 'Invalid status.';

        if (empty($errors)) {
            $db    = get_db();
            $token = generate_token();
            $user  = current_user();

            $db->prepare(
                'INSERT INTO campaigns (name, client_context, public_token, status, intro_copy, signature_prompt, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$name, $client_context, $token, $status, $intro_copy, $signature_prompt, $user['id']]);
            $cid = (int)$db->lastInsertId();

            $slot_stmt = $db->prepare(
                'INSERT INTO campaign_slots (campaign_id, sort_order, label, prompt_text, max_seconds, required)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach (($_POST['slot_label'] ?? []) as $i => $label) {
                $label = trim($label);
                if (!$label) continue;
                $max = ($_POST['slot_max_seconds'][$i] ?? '') !== '' ? (int)$_POST['slot_max_seconds'][$i] : null;
                $req = isset($_POST['slot_required'][$i]) ? 1 : 0;
                $slot_stmt->execute([$cid, $i, $label, trim($_POST['slot_prompt'][$i] ?? ''), $max, $req]);
            }

            $att_stmt = $db->prepare(
                'INSERT INTO campaign_attestations (campaign_id, sort_order, text, required) VALUES (?, ?, ?, ?)'
            );
            foreach (($_POST['att_text'] ?? []) as $i => $text) {
                $text = trim($text);
                if (!$text) continue;
                $req = isset($_POST['att_required'][$i]) ? 1 : 0;
                $att_stmt->execute([$cid, $i, $text, $req]);
            }

            flash_success('Campaign created.');
            redirect(BASE_URL . '/admin/campaign_view.php?id=' . $cid);
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<h2 class="mb-4">Create Campaign</h2>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . e($e) . '</li>'; ?></ul></div>
<?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

  <div class="card mb-4">
    <div class="card-header fw-semibold">Campaign Details</div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">Campaign Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required value="<?= e($_POST['name'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Client / Dealership Context</label>
        <textarea name="client_context" class="form-control" rows="2"><?= e($_POST['client_context'] ?? '') ?></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Intro Copy <small class="text-muted">(shown to dealer)</small></label>
        <textarea name="intro_copy" class="form-control" rows="3"><?= e($_POST['intro_copy'] ?? '') ?></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Signature Prompt</label>
        <input type="text" name="signature_prompt" class="form-control" placeholder="e.g. By signing, I confirm..." value="<?= e($_POST['signature_prompt'] ?? '') ?>">
      </div>
      <div class="mb-0">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php foreach (['draft','active','closed'] as $s): ?>
            <option value="<?= $s ?>" <?= ($_POST['status'] ?? 'draft') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span class="fw-semibold">Recording Slots</span>
      <button type="button" class="btn btn-sm btn-outline-primary" onclick="addSlot()">+ Add Slot</button>
    </div>
    <div class="card-body" id="slots-container">
      <p class="text-muted mb-0" id="slots-empty">No slots yet. Click "+ Add Slot".</p>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span class="fw-semibold">Attestations</span>
      <button type="button" class="btn btn-sm btn-outline-primary" onclick="addAtt()">+ Add Attestation</button>
    </div>
    <div class="card-body" id="atts-container">
      <p class="text-muted mb-0" id="atts-empty">No attestations yet. Click "+ Add Attestation".</p>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-success">Create Campaign</button>
    <a href="<?= e(BASE_URL) ?>/admin/campaigns.php" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>

<script>
let slotIdx = 0;
let attIdx  = 0;

function addSlot() {
    document.getElementById('slots-empty').style.display = 'none';
    const i = slotIdx++;
    const div = document.createElement('div');
    div.className = 'border rounded p-3 mb-3 slot-row';
    div.innerHTML = `
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label form-label-sm fw-semibold">Label *</label>
          <input type="text" name="slot_label[${i}]" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-4">
          <label class="form-label form-label-sm">Script / Prompt</label>
          <textarea name="slot_prompt[${i}]" class="form-control form-control-sm" rows="2"></textarea>
        </div>
        <div class="col-md-2">
          <label class="form-label form-label-sm">Max Seconds</label>
          <input type="number" name="slot_max_seconds[${i}]" class="form-control form-control-sm" min="1">
        </div>
        <div class="col-auto">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="slot_required[${i}]" value="1" checked>
            <label class="form-check-label">Required</label>
          </div>
        </div>
        <div class="col-auto">
          <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">Remove</button>
        </div>
      </div>`;
    document.getElementById('slots-container').appendChild(div);
}

function addAtt() {
    document.getElementById('atts-empty').style.display = 'none';
    const i = attIdx++;
    const div = document.createElement('div');
    div.className = 'border rounded p-3 mb-3 att-row';
    div.innerHTML = `
      <div class="row g-2 align-items-end">
        <div class="col">
          <label class="form-label form-label-sm fw-semibold">Attestation Text *</label>
          <input type="text" name="att_text[${i}]" class="form-control form-control-sm" required>
        </div>
        <div class="col-auto">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="att_required[${i}]" value="1" checked>
            <label class="form-check-label">Required</label>
          </div>
        </div>
        <div class="col-auto">
          <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">Remove</button>
        </div>
      </div>`;
    document.getElementById('atts-container').appendChild(div);
}

function removeRow(btn) {
    btn.closest('.slot-row, .att-row').remove();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
