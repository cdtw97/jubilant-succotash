// Core game logic, state, and loop
// This file contains the core game logic, state, and game loop.

// ----- Imports -----
import {
  updateHUD,
  updateOverlay,
  draw,
  setOverlayVisibility,
  setRunStatus,
  resetRunStatus,
  startTimerDisplay,
  stopTimerDisplay,
  resetTimerDisplay,
  showReadyOverlay,
} from "./ui.js";
import {
  getSettings,
  getBest,
  setBest,
  playSound,
  submitGameRun,
} from "./main.js";

// ----- State Management -----
let state = null;
let rafId = null;

export const getState = () => state;

function stopLoop() {
  if (rafId !== null) {
    cancelAnimationFrame(rafId);
    rafId = null;
  }
}

// ----- Core Game Functions -----
export function resetGame() {
  stopLoop();

  const settings = getSettings();
  const N = settings.gridSize;
  const startLen = 4;
  const startX = Math.floor(N / 2);
  const startY = Math.floor(N / 2);
  const snake = [];

  for (let i = 0; i < startLen; i++) {
    snake.push({ x: startX - i, y: startY });
  }

  state = {
    status: "ready", // ready | running | paused | over
    grid: N,
    speed: settings.speed,
    stepMs: Math.max(50, Math.round(1000 / settings.speed)),
    lastTs: 0,
    acc: 0,
    snake,
    prevSnake: null,
    dir: { x: 1, y: 0 },
    nextDirs: [],
    apples: [],
    score: 0,
    best: getBest(),
    walls: settings.walls,
    applesOnBoard: settings.apples,
    startTime: null,
    elapsedTime: 0,
    scoreSubmitted: false,
  };

  while (state.apples.length < state.applesOnBoard) {
    if (!placeApple()) break;
  }

  resetRunStatus();
  showReadyOverlay();
  resetTimerDisplay();
  updateHUD();
  draw(0);
}

export function start() {
  if (!state || state.status === "running") return;

  stopLoop();
  state.status = "running";
  state.startTime = performance.now();
  state.lastTs = state.startTime;
  rafId = requestAnimationFrame(loop);
}

export function togglePause() {
  if (!state) return;

  if (state.status === "running") {
    stopLoop();
    state.status = "paused";
    if (state.startTime !== null) {
      state.elapsedTime += performance.now() - state.startTime;
    }
    state.startTime = null;
    stopTimerDisplay();
    updateOverlay("Paused", "Press Space/P to resume");
    setOverlayVisibility(true);
  } else if (state.status === "paused" || state.status === "ready") {
    setOverlayVisibility(false);
    start();
    startTimerDisplay();
  }
}

function loop(ts) {
  if (!state || state.status !== "running") {
    rafId = null;
    return;
  }

  const dt = ts - state.lastTs;
  state.lastTs = ts;
  state.acc += dt;

  while (state.acc >= state.stepMs && state.status === "running") {
    step();
    state.acc -= state.stepMs;
  }

  draw(state.acc / state.stepMs);

  if (state.status === "running") {
    rafId = requestAnimationFrame(loop);
  } else {
    rafId = null;
  }
}

function step() {
  state.prevSnake = state.snake.map((p) => ({ ...p }));

  if (state.nextDirs.length) {
    const nd = state.nextDirs.shift();
    if (!isOpposite(nd, state.dir)) {
      state.dir = nd;

      let directionStr;
      if (nd.y === -1) directionStr = "up";
      else if (nd.y === 1) directionStr = "down";
      else if (nd.x === -1) directionStr = "left";
      else if (nd.x === 1) directionStr = "right";

      playSound(directionStr);
    }
  }

  const head = state.snake[0];
  let nx = head.x + state.dir.x;
  let ny = head.y + state.dir.y;

  if (state.walls) {
    if (nx < 0 || ny < 0 || nx >= state.grid || ny >= state.grid) {
      return gameOver();
    }
  } else {
    if (nx < 0) nx = state.grid - 1;
    else if (nx >= state.grid) nx = 0;

    if (ny < 0) ny = state.grid - 1;
    else if (ny >= state.grid) ny = 0;
  }

  const willGrow = appleAt(nx, ny);
  for (let i = 0; i < state.snake.length - (willGrow ? 0 : 1); i++) {
    const s = state.snake[i];
    if (s.x === nx && s.y === ny) return gameOver();
  }

  state.snake.unshift({ x: nx, y: ny });

  if (willGrow) {
    removeAppleAt(nx, ny);
    state.score += 1;

    while (state.apples.length < state.applesOnBoard) {
      if (!placeApple()) break;
    }

    if (state.score > state.best) {
      state.best = setBest(state.score);
    }
  } else {
    state.snake.pop();
  }

  updateHUD();
}

