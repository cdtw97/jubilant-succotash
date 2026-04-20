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
const playBtn = $("#playBtn");
const gridSel = $("#grid");
const speedSel = $("#speed");
const applesSel = $("#apples");
const wallsSel = $("#walls");
const themeSel = $("#theme");
const applyBtn = $("#applyBtn");
const snakeStyleSel = $("#snakeStyle");
const settingsBtn = $("#settingsBtn");
const settingsPanel = $("#settings-panel");
const backBtn = $("#backBtn");
const gameCard = $(".game-card");
const boardSizeSel = $("#boardSize");
const pauseBtn = document.querySelector("#pauseBtn");
const settingsTabs = document.querySelectorAll(".tab-btn");
const settingsContentPanels = document.querySelectorAll(".tab-content");
const timeEl = $("#time");
let timerInterval = null;
canvas.focus({ preventScroll: true });
export const dpr = Math.max(1, Math.min(2, window.devicePixelRatio || 1));
// ui.js (run once after DOM ready)
function isTypingTarget(el) {
  return (
    el &&
    (el.tagName === "INPUT" ||
      el.tagName === "TEXTAREA" ||
      el.tagName === "SELECT" ||
      el.isContentEditable)
  );
}
// ----- Event Listeners -----
export function initEventListeners() {
  window.addEventListener(
    "keydown",
    (e) => {
      if (
        ["ArrowUp", "ArrowDown", "ArrowLeft", "ArrowRight", " "].includes(e.key)
      ) {
        if (isTypingTarget(e.target)) return; // don’t block forms
        e.preventDefault(); // stop caret move/scroll
      }
    },
    { passive: false }
  );
  playBtn.addEventListener("click", () => {
    initAudio();
    overlay.classList.add("hidden");
    start();
    canvas.focus?.({ preventScroll: true });
    canvas.focus?.({ preventScroll: true });
    startTimerDisplay(); // Start the timer UI
  });
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
  // --- Live Volume Control Listeners ---
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
    // Play a sound for immediate feedback
    playSound("right");
  });
  // Show settings panel
  settingsBtn.addEventListener("click", () => {
    settingsPanel.classList.remove("hidden");
  });
  // --- Tab Switching Logic ---
  settingsTabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      // Get the target tab content ID from the button's data-tab attribute
      const targetId = tab.dataset.tab;

      // Remove 'active' class from all tabs and content panels
      settingsTabs.forEach((t) => t.classList.remove("active"));
      settingsContentPanels.forEach((p) => p.classList.remove("active"));

      // Add 'active' class to the clicked tab and its corresponding content panel
      tab.classList.add("active");
      document.querySelector(`#${targetId}`).classList.add("active");
      // Update Apply button label per tab
      applyBtn.textContent =
        targetId === "gameplay" ? "Apply & Restart" : "Apply";
    });
  });
  // Hide settings panel
  applyBtn.textContent = "Apply & Restart";
  backBtn.addEventListener("click", () => {
    settingsPanel.classList.add("hidden");
  });

  applyBtn.addEventListener("click", () => {
    const activePanel = document.querySelector(".tab-content.active");
    const activeTab = activePanel ? activePanel.id : "gameplay";
    let settings = getSettings();

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
      // Volume sliders apply live on input; nothing to do here.
    }
  });

  window.addEventListener("keydown", handleKey);

  // --- NEW Improved Swipe Gesture Logic ---
  let dragState = null;
  const swipeDeadzone = 20; // A slightly smaller deadzone feels more responsive here

  canvas.addEventListener("pointerdown", (e) => {
    const state = getState();
    if (state.status !== "running") return;

    // Anchor the start of the drag and record the snake's current direction
    dragState = {
      startX: e.clientX,
      startY: e.clientY,
      // Store the snake's direction when the touch starts
      startDir: state.dir,
    };
  });

  canvas.addEventListener("pointermove", (e) => {
    if (!dragState) return;

    const dx = e.clientX - dragState.startX;
    const dy = e.clientY - dragState.startY;

    // Check if we've moved far enough from our last anchor point
    if (Math.abs(dx) > swipeDeadzone || Math.abs(dy) > swipeDeadzone) {
      let newDirKey;
      // Determine the primary direction of the drag
      if (Math.abs(dx) > Math.abs(dy)) {
        newDirKey = dx > 0 ? "ArrowRight" : "ArrowLeft";
      } else {
        newDirKey = dy > 0 ? "ArrowDown" : "ArrowUp";
      }

      // Get the last direction command sent
      const state = getState();
      const lastDir =
        state.nextDirs.length > 0
          ? state.nextDirs[state.nextDirs.length - 1]
          : state.dir;

      // Check if this is a new, valid direction
      let isNewDirection = false;
      if (newDirKey === "ArrowUp" && lastDir.y === 0) isNewDirection = true;
      if (newDirKey === "ArrowDown" && lastDir.y === 0) isNewDirection = true;
      if (newDirKey === "ArrowLeft" && lastDir.x === 0) isNewDirection = true;
      if (newDirKey === "ArrowRight" && lastDir.x === 0) isNewDirection = true;

      if (isNewDirection) {
        // Send the new direction to the game
        handleKey({ key: newDirKey });

        // IMPORTANT: Reset the anchor to the current finger position
        // This allows the next drag to be measured from here.
        dragState.startX = e.clientX;
        dragState.startY = e.clientY;
      }
    }
  });

  canvas.addEventListener("pointerup", (e) => {
    // Clear the drag state when the finger is lifted
    dragState = null;
  });

  window.addEventListener("resize", () => resizeCanvas(true));
}
//HIIIII//
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
      startTimerDisplay(); // ensure timer resumes
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
    settingsPanel.classList.remove("hidden");
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

