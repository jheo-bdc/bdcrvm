// recorder.js — MediaRecorder-based per-slot recording UI
// Depends on: pushAudit() defined in submit.php inline script

(function () {
    'use strict';

    const MIME_PREFS = [
        'audio/webm;codecs=opus',
        'audio/webm',
        'audio/ogg;codecs=opus',
        'audio/ogg',
        'audio/mp4',
    ];

    const MIME_EXT = {
        'audio/webm': 'webm',
        'audio/ogg':  'ogg',
        'audio/mp4':  'mp4',
        'audio/mpeg': 'mp3',
        'audio/wav':  'wav',
    };

    function pickMime() {
        for (const m of MIME_PREFS) {
            if (typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported(m)) return m;
        }
        return '';
    }

    function baseMime(full) {
        return full.split(';')[0].trim();
    }

    function extFromMime(mime) {
        return MIME_EXT[baseMime(mime)] || 'webm';
    }

    function padTime(n) {
        return String(n).padStart(2, '0');
    }

    document.querySelectorAll('.recording-card').forEach(function (card) {
        const slotId     = card.dataset.slotId;
        const isRequired = card.dataset.required === '1';
        const maxSecEl   = card.querySelector('.max-seconds');
        const maxSec     = maxSecEl ? parseInt(maxSecEl.value, 10) : null;

        const btnRecord   = card.querySelector('.btn-record');
        const btnStop     = card.querySelector('.btn-stop');
        const btnRerecord = card.querySelector('.btn-rerecord');
        const btnAccept   = card.querySelector('.btn-accept');
        const timerEl     = card.querySelector('.timer-display');
        const audioEl     = card.querySelector('.preview-audio');
        const statusEl    = card.querySelector('.status-msg');
        const fileInput   = card.querySelector('.recording-input');
        const durInput    = card.querySelector('.duration-input');
        const badge       = card.querySelector('.accepted-badge');

        let mediaRecorder = null;
        let chunks        = [];
        let stream        = null;
        let timerHandle   = null;
        let elapsed       = 0;
        let finalBlob     = null;

        // ── Timer ──────────────────────────────────────────────────────
        function startTimer() {
            elapsed = 0;
            timerEl.textContent = '00:00';
            timerEl.classList.remove('d-none');
            timerHandle = setInterval(function () {
                elapsed++;
                timerEl.textContent = padTime(Math.floor(elapsed / 60)) + ':' + padTime(elapsed % 60);
                if (maxSec && elapsed >= maxSec) {
                    statusEl.textContent = 'Max duration reached — stopping.';
                    doStop();
                }
            }, 1000);
        }

        function stopTimer() {
            clearInterval(timerHandle);
            timerHandle = null;
        }

        // ── Record ─────────────────────────────────────────────────────
        btnRecord.addEventListener('click', async function () {
            statusEl.textContent = 'Requesting microphone access…';
            btnRecord.disabled = true;

            try {
                stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (err) {
                statusEl.textContent = 'Microphone access denied: ' + err.message;
                btnRecord.disabled = false;
                return;
            }

            chunks    = [];
            finalBlob = null;

            const chosenMime = pickMime();
            const opts       = chosenMime ? { mimeType: chosenMime } : {};

            try {
                mediaRecorder = new MediaRecorder(stream, opts);
            } catch (e) {
                mediaRecorder = new MediaRecorder(stream);
            }

            mediaRecorder.ondataavailable = function (e) {
                if (e.data && e.data.size > 0) chunks.push(e.data);
            };

            mediaRecorder.onstop = function () {
                const mimeUsed = mediaRecorder.mimeType || 'audio/webm';
                finalBlob      = new Blob(chunks, { type: mimeUsed });
                const url      = URL.createObjectURL(finalBlob);
                audioEl.src    = url;
                audioEl.classList.remove('d-none');
                btnRerecord.classList.remove('d-none');
                btnAccept.classList.remove('d-none');
                statusEl.textContent = 'Preview your recording, then Accept or Re-record.';
                pushAudit('recording_stopped', { slot_id: slotId, duration_s: elapsed });
            };

            mediaRecorder.start(1000);
            startTimer();

            btnRecord.classList.add('d-none');
            btnStop.classList.remove('d-none');
            btnStop.disabled = false;
            statusEl.textContent = 'Recording…';
            pushAudit('recording_started', { slot_id: slotId });
        });

        // ── Stop ───────────────────────────────────────────────────────
        function doStop() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            }
            stopTimer();
            if (stream) {
                stream.getTracks().forEach(function (t) { t.stop(); });
                stream = null;
            }
            btnStop.disabled = true;
            btnStop.classList.add('d-none');
        }

        btnStop.addEventListener('click', doStop);

        // ── Re-record ──────────────────────────────────────────────────
        btnRerecord.addEventListener('click', function () {
            finalBlob = null;
            chunks    = [];
            elapsed   = 0;

            audioEl.src = '';
            audioEl.classList.add('d-none');
            btnRerecord.classList.add('d-none');
            btnAccept.classList.add('d-none');
            btnRecord.classList.remove('d-none');
            btnRecord.disabled = false;
            badge.classList.add('d-none');
            timerEl.classList.add('d-none');
            timerEl.textContent = '00:00';
            statusEl.textContent = '';

            // Clear accepted file
            try {
                fileInput.files = new DataTransfer().files;
            } catch (_) {}
            if (durInput) durInput.value = '';

            pushAudit('recording_rerecorded', { slot_id: slotId });
        });

        // ── Accept ─────────────────────────────────────────────────────
        btnAccept.addEventListener('click', function () {
            if (!finalBlob) return;

            const mimeUsed = finalBlob.type || 'audio/webm';
            const ext      = extFromMime(mimeUsed);
            const filename = 'slot_' + slotId + '.' + ext;
            const file     = new File([finalBlob], filename, { type: baseMime(mimeUsed) });

            try {
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;
            } catch (e) {
                statusEl.textContent = 'Could not assign file — try a different browser.';
                return;
            }

            if (durInput) durInput.value = String(elapsed);

            btnAccept.classList.add('d-none');
            btnRerecord.classList.remove('d-none');
            badge.classList.remove('d-none');
            statusEl.textContent = '';

            pushAudit('recording_accepted', { slot_id: slotId, mime: baseMime(mimeUsed), duration_s: elapsed });
        });
    });

    // ── Client-side pre-submit validation ─────────────────────────────
    const form = document.getElementById('submission-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            const errs = [];

            document.querySelectorAll('.recording-card').forEach(function (card) {
                if (card.dataset.required !== '1') return;
                const fi = card.querySelector('.recording-input');
                if (!fi || !fi.files || fi.files.length === 0) {
                    const label = card.querySelector('.card-header .fw-semibold');
                    errs.push('Recording required: ' + (label ? label.textContent.trim() : 'unknown slot'));
                }
            });

            document.querySelectorAll('.attestation-check').forEach(function (cb) {
                if (cb.dataset.required === '1' && !cb.checked) {
                    errs.push('Please check all required attestations.');
                }
            });

            if (errs.length > 0) {
                e.preventDefault();
                const errEl = document.getElementById('submit-errors');
                const unique = [...new Set(errs)];
                errEl.innerHTML = '<ul class="mb-0">' + unique.map(function (m) {
                    return '<li>' + m.replace(/&/g,'&amp;').replace(/</g,'&lt;') + '</li>';
                }).join('') + '</ul>';
                errEl.classList.remove('d-none');
                errEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }
})();
