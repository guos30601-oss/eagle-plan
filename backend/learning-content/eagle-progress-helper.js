(function () {
  const DAY_MS = 24 * 60 * 60 * 1000;
  const startKey = "eaglePlanStartDate";

  function localDateOnly(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }

  function parseLocalDate(value) {
    if (!value) return localDateOnly(new Date());
    const parts = value.split("-").map(Number);
    return new Date(parts[0], parts[1] - 1, parts[2]);
  }

  function getProfile() {
    try {
      return JSON.parse(localStorage.getItem("eagleStudentProfile") || "null");
    } catch (error) {
      return null;
    }
  }

  function getCurrentDay() {
    const profile = getProfile();
    const saved = profile && profile.startDate ? profile.startDate : localStorage.getItem(startKey);
    const start = parseLocalDate(saved);
    const today = localDateOnly(new Date());
    const diff = Math.floor((today - start) / DAY_MS) + 1;
    return Math.min(45, Math.max(1, diff));
  }

  function decorateCards(currentDay) {
    document.querySelectorAll(".day, .day-card").forEach(card => {
      const href = card.getAttribute("href") || "";
      const dayFromHref = href.match(/day-(\d{2})\.html/);
      const dayFromData = card.dataset.day || card.querySelector(".day-no")?.textContent || card.querySelector("b")?.textContent || "";
      const day = dayFromHref ? Number(dayFromHref[1]) : Number(String(dayFromData).replace(/\D/g, ""));
      if (!day || day > 45) return;
      card.classList.toggle("is-done", day < currentDay);
      card.classList.toggle("is-today", day === currentDay);
      card.classList.toggle("is-locked", day > currentDay);
      if (day > currentDay) {
        card.setAttribute("aria-disabled", "true");
        card.addEventListener("click", event => {
          event.preventDefault();
        });
      }
    });
  }

  function insertContinueButton(currentDay) {
    const wrap = document.querySelector(".wrap") || document.body;
    if (!wrap || document.querySelector(".continue-progress")) return;
    const dayId = String(currentDay).padStart(2, "0");
    const node = document.createElement("div");
    node.className = "continue-progress";
    node.innerHTML = `<div><b>继续昨日进度</b><span> 今天直接回到 Day ${currentDay}，不用重新找入口。</span></div><a href="daily-tasks/day-${dayId}.html">一键直达 Day ${currentDay}</a>`;
    wrap.insertBefore(node, wrap.firstElementChild);
  }

  document.addEventListener("DOMContentLoaded", () => {
    const currentDay = getCurrentDay();
    insertContinueButton(currentDay);
    decorateCards(currentDay);
  });
})();
