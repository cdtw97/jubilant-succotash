// Centralized theme color cache and application
let THEME_COLORS = {
  grid: '#2a2a2a',
  accent: '#4CAF50',
  accent2: '#9CCC65',
  snakeClassicHead: '#ffffff',
  snakeClassicBody: '#88ff88',
  snakeTubeHead: '#ffffff',
  snakeTubeBody: '#6fdc6f',
};

export function getThemeColors() { return THEME_COLORS; }

export function refreshThemeColors() {
  const root = document.documentElement;
  const cs = getComputedStyle(root);
  const read = (v) => cs.getPropertyValue(v).trim();
  THEME_COLORS = {
    ...THEME_COLORS,
    grid: read('--grid') || read('--grid-color') || THEME_COLORS.grid,
    accent: read('--accent') || THEME_COLORS.accent,
    accent2: read('--accent-2') || THEME_COLORS.accent2,
    snakeClassicHead: read('--snake-classic-head') || THEME_COLORS.snakeClassicHead,
    snakeClassicBody: read('--snake-classic-body') || THEME_COLORS.snakeClassicBody,
    snakeTubeHead: read('--snake-tube-head') || THEME_COLORS.snakeTubeHead,
    snakeTubeBody: read('--snake-tube-body') || THEME_COLORS.snakeTubeBody,
  };
}

export function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  document.body && document.body.setAttribute('data-theme', theme);
  refreshThemeColors();
}
