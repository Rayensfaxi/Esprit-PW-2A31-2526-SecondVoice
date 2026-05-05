<?php
// Reusable in-browser voice-dictation widget.
// Powered by Vosk (WASM) for STT and the echome.php endpoint for polish.
//
// Usage:
//   <textarea id="myField"></textarea>
//   <button type="button" class="vd-btn" id="vd-myField">🎙️ Dicter</button>
//   <?php require __DIR__ . '/../partials/voice-dictate.php'; ?>
//   <script>
//     VoiceDictate.attachButton(document.getElementById('vd-myField'), 'myField', { polishUrl: 'echome.php' });
//   </script>
?>
<script src="https://cdn.jsdelivr.net/npm/vosk-browser@0.0.8/dist/vosk.js"></script>

<style>
.vd-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 13px;
  font-family: inherit;
  font-size: .8rem;
  font-weight: 700;
  background: #eeeeff;
  color: #5046e5;
  border: 1px solid #d6d2ff;
  border-radius: 8px;
  cursor: pointer;
  user-select: none;
  transition: background .15s, transform .12s;
  white-space: nowrap;
}
.vd-btn:hover { background: #e0dcff; transform: translateY(-1px); }
.vd-btn:disabled { opacity: .55; cursor: wait; transform: none; }
.vd-btn.recording {
  background: #fee2e2;
  color: #b91c1c;
  border-color: #fca5a5;
  animation: vd-pulse 1.4s ease-in-out infinite;
}
@keyframes vd-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,.45); }
  50%      { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
}

