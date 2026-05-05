<?php
// Pro confirmation modal — single instance per page.
//
// Usage on any element:
//   <form data-confirm="Supprimer ce guide ?"
//         data-confirm-type="danger"
//         data-confirm-title="Supprimer ce guide ?"
//         data-confirm-action="Oui, supprimer">
//     ...
//   </form>
//
//   <button data-confirm="Refuser cette demande ?"
//           data-confirm-type="danger"
//           data-confirm-action="Refuser">Refuser</button>
//
// Supported types: danger | warning | info | success
?>
<div class="ph-confirm" id="phConfirm" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="phConfirmTitle">
  <div class="ph-confirm-backdrop" data-ph-confirm-cancel></div>
  <div class="ph-confirm-card">
    <div class="ph-confirm-icon" id="phConfirmIcon" aria-hidden="true">⚠️</div>
    <h3 class="ph-confirm-title" id="phConfirmTitle">Confirmer l'action</h3>
    <p class="ph-confirm-text" id="phConfirmText">Cette action est définitive. Voulez-vous continuer ?</p>
    <div class="ph-confirm-actions">
      <button type="button" class="ph-confirm-btn ph-confirm-cancel" data-ph-confirm-cancel>Annuler</button>
      <button type="button" class="ph-confirm-btn ph-confirm-ok" id="phConfirmOk">Confirmer</button>
    </div>
  </div>
</div>

