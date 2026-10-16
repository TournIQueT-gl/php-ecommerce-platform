(() => {
  'use strict';

  const root = document.documentElement;
  const ar = root.lang.toLowerCase().startsWith('ar');
  const t = (en, arabic) => ar ? arabic : en;
  root.classList.add('ui-enhanced');

  const escapeText = (value) => String(value ?? '');
  let pendingForm = null;
  let pendingSubmitter = null;
  let pendingPasswordTarget = null;
  let previousFocus = null;

  const modal = document.createElement('dialog');
  modal.className = 'ui-confirm-backdrop';
  modal.setAttribute('aria-labelledby', 'ui-confirm-title');
  modal.setAttribute('aria-describedby', 'ui-confirm-message');
  modal.innerHTML = `
    <div class="ui-confirm">
      <div class="ui-confirm-mark" aria-hidden="true">◇</div>
      <p class="ui-confirm-kicker">CONFIRM ACTION</p>
      <h2 id="ui-confirm-title">Are you sure?</h2>
      <p id="ui-confirm-message"></p>
      <div class="ui-confirm-reauth" hidden>
        <label for="ui-confirm-password">ADMIN PASSWORD</label>
        <input id="ui-confirm-password" type="password" maxlength="256" autocomplete="current-password" placeholder="Current admin password">
        <p class="ui-confirm-error" role="alert"></p>
      </div>
      <div class="ui-confirm-actions">
        <button type="button" class="ui-confirm-cancel">Cancel</button>
        <button type="button" class="ui-confirm-submit">Confirm</button>
      </div>
    </div>`;
  document.body.appendChild(modal);
  if (ar) {
    const strings = {'.ui-confirm-kicker':'تأكيد الإجراء','#ui-confirm-title':'هل أنت متأكد؟','.ui-confirm-reauth label':'كلمة مرور المسؤول','.ui-confirm-cancel':'إلغاء','.ui-confirm-submit':'تأكيد'};
    for (const [selector, translation] of Object.entries(strings)) { const el = modal.querySelector(selector); if (el) el.textContent = translation; }
    const password = modal.querySelector('#ui-confirm-password');
    if (password) password.placeholder = 'كلمة مرور المسؤول الحالية';
  }

  const messageNode = modal.querySelector('#ui-confirm-message');
  const confirmButton = modal.querySelector('.ui-confirm-submit');
  const cancelButton = modal.querySelector('.ui-confirm-cancel');
  const reauthWrap = modal.querySelector('.ui-confirm-reauth');
  const reauthInput = modal.querySelector('#ui-confirm-password');
  const reauthError = modal.querySelector('.ui-confirm-error');
  const dialog = modal.querySelector('.ui-confirm');

  const modalFocusables = () => Array.from(dialog.querySelectorAll(
    'button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),a[href],[tabindex]:not([tabindex="-1"])'
  )).filter((el) => !el.hasAttribute('hidden') && el.getClientRects().length > 0);

  const resetModalFields = () => {
    if (reauthInput) reauthInput.value = '';
    if (reauthError) reauthError.textContent = '';
  };

  const closeModal = () => {
    if (modal.open) modal.close();
    document.body.classList.remove('ui-modal-open');
    resetModalFields();
    const focusTarget = previousFocus;
    pendingForm = null;
    pendingSubmitter = null;
    pendingPasswordTarget = null;
    previousFocus = null;
    if (focusTarget && typeof focusTarget.focus === 'function') focusTarget.focus();
  };

  const openModal = (form, submitter, message, tone, passwordTarget) => {
    pendingForm = form;
    pendingSubmitter = submitter || null;
    pendingPasswordTarget = passwordTarget || null;
    previousFocus = document.activeElement;
    messageNode.textContent = escapeText(message);
    modal.dataset.tone = tone || 'warning';
    confirmButton.textContent = tone === 'danger' ? t('Confirm destructive action','تأكيد الإجراء النهائي') : 'Confirm';
    const needsPassword = pendingPasswordTarget instanceof HTMLInputElement;
    reauthWrap.hidden = !needsPassword;
    const reauthLabel = reauthWrap?.querySelector('label');
    if (reauthLabel) reauthLabel.textContent = pendingPasswordTarget?.dataset?.adminReauthLabel || t('ADMIN PASSWORD','كلمة مرور المسؤول');
    resetModalFields();
    document.body.classList.add('ui-modal-open');
    if (typeof modal.showModal === 'function') modal.showModal();
    else modal.setAttribute('open', '');
    window.requestAnimationFrame(() => {
      if (needsPassword) reauthInput.focus();
      else cancelButton.focus();
    });
  };

  cancelButton.addEventListener('click', closeModal);
  modal.addEventListener('cancel', (event) => { event.preventDefault(); closeModal(); });
  modal.addEventListener('click', (event) => {
    if (event.target === modal) closeModal();
  });
  document.addEventListener('keydown', (event) => {
    if (!modal.open) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeModal();
      return;
    }
    if (event.key !== 'Tab') return;
    const focusables = modalFocusables();
    if (!focusables.length) {
      event.preventDefault();
      dialog.focus?.();
      return;
    }
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  confirmButton.addEventListener('click', () => {
    if (!pendingForm) return closeModal();

    if (pendingPasswordTarget instanceof HTMLInputElement) {
      const value = reauthInput.value;
      if (!value) {
        reauthError.textContent = t('Enter your current admin password.','أدخل كلمة مرور المسؤول الحالية.');
        reauthInput.focus();
        return;
      }
      pendingPasswordTarget.value = value;
    }

    const form = pendingForm;
    const submitter = pendingSubmitter;
    form.dataset.uiConfirmed = '1';
    closeModal();
    if (typeof form.requestSubmit === 'function') form.requestSubmit(submitter || undefined);
    else form.submit();
  });

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    const submitter = event.submitter instanceof HTMLElement ? event.submitter : form.querySelector('[type="submit"]');
    let passwordTarget = form.querySelector('input[name="admin_password"]');
    const submitterRequiresReauth = submitter?.dataset?.requireReauth === '1';
    if (submitterRequiresReauth && !(passwordTarget instanceof HTMLInputElement)) {
      passwordTarget = document.createElement('input');
      passwordTarget.type = 'hidden';
      passwordTarget.name = 'admin_password';
      passwordTarget.dataset.adminReauthRequired = '1';
      form.appendChild(passwordTarget);
    }
    const passwordIsVisible = passwordTarget instanceof HTMLInputElement && passwordTarget.type !== 'hidden';
    const passwordAlreadyEntered = passwordIsVisible && passwordTarget.value.trim() !== '';
    const declaredReauth = passwordTarget instanceof HTMLInputElement && passwordTarget.dataset.adminReauthRequired === '1';
    const needsReauth = passwordTarget instanceof HTMLInputElement && !passwordAlreadyEntered && (submitterRequiresReauth || declaredReauth || passwordIsVisible);
    const submitConfirm = submitter?.dataset?.confirm || '';
    const needsConfirmation = Boolean(submitConfirm || form.dataset.confirm);

    if ((needsConfirmation || needsReauth) && form.dataset.uiConfirmed !== '1') {
      event.preventDefault();
      const message = submitConfirm || form.dataset.confirm || t('Confirm this action with your current admin password.','أكّد الإجراء بكلمة مرور المسؤول الحالية.');
      const tone = submitter?.dataset?.confirmTone || form.dataset.confirmTone || 'warning';
      openModal(form, submitter, message, tone, needsReauth ? passwordTarget : null);
      return;
    }

    delete form.dataset.uiConfirmed;
    // AJAX forms own their loading/disabled state. Disabling an unnamed
    // submitter here (during capture) prevents an AJAX form's bubble-phase
    // handler from submitting its request at all. Confirmation and reauth
    // above still apply to these forms.
    if (form.hasAttribute('data-ui-async-submit')) return;
    if (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement) {
      // A named submit button carries business data (for example action=delete or
      // decision=reject). Disabling it inside the submit event can remove that
      // name/value from the browser's form payload. Keep named submitters enabled
      // but interaction-locked by the loading class; unnamed submitters can be
      // disabled normally.
      if (!submitter.name) submitter.disabled = true;
      submitter.classList.add('is-loading');
      submitter.setAttribute('aria-busy', 'true');
    }
  }, true);

  const localDateFormatter = new Intl.DateTimeFormat(undefined, {
    year: 'numeric', month: 'short', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
    timeZoneName: 'short'
  });

  const enhanceUi = (scope = document) => {
    scope.querySelectorAll('img[data-ui-image-fallback-source]').forEach((img) => {
      if (!(img instanceof HTMLImageElement) || img.dataset.uiImageBound === '1') return;
      img.dataset.uiImageBound = '1';
      const fallback = img.nextElementSibling;
      const fail = () => {
        img.hidden = true;
        if (fallback instanceof HTMLElement && fallback.matches('[data-ui-image-fallback]')) fallback.hidden = false;
      };
      img.addEventListener('error', fail, { once: true });
      if (img.complete && img.naturalWidth === 0) fail();
    });

    scope.querySelectorAll('.admin-alert, .dashboard-alert').forEach((alert) => {
      if (alert.dataset.uiAlertBound === '1') return;
      alert.dataset.uiAlertBound = '1';
      if (!alert.querySelector('.ui-alert-close')) {
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'ui-alert-close';
        close.setAttribute('aria-label', t('Dismiss message','إغلاق الرسالة'));
        close.textContent = '×';
        close.addEventListener('click', () => {
          alert.classList.add('is-hiding');
          window.setTimeout(() => alert.remove(), 180);
        });
        alert.appendChild(close);
      }
      if (alert.classList.contains('success')) {
        window.setTimeout(() => {
          if (!alert.isConnected || alert.matches(':hover, :focus-within')) return;
          alert.classList.add('is-hiding');
          window.setTimeout(() => alert.remove(), 180);
        }, 5500);
      }
    });

    scope.querySelectorAll('[data-copy]').forEach((button) => {
      if (button.dataset.uiCopyBound === '1') return;
      button.dataset.uiCopyBound = '1';
      button.addEventListener('click', async () => {
        const value = button.getAttribute('data-copy') || '';
        if (!value) return;
        const original = button.textContent;
        try {
          if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(value);
          else {
            const area = document.createElement('textarea');
            area.value = value;
            area.setAttribute('readonly', '');
            area.style.position = 'absolute';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
          }
          button.textContent = t('Copied','تم النسخ');
        } catch (_) {
          button.textContent = t('Copy failed','تعذّر النسخ');
        }
        window.setTimeout(() => { button.textContent = original; }, 1400);
      });
    });

    scope.querySelectorAll('[data-local-epoch]').forEach((node) => {
      if (node.dataset.localized === '1') return;
      const epoch = Number(node.getAttribute('data-local-epoch'));
      if (!Number.isFinite(epoch) || epoch <= 0) return;
      try {
        node.textContent = localDateFormatter.format(new Date(epoch * 1000));
        node.dataset.localized = '1';
      } catch (_) {}
    });
  };

  window.AuraaUI = Object.assign(window.AuraaUI || {}, { enhance: enhanceUi });
  enhanceUi(document);
  document.addEventListener('auraa:content-updated', (event) => {
    const target = event.detail?.root instanceof Element ? event.detail.root : document;
    enhanceUi(target);
  });
})();