.vd-status {
  display: none;
  margin-top: 6px;
  padding: 9px 12px;
  background: #f5f3ff;
  border: 1px solid #d6d2ff;
  border-radius: 8px;
  font-size: .82rem;
  color: #4f46e5;
  font-weight: 600;
  line-height: 1.45;
  word-wrap: break-word;
}
.vd-status.visible { display: block; animation: vd-fade .25s ease; }
.vd-status.success { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
.vd-status.error   { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
.vd-status .vd-live { font-style: italic; color: #6b7a9f; font-weight: 500; }
@keyframes vd-fade {
  from { opacity: 0; transform: translateY(-3px); }
  to   { opacity: 1; transform: none; }
}
</style>

<script>
window.VoiceDictate = (function () {
  // Module-scoped Vosk model — loaded once even if many fields use the widget.
  let vModel   = null;
  let vLoading = false;
  let vFailed  = null;
  let active   = null;  // currently-running session

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
  }

  function setStatus(el, html, kind) {
    if (!el) return;
    el.innerHTML = html;
    el.classList.add('visible');
    el.classList.remove('success', 'error');
    if (kind) el.classList.add(kind);
  }
  function hideStatus(el, ms) {
    if (!el) return;
    setTimeout(() => el.classList.remove('visible'), ms || 0);
  }

  async function ensureModel(statusEl) {
    if (vModel)  return true;
    if (vFailed) return false;
    if (vLoading) {
      while (vLoading) await new Promise(r => setTimeout(r, 200));
      return !!vModel;
    }
    vLoading = true;
    setStatus(statusEl, '⏳ Téléchargement du modèle vocal français (~46&nbsp;Mo, mis en cache ensuite)…');
    try {
      if (typeof Vosk === 'undefined') throw new Error('Bibliothèque Vosk non chargée (vérifiez votre connexion).');
      vModel = await Vosk.createModel('https://ccoreilly.github.io/vosk-browser/models/vosk-model-small-fr-pguyot-0.3.tar.gz');
      return true;
    } catch (e) {
      vFailed = e;
      setStatus(statusEl, '❌ Modèle vocal indisponible : ' + escapeHtml(e.message), 'error');
      return false;
    } finally {
      vLoading = false;
    }
  }

  async function start(textarea, btn, statusEl, opts) {
    if (!await ensureModel(statusEl)) return;

    let stream;
    try {
      // Chrome's audio DSP: echo cancellation, noise suppression, auto-gain.
      // AGC in particular boosts quiet voices a lot — critical for Vosk accuracy.
      stream = await navigator.mediaDevices.getUserMedia({
        audio: {
          echoCancellation: true,
          noiseSuppression: true,
          autoGainControl:  true,
          channelCount: 1,
        }
      });
    } catch (e) {
      setStatus(statusEl, '❌ Permission micro refusée. Cliquez sur 🔒 dans la barre d\'adresse → Microphone → Autoriser.', 'error');
      return;
    }

    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    if (ctx.state === 'suspended') { try { await ctx.resume(); } catch (e) {} }

    const recognizer = new vModel.KaldiRecognizer(ctx.sampleRate);
    recognizer.setWords(true);

    let finalText = '';

    recognizer.on('result', (msg) => {
      const t = ((msg && msg.result && msg.result.text) || '').trim();
      if (t) {
        finalText = (finalText ? finalText + ' ' : '') + t;
        setStatus(statusEl, '🎙️ Capté : <span class="vd-live">' + escapeHtml(finalText) + '</span>');
      }
    });
    recognizer.on('partialresult', (msg) => {
      const p = ((msg && msg.result && msg.result.partial) || '').trim();
      const live = (finalText ? finalText + ' ' : '') + p;
      if (live) setStatus(statusEl, '🎙️ J\'écoute : <span class="vd-live">' + escapeHtml(live) + '…</span>');
    });

    const src  = ctx.createMediaStreamSource(stream);
    const node = ctx.createScriptProcessor(4096, 1, 1);
    node.onaudioprocess = (e) => {
      try { recognizer.acceptWaveform(e.inputBuffer); } catch (err) { /* skip bad chunk */ }
    };
    src.connect(node);
    node.connect(ctx.destination);

    active = {
      ctx, src, node, recognizer, stream, statusEl, btn, textarea, opts,
      getText: () => finalText
    };

    btn.classList.add('recording');
    btn.dataset.label = btn.innerHTML;
    btn.innerHTML = '⏹ Stop';
    setStatus(statusEl, '🎙️ J\'écoute… parlez librement, cliquez sur ⏹ pour insérer.');
  }

  async function stop() {
    if (!active) return;
    const r = active;
    active = null;

    // Wait briefly so the last audio chunks reach the recognizer.
    await new Promise(resolve => setTimeout(resolve, 350));

    try { r.node.onaudioprocess = null; } catch (e) {}
    try { r.src.disconnect(); }            catch (e) {}
    try { r.node.disconnect(); }           catch (e) {}
    try { r.stream.getTracks().forEach(t => t.stop()); } catch (e) {}
    try { r.recognizer.remove(); }         catch (e) {}
    try { await r.ctx.close(); }           catch (e) {}

    r.btn.classList.remove('recording');
    r.btn.innerHTML = r.btn.dataset.label || '🎙️ Dicter';

    const raw = (r.getText() || '').trim();
    if (!raw) {
      setStatus(r.statusEl, '⚠️ Rien n\'a été capté. Réessayez en parlant plus fort, plus près du micro.', 'error');
      hideStatus(r.statusEl, 4000);
      return;
    }

    setStatus(r.statusEl, '✨ Clarification de votre phrase en cours…');
    try {
      const res = await fetch(r.opts.polishUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ text: raw }),
      });
      const j = await res.json();
      if (!j.ok) throw new Error(j.error || 'polish failed');

      const polished = j.polished || raw;
      const cur = (r.textarea.value || '').trim();
      const sep = cur ? ' ' : '';
      r.textarea.value = cur + sep + polished;
      // Notify any listeners (char counters, validators, etc.)
      r.textarea.dispatchEvent(new Event('input',  { bubbles: true }));
      r.textarea.dispatchEvent(new Event('change', { bubbles: true }));
      r.textarea.focus();

      const changes = (j.changes || []).join(' · ');
      const tail = changes ? ' <small style="opacity:.75">(' + escapeHtml(changes) + ')</small>' : '';
      setStatus(r.statusEl, '✓ Inséré : « ' + escapeHtml(polished) + ' »' + tail, 'success');
      hideStatus(r.statusEl, 5000);
    } catch (e) {
      setStatus(r.statusEl, '❌ Erreur de clarification : ' + escapeHtml(e.message), 'error');
    }
  }

  function attachButton(btn, textareaId, opts) {
    if (!btn) return;
    const ta = document.getElementById(textareaId);
    if (!ta) return;
    opts = Object.assign({ polishUrl: 'echome.php' }, opts || {});

    // Find or create a status element under the field.
    let statusEl = ta.parentNode.querySelector('.vd-status[data-vd-for="' + textareaId + '"]');
    if (!statusEl) {
      statusEl = document.createElement('div');
      statusEl.className = 'vd-status';
      statusEl.setAttribute('data-vd-for', textareaId);
      // Insert just after the textarea/input
      ta.parentNode.insertBefore(statusEl, ta.nextSibling);
    }

    btn.addEventListener('click', async () => {
      btn.disabled = true;
      try {
        if (active) await stop();
        else        await start(ta, btn, statusEl, opts);
      } finally {
        btn.disabled = false;
      }
    });
  }

  return { attachButton };
})();
</script>
