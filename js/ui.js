// DOM interaction, event listeners, drawing
// This file handles all DOM interaction, drawing, and event listeners.

// ----- Imports -----
import {
  handleKey,
  togglePause,
  start,
  resetGame,
  getSettings,
  saveSettings,
  applyTheme,
  getState,
  initAudio,
  playSound,
  ensureAudioReady,
} from "./main.js";

// ----- DOM Element Selection -----
const $ = (sel) => document.querySelector(sel);
export const canvas = $("#game");
export const ctx = canvas.getContext("2d");
const masterVolumeSlider = $("#master-volume");
const sfxVolumeSlider = $("#sfx-volume");
const scoreEl = $("#score");
const bestEl = $("#best");
const speedLabel = $("#speedLabel");
const overlay = $("#overlay");
const gridSel = $("#grid");
const speedSel = $("#speed");
const applesSel = $("#apples");
const wallsSel = $("#walls");
const themeSel = $("#theme");
const applyBtn = $("#applyBtn");
const snakeStyleSel = $("#snakeStyle");
const settingsPanel = $("#settings-panel");
const backBtn = $("#backBtn");
const wrapEl = $(".wrap");
const gameCard = $(".game-card");
const hud = $(".hud-2");
const boardSizeSel = $("#boardSize");
const mobileControls = $("#mobileControls");
const pauseBtn = document.querySelector("#pauseBtn");
const settingsTabs = document.querySelectorAll(".tab-btn");
const settingsContentPanels = document.querySelectorAll(".tab-content");
const timeEl = $("#time");
const readyOverlayMarkup = overlay.innerHTML;
let timerInterval = null;

canvas.focus({ preventScroll: true });
export let dpr = Math.max(1, Math.min(2, window.devicePixelRatio || 1));

function isTypingTarget(el) {
  return (
    el &&
    (el.tagName === "INPUT" ||
      el.tagName === "TEXTAREA" ||
      el.tagName === "SELECT" ||
      el.isContentEditable)
  );
}

function isGameInputTarget(el) {
  return (
    isTypingTarget(el) ||
    (el &&
      (el.tagName === "BUTTON" ||
        el.tagName === "A" ||
        el.tagName === "SUMMARY"))
  );
}

function isSettingsOpen() {
  return !settingsPanel.classList.contains("hidden");
}

function openSettingsPanel() {
  settingsPanel.classList.remove("hidden");
}

const BOARD_SIZE_PRESETS = {
  small: { factor: 0.68, max: 500 },
  medium: { factor: 0.82, max: 680 },
  large: { factor: 0.96, max: 860 },
};

function getBoardWidthForViewport(size) {
  const preset = BOARD_SIZE_PRESETS[size] || BOARD_SIZE_PRESETS.medium;
  const wrapStyles = wrapEl ? getComputedStyle(wrapEl) : null;
  const horizontalPadding = wrapStyles
    ? (parseFloat(wrapStyles.paddingLeft) || 0) +
      (parseFloat(wrapStyles.paddingRight) || 0)
    : 0;
  const availableWidth = Math.max(
    240,
    (wrapEl?.clientWidth || window.innerWidth) - horizontalPadding
  );
  const hudHeight = hud?.offsetHeight || 0;
  const controlsHeight =
    isMobile() && mobileControls ? mobileControls.offsetHeight + 20 : 0;
  const verticalSafety = isMobile() ? 32 : 48;
  const availableHeight = Math.max(
    240,
    window.innerHeight - hudHeight - controlsHeight - verticalSafety
  );

  if (isMobile()) {
    return Math.floor(Math.min(availableWidth, availableHeight));
  }

  const viewportTarget = Math.min(
    preset.max,
    Math.round(Math.min(window.innerWidth, availableHeight) * preset.factor)
  );
  const minWidth = Math.min(320, availableWidth, availableHeight);
  return Math.max(
    minWidth,
    Math.floor(Math.min(availableWidth, availableHeight, viewportTarget))
  );
}

function refreshBoardLayout(redraw = true) {
  const settings = getSettings();
  applyBoardSize(settings.boardSize || "medium", redraw);
}

