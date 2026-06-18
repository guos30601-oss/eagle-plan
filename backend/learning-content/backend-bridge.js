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

  function dayFromScore(item) {
    const text = `${item.dayLabel || ''} ${item.label || ''} ${item.id || ''}`;
    const match = text.match(/day[-\s]*(\d+)/i);
    return match ? Number(match[1]) : currentDay();
  }

  function syncTestScoreMistakes(value) {
    let scores = [];
    try {
      scores = JSON.parse(value || '[]');
    } catch (error) {
      return;
    }
    const latest = scores[scores.length - 1];
    if (!latest || Number(latest.wrong || latest.wrongCount || 0) <= 0) return;
    const day = dayFromScore(latest);
    const id = `daily-test-${latest.id || `day-${String(day || 0).padStart(2, '0')}`}`;
    let mistakes = [];
    try {
      mistakes = JSON.parse(localStorage.getItem('eagleMistakePool') || '[]');
    } catch (error) {}
    const next = mistakes.filter(item => item.id !== id);
    next.unshift({
      id,
      day,
      date: latest.dayLabel || (day ? `Day ${day}` : '日测'),
      source: '每日一测',
      qtype: latest.label || '日测错题',
      knowledge: latest.note || '日测待复盘',
      question: `${latest.wrong || latest.wrongCount} 道题需要回看`,
      yourAnswer: '',
      answer: '查看当天测试解析',
      reason: latest.note || '待复盘',
      remedy: day ? `回看 Day ${day} 测试解析，订正后标记已攻克。` : '回看当天测试解析，订正后标记已攻克。',
      status: '待补救',
      retest: day ? `test-days/day-${String(day).padStart(2, '0')}.html` : 'eagle-test-pack.html',
      savedAt: latest.savedAt || new Date().toISOString()
    });
    localStorage.setItem('eagleMistakePool', JSON.stringify(next.slice(0, 500)));
  }

  function patchLocalProgressStorage() {
    if (!window.localStorage || localStorage.__eaglePatched) return;
    const originalSetItem = localStorage.setItem.bind(localStorage);
    localStorage.setItem = function (key, value) {
      const result = originalSetItem(key, value);
      if (key === 'eagleTestScores') syncTestScoreMistakes(value);
      return result;
    };
    localStorage.__eaglePatched = true;
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
    patchLocalProgressStorage();
  });
})();
