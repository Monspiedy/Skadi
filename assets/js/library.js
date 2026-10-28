(() => {
  'use strict';

  document.querySelectorAll('[data-confirm-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const message = form.dataset.confirmForm || 'Continue with this action?';
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  });
})();
