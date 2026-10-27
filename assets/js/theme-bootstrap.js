(function () {
  'use strict';

  var root = document.documentElement;
  var allowedThemes = ['skadi', 'dark', 'light'];

  root.classList.remove('no-js');
  root.classList.add('js');

  if (root.dataset.authenticated === 'true') {
    return;
  }

  try {
    var savedTheme = window.localStorage.getItem('skadi-theme');

    if (allowedThemes.indexOf(savedTheme) !== -1) {
      root.dataset.theme = savedTheme;
      root.dataset.bsTheme = savedTheme === 'light' ? 'light' : 'dark';
    }
  } catch (error) {
    // Storage can be unavailable in private or restricted browser contexts.
  }
}());