function bindReadyOverlayButtons() {
  document.getElementById("playBtn")?.addEventListener("click", () => {
    initAudio();
    overlay.classList.add("hidden");
    start();
    canvas.focus?.({ preventScroll: true });
    startTimerDisplay();
  });

  document.getElementById("settingsBtn")?.addEventListener("click", () => {
    openSettingsPanel();
  });
}

export function showReadyOverlay() {
  overlay.innerHTML = readyOverlayMarkup;
  bindReadyOverlayButtons();
}

// ----- Event Listeners -----
export function initEventListeners() {
  bindReadyOverlayButtons();

  window.addEventListener(
    "keydown",
    (e) => {
      if (isSettingsOpen() || isGameInputTarget(e.target)) return;
      if (
        ["ArrowUp", "ArrowDown", "ArrowLeft", "ArrowRight", " "].includes(e.key)
      ) {
        e.preventDefault();
      }
    },
    { passive: false }
  );

  ["pointerdown", "touchend", "keydown", "click"].forEach((evt) =>
    window.addEventListener(
      evt,
      () => {
        initAudio();
        ensureAudioReady();
      },
      { passive: true }
    )
  );

  masterVolumeSlider.addEventListener("input", () => {
    const settings = getSettings();
    settings.masterVolume = parseFloat(masterVolumeSlider.value);
    saveSettings(settings);
  });

  pauseBtn?.addEventListener("click", () => {
    const wasRunning = getState().status === "running";
    togglePause();
    const nowRunning = getState().status === "running";

    if (wasRunning && !nowRunning) {
      stopTimerDisplay();
      overlay.classList.remove("hidden");
      updateOverlay("Paused", "Game is paused");
    } else if (!wasRunning && nowRunning) {
      startTimerDisplay();
      overlay.classList.add("hidden");
    }
  });

  sfxVolumeSlider.addEventListener("input", () => {
    initAudio();
    const settings = getSettings();
    settings.sfxVolume = parseFloat(sfxVolumeSlider.value);
    saveSettings(settings);
    playSound("right");
  });

  settingsTabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      const targetId = tab.dataset.tab;

      settingsTabs.forEach((t) => t.classList.remove("active"));
      settingsContentPanels.forEach((p) => p.classList.remove("active"));

      tab.classList.add("active");
      document.querySelector(`#${targetId}`).classList.add("active");
      applyBtn.textContent =
        targetId === "gameplay" ? "Apply & Restart" : "Apply";
    });
  });

  applyBtn.textContent = "Apply & Restart";
  backBtn.addEventListener("click", () => {
    settingsPanel.classList.add("hidden");
  });

  applyBtn.addEventListener("click", () => {
    const activePanel = document.querySelector(".tab-content.active");
    const activeTab = activePanel ? activePanel.id : "gameplay";
    const settings = getSettings();

    if (activeTab === "gameplay") {
      settings.gridSize = parseInt(gridSel.value, 10);
      settings.speed = parseInt(speedSel.value, 10);
      settings.apples = parseInt(applesSel.value, 10);
      settings.walls = wallsSel.value === "walls";
      saveSettings(settings);
      resetGame();
      overlay.classList.remove("hidden");
      updateHUD();
    } else if (activeTab === "visuals") {
      settings.boardSize = boardSizeSel.value;
      settings.theme = themeSel.value;
      settings.snakeStyle = snakeStyleSel.value;
      saveSettings(settings);
      applyTheme(settings.theme);
      applyBoardSize(settings.boardSize);
      updateHUD();
    } else if (activeTab === "sound") {
      // Volume sliders apply live on input.
    }
  });

  window.addEventListener("keydown", (e) => {
    if (isSettingsOpen() || isGameInputTarget(e.target)) return;
    handleKey(e);
  });

  let dragState = null;
  const swipeDeadzone = 20;

  canvas.addEventListener("pointerdown", (e) => {
    const state = getState();
    if (state.status !== "running") return;

    dragState = {
      startX: e.clientX,
      startY: e.clientY,
      startDir: state.dir,
    };
  });

  canvas.addEventListener("pointermove", (e) => {
    if (!dragState) return;

    const dx = e.clientX - dragState.startX;
    const dy = e.clientY - dragState.startY;

    if (Math.abs(dx) > swipeDeadzone || Math.abs(dy) > swipeDeadzone) {
      let newDirKey;
      if (Math.abs(dx) > Math.abs(dy)) {
        newDirKey = dx > 0 ? "ArrowRight" : "ArrowLeft";
      } else {
        newDirKey = dy > 0 ? "ArrowDown" : "ArrowUp";
      }

      const state = getState();
      const lastDir =
        state.nextDirs.length > 0
          ? state.nextDirs[state.nextDirs.length - 1]
          : state.dir;

      let isNewDirection = false;
      if (newDirKey === "ArrowUp" && lastDir.y === 0) isNewDirection = true;
      if (newDirKey === "ArrowDown" && lastDir.y === 0) isNewDirection = true;
      if (newDirKey === "ArrowLeft" && lastDir.x === 0) isNewDirection = true;
      if (newDirKey === "ArrowRight" && lastDir.x === 0) isNewDirection = true;

      if (isNewDirection) {
        handleKey({ key: newDirKey });
        dragState.startX = e.clientX;
        dragState.startY = e.clientY;
      }
    }
  });

  canvas.addEventListener("pointerup", () => {
    dragState = null;
  });

  window.addEventListener("resize", () => refreshBoardLayout(true));
  window.addEventListener("orientationchange", () => refreshBoardLayout(true));
}

