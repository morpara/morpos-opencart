/*! morpos-toast – tiny toast helper (no deps) */
(() => {
  const PRIMARY = "#7c3aed";
  const PRESETS = {
    default: PRIMARY,
    success: "#16a34a",
    danger: "#dc2626",
    warning: "#d97706",
    info: "#2563eb",
  };
  const POSITIONS = new Set([
    "top-right", "top-left", "bottom-right", "bottom-left", "top-center", "bottom-center"
  ]);
  const containers = new Map();

  function getContainer(position) {
    if (!containers.has(position)) {
      const div = document.createElement("div");
      div.className = `morpos-container ${position}`;
      document.body.appendChild(div);
      containers.set(position, div);
    }
    return containers.get(position);
  }

  // simple YIQ contrast on hex colors
  function contrastOn(hex) {
    const h = (hex || "").replace("#", "");
    if (h.length !== 6) return "#ffffff";
    const r = parseInt(h.slice(0, 2), 16), g = parseInt(h.slice(2, 4), 16), b = parseInt(h.slice(4, 6), 16);
    return (r * 299 + g * 587 + b * 114) / 1000 >= 140 ? "#111827" : "#ffffff";
  }

  function escapeHTML(s = "") {
    return s
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function toast(opts = {}) {
    const {
      title = "Done!",
      description = "",
      duration = 3000,
      position = "top-right",
      color = "default", // "success" | "danger" | "warning" | "info" | any CSS color
    } = opts;

    const pos = POSITIONS.has(position) ? position : "top-right";
    const bg = PRESETS[color] || color || PRIMARY;

    // If bg is hex, compute text color; otherwise default to white
    const useHex = /^#([0-9a-f]{3}){1,2}$/i.test(bg);
    const textColor = useHex ? contrastOn(bg) : "#ffffff";

    const el = document.createElement("div");
    el.className = "morpos-toast morpos-enter";
    el.setAttribute("role", "status");
    el.setAttribute("aria-live", "polite");
    el.style.background = `linear-gradient(0deg, ${bg}cc, ${bg}cc)`;
    el.style.color = textColor;

    el.innerHTML = `
      <div class="morpos-accent" style="color:${bg}"></div>
      <div class="morpos-content">
        <div class="morpos-title">${escapeHTML(title)}</div>
        ${description ? `<div class="morpos-desc">${escapeHTML(description)}</div>` : ""}
      </div>
      <button class="morpos-close" aria-label="Close" title="Close">&times;</button>
      <div class="morpos-progress"></div>
    `;

    const container = getContainer(pos);
    container.prepend(el); // newest on top

    const progress = el.querySelector(".morpos-progress");
    const closeBtn = el.querySelector(".morpos-close");

    // Close with reliable exit animation
    let rafId = null, paused = false;
    const total = Math.max(0, +duration || 0);
    let startTime = performance.now();

    const reallyRemove = () => { if (el.isConnected) el.remove(); };

    const close = () => {
      if (!el.isConnected) return;
      // cancel progress loop
      if (rafId) cancelAnimationFrame(rafId);
      // ensure the browser registers a class change -> reflow + next frame
      el.classList.remove("morpos-enter");
      // force reflow
      void el.offsetWidth;
      requestAnimationFrame(() => {
        el.classList.add("morpos-leave");
        // remove after animation completes (fallback timer too)
        const onEnd = () => { el.removeEventListener("animationend", onEnd); reallyRemove(); };
        el.addEventListener("animationend", onEnd, { once: true });
        setTimeout(reallyRemove, 400); // fallback in case animationend is missed
      });
    };

    closeBtn?.addEventListener("click", close);

    // Progress via rAF
    const tick = (now) => {
      if (paused || total === 0) { rafId = requestAnimationFrame(tick); return; }
      const elapsed = now - startTime;
      const left = Math.max(0, 1 - elapsed / total);
      if (progress) progress.style.transform = `scaleX(${left})`;
      if (elapsed >= total) { close(); return; }
      rafId = requestAnimationFrame(tick);
    };
    if (total) rafId = requestAnimationFrame(tick);

    // Pause on hover/focus (and keep progress accurate on resume)
    const setPaused = (v) => {
      if (paused === v) return;
      paused = v;
      if (!paused) {
        const scale = parseFloat((progress?.style.transform || "scaleX(1)").match(/scaleX\((.*)\)/)?.[1] || "1");
        startTime = performance.now() - (total * (1 - scale));
      }
    };
    el.addEventListener("mouseenter", () => setPaused(true));
    el.addEventListener("mouseleave", () => setPaused(false));
    el.addEventListener("focusin", () => setPaused(true));
    el.addEventListener("focusout", () => setPaused(false));

    return { close, el };
  }

  // expose
  window.toast = toast;
})();