// ===== Tube sprite renderer (simple + fast) =====
// Put these at module scope (above drawTubeSnake)

// ---- The one-and-only draw function ----
// --- Tube snake: analytic renderer (no sprites) ---
// Draws a continuous variable-width tube made of "capsule" fills between centers.
// Fixes wrap glitches by drawing toroidal copies when walls are off.
// ui.js
function drawTubeSnake(ctx, centers, colors, tubeWidth) {
  if (!centers || centers.length === 0) return;

  // Draw a smooth Catmull–Rom path
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

    // Pad endpoints so the first and last segment get good tangents
    const p = [pts[0], ...pts, pts[pts.length - 1]];
    for (let i = 1; i < p.length - 2; i++) {
      const p0 = p[i - 1],
        p1 = p[i],
        p2 = p[i + 1],
        p3 = p[i + 2];
      const cp1x = p1.x + ((p2.x - p0.x) / 6) * tension;
      const cp1y = p1.y + ((p2.y - p0.y) / 6) * tension;
      const cp2x = p2.x - ((p3.x - p1.x) / 6) * tension;
      const cp2y = p2.y - ((p3.y - p1.y) / 6) * tension;
      ctx.bezierCurveTo(cp1x, cp1y, cp2x, cp2y, p2.x, p2.y);
    }
    ctx.stroke();
  }

  // Draw the whole body once — this eliminates the “teardrop at each join”
  const tailToHead = centers.slice().reverse(); // we want tail → head order for the path
  strokeSmoothPath(tailToHead, 1.2, tubeWidth, colors.body);

  // Tint the head by overdrawing just the last few points (slightly narrower to avoid halo)
  const headWindow = Math.min(4, tailToHead.length);
  if (headWindow >= 2) {
    strokeSmoothPath(
      tailToHead.slice(-headWindow),
      1.2,
      Math.max(2, Math.floor(tubeWidth * 0.9)),
      colors.head
    );
  }

  // Eyes (optional)
  const head = centers[0];
  let dx = 1,
    dy = 0;
  if (centers.length >= 2) {
    const vx = head.x - centers[1].x,
      vy = head.y - centers[1].y;
    const len = Math.hypot(vx, vy) || 1;
    dx = vx / len;
    dy = vy / len;
  }
  const px = -dy,
    py = dx; // perpendicular
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

function applyBoardSize(size) {
  gameCard.setAttribute("data-size", size);
  // We must call resizeCanvas so it adjusts to the new container size
  resizeCanvas(true);
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

// Keep the canvas' internal resolution matched to its CSS size so it stays sharp
function syncCanvasToCSS() {
  if (!isMobile()) return;

  const cssSize = Math.floor(canvas.clientWidth); // square due to CSS aspect-ratio
  const dprNow = Math.max(1, Math.min(2, window.devicePixelRatio || 1));

  // Set the internal pixel buffer to CSS size * DPR for crisp rendering
  const needW = cssSize * dprNow;
  const needH = cssSize * dprNow;
  if (canvas.width !== needW || canvas.height !== needH) {
    canvas.width = needW;
    canvas.height = needH;
    ctx.setTransform(dprNow, 0, 0, dprNow, 0, 0);
    draw(0);
  }
}

// Call once on load and on every resize/orientation change
window.addEventListener("resize", syncCanvasToCSS);
window.addEventListener("orientationchange", syncCanvasToCSS);
document.addEventListener("DOMContentLoaded", syncCanvasToCSS);

// Disable/hide the Board size control on mobile (requires data-desktop-only OR fallback by label)
function disableDesktopOnlySettings() {
  const desktopOnly = document.querySelectorAll("[data-desktop-only]");
  const on = isMobile();
  desktopOnly.forEach((node) => {
    node.style.display = on ? "none" : "";
    node
      .querySelectorAll("input, select, button")
      .forEach((el) => (el.disabled = on));
  });

  // Fallback if attribute wasn't added: try to find a row mentioning "Board size"
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
// Reuse your existing keyboard handler by dispatching real keydown events
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
  updateTimerDisplay(); // Final update
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
  const cssSize = Math.min(
    $(".canvas-wrap").clientWidth,
    window.innerHeight - 120
  );
  const size = Math.max(280, Math.min(cssSize, 900));
  canvas.style.width = size + "px";
  canvas.style.height = size + "px";
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
  const gridW = cell * N,
    gridH = cell * N;
  const ox = Math.floor((W - gridW) / 2);
  const oy = Math.floor((H - gridH) / 2);

  ctx.clearRect(0, 0, W, H);
  const css = getComputedStyle(document.body);
  const colBody = css.getPropertyValue("--accent").trim();
  const colHead = css.getPropertyValue("--accent-2").trim();
  const colGrid = css.getPropertyValue("--grid").trim();
  const lerp = (a, b, t) => a * (1 - t) + b * t;

  function interpCell(from, to, alpha) {
    let fx = from.x,
      fy = from.y,
      tx = to.x,
      ty = to.y;
    if (!state.walls) {
      const dx = tx - fx,
        dy = ty - fy;
      if (dx > 1) fx += N;
      else if (dx < -1) fx -= N;
      if (dy > 1) fy += N;
      else if (dy < -1) fy -= N;
    }
    return {
      x: ox + lerp(fx, tx, alpha) * cell + cell / 2,
      y: oy + lerp(fy, ty, alpha) * cell + cell / 2,
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
      gridW,
      gridH
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
        const ex = sz * 0.22,
          ey = sz * 0.22,
          eR = Math.max(2, Math.floor(sz * 0.08));
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