// ----- UI Sync -----
export function syncUiToSettings() {
  const settings = getSettings();
  applyTheme(settings.theme);
  boardSizeSel.value = settings.boardSize;
  applyBoardSize(settings.boardSize);
  gridSel.value = String(settings.gridSize);
  speedSel.value = String(settings.speed);
  applesSel.value = String(settings.apples);
  wallsSel.value = settings.walls ? "walls" : "wrap";
  themeSel.value = settings.theme;
  snakeStyleSel.value = settings.snakeStyle || "tube";
  masterVolumeSlider.value = settings.masterVolume;
  sfxVolumeSlider.value = settings.sfxVolume;
}

// ----- HUD & Overlay Updates -----
export function updateHUD() {
  const state = getState();
  const settings = getSettings();
  scoreEl.textContent = state.score;
  bestEl.textContent = state.best;
  speedLabel.textContent = settings.speed;
}

export function updateOverlay(title, subtitle) {
  const state = getState();
  const isOver = state.status === "over";

  overlay.innerHTML = `
    <div class="title">${title}</div>
    <div class="subtitle">${subtitle}</div>
    <div class="overlay-actions">
      ${
        isOver
          ? `<button class="button" id="overlayPlayAgainBtn">Play again</button>`
          : `
           <button class="button" id="overlayResumeBtn">Resume</button>
           <button class="button secondary" id="overlayRestartBtn">Restart</button>
         `
      }
      <button class="button secondary" id="overlaySettingsBtn">Settings</button>
    </div>`;

  if (isOver) {
    $("#overlayPlayAgainBtn")?.addEventListener("click", () => {
      overlay.classList.add("hidden");
      resetGame();
      start();
      canvas.focus?.({ preventScroll: true });
      startTimerDisplay();
    });
  } else {
    $("#overlayResumeBtn")?.addEventListener("click", () => {
      overlay.classList.add("hidden");
      start();
      canvas.focus?.({ preventScroll: true });
      startTimerDisplay();
    });

    $("#overlayRestartBtn")?.addEventListener("click", () => {
      resetGame();
      start();
      canvas.focus?.({ preventScroll: true });
      startTimerDisplay();
      overlay.classList.add("hidden");
    });
  }

  $("#overlaySettingsBtn")?.addEventListener("click", () => {
    openSettingsPanel();
  });
}

export function setOverlayVisibility(visible) {
  if (visible) {
    overlay.classList.remove("hidden");
  } else {
    overlay.classList.add("hidden");
  }
}

// ----- Drawing -----
function roundRectPath(ctx, x, y, w, h, r) {
  const rr = Math.min(r, w * 0.5, h * 0.5);
  ctx.beginPath();
  ctx.moveTo(x + rr, y);
  ctx.arcTo(x + w, y, x + w, y + h, rr);
  ctx.arcTo(x + w, y + h, x, y + h, rr);
  ctx.arcTo(x, y + h, x, y, rr);
  ctx.arcTo(x, y, x + w, y, rr);
}