<style>
  .ph-confirm {
    position: fixed;
    inset: 0;
    z-index: 100000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    font-family: 'Plus Jakarta Sans', 'Outfit', system-ui, sans-serif;
  }
  .ph-confirm.is-open { display: flex; }

  .ph-confirm-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 22, 41, .55);
    -webkit-backdrop-filter: blur(8px);
    backdrop-filter: blur(8px);
    animation: ph-confirm-fade .25s ease both;
  }

  .ph-confirm-card {
    position: relative;
    max-width: 440px;
    width: 100%;
    background: #ffffff;
    border-radius: 20px;
    padding: 32px 28px 24px;
    box-shadow:
      0 30px 70px rgba(15,22,41,.32),
      0 6px 16px rgba(0,0,0,.10);
    text-align: center;
    animation: ph-confirm-pop .45s cubic-bezier(.18,1.25,.4,1) both;
  }

  .ph-confirm-icon {
    font-size: 3rem;
    line-height: 1;
    margin-bottom: 10px;
    animation: ph-confirm-bump .65s cubic-bezier(.18,1.6,.4,1) .08s both;
  }
  .ph-confirm-title {
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f1629;
    margin: 0 0 8px;
    letter-spacing: -.005em;
  }
  .ph-confirm-text {
    font-size: .94rem;
    line-height: 1.55;
    color: #6b7a9f;
    margin: 0 0 24px;
    font-weight: 500;
  }

  .ph-confirm-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
  }
  .ph-confirm-btn {
    flex: 1;
    max-width: 170px;
    padding: 12px 18px;
    border-radius: 11px;
    border: 0;
    font-family: inherit;
    font-size: .92rem;
    font-weight: 700;
    cursor: pointer;
    transition: transform .18s cubic-bezier(.2,.9,.3,1),
                box-shadow .25s cubic-bezier(.2,.9,.3,1),
                background-color .2s;
  }
  .ph-confirm-cancel {
    background: #eef0fb;
    color: #5046e5;
  }
  .ph-confirm-cancel:hover { background: #e2e8f8; transform: translateY(-1px); }

  /* Default action button — danger style */
  .ph-confirm-ok {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #fff;
    box-shadow: 0 6px 18px rgba(239,68,68,.38);
  }
  .ph-confirm-ok:hover { transform: translateY(-1px); box-shadow: 0 10px 26px rgba(239,68,68,.48); }
  .ph-confirm-ok:active { transform: translateY(0); }

  /* Type variants on the modal root */
  .ph-confirm[data-type="warning"] .ph-confirm-ok {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    box-shadow: 0 6px 18px rgba(245,158,11,.38);
  }
  .ph-confirm[data-type="warning"] .ph-confirm-ok:hover { box-shadow: 0 10px 26px rgba(245,158,11,.48); }

  .ph-confirm[data-type="info"] .ph-confirm-ok {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    box-shadow: 0 6px 18px rgba(59,130,246,.38);
  }
  .ph-confirm[data-type="info"] .ph-confirm-ok:hover { box-shadow: 0 10px 26px rgba(59,130,246,.48); }

  .ph-confirm[data-type="success"] .ph-confirm-ok {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    box-shadow: 0 6px 18px rgba(16,185,129,.38);
  }
  .ph-confirm[data-type="success"] .ph-confirm-ok:hover { box-shadow: 0 10px 26px rgba(16,185,129,.48); }

  /* Closing animation */
  .ph-confirm.is-closing .ph-confirm-card { animation: ph-confirm-pop-out .22s cubic-bezier(.4,0,1,1) forwards; }
  .ph-confirm.is-closing .ph-confirm-backdrop { animation: ph-confirm-fade-out .22s ease forwards; }

  @keyframes ph-confirm-fade {
    from { opacity: 0; }
    to   { opacity: 1; }
  }
  @keyframes ph-confirm-fade-out {
    to { opacity: 0; }
  }
  @keyframes ph-confirm-pop {
    0%   { opacity: 0; transform: scale(.85) translateY(20px); }
    60%  { opacity: 1; transform: scale(1.015) translateY(-3px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
  }
  @keyframes ph-confirm-pop-out {
    to { opacity: 0; transform: scale(.92) translateY(8px); }
  }
  @keyframes ph-confirm-bump {
    0%   { opacity: 0; transform: scale(.4) rotate(-22deg); }
    100% { opacity: 1; transform: scale(1)   rotate(0); }
  }

  @media (max-width: 480px) {
    .ph-confirm-card { padding: 26px 22px 20px; }
    .ph-confirm-actions { flex-direction: column; gap: 8px; }
    .ph-confirm-btn { max-width: none; width: 100%; }
  }

  @media (prefers-reduced-motion: reduce) {
    .ph-confirm-card, .ph-confirm-icon, .ph-confirm-backdrop, .ph-confirm.is-closing .ph-confirm-card, .ph-confirm.is-closing .ph-confirm-backdrop {
      animation: none !important;
    }
  }
</style>

<script>
  (function () {
    var modal   = document.getElementById('phConfirm');
    if (!modal) return;
    var titleEl = document.getElementById('phConfirmTitle');
    var textEl  = document.getElementById('phConfirmText');
    var iconEl  = document.getElementById('phConfirmIcon');
    var okBtn   = document.getElementById('phConfirmOk');

    var ICONS = { danger: '🗑️', warning: '⚠️', info: 'ℹ️', success: '✅' };
    var DEFAULT_TITLES = {
      danger:  'Confirmer la suppression',
      warning: "Confirmer l'action",
      info:    'Confirmer',
      success: 'Confirmer'
    };

    var pending = null;

    function open(payload) {
      var type = (payload.type || 'danger').toLowerCase();
      modal.dataset.type     = type;
      iconEl.textContent     = payload.icon  || ICONS[type] || ICONS.danger;
      titleEl.textContent    = payload.title || DEFAULT_TITLES[type] || DEFAULT_TITLES.danger;
      textEl.textContent     = payload.text  || '';
      okBtn.textContent      = payload.actionLabel || 'Confirmer';
      pending = payload;
      modal.classList.remove('is-closing');
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      // Focus the cancel button for safer keyboard default
      setTimeout(function () {
        var cancel = modal.querySelector('.ph-confirm-cancel');
        if (cancel) cancel.focus();
      }, 50);
    }

    function close() {
      if (!modal.classList.contains('is-open')) return;
      modal.classList.add('is-closing');
      setTimeout(function () {
        modal.classList.remove('is-open', 'is-closing');
        modal.setAttribute('aria-hidden', 'true');
        pending = null;
      }, 220);
    }

    function commit() {
      if (!pending) return;
      var p = pending;
      // Hide modal immediately (no exit animation when proceeding, looks snappier)
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      pending = null;

      if (p.kind === 'form' && p.el) {
        // Carry the clicked button's name/value if any (for forms with multiple submits)
        if (p.buttonName) {
          var hidden = document.createElement('input');
          hidden.type  = 'hidden';
          hidden.name  = p.buttonName;
          hidden.value = p.buttonValue || '';
          p.el.appendChild(hidden);
        }
        p.el.dataset.phConfirmed = 'yes';
        p.el.submit();
      } else if (p.kind === 'link' && p.el) {
        p.el.dataset.phConfirmed = 'yes';
        p.el.click();
      }
    }

    function payloadFromEl(el, kind, formEl, buttonName, buttonValue) {
      return {
        kind: kind,
        el: kind === 'form' ? formEl : el,
        type:  el.dataset.confirmType,
        title: el.dataset.confirmTitle,
        text:  el.dataset.confirm,
        actionLabel: el.dataset.confirmAction,
        icon:  el.dataset.confirmIcon,
        buttonName:  buttonName,
        buttonValue: buttonValue
      };
    }

    // Hook forms with [data-confirm]
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (form.dataset.phConfirmed === 'yes') return;
        e.preventDefault();
        open(payloadFromEl(form, 'form', form));
      });
    });

    // Hook submit buttons inside forms with [data-confirm] on the button itself
    document.querySelectorAll('button[data-confirm]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        if (btn.dataset.phConfirmed === 'yes') return;
        var form = btn.closest('form');
        if (!form) return;
        e.preventDefault();
        open(payloadFromEl(btn, 'form', form, btn.name || '', btn.value || ''));
      });
    });

    // Hook anchors with [data-confirm]
    document.querySelectorAll('a[data-confirm]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        if (a.dataset.phConfirmed === 'yes') return;
        e.preventDefault();
        open(payloadFromEl(a, 'link'));
      });
    });

    okBtn.addEventListener('click', commit);
    modal.querySelectorAll('[data-ph-confirm-cancel]').forEach(function (el) {
      el.addEventListener('click', close);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
      if (e.key === 'Enter'  && modal.classList.contains('is-open')) { e.preventDefault(); commit(); }
    });
  })();
</script>
