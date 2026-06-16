(function () {
  function currentDay() {
    const match = location.pathname.match(/day-(\d{2})\.html$/);
    return match ? Number(match[1]) : null;
  }

  function inferTask() {
    const path = location.pathname;
    if (path.includes('/vocab-days/')) return 'vocab';
    if (path.includes('/lecture-days/')) return 'lecture';
    if (path.includes('/workbook-days/')) return 'workbook';
    if (path.includes('/test-days/')) return 'test';
    return '';
  }

  function mark(task) {
    const day = currentDay();
    if (!day || !task || !window.EAGLE_BACKEND) return;
    const form = new FormData();
    form.append('day', String(day));
    form.append('task', task);
    navigator.sendBeacon?.(window.EAGLE_BACKEND.save, form) ||
      fetch(window.EAGLE_BACKEND.save, { method: 'POST', body: form, credentials: 'same-origin' }).catch(() => {});
  }

  document.addEventListener('DOMContentLoaded', () => {
    const task = inferTask();
    if (task) {
      mark(task);
    }

    document.querySelectorAll('a[href]').forEach(link => {
      const href = link.getAttribute('href') || '';
      if (/^https?:|^mailto:|^tel:|^#/.test(href)) return;
      if (href.startsWith('/')) return;
      if (href.includes('../')) return;
      link.setAttribute('href', href.replace(/^\.?\//, ''));
    });
  });
})();