function drawTubeSnake(ctx, centers, colors, tubeWidth, wrapBounds = null) {
  if (!centers || centers.length === 0) return;

  function strokeSmoothPath(
    pts,
    tension = 1.1,
    width = tubeWidth,
    color = colors.body
  ) {
    if (pts.length < 2) return;

    ctx.strokeStyle = color;
    ctx.lineWidth = width;
    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    ctx.beginPath();
    ctx.moveTo(pts[0].x, pts[0].y);

    if (pts.length === 2) {
      ctx.lineTo(pts[1].x, pts[1].y);
      ctx.stroke();
      return;
    }

    const p = [pts[0], ...pts, pts[pts.length - 1]];
    for (let i = 1; i < p.length - 2; i++) {
      const p0 = p[i - 1];
      const p1 = p[i];
      const p2 = p[i + 1];
      const p3 = p[i + 2];
      const cp1x = p1.x + ((p2.x - p0.x) / 6) * tension;
      const cp1y = p1.y + ((p2.y - p0.y) / 6) * tension;
      const cp2x = p2.x - ((p3.x - p1.x) / 6) * tension;
      const cp2y = p2.y - ((p3.y - p1.y) / 6) * tension;
      ctx.bezierCurveTo(cp1x, cp1y, cp2x, cp2y, p2.x, p2.y);
    }
    ctx.stroke();
  }

  function drawOneTube(points) {
    const tailToHead = points.slice().reverse();
    strokeSmoothPath(tailToHead, 1.2, tubeWidth, colors.body);

    const headWindow = Math.min(4, tailToHead.length);
    if (headWindow >= 2) {
      strokeSmoothPath(
        tailToHead.slice(-headWindow),
        1.2,
        Math.max(2, Math.floor(tubeWidth * 0.9)),
        colors.head
      );
    }

    const head = points[0];
    let dx = 1;
    let dy = 0;

    if (points.length >= 2) {
      const vx = head.x - points[1].x;
      const vy = head.y - points[1].y;
      const len = Math.hypot(vx, vy) || 1;
      dx = vx / len;
      dy = vy / len;
    }

    const px = -dy;
    const py = dx;
    const eyeOffset = Math.max(3, Math.round(tubeWidth * 0.22));
    const eyeForward = Math.max(2, Math.round(tubeWidth * 0.18));
    const eyeR = Math.max(2, Math.round(tubeWidth * 0.12));

    ctx.fillStyle = "rgba(0,0,0,.75)";
    ctx.beginPath();
    ctx.arc(
      head.x + px * eyeOffset + dx * eyeForward,
      head.y + py * eyeOffset + dy * eyeForward,
      eyeR,
      0,
      Math.PI * 2
    );
    ctx.fill();

    ctx.beginPath();
    ctx.arc(
      head.x - px * eyeOffset + dx * eyeForward,
      head.y - py * eyeOffset + dy * eyeForward,
      eyeR,
      0,
      Math.PI * 2
    );
    ctx.fill();
  }

  if (!wrapBounds) {
    drawOneTube(centers);
    return;
  }

  const { ox, oy, gridW, gridH } = wrapBounds;
  const unwrapped = [centers[0]];

  for (let i = 1; i < centers.length; i++) {
    const prev = unwrapped[i - 1];
    let x = centers[i].x;
    let y = centers[i].y;

    while (x - prev.x > gridW * 0.5) x -= gridW;
    while (x - prev.x < -gridW * 0.5) x += gridW;
    while (y - prev.y > gridH * 0.5) y -= gridH;
    while (y - prev.y < -gridH * 0.5) y += gridH;

    unwrapped.push({ x, y });
  }

  const xs = unwrapped.map((point) => point.x);
  const ys = unwrapped.map((point) => point.y);
  const minX = Math.min(...xs);
  const maxX = Math.max(...xs);
  const minY = Math.min(...ys);
  const maxY = Math.max(...ys);
  const minShiftX = Math.ceil((ox - maxX) / gridW);
  const maxShiftX = Math.floor((ox + gridW - minX) / gridW);
  const minShiftY = Math.ceil((oy - maxY) / gridH);
  const maxShiftY = Math.floor((oy + gridH - minY) / gridH);

  ctx.save();
  ctx.beginPath();
  ctx.rect(ox, oy, gridW, gridH);
  ctx.clip();

  for (let sx = minShiftX; sx <= maxShiftX; sx++) {
    for (let sy = minShiftY; sy <= maxShiftY; sy++) {
      const shifted = unwrapped.map((point) => ({
        x: point.x + sx * gridW,
        y: point.y + sy * gridH,
      }));
      drawOneTube(shifted);
    }
  }

  ctx.restore();
}

