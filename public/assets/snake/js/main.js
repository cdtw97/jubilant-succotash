// Entry point, initialization
// This is the main entry point file. It connects all the modules and initializes the game.

// ----- Re-export functions to avoid circular dependencies -----
export * from "./audio.js";
export * from "./game.js";
export * from "./ui.js";

// ----- Imports -----
import {
  initEventListeners,
  syncUiToSettings,
  updateHUD,
} from "./ui.js";
import { resetGame } from "./game.js";

// ----- Updates -----
const updateOverlay = document.getElementById("updateOverlay");
const updateOverlayText = document.getElementById("updateOverlayText");

function showUpdateOverlay(text = "Update available, fetching update...") {
  if (updateOverlayText) updateOverlayText.textContent = text;
  updateOverlay?.classList.remove("hidden");
}

// 1) Listen for SW messages (covers mobile PWA cold starts)
navigator.serviceWorker?.addEventListener("message", (event) => {
  const data = event.data || {};

  // Do not show on first-ever install when there is no active controller yet.
  if (!navigator.serviceWorker.controller) return;

  if (data.type === "SW_INSTALLING") {
    showUpdateOverlay();
  } else if (data.type === "SW_ACTIVATED") {
    showUpdateOverlay("Update installed. Reloading...");
    setTimeout(() => window.location.reload(), 80);
  }
});

// 2) Handle controller changes once.
navigator.serviceWorker?.addEventListener("controllerchange", () => {
  showUpdateOverlay("Update installed. Reloading...");
  setTimeout(() => window.location.reload(), 80);
});

// ----- Settings & Storage -----
let settings = {};
let bestScore = Number(document.body?.dataset.initialBestScore || 0);
const THEME_ALIASES = {
  classic: "living-forest",
  dark: "terminal",
  neon: "cyber-grid",
  retro: "classic-handheld",
};
const AVAILABLE_THEMES = new Set([
  "cyber-grid",
  "e-ink",
  "classic-handheld",
  "living-forest",
  "terminal",
]);

const runtimeConfig = {
  audioBaseUrl: document.body?.dataset.audioBaseUrl || "",
  csrfToken: document.body?.dataset.csrfToken || "",
  telemetryEndpoint: document.body?.dataset.telemetryEndpoint || "",
  authenticated: document.body?.dataset.authenticated === "1",
  loginUrl: document.body?.dataset.loginUrl || "",
  profileUrl: document.body?.dataset.profileUrl || "",
  initialBestScore: Number(document.body?.dataset.initialBestScore || 0),
  serviceWorkerUrl: document.body?.dataset.serviceWorkerUrl || "",
};

export const getSettings = () => settings;
export const getGameConfig = () => runtimeConfig;

export function normalizeTheme(name) {
  const normalized = THEME_ALIASES[name] ?? name;
  return AVAILABLE_THEMES.has(normalized) ? normalized : "living-forest";
}

export function saveSettings(newSettings) {
  settings = {
    ...newSettings,
    theme: normalizeTheme(newSettings?.theme),
  };
  localStorage.setItem("snake_settings_v1", JSON.stringify(settings));
}

async function registerServiceWorker() {
  if (!("serviceWorker" in navigator) || runtimeConfig.serviceWorkerUrl === "") {
    return;
  }

  try {
    const reg = await navigator.serviceWorker.register(
      runtimeConfig.serviceWorkerUrl,
      {
        updateViaCache: "none",
      }
    );

    if (reg.waiting) {
      showUpdateOverlay();
      reg.waiting.postMessage({ type: "SKIP_WAITING" });
    }

    reg.addEventListener("updatefound", () => {
      const newSW = reg.installing;
      if (!newSW) return;

      if (navigator.serviceWorker.controller) {
        showUpdateOverlay();
      }

      newSW.addEventListener("statechange", () => {
        if (
          newSW.state === "installed" &&
          navigator.serviceWorker.controller
        ) {
          reg.waiting?.postMessage({ type: "SKIP_WAITING" });
        }
      });
    });

    reg.update();
    document.addEventListener("visibilitychange", () => {
      if (document.visibilityState === "visible") reg.update();
    });
    setInterval(() => reg.update(), 5 * 60 * 1000);
  } catch (err) {
    console.warn("Service worker registration failed:", err);
  }
}

function loadSettings() {
  const def = {
    boardSize: "medium",
    gridSize: 21,
    speed: 8,
    apples: 1,
    walls: true,
    theme: "living-forest",
    snakeStyle: "tube",
    masterVolume: 0.4,
    sfxVolume: 1.0,
  };

  try {
    const loaded = Object.assign(
      def,
      JSON.parse(localStorage.getItem("snake_settings_v1") || "{}")
    );

    loaded.theme = normalizeTheme(loaded.theme);
    return loaded;
  } catch {
    return def;
  }
}

export function getBest() {
  return bestScore;
}

export function setBest(v) {
  const nextBest = Number(v) || 0;
  bestScore = Math.max(bestScore, nextBest);
  return bestScore;
}

export async function submitGameRun(payload) {
  if (runtimeConfig.telemetryEndpoint === "") {
    return null;
  }

  const response = await fetch(runtimeConfig.telemetryEndpoint, {
    method: "POST",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json; charset=utf-8",
      "X-CSRF-Token": runtimeConfig.csrfToken,
    },
    body: JSON.stringify(payload),
  });

  const result = await response
    .json()
    .catch(() => ({ error: { message: "Run telemetry failed." } }));

  if (!response.ok) {
    const error = new Error(result?.error?.message || "Run telemetry failed.");
    error.payload = result;
    throw error;
  }

  const submittedBest = Number(result?.data?.personal_best_score ?? 0);
  if (submittedBest > 0) {
    setBest(submittedBest);
  }

  return result;
}

export function applyTheme(name) {
  document.body.setAttribute("data-theme", normalizeTheme(name));
}

// ----- Initialization -----
function init() {
  settings = loadSettings();
  bestScore = Number.isFinite(runtimeConfig.initialBestScore)
    ? runtimeConfig.initialBestScore
    : 0;
  resetGame();
  initEventListeners();
  syncUiToSettings();
  updateHUD();
}

registerServiceWorker();
init();
