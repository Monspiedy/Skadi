(function () {
  'use strict';

  var selector = document.querySelector('[data-theme-selector]');

  if (!selector) {
    return;
  }

  var root = document.documentElement;
  var status = document.querySelector('[data-theme-status]');
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var isAuthenticated = root.dataset.authenticated === 'true';
  var previousTheme = root.dataset.theme || 'skadi';

  function applyTheme(theme) {
    root.dataset.theme = theme;
    root.dataset.bsTheme = theme === 'light' ? 'light' : 'dark';
    selector.value = theme;

    var themeColor = document.querySelector('meta[name="theme-color"]');

    if (themeColor) {
      themeColor.content = theme === 'light' ? '#eef5f8' : (theme === 'dark' ? '#15171b' : '#081522');
    }
  }

  function announce(message) {
    if (status) {
      status.textContent = message;
    }
  }

  applyTheme(root.dataset.theme || 'skadi');

  selector.addEventListener('change', function () {
    var selectedTheme = selector.value;
    applyTheme(selectedTheme);

    try {
      window.localStorage.setItem('skadi-theme', selectedTheme);
    } catch (error) {
      announce('Theme applied for this page. Browser storage is unavailable.');
    }

    if (!isAuthenticated) {
      previousTheme = selectedTheme;
      announce(selectedTheme + ' theme selected.');
      return;
    }

    selector.disabled = true;
    announce('Saving theme.');

    window.fetch(selector.dataset.saveUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfMeta ? csrfMeta.content : ''
      },
      body: JSON.stringify({ theme: selectedTheme })
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('Theme save failed.');
      }

      return response.json();
    }).then(function (result) {
      previousTheme = result.theme;
      announce(result.theme + ' theme saved.');
    }).catch(function () {
      applyTheme(previousTheme);
      announce('Theme was not saved. The previous theme has been restored.');
    }).finally(function () {
      selector.disabled = false;
      selector.focus();
    });
  });
}());
