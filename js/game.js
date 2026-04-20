// Core game logic, state, and loop
// This file contains the core game logic, state, and game loop.

// ----- Imports -----
import {
  updateHUD,
  updateOverlay,
  draw,
  setOverlayVisibility,
   startTimerDisplay,
  stopTimerDisplay, 
  resetTimerDisplay
} from "./ui.js";
import { getSettings, getBest, saveBest, playSound } from "./main.js";

// ----- State Management -----
let state = null;
let rafId = null;

export const getState = () => state;

// ----- Core Game Functions -----
export function resetGame() {
  cancelAnimationFrame(rafId);
  const settings = getSettings();
  const N = settings.gridSize;
  const startLen = 4;
  const startX = Math.floor(N / 2);
  const startY = Math.floor(N / 2);
  const snake = [];
  for (let i = 0; i < startLen; i++) snake.push({ x: startX - i, y: startY });

  state = {
    status: "ready", // ready | running | paused | over
    grid: N,
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
  };

  while (state.apples.length < state.applesOnBoard) placeApple();
  resetTimerDisplay();
  draw(0);
  
}

export function start() {
  if (state.status === "running") return;
  state.status = "running";
  state.startTime = performance.now(); 
  state.lastTs = performance.now();
  rafId = requestAnimationFrame(loop);
}

export function togglePause() {
  if (state.status === "running") {
    state.status = "paused";
    state.elapsedTime += performance.now() - state.startTime;
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
  rafId = requestAnimationFrame(loop);
  const dt = ts - state.lastTs;
  state.lastTs = ts;
  if (state.status !== "running") return;
  state.acc += dt;
  while (state.acc >= state.stepMs) {
    step();
    state.acc -= state.stepMs;
  }
  draw(state.acc / state.stepMs);
}

function step() {
  state.prevSnake = state.snake.map((p) => ({ ...p }));
  if (state.nextDirs.length) {
    const nd = state.nextDirs.shift();
    if (!isOpposite(nd, state.dir)){
      
      state.dir = nd;
       let directionStr;
      if (nd.y === -1) directionStr = 'up';
      else if (nd.y === 1) directionStr = 'down';
      else if (nd.x === -1) directionStr = 'left';
      else if (nd.x === 1) directionStr = 'right';
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
  const newHead = { x: nx, y: ny };

  const willGrow = appleAt(nx, ny);
  for (let i = 0; i < state.snake.length - (willGrow ? 0 : 1); i++) {
    const s = state.snake[i];
    if (s.x === nx && s.y === ny) return gameOver();
  }

  state.snake.unshift(newHead);
  if (willGrow) {
    removeAppleAt(nx, ny);
    state.score += 1;
    while (state.apples.length < state.applesOnBoard) placeApple();
    if (state.score > state.best) {
      state.best = state.score;
      saveBest(state.best);
    }
  } else {
    state.snake.pop();
  }
  updateHUD();
}

function gameOver() {
  state.status = "over";
  state.elapsedTime += performance.now() - state.startTime;
  state.startTime = null;
  stopTimerDisplay();
  
  updateOverlay("Game Over", `Score: ${state.score} · Best: ${state.best}`);
  setOverlayVisibility(true);
}

// ----- Input Handling -----
// js/game.js

export function handleKey(e) {
  const k = e.key;

  // --- Directional Input ---
  if (["ArrowUp", "w", "W"].includes(k)) queueDir(0, -1);
  else if (["ArrowDown", "s", "S"].includes(k)) queueDir(0, 1);
  else if (["ArrowLeft", "a", "A"].includes(k)) queueDir(-1, 0);
  else if (["ArrowRight", "d", "D"].includes(k)) queueDir(1, 0);
  
  // --- Action Input ---
  else if (k === " " || k === "p" || k === "P") {
    togglePause();
  } 
  else if (k === "r" || k === "R") {
    resetGame();
    setOverlayVisibility(true);
    updateHUD();
  } 
  else if (k === "Enter" && state.status !== "running") {
    // This is the corrected logic block
    if (state.status === 'over') {
      // If the game is over, RESET and start
      resetGame();
      start();
      startTimerDisplay();
      setOverlayVisibility(false);
    } else {
      // If the game is ready or paused, just start/resume
      togglePause();
    }
  }
}

function queueDir(x, y) {
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
    const x = randInt(N),
      y = randInt(N);
    const key = `${x},${y}`;
    if (!taken.has(key) && !appleAt(x, y)) {
      state.apples.push({ x, y });
      return;
    }
  }
  updateOverlay("You filled the board!", "Press R to play again.");
  setOverlayVisibility(true);
  state.status = "over";
}