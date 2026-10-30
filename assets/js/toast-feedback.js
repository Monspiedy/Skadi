(() => {
  'use strict';

  const toasts = [...document.querySelectorAll('[data-success-toast]')];
  if (toasts.length === 0) return;

  const dismissToast = (toast) => {
    if (toast.classList.contains('is-leaving')) return;
    toast.classList.add('is-leaving');
    window.setTimeout(() => toast.remove(), 220);
  };

  toasts.forEach((toast) => {
    const dismiss = toast.querySelector('[data-toast-dismiss]');
    dismiss.hidden = false;
    dismiss.addEventListener('click', () => dismissToast(toast));
  });
})();
