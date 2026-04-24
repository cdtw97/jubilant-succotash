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
  normalizeTheme,
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
const snakeStageFrame = $(".snake-stage__frame");
const gameCard = $(".game-card");
const hud = $(".hud-2");
const boardSizeSel = $("#boardSize");
const mobileControls = $("#mobileControls");
const pauseBtn = document.querySelector("#pauseBtn");
const fullscreenBtn = $("#fullscreenBtn");
const fullscreenLabel = $("#fullscreenLabel");
const fullscreenIconPath = $("#fullscreenIconPath");
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

function getFullscreenElement() {
  return document.fullscreenElement || document.webkitFullscreenElement || null;
}

function isGameFullscreen() {
  return getFullscreenElement() === snakeStageFrame;
}

function canUseFullscreen() {
  return Boolean(
    snakeStageFrame?.requestFullscreen || snakeStageFrame?.webkitRequestFullscreen
  );
}

async function exitGameFullscreen() {
  const exitFullscreen =
    document.exitFullscreen || document.webkitExitFullscreen;

  if (!exitFullscreen || !getFullscreenElement()) {
    return;
  }

  await exitFullscreen.call(document);
}

async function enterGameFullscreen() {
  const requestFullscreen =
    snakeStageFrame?.requestFullscreen || snakeStageFrame?.webkitRequestFullscreen;

  if (!requestFullscreen || !snakeStageFrame) {
    return;
  }

  await requestFullscreen.call(snakeStageFrame);
}

function syncFullscreenUi() {
  const isFullscreen = isGameFullscreen();
  document.body.classList.toggle("snake-immersive", isFullscreen);
  fullscreenBtn?.classList.toggle("hidden", !canUseFullscreen());
  fullscreenBtn?.classList.toggle("is-active", isFullscreen);
  fullscreenBtn?.setAttribute("aria-pressed", isFullscreen ? "true" : "false");
  fullscreenBtn?.setAttribute(
    "aria-label",
    isFullscreen ? "Exit fullscreen" : "Enter fullscreen"
  );
  fullscreenBtn?.setAttribute(
    "title",
    isFullscreen ? "Exit fullscreen" : "Enter fullscreen"
  );

  if (fullscreenLabel) {
    fullscreenLabel.textContent = isFullscreen ? "Exit" : "Fullscreen";
  }

  if (fullscreenIconPath) {
    fullscreenIconPath.setAttribute(
      "d",
      isFullscreen
        ? "M8 5H5v3h2V7h1V5Zm9 0h-3v2h1v1h2V5ZM7 16H5v3h3v-2H7v-1Zm10 0h-2v1h-1v2h3v-3Z"
        : "M5 5h5v2H7v3H5V5Zm9 0h5v5h-2V7h-3V5ZM5 14h2v3h3v2H5v-5Zm12 0h2v5h-5v-2h3v-3Z"
    );
  }

  refreshBoardLayout(Boolean(getState()));

  if (isFullscreen) {
    canvas.focus?.({ preventScroll: true });
  }
}

function openSettingsPanel() {
  if (isGameFullscreen()) {
    void exitGameFullscreen().finally(() => {
      settingsPanel.classList.remove("hidden");
    });
    return;
  }

  settingsPanel.classList.remove("hidden");
}

const BOARD_SIZE_PRESETS = {
  small: { factor: 0.68, max: 500 },
  medium: { factor: 0.82, max: 680 },
  large: { factor: 0.96, max: 860 },
};

