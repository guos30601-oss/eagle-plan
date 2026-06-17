(function () {
  const backend = window.EAGLE_BACKEND || {};

  function currentDay() {
    const match = location.pathname.match(/day-(\d{2})\.html$/);
    return match ? Number(match[1]) : null;
  }

  function dayFromHref(href) {
    const match = (href || '').match(/day-(\d{2})\.html/);
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
    if (!day || !task || !backend.save) return;
    const form = new FormData();
    form.append('day', String(day));
    form.append('task', task);
    navigator.sendBeacon?.(backend.save, form) ||
      fetch(backend.save, { method: 'POST', body: form, credentials: 'same-origin' }).catch(() => {});
  }

  function lockUnavailableLinks() {
    const maxDay = Number(backend.maxDay || 45);
    const entitledDays = Number(backend.entitledDays || 45);
    document.querySelectorAll('a[href]').forEach(link => {
      const day = dayFromHref(link.getAttribute('href'));
      if (!day || day <= maxDay) return;
      const outsideEntitlement = day > entitledDays;
      link.href = outsideEntitlement ? (backend.unlockUrl || '/unlock') : '#';
      link.classList.add('eagle-locked-day');
      link.setAttribute('aria-label', outsideEntitlement ? `Day ${day} 需要解锁完整版` : `Day ${day} 将按学习进度开放`);
      if (!outsideEntitlement) {
        link.addEventListener('click', event => event.preventDefault());
      }
      const open = link.querySelector('.open');
      if (open) open.textContent = outsideEntitlement ? '解锁完整版后开放' : '按学习进度开放';
      if (!link.querySelector('.eagle-lock-note')) {
        const note = document.createElement('span');
        note.className = 'eagle-lock-note';
        note.textContent = outsideEntitlement ? '体验版暂未开放' : '还没到这一天';
        link.appendChild(note);
      }
    });
  }

  function injectLockStyles() {
    if (document.getElementById('eagle-backend-lock-style')) return;
    const style = document.createElement('style');
    style.id = 'eagle-backend-lock-style';
    style.textContent = `
      .eagle-locked-day {
        opacity: .58;
        filter: grayscale(.25);
        position: relative;
      }
      .eagle-locked-day::after {
        content: "待解锁";
        position: absolute;
        right: 10px;
        top: 10px;
        border: 1px solid #d8e2f0;
        border-radius: 999px;
        background: #fff;
        color: #33415c;
        font-size: 12px;
        font-weight: 900;
        padding: 4px 8px;
      }
      .eagle-lock-note {
        margin-top: auto;
        color: #8a5a00;
        font-size: 12px;
        font-weight: 900;
      }
    `;
    document.head.appendChild(style);
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

    injectLockStyles();
    lockUnavailableLinks();
  });
})();