function gameOver(
  title = "Game Over",
  subtitle = null
) {
  stopLoop();
  state.status = "over";
  state.best = setBest(Math.max(state.best, state.score));

  if (state.startTime !== null) {
    state.elapsedTime += performance.now() - state.startTime;
  }

  state.startTime = null;
  stopTimerDisplay();
  updateHUD();
  const overlaySubtitle = subtitle ?? `Score: ${state.score} - Best: ${state.best}`;
  updateOverlay(title, overlaySubtitle);
  setOverlayVisibility(true);

  if (!state.scoreSubmitted) {
    state.scoreSubmitted = true;
    setRunStatus("Syncing run telemetry...", "pending");
    void submitFinalScore(state);
  }
}

function buildGameOverSubtitle(gameState, extraNote = "") {
  const base = `Score: ${gameState.score} - Best: ${gameState.best}`;

  if (extraNote === "") {
    return base;
  }

  return `${base}<br>${extraNote}`;
}

async function submitFinalScore(gameState) {
  if (!gameState) return;

  try {
    const settings = getSettings();
    const result = await submitGameRun({
      theme: settings.theme,
      board_size: settings.boardSize,
      grid_size: gameState.grid,
      speed_level: gameState.speed,
      apple_type: "standard",
      apple_count: gameState.applesOnBoard,
      walls_enabled: gameState.walls,
      snake_style: settings.snakeStyle || "tube",
      score: gameState.score,
      length: gameState.snake.length,
      duration_seconds: Math.floor(gameState.elapsedTime / 1000),
    });

    const submittedBest = Number(result?.data?.personal_best_score ?? 0);
    if (submittedBest > 0) {
      const nextBest = setBest(submittedBest);

      if (state === gameState) {
        state.best = nextBest;
        updateHUD();
        updateOverlay(
          "Game Over",
          buildGameOverSubtitle(
            state,
            '<span class="overlay-note overlay-note--success">Run saved to your profile.</span>'
          )
        );
        setOverlayVisibility(true);
      }
    }

    if (state === gameState) {
      setRunStatus("Run saved to your profile.", "success");
    }
  } catch (error) {
    const errorCode = error?.payload?.error?.code || "";
    const isAuthError = errorCode === "authentication_required";
    const statusMessage = isAuthError
      ? "Sign in to save runs, scores, and recent history."
      : "Run telemetry could not be saved right now.";
    const overlayNoteClass = isAuthError
      ? "overlay-note overlay-note--warning"
      : "overlay-note overlay-note--error";
    const tone = isAuthError ? "warning" : "error";

    if (state === gameState) {
      updateOverlay(
        "Game Over",
        buildGameOverSubtitle(
          state,
          `<span class="${overlayNoteClass}">${statusMessage}</span>`
        )
      );
      setOverlayVisibility(true);
    }

    if (state === gameState) {
      setRunStatus(statusMessage, tone);
    }
    console.warn("Run telemetry failed:", error);
  }
}

// ----- Input Handling -----
export function handleKey(e) {
  if (!state) return;

  const k = e.key;

  // --- Directional Input ---
  if (["ArrowUp", "w", "W"].includes(k)) queueDir(0, -1);
  else if (["ArrowDown", "s", "S"].includes(k)) queueDir(0, 1);
  else if (["ArrowLeft", "a", "A"].includes(k)) queueDir(-1, 0);
  else if (["ArrowRight", "d", "D"].includes(k)) queueDir(1, 0);

  // --- Action Input ---
  else if (k === " " || k === "p" || k === "P") {
    togglePause();
  } else if (k === "r" || k === "R") {
    resetGame();
    setOverlayVisibility(true);
  } else if (k === "Enter" && state.status !== "running") {
    if (state.status === "over") {
      resetGame();
      start();
      startTimerDisplay();
      setOverlayVisibility(false);
    } else {
      togglePause();
    }
  }
}

function queueDir(x, y) {
  if (!state) return;
  if (state.status !== "running") return;

  const nd = { x, y };
  const last = state.nextDirs.length
    ? state.nextDirs[state.nextDirs.length - 1]
    : state.dir;

  if (isOpposite(nd, last) || isSame(nd, last)) return;

  state.nextDirs.push(nd);
  if (state.nextDirs.length > 2) state.nextDirs.shift();
}

const isOpposite = (a, b) => a.x === -b.x && a.y === -b.y;
const isSame = (a, b) => a.x === b.x && a.y === b.y;

// ----- Apple Logic -----
function randInt(n) {
  return (Math.random() * n) | 0;
}

function appleAt(x, y) {
  return state.apples.some((a) => a.x === x && a.y === y);
}

function removeAppleAt(x, y) {
  state.apples = state.apples.filter((a) => !(a.x === x && a.y === y));
}

function placeApple() {
  const N = state.grid;
  const taken = new Set(state.snake.map((p) => `${p.x},${p.y}`));

  for (let tries = 0; tries < 1000; tries++) {
    const x = randInt(N);
    const y = randInt(N);
    const key = `${x},${y}`;

    if (!taken.has(key) && !appleAt(x, y)) {
      state.apples.push({ x, y });
      return true;
    }
  }

  gameOver("You filled the board!", "Press R to play again.");
  return false;
}