function getBoardWidthForViewport(size) {
  const preset = BOARD_SIZE_PRESETS[size] || BOARD_SIZE_PRESETS.medium;
  const isFullscreen = isGameFullscreen();
  const wrapStyles = wrapEl ? getComputedStyle(wrapEl) : null;
  const horizontalPadding = wrapStyles
    ? (parseFloat(wrapStyles.paddingLeft) || 0) +
      (parseFloat(wrapStyles.paddingRight) || 0)
    : 0;
  const availableWidth = Math.max(
    240,
    (wrapEl?.clientWidth || window.innerWidth) -
      horizontalPadding -
      (isFullscreen ? (isMobile() ? 24 : 40) : 0)
  );
  const hudHeight = hud?.offsetHeight || 0;
  const controlsHeight =
    !isFullscreen && isMobile() && mobileControls
      ? mobileControls.offsetHeight + 20
      : 0;
  const verticalSafety = isFullscreen
    ? isMobile()
      ? 24
      : 36
    : isMobile()
      ? 32
      : 48;
  const availableHeight = Math.max(
    240,
    window.innerHeight - hudHeight - controlsHeight - verticalSafety
  );

  if (isFullscreen) {
    return Math.floor(Math.min(availableWidth, availableHeight));
  }

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
  applyBoardSize(settings.boardSize || "medium", redraw && Boolean(getState()));
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
  fullscreenBtn?.addEventListener("click", async () => {
    try {
      if (isGameFullscreen()) {
        await exitGameFullscreen();
      } else {
        await enterGameFullscreen();
      }
    } catch (error) {
      console.warn("Fullscreen toggle failed:", error);
    }
  });

  document.addEventListener("fullscreenchange", syncFullscreenUi);
  document.addEventListener("webkitfullscreenchange", syncFullscreenUi);

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
      (event) => {
        if (!event.isTrusted) return;
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
    const state = getState();
    if (!state) return;

    const wasRunning = state.status === "running";
    togglePause();
    const nextState = getState();
    if (!nextState) return;

    const nowRunning = nextState.status === "running";

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
    if (!state) return;
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
      if (!state) return;

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
  syncFullscreenUi();
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
  if (!state) return;
  const settings = getSettings();
  scoreEl.textContent = state.score;
  bestEl.textContent = state.best;
  speedLabel.textContent = settings.speed;
}

export function updateOverlay(title, subtitle) {
  const state = getState();
  if (!state) return;
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

const THEME_PROFILES = {
  "cyber-grid": {
    bodyColor: "#00d9ff",
    headColor: "#ff3dc8",
    gridColor: "rgba(69, 111, 255, 0.18)",
    boardGradient: ["#12031b", "#06070f"],
    foodPrimary: "#ffd84a",
    foodSecondary: "#fffbd1",
    foodOutline: "#351049",
    foodMode: "pulse-orb",
    tubeShadow: "rgba(0, 217, 255, 0.38)",
    tubeHeadShadow: "rgba(255, 61, 200, 0.45)",
    shadowBlur: 18,
    shadowOffsetY: 0,
    blockRadius: "rounded",
    boardEffect: "perspective-grid",
    squareGrid: false,
  },
  "e-ink": {
    bodyColor: "#5e6059",
    headColor: "#111111",
    gridColor: "rgba(65, 70, 69, 0.08)",
    boardGradient: ["#ebe4d6", "#ddd7ca"],
    foodPrimary: "#1f5b31",
    foodSecondary: "#1f5b31",
    foodMode: "x-mark",
    tubeShadow: "transparent",
    tubeHeadShadow: "transparent",
    shadowBlur: 0,
    shadowOffsetY: 0,
    blockRadius: "square",
    boardEffect: "paper-noise",
    squareGrid: true,
    forceBlockStyle: true,
  },
  "classic-handheld": {
    bodyColor: "#4b6223",
    headColor: "#1e2c0f",
    gridColor: "rgba(46, 71, 24, 0.12)",
    boardGradient: ["#9bbc0f", "#8bac0f"],
    foodPrimary: "#0c1a06",
    foodSecondary: "#0c1a06",
    foodMode: "pixel-seed",
    tubeShadow: "rgba(35, 61, 20, 0.18)",
    tubeHeadShadow: "rgba(35, 61, 20, 0.2)",
    shadowBlur: 0,
    shadowOffsetY: 2,
    blockRadius: "soft",
    boardEffect: "lcd",
    squareGrid: true,
  },
  "living-forest": {
    bodyColor: "#436b4d",
    bodyColorEnd: "#1d5538",
    headColor: "#d06737",
    gridColor: "rgba(145, 169, 111, 0.12)",
    boardGradient: ["#f7f2e5", "#efe3cc"],
    foodPrimary: "#b82542",
    foodSecondary: "#ff8c9d",
    foodOutline: "#fff3ee",
    foodMode: "berry",
    tubeShadow: "rgba(29, 85, 56, 0.18)",
    tubeHeadShadow: "rgba(208, 103, 55, 0.16)",
    shadowBlur: 12,
    shadowOffsetY: 1,
    blockRadius: "pebble",
    boardEffect: "organic",
    squareGrid: false,
  },
  "terminal": {
    bodyColor: "#00c830",
    headColor: "#dfffe5",
    gridColor: "rgba(0, 255, 65, 0.09)",
    boardGradient: ["#010201", "#020602"],
    foodPrimary: "#ffffff",
    foodSecondary: "#ffffff",
    foodMode: "glyph",
    foodGlyph: "_",
    tubeShadow: "rgba(0, 200, 48, 0.22)",
    tubeHeadShadow: "rgba(223, 255, 229, 0.18)",
    shadowBlur: 10,
    shadowOffsetY: 0,
    blockRadius: "soft",
    boardEffect: "scanlines",
    squareGrid: true,
  },
};

function getThemeProfile(themeName) {
  const normalized = normalizeTheme(themeName);
  return THEME_PROFILES[normalized] || THEME_PROFILES["living-forest"];
}

function drawBoardBackground(ctx, theme, ox, oy, gridW, gridH, cell, time) {
  const gradient = ctx.createLinearGradient(ox, oy, ox, oy + gridH);
  gradient.addColorStop(0, theme.boardGradient[0]);
  gradient.addColorStop(1, theme.boardGradient[1]);
  ctx.fillStyle = gradient;
  ctx.fillRect(ox, oy, gridW, gridH);

  ctx.save();
  ctx.beginPath();
  ctx.rect(ox, oy, gridW, gridH);
  ctx.clip();

  if (theme.boardEffect === "perspective-grid") {
    const horizonY = oy + gridH * 0.2;
    const scroll = (time * 0.08) % (cell * 1.15);

    ctx.strokeStyle = "rgba(103, 152, 255, 0.28)";
    ctx.lineWidth = 1;
    for (let x = -gridW; x <= gridW * 2; x += cell * 0.95) {
      ctx.beginPath();
      ctx.moveTo(ox + gridW * 0.5, horizonY);
      ctx.lineTo(ox + x, oy + gridH + cell);
      ctx.stroke();
    }

    for (let y = horizonY + scroll; y <= oy + gridH + cell; y += cell * 0.9) {
      const t = (y - horizonY) / Math.max(1, gridH - horizonY + oy);
      const halfWidth = gridW * (0.08 + t * 0.62);
      ctx.beginPath();
      ctx.moveTo(ox + gridW * 0.5 - halfWidth, y);
      ctx.lineTo(ox + gridW * 0.5 + halfWidth, y);
      ctx.stroke();
    }
  } else if (theme.boardEffect === "paper-noise") {
    ctx.fillStyle = "rgba(89, 84, 74, 0.06)";
    const count = Math.max(60, Math.floor((gridW * gridH) / 1500));
    for (let i = 0; i < count; i++) {
      const px = ox + (Math.sin(i * 91.17) * 0.5 + 0.5) * gridW;
      const py = oy + (Math.cos(i * 57.31) * 0.5 + 0.5) * gridH;
      ctx.fillRect(px, py, 1, 1);
    }
  } else if (theme.boardEffect === "lcd") {
    ctx.fillStyle = "rgba(40, 58, 18, 0.045)";
    for (let y = oy; y <= oy + gridH; y += 3) {
      ctx.fillRect(ox, y, gridW, 1);
    }
  } else if (theme.boardEffect === "organic") {
    const glow = ctx.createRadialGradient(
      ox + gridW * 0.25,
      oy + gridH * 0.2,
      cell,
      ox + gridW * 0.25,
      oy + gridH * 0.2,
      gridW * 0.7
    );
    glow.addColorStop(0, "rgba(255,255,255,0.22)");
    glow.addColorStop(1, "rgba(255,255,255,0)");
    ctx.fillStyle = glow;
    ctx.fillRect(ox, oy, gridW, gridH);
  } else if (theme.boardEffect === "scanlines") {
    ctx.fillStyle = "rgba(0, 0, 0, 0.06)";
    for (let y = oy; y <= oy + gridH; y += 4) {
      ctx.fillRect(ox, y, gridW, 1);
    }
  }

  ctx.restore();
}

function drawThemeOverlay(ctx, theme, ox, oy, gridW, gridH) {
  if (theme.boardEffect !== "scanlines") {
    return;
  }

  ctx.save();
  ctx.beginPath();
  ctx.rect(ox, oy, gridW, gridH);
  ctx.clip();
  ctx.fillStyle = "rgba(208, 255, 214, 0.03)";
  for (let y = oy + 1; y <= oy + gridH; y += 4) {
    ctx.fillRect(ox, y, gridW, 1);
  }
  ctx.restore();
}

function drawFood(ctx, theme, apple, ox, oy, cell, time) {
  const cx = ox + apple.x * cell + cell / 2;
  const cy = oy + apple.y * cell + cell / 2;
  const size = Math.max(4, cell * 0.32);

  ctx.save();

  switch (theme.foodMode) {
    case "pulse-orb": {
      const pulse = 0.88 + Math.sin(time / 150) * 0.12;
      const radius = size * pulse;
      const glow = ctx.createRadialGradient(
        cx,
        cy,
        radius * 0.15,
        cx,
        cy,
        radius
      );
      glow.addColorStop(0, theme.foodSecondary);
      glow.addColorStop(1, theme.foodPrimary);
      ctx.shadowBlur = cell * 0.65;
      ctx.shadowColor = "rgba(255, 233, 92, 0.52)";
      ctx.fillStyle = glow;
      ctx.beginPath();
      ctx.arc(cx, cy, radius, 0, Math.PI * 2);
      ctx.fill();
      if (theme.foodOutline) {
        ctx.lineWidth = Math.max(2, cell * 0.08);
        ctx.strokeStyle = theme.foodOutline;
        ctx.stroke();
      }
      break;
    }
    case "x-mark": {
      ctx.strokeStyle = theme.foodPrimary;
      ctx.lineWidth = Math.max(2, cell * 0.09);
      ctx.lineCap = "square";
      ctx.beginPath();
      ctx.moveTo(cx - size * 0.7, cy - size * 0.7);
      ctx.lineTo(cx + size * 0.7, cy + size * 0.7);
      ctx.moveTo(cx + size * 0.7, cy - size * 0.7);
      ctx.lineTo(cx - size * 0.7, cy + size * 0.7);
      ctx.stroke();
      break;
    }
    case "pixel-seed": {
      const pixel = Math.max(3, Math.floor(cell * 0.24));
      ctx.fillStyle = theme.foodPrimary;
      ctx.fillRect(cx - pixel, cy - pixel, pixel * 2, pixel * 2);
      break;
    }
    case "glyph": {
      const glyph = Math.floor(time / 320) % 2 === 0 ? theme.foodGlyph : "*";
      ctx.fillStyle = theme.foodPrimary;
      ctx.shadowBlur = cell * 0.16;
      ctx.shadowColor = "rgba(255,255,255,0.24)";
      ctx.font = `${Math.floor(cell * 0.95)}px "Courier New", monospace`;
      ctx.textAlign = "center";
      ctx.textBaseline = "middle";
      ctx.fillText(glyph, cx, cy + 1);
      break;
    }
    case "berry":
    default: {
      const radius = size;
      const grd = ctx.createRadialGradient(cx, cy, radius * 0.18, cx, cy, radius);
      grd.addColorStop(0, "rgba(255,255,255,0.8)");
      grd.addColorStop(1, theme.foodPrimary);
      ctx.fillStyle = grd;
      ctx.beginPath();
      ctx.arc(cx, cy, radius, 0, Math.PI * 2);
      ctx.fill();
      if (theme.foodOutline) {
        ctx.lineWidth = Math.max(2, cell * 0.08);
        ctx.strokeStyle = theme.foodOutline;
        ctx.stroke();
      }
      ctx.fillStyle = theme.foodSecondary;
      ctx.beginPath();
      ctx.arc(cx - radius * 0.24, cy - radius * 0.25, radius * 0.28, 0, Math.PI * 2);
      ctx.fill();
      break;
    }
  }

  ctx.restore();
}

function drawTubeSnake(ctx, centers, colors, tubeWidth, theme, wrapBounds = null) {
  if (!centers || centers.length === 0) return;

  function strokeRoundedPath(pts, width = tubeWidth, color = colors.body) {
    if (pts.length < 2) return;

    ctx.strokeStyle = color;
    ctx.lineWidth = width;
    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    ctx.shadowBlur = theme.shadowBlur || 0;
    ctx.shadowColor =
      color === colors.head ? theme.tubeHeadShadow : theme.tubeShadow;
    ctx.shadowOffsetY = theme.shadowOffsetY || 0;
    ctx.beginPath();
    ctx.moveTo(pts[0].x, pts[0].y);

    if (pts.length === 2) {
      ctx.lineTo(pts[1].x, pts[1].y);
      ctx.stroke();
      return;
    }

    const cornerRadius = width * 0.78;

    for (let i = 1; i < pts.length - 1; i++) {
      const prev = pts[i - 1];
      const current = pts[i];
      const next = pts[i + 1];
      const inDx = current.x - prev.x;
      const inDy = current.y - prev.y;
      const outDx = next.x - current.x;
      const outDy = next.y - current.y;
      const inLen = Math.hypot(inDx, inDy);
      const outLen = Math.hypot(outDx, outDy);

      if (inLen < 0.001 || outLen < 0.001) {
        ctx.lineTo(current.x, current.y);
        continue;
      }

      const cross = inDx * outDy - inDy * outDx;
      const dot = inDx * outDx + inDy * outDy;

      if (Math.abs(cross) < 0.001 || dot < 0) {
        ctx.lineTo(current.x, current.y);
        continue;
      }

      const radius = Math.min(cornerRadius, inLen * 0.5, outLen * 0.5);
      const startX = current.x - (inDx / inLen) * radius;
      const startY = current.y - (inDy / inLen) * radius;

      ctx.lineTo(startX, startY);
      ctx.arcTo(current.x, current.y, next.x, next.y, radius);
    }

    const last = pts[pts.length - 1];
    ctx.lineTo(last.x, last.y);
    ctx.stroke();
    ctx.shadowBlur = 0;
    ctx.shadowColor = "transparent";
    ctx.shadowOffsetY = 0;
  }

  function drawOneTube(points) {
    const tailToHead = points.slice().reverse();
    strokeRoundedPath(tailToHead, tubeWidth, colors.body);

    const headWindow = Math.min(4, tailToHead.length);
    if (headWindow >= 2) {
      strokeRoundedPath(
        tailToHead.slice(-headWindow),
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
  if (redraw && getState()) draw(0);
}

export function draw(alpha = 0) {
  ctx.save();
  const state = getState();
  if (!state) {
    ctx.restore();
    return;
  }
  const settings = getSettings();
  const theme = getThemeProfile(settings.theme);
  const W = canvas.width / dpr;
  const H = canvas.height / dpr;
  const N = state.grid;
  const cell = Math.floor(Math.min(W, H) / N);
  const gridW = cell * N;
  const gridH = cell * N;
  const ox = Math.floor((W - gridW) / 2);
  const oy = Math.floor((H - gridH) / 2);
  const time = performance.now();

  ctx.clearRect(0, 0, W, H);
  const lerp = (a, b, t) => a * (1 - t) + b * t;
  const colGrid = theme.gridColor;

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

  drawBoardBackground(ctx, theme, ox, oy, gridW, gridH, cell, time);

  if (theme.squareGrid) {
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
  }

  for (const a of state.apples) {
    drawFood(ctx, theme, a, ox, oy, cell, time);
  }

  const prev = state.prevSnake || state.snake;
  const centers = state.snake.map((seg, i) => {
    const from = prev[i] || seg;
    return interpCell(from, seg, alpha);
  });
  if (!centers.length) {
    ctx.restore();
    return;
  }

  const bodyGradient =
    theme.bodyColorEnd && centers.length > 1
      ? (() => {
          const tail = centers[centers.length - 1];
          const head = centers[0];
          const gradient = ctx.createLinearGradient(
            tail.x,
            tail.y,
            head.x,
            head.y
          );
          gradient.addColorStop(0, theme.bodyColor);
          gradient.addColorStop(1, theme.bodyColorEnd);
          return gradient;
        })()
      : theme.bodyColor;

  const effectiveSnakeStyle = theme.forceBlockStyle
    ? "blocks"
    : settings.snakeStyle || "tube";

  if (effectiveSnakeStyle === "tube") {
    const tubeWidth = Math.max(6, Math.floor(cell * 0.74));
    drawTubeSnake(
      ctx,
      centers,
      { head: theme.headColor, body: bodyGradient },
      tubeWidth,
      theme,
      state.walls ? null : { ox, oy, gridW, gridH }
    );
  } else {
    const pad = Math.max(1, Math.floor(cell * 0.12));
    const sz = cell - pad * 2;
    const r =
      theme.blockRadius === "square"
        ? 0
        : theme.blockRadius === "pebble"
          ? Math.min(18, Math.floor(sz * 0.45))
          : Math.min(8, Math.floor(sz * 0.18));
    centers.forEach((c, i) => {
      const isHead = i === 0;
      const color = isHead ? theme.headColor : bodyGradient;
      const rx = Math.round(c.x - cell / 2 + pad);
      const ry = Math.round(c.y - cell / 2 + pad);
      ctx.shadowBlur = theme.shadowBlur || 0;
      ctx.shadowColor = isHead ? theme.tubeHeadShadow : theme.tubeShadow;
      ctx.shadowOffsetY = theme.shadowOffsetY || 0;
      ctx.fillStyle = color;
      roundRectPath(ctx, rx, ry, sz, sz, r);
      ctx.fill();
      ctx.shadowBlur = 0;
      ctx.shadowColor = "transparent";
      ctx.shadowOffsetY = 0;
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

  drawThemeOverlay(ctx, theme, ox, oy, gridW, gridH);
  ctx.restore();
}
