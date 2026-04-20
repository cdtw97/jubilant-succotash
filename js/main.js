// Entry point, initialization
// This is the main entry point file. It connects all the modules and initializes the game.

// ----- Re-export functions to avoid circular dependencies -----
// This is a common pattern in modular JS. game.js and ui.js can import from this central file.
export * from "./audio.js";
export * from "./game.js";
export * from "./ui.js";

// ----- Imports -----
import {
  initEventListeners,
  syncUiToSettings,
  resizeCanvas,
  updateHUD,
} from "./ui.js";
import { resetGame, getState } from "./game.js";

// ----- Updates -----
const updateOverlay = document.getElementById("updateOverlay");
const updateOverlayText = document.getElementById("updateOverlayText");

function showUpdateOverlay(text = "Update available, fetching update…") {
  if (updateOverlayText) updateOverlayText.textContent = text;
  updateOverlay?.classList.remove("hidden");
}
function hideUpdateOverlay() {
  updateOverlay?.classList.add("hidden");
}
// 1) Listen for SW messages (covers mobile PWA cold starts)
navigator.serviceWorker?.addEventListener('message', (event) => {
  const data = event.data || {};
  // Don’t show on first-ever install (no controller yet)
  if (!navigator.serviceWorker.controller) return;

  if (data.type === 'SW_INSTALLING') {
    showUpdateOverlay(); // fetching & caching new version
  } else if (data.type === 'SW_ACTIVATED') {
    showUpdateOverlay('Update installed. Reloading…');
    setTimeout(() => window.location.reload(), 80);
  }
});

// 2) Still handle controllerchange (desktop + some mobiles)
navigator.serviceWorker?.addEventListener('controllerchange', () => {
  showUpdateOverlay('Update installed. Reloading…');
  setTimeout(() => window.location.reload(), 80);
});



// ----- Settings & Storage -----
let settings = {};

export const getSettings = () => settings;

export function saveSettings(newSettings) {
  settings = newSettings;
  localStorage.setItem("snake_settings_v1", JSON.stringify(settings));
}
async function registerServiceWorker() {
  if (!("serviceWorker" in navigator)) return;

  try {
    // Important: updateViaCache:'none' so sw.js never sticks in HTTP cache
    const reg = await navigator.serviceWorker.register("sw.js", { updateViaCache: "none" });

    // If there’s already a waiting SW, take it now
    if (reg.waiting) {
      showUpdateOverlay();
      reg.waiting.postMessage({ type: "SKIP_WAITING" });
    }

    // When a new SW is found (installing), show overlay and fast-track it
    reg.addEventListener("updatefound", () => {
      const newSW = reg.installing;
      if (!newSW) return;

      if (navigator.serviceWorker.controller) {
    showUpdateOverlay();
  }

      newSW.addEventListener("statechange", () => {
        // When it finishes installing and we already have a controller, it's an update
        if (newSW.state === "installed" && navigator.serviceWorker.controller) {
          reg.waiting?.postMessage({ type: "SKIP_WAITING" });
        }
      });
    });

    // When the new SW takes control, reload to get fresh files
    navigator.serviceWorker.addEventListener("controllerchange", () => {
      showUpdateOverlay("Update installed. Reloading…");
      // Give the browser a beat to swap controllers
      setTimeout(() => window.location.reload(), 80);
    });

    // Be proactive: check on load, when tab becomes visible, and every 5 minutes
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
    theme: "classic",
    snakeStyle: "tube",
    masterVolume: 0.4,
    sfxVolume: 1.0,
  };
  try {
    return Object.assign(def, JSON.parse(localStorage.getItem("snake_settings_v1") || "{}")
    );
  } catch {
    return def;
  }
}

export function getBest() {
  const key = `snake_best_v1_${settings.gridSize}_${
    settings.walls ? "walls" : "wrap"
  }`;
  return parseInt(localStorage.getItem(key), 10) || 0;
}

export function saveBest(v) {
  const key = `snake_best_v1_${settings.gridSize}_${
    settings.walls ? "walls" : "wrap"
  }`;
  localStorage.setItem(key, String(v));
}

export function applyTheme(name) {
  document.body.setAttribute("data-theme", name);
}

// ----- Initialization -----
function init() {
  settings = loadSettings();
  initEventListeners();
  
  // 1. Create the game state first.
  resetGame();
  
  // 2. Now that state exists, sync the UI to it.
  syncUiToSettings();
  updateHUD();
}

// Start the game
registerServiceWorker();
init();