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

  document.querySelectorAll('[data-image-input]').forEach((input) => {
    input.addEventListener('change', () => {
      const files = Array.from(input.files || []);
      const singleTargetId = input.dataset.previewTarget;
      const listTargetId = input.dataset.previewContainer;

      if (singleTargetId) {
        const preview = document.getElementById(singleTargetId);
        if (!preview) {
          return;
        }
        if (preview.dataset.objectUrl) {
          URL.revokeObjectURL(preview.dataset.objectUrl);
        }
        if (files.length === 0 || !files[0].type.startsWith('image/')) {
          preview.hidden = true;
          preview.removeAttribute('src');
          return;
        }
        const objectUrl = URL.createObjectURL(files[0]);
        preview.src = objectUrl;
        preview.dataset.objectUrl = objectUrl;
        preview.hidden = false;
      }

      if (listTargetId) {
        const container = document.getElementById(listTargetId);
        if (!container) {
          return;
        }
        container.querySelectorAll('[data-object-url]').forEach((preview) => {
          URL.revokeObjectURL(preview.dataset.objectUrl);
        });
        container.replaceChildren();
        files.forEach((file) => {
          if (!file.type.startsWith('image/')) {
            return;
          }
          const objectUrl = URL.createObjectURL(file);
          const figure = document.createElement('figure');
          const image = document.createElement('img');
          const caption = document.createElement('figcaption');
          image.src = objectUrl;
          image.alt = '';
          image.dataset.objectUrl = objectUrl;
          caption.textContent = file.name;
          figure.append(image, caption);
          container.append(figure);
        });
      }
    });
  });

  const featuredForm = document.querySelector('[data-featured-form]');
  if (featuredForm) {
    featuredForm.addEventListener('submit', (event) => {
      const selections = Array.from(featuredForm.querySelectorAll('select'))
        .map((select) => select.value)
        .filter(Boolean);
      const error = featuredForm.querySelector('[data-featured-error]');
      if (new Set(selections).size !== selections.length) {
        event.preventDefault();
        error.textContent = 'Choose each featured game only once.';
        error.hidden = false;
      } else {
        error.hidden = true;
      }
    });
  }
})();
