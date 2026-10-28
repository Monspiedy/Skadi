(() => {
  'use strict';

  const countdown = document.querySelector('[data-sale-countdown]');
  if (!countdown) {
    return;
  }

  const label = countdown.querySelector('[data-sale-countdown-label]');
  const target = Date.parse(countdown.dataset.targetTime || '');
  const active = countdown.dataset.saleActive === 'true';
  if (!label || !Number.isFinite(target)) {
    return;
  }

  const render = () => {
    const remainingSeconds = Math.max(0, Math.floor((target - Date.now()) / 1000));
    if (remainingSeconds === 0) {
      label.textContent = active ? 'Weekend sale has ended. Refresh for current prices.' : 'Weekend sale is starting. Refresh for current prices.';
      return false;
    }

    const days = Math.floor(remainingSeconds / 86400);
    const hours = Math.floor((remainingSeconds % 86400) / 3600);
    const minutes = Math.floor((remainingSeconds % 3600) / 60);
    const seconds = remainingSeconds % 60;
    const prefix = active ? 'Sale ends in' : 'Sale starts in';
    label.textContent = `${prefix} ${days}d ${hours}h ${minutes}m ${seconds}s`;
    return true;
  };

  if (render()) {
    const timer = window.setInterval(() => {
      if (!render()) {
        window.clearInterval(timer);
      }
    }, 1000);
  }
})();