function applyBoardSize(size, redraw = true) {
  const effectiveSize = isMobile() ? "large" : size;
  const boardWidth = getBoardWidthForViewport(effectiveSize);

  gameCard.setAttribute("data-size", effectiveSize);
  gameCard.style.width = `${boardWidth}px`;
  gameCard.style.maxWidth = "100%";
  resizeCanvas(redraw);
}

function updateTimerDisplay() {
  const state = getState();
  if (!state) return;

  let currentTime = state.elapsedTime;
  if (state.startTime) {
    currentTime += performance.now() - state.startTime;
  }
  timeEl.textContent = formatTime(currentTime);
}

// --- Mobile helpers ---
function isMobile() {
  return (
    window.matchMedia("(pointer: coarse)").matches || window.innerWidth < 820
  );
}

function disableDesktopOnlySettings() {
  const desktopOnly = document.querySelectorAll("[data-desktop-only]");
  const on = isMobile();

  desktopOnly.forEach((node) => {
    node.style.display = on ? "none" : "";
    node
      .querySelectorAll("input, select, button")
      .forEach((el) => (el.disabled = on));
  });

  if (on && desktopOnly.length === 0) {
    const rows = Array.from(
      document.querySelectorAll(".setting-row, .form-group")
    );
    const row = rows.find((r) => /board\s*size/i.test(r.textContent || ""));
    if (row) {
      row.style.display = "none";
      row
        .querySelectorAll("input, select, button")
        .forEach((el) => (el.disabled = true));
    }
  }
}

window.addEventListener("resize", disableDesktopOnlySettings);
document.addEventListener("DOMContentLoaded", disableDesktopOnlySettings);

// --- Mobile D-pad wiring ---
function tapKey(key) {
  const ev = new KeyboardEvent("keydown", { key });
  window.dispatchEvent(ev);
}

function bindPad(btn, key) {
  btn?.addEventListener(
    "pointerdown",
    (e) => {
      e.preventDefault();
      tapKey(key);
    },
    { passive: false }
  );

  btn?.addEventListener("click", (e) => {
    e.preventDefault();
    tapKey(key);
  });
}

const btnUp = document.getElementById("btnUp");
const btnDown = document.getElementById("btnDown");
const btnLeft = document.getElementById("btnLeft");
const btnRight = document.getElementById("btnRight");

bindPad(btnUp, "ArrowUp");
bindPad(btnDown, "ArrowDown");
bindPad(btnLeft, "ArrowLeft");
bindPad(btnRight, "ArrowRight");

export function startTimerDisplay() {
  if (timerInterval) clearInterval(timerInterval);
  timerInterval = setInterval(updateTimerDisplay, 100);
}

export function stopTimerDisplay() {
  clearInterval(timerInterval);
  timerInterval = null;
  updateTimerDisplay();
}

export function resetTimerDisplay() {
  stopTimerDisplay();
  timeEl.textContent = "0:00";
}

function formatTime(ms) {
  const totalSeconds = Math.floor(ms / 1000);
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  return `${minutes}:${seconds.toString().padStart(2, "0")}`;
}

export function resizeCanvas(redraw = false) {
  const wrapRect = canvas.parentElement?.getBoundingClientRect();
  if (!wrapRect) return;

  const size = Math.max(1, Math.floor(Math.min(wrapRect.width, wrapRect.height)));
  dpr = Math.max(1, Math.min(2, window.devicePixelRatio || 1));
  canvas.width = Math.round(size * dpr);
  canvas.height = Math.round(size * dpr);
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  ctx.clearRect(0, 0, canvas.width / dpr, canvas.height / dpr);
  if (redraw) draw(0);
}

