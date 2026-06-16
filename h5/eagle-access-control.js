(function () {
  const PLAN_KEY = "eagleAccessPlan";
  const UNLOCK_CODE = "EAGLE299";
  const TRIAL_DAYS = 3;

  function currentPlan() {
    return localStorage.getItem(PLAN_KEY) || "trial";
  }

  function isFull() {
    return currentPlan() === "full";
  }

  function dayFromPath(pathname) {
    const match = pathname.match(/(?:daily-tasks|vocab-days|lecture-days|workbook-days|test-days)\/day-(\d{2})\.html$/);
    return match ? Number(match[1]) : null;
  }

  function rootPrefix() {
    return location.pathname.includes("/") && /\/(?:daily-tasks|vocab-days|lecture-days|workbook-days|test-days)\//.test(location.pathname) ? "../" : "";
  }

  function goPaywall(day) {
    const target = `${rootPrefix()}eagle-paywall.html${day ? `?day=${String(day).padStart(2, "0")}` : ""}`;
    location.replace(target);
  }

  function protectDayPage() {
    const day = dayFromPath(location.pathname.replace(/\\/g, "/"));
    if (day && day > TRIAL_DAYS && !isFull()) {
      goPaywall(day);
    }
  }

  function softenLockedLinks() {
    if (isFull()) return;
    document.querySelectorAll("a[href]").forEach(link => {
      const href = link.getAttribute("href") || "";
      const match = href.match(/(?:daily-tasks|vocab-days|lecture-days|workbook-days|test-days)\/day-(\d{2})\.html|day-(\d{2})\.html/);
      const day = match ? Number(match[1] || match[2]) : null;
      if (!day || day <= TRIAL_DAYS) return;
      link.addEventListener("click", event => {
        event.preventDefault();
        goPaywall(day);
      });
      link.classList.add("eagle-trial-locked-link");
      if (!link.querySelector(".eagle-soft-lock")) {
        const lock = document.createElement("span");
        lock.className = "eagle-soft-lock";
        lock.textContent = "待解锁";
        link.appendChild(lock);
      }
    });
  }

  function injectAccessBar() {
    const file = location.pathname.split("/").pop() || "index.html";
    if (file !== "index.html") return;
    if (document.querySelector(".eagle-access-bar")) return;

    const bar = document.createElement("div");
    bar.className = "eagle-access-bar";
    bar.innerHTML = isFull()
      ? `<strong>完整版已解锁</strong><span>45 天任务、资料、题库和成长轨迹都已打开。</span><a href="eagle-paywall.html">查看权益</a>`
      : `<strong>体验版</strong><span>先体验 Day1-Day3。觉得适合，再解锁完整 45 天。</span><a href="eagle-paywall.html">解锁完整版</a>`;
    document.body.prepend(bar);
  }

  function injectStyle() {
    if (document.getElementById("eagle-access-style")) return;
    const style = document.createElement("style");
    style.id = "eagle-access-style";
    style.textContent = `
      .eagle-access-bar {
        width: min(1240px, calc(100% - 32px));
        margin: 14px auto 0;
        padding: 10px 14px;
        border: 1px solid rgba(184,134,43,.28);
        border-radius: 8px;
        background: rgba(255,250,240,.86);
        color: #102653;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 10px 24px rgba(78,54,22,.08);
        backdrop-filter: blur(8px);
        font-size: 14px;
      }
      .eagle-access-bar strong { color: #0b3a78; }
      .eagle-access-bar span { flex: 1; color: #665f53; }
      .eagle-access-bar a {
        color: #0b3a78;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
      }
      .eagle-soft-lock {
        display: inline-flex;
        margin-left: 6px;
        padding: 1px 6px;
        border-radius: 999px;
        background: rgba(184,134,43,.14);
        color: #8a641f;
        font-size: 12px;
        font-weight: 700;
        vertical-align: middle;
      }
      @media (max-width: 720px) {
        .eagle-access-bar { align-items: flex-start; flex-direction: column; gap: 4px; }
      }
    `;
    document.head.appendChild(style);
  }

  function registerServiceWorker() {
    if (!("serviceWorker" in navigator)) return;
    if (!/^https?:$/.test(location.protocol)) return;
    navigator.serviceWorker.register(`${rootPrefix()}service-worker.js`).catch(() => {});
  }

  window.EagleAccess = {
    unlock(code) {
      if ((code || "").trim().toUpperCase() !== UNLOCK_CODE) return false;
      localStorage.setItem(PLAN_KEY, "full");
      localStorage.setItem("eagleUnlockAt", new Date().toISOString());
      return true;
    },
    setTrial() {
      localStorage.setItem(PLAN_KEY, "trial");
    },
    plan: currentPlan,
    isFull
  };

  document.addEventListener("DOMContentLoaded", () => {
    injectStyle();
    protectDayPage();
    softenLockedLinks();
    injectAccessBar();
    registerServiceWorker();
  });
})();
