/* One consistent SweetAlert2 presentation for server feedback, validation and confirmations. */
(() => {
  'use strict';
  const ready = () => {
    const swal = window.Swal;
    if (!swal || typeof swal.fire !== 'function') return; // Preserve visible messages if library cannot load.
    const theme = { confirmButtonColor: '#151515', cancelButtonColor: '#888888',
      background: '#fff', color: '#171717', buttonsStyling: true };
    const notices = [...document.querySelectorAll('.auth-alert, .sp-message, .notice[role="status"], .notice[role="alert"], .alert[role="alert"], .public-courses-message-error, .apply-note-error')];
    const messages = notices.map(el => {
      const value = (el.textContent || '').trim();
      const isError = el.getAttribute('role') === 'alert' || el.classList.contains('error') || el.classList.contains('sp-message-error') || el.classList.contains('public-courses-message-error') || el.classList.contains('apply-note-error');
      return { value, isError };
    }).filter(item => item.value);
    if (messages.length) {
      notices.forEach(node => { if (node.textContent.trim()) node.remove(); });
      (async () => { for (const item of messages) {
        await swal.fire({ ...theme, icon: item.isError ? 'error' : 'success',
          title: item.isError ? 'Please check' : 'Success', text: item.value });
      } })();
    }
    document.addEventListener('submit', event => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement) || form.dataset.lmsConfirmed === 'true') return;
      const message = event.submitter?.dataset.confirm || form.dataset.confirm;
      if (!message) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      const submitter = event.submitter;
      void swal.fire({ ...theme, icon: 'question', title: 'Confirm action', text: message,
        showCancelButton: true, confirmButtonText: 'Confirm', cancelButtonText: 'Cancel', reverseButtons: true })
        .then(result => {
          if (!result.isConfirmed) return;
          form.dataset.lmsConfirmed = 'true';
          try { form.requestSubmit(submitter || undefined); }
          finally { delete form.dataset.lmsConfirmed; }
        });
    }, true);
    let invalidOpen = false;
    document.addEventListener('invalid', event => {
      const field = event.target;
      if (!(field instanceof HTMLElement) || !field.closest('form')) return;
      event.preventDefault();
      if (invalidOpen) return;
      invalidOpen = true;
      const label = field.labels?.[0]?.textContent?.trim()?.replace(/\s+/g, ' ') || 'This field';
      void swal.fire({ ...theme, icon: 'warning', title: 'Complete the form',
        text: field.validity?.valueMissing ? `${label} is required.` : `Please check ${label}.` })
        .then(() => { invalidOpen = false; field.focus(); });
    }, true);
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
  else ready();
})();