export function draw(alpha = 0) {
  ctx.save();
  const state = getState();
  const settings = getSettings();
  const W = canvas.width / dpr;
  const H = canvas.height / dpr;
  const N = state.grid;
  const cell = Math.floor(Math.min(W, H) / N);
  const gridW = cell * N;
  const gridH = cell * N;
  const ox = Math.floor((W - gridW) / 2);
  const oy = Math.floor((H - gridH) / 2);

  ctx.clearRect(0, 0, W, H);
  const css = getComputedStyle(document.body);
  const colBody = css.getPropertyValue("--accent").trim();
  const colHead = css.getPropertyValue("--accent-2").trim();
  const colGrid = css.getPropertyValue("--grid").trim();
  const lerp = (a, b, t) => a * (1 - t) + b * t;

  function interpCell(from, to, t) {
    let fx = from.x;
    let fy = from.y;
    const tx = to.x;
    const ty = to.y;

    if (!state.walls) {
      const dx = tx - fx;
      const dy = ty - fy;
      if (dx > 1) fx += N;
      else if (dx < -1) fx -= N;
      if (dy > 1) fy += N;
      else if (dy < -1) fy -= N;
    }

    return {
      x: ox + lerp(fx, tx, t) * cell + cell / 2,
      y: oy + lerp(fy, ty, t) * cell + cell / 2,
    };
  }

  ctx.strokeStyle = colGrid;
  ctx.lineWidth = 1;
  ctx.beginPath();
  for (let i = 0; i <= N; i++) {
    const x = ox + i * cell + 0.5;
    ctx.moveTo(x, oy + 0.5);
    ctx.lineTo(x, oy + gridH + 0.5);
    const y = oy + i * cell + 0.5;
    ctx.moveTo(ox + 0.5, y);
    ctx.lineTo(ox + gridW + 0.5, y);
  }
  ctx.stroke();

  for (const a of state.apples) {
    const r = Math.floor(cell * 0.36);
    const cx = ox + a.x * cell + cell / 2;
    const cy = oy + a.y * cell + cell / 2;
    const grd = ctx.createRadialGradient(cx, cy, r * 0.2, cx, cy, r);
    grd.addColorStop(0, "rgba(255,255,255,.85)");
    grd.addColorStop(1, "rgba(255,0,0,.85)");
    ctx.fillStyle = grd;
    ctx.beginPath();
    ctx.arc(cx, cy, r, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = "#16a34a";
    ctx.beginPath();
    ctx.ellipse(
      cx + r * 0.2,
      cy - r * 0.8,
      r * 0.32,
      r * 0.18,
      -0.4,
      0,
      Math.PI * 2
    );
    ctx.fill();
  }

  const prev = state.prevSnake || state.snake;
  const centers = state.snake.map((seg, i) => {
    const from = prev[i] || seg;
    return interpCell(from, seg, alpha);
  });
  if (!centers.length) return;

  if ((settings.snakeStyle || "tube") === "tube") {
    const tubeWidth = Math.max(6, Math.floor(cell * 0.74));
    drawTubeSnake(
      ctx,
      centers,
      { head: colHead, body: colBody },
      tubeWidth,
      state.walls ? null : { ox, oy, gridW, gridH }
    );
  } else {
    const pad = Math.max(1, Math.floor(cell * 0.12));
    const sz = cell - pad * 2;
    const r = Math.min(10, Math.floor(sz * 0.25));
    centers.forEach((c, i) => {
      const isHead = i === 0;
      const color = isHead ? colHead : colBody;
      const rx = Math.round(c.x - cell / 2 + pad);
      const ry = Math.round(c.y - cell / 2 + pad);
      ctx.fillStyle = color;
      roundRectPath(ctx, rx, ry, sz, sz, r);
      ctx.fill();
      if (isHead) {
        ctx.fillStyle = "rgba(0,0,0,.7)";
        const ex = sz * 0.22;
        const ey = sz * 0.22;
        const eR = Math.max(2, Math.floor(sz * 0.08));
        ctx.beginPath();
        ctx.arc(rx + ex, ry + ey, eR, 0, Math.PI * 2);
        ctx.fill();
        ctx.beginPath();
        ctx.arc(rx + sz - ex, ry + ey, eR, 0, Math.PI * 2);
        ctx.fill();
      }
    });
  }

  ctx.restore();
}
