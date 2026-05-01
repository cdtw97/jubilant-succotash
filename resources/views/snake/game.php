<?php

declare(strict_types=1);

$title = $title ?? 'Snake Game';
$activeNav = 'play';
$pageClass = 'snake-page';
$styles = [asset('assets/snake/css/style.css')];
$scripts = [
    [
        'src' => asset('assets/snake/js/main.js'),
        'type' => 'module',
    ],
];
$bodyAttributes = [
    'data-audio-base-url' => (string) ($audioBaseUrl ?? ''),
    'data-csrf-token' => (string) ($csrfToken ?? ''),
    'data-telemetry-endpoint' => (string) ($telemetrySubmissionUrl ?? ''),
    'data-authenticated' => !empty($isAuthenticated) ? '1' : '0',
    'data-login-url' => (string) ($loginUrl ?? ''),
    'data-profile-url' => (string) ($profileUrl ?? ''),
    'data-initial-best-score' => (string) ($initialBestScore ?? 0),
    'data-theme' => 'living-forest',
];

ob_start();
?>
<main class="page-shell snake-shell container-xxl px-0">
    <section class="snake-stage">
        <div class="snake-stage__frame">
            <div aria-label="Game notifications" class="notification-rail" id="notificationRail">
                <div class="notification-card notification-card--player" id="playerStatusNotice">
                    <div class="notification-card__header">
                        <span class="notification-card__eyebrow">Player status</span>
                        <button
                            aria-controls="playerStatusNotice"
                            aria-label="Dismiss player status"
                            class="notification-card__dismiss"
                            data-dismiss-target="playerStatusNotice"
                            type="button">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="player-status">
                        <?php if (!empty($isAuthenticated) && is_array($authUser ?? null)): ?>
                            <a class="player-status__pill player-status__pill--live" href="<?= e((string) ($profileUrl ?? '')) ?>">
                                Logged In: <?= e((string) (($authUser ?? [])['username'] ?? 'Player')) ?>
                            </a>
                        <?php else: ?>
                            <a class="player-status__pill player-status__pill--guest" href="<?= e((string) ($loginUrl ?? '')) ?>">
                                Guest Mode: Sign in to save runs
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="notification-card notification-card--run" id="runStatusNotice">
                    <div class="notification-card__header">
                        <span class="notification-card__eyebrow">Live run updates</span>
                        <button
                            aria-controls="runStatusNotice"
                            aria-label="Dismiss run status"
                            class="notification-card__dismiss"
                            data-dismiss-target="runStatusNotice"
                            type="button">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div
                        aria-atomic="true"
                        aria-live="polite"
                        class="run-status run-status--<?= !empty($isAuthenticated) ? 'live' : 'warning' ?>"
                        data-default-message="<?= e((string) ($telemetryStatusText ?? '')) ?>"
                        data-default-tone="<?= !empty($isAuthenticated) ? 'live' : 'warning' ?>"
                        id="runStatus"
                        role="status"><?= e((string) ($telemetryStatusText ?? '')) ?></div>
                </div>
            </div>
            <div class="wrap">
                <div class="game-card">
                    <div class="hud-2">
                        <div class="hud-topline">
                            <button
                                aria-label="Enter fullscreen"
                                class="hud-icon-btn"
                                id="fullscreenBtn"
                                title="Enter fullscreen"
                                type="button">
                                <svg aria-hidden="true" class="hud-icon-btn__icon" viewBox="0 0 24 24">
                                    <path
                                        id="fullscreenIconPath"
                                        d="M5 5h5v2H7v3H5V5Zm9 0h5v5h-2V7h-3V5ZM5 14h2v3h3v2H5v-5Zm12 0h2v5h-5v-2h3v-3Z"
                                        fill="currentColor" />
                                </svg>
                                <span class="hud-icon-btn__label" id="fullscreenLabel">Fullscreen</span>
                            </button>
                        </div>
                        <div class="hud-summary">
                            <div aria-label="Game stats" class="hud-stats">
                                <div class="badge badge--stat">
                                    <span class="badge__label">Score</span>
                                    <span class="badge__value" id="score">0</span>
                                </div>
                                <div class="badge badge--stat">
                                    <span class="badge__label">Best</span>
                                    <span class="badge__value" id="best">0</span>
                                </div>
                            </div>
                            <div aria-label="Current settings" class="hud-settings">

                                <div class="hud-settings__items">
                                    <div class="badge badge--setting" id="hudSpeed">
                                        <span class="badge__label">Speed</span>
                                        <span class="badge__value"><span id="speedLabel">8</span>/s</span>
                                    </div>
                                    <div class="badge badge--setting">
                                        <span class="badge__label">Time</span>
                                        <span class="badge__value" id="time">0:00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="canvas-wrap">
                        <canvas aria-label="Snake game" id="game" role="img"></canvas>
                        <div class="overlay" id="overlay">
                            <div class="title">Snake</div>
                            <div class="subtitle">
                                <p class="desktop-controls">
                                    <b>Controls:</b><br>
                                    Arrow keys / WASD to move <br>
                                    Space / P to pause <br>
                                    R to restart.
                                </p>
                                <p class="mobile-controls">
                                    <b>Controls:</b> <br>
                                    Swipe to move <br>
                                    Use on-screen buttons to pause or restart.
                                </p>
                            </div>
                            <div class="overlay-launch">
                                <button class="button" id="playBtn">Play</button>
                                <button class="button secondary" id="settingsBtn">Settings</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mobile-keys" id="mobileControls">
                    <div aria-label="Directional pad" class="mobile-pad">
                        <button aria-label="Up" class="dpad" id="btnUp">&#9650;</button>
                        <button aria-label="Left" class="dpad" id="btnLeft">&#9664;</button>
                        <button aria-label="Down" class="dpad" id="btnDown">&#9660;</button>
                        <button aria-label="Right" class="dpad" id="btnRight">&#9654;</button>
                        <button aria-label="Pause" class="icon-btn" id="pauseBtn">&#9208;</button>
                    </div>
                </div>
                <div class="settings-panel hidden" id="settings-panel">
                    <div class="sidebar">
                        <h2>Settings</h2>
                        <div class="settings-tabs">
                            <button class="tab-btn active" data-tab="gameplay">Gameplay</button>
                            <button class="tab-btn" data-tab="visuals">Visuals</button>
                            <button class="tab-btn" data-tab="sound">Sound</button>
                        </div>
                        <div class="settings-content">
                            <div class="tab-content active" id="gameplay">
                                <div class="control">
                                    <label for="grid">Grid size</label>
                                    <select id="grid">
                                        <option value="15">15 x 15 (roomy)</option>
                                        <option selected value="21">21 x 21 (classic)</option>
                                        <option value="25">25 x 25</option>
                                        <option value="31">31 x 31 (pro)</option>
                                    </select>
                                </div>
                                <div class="control">
                                    <label for="speed">Speed</label>
                                    <select id="speed">
                                        <option value="3">3 / sec</option>
                                        <option value="5">5 / sec</option>
                                        <option selected value="8">8 / sec</option>
                                        <option value="10">10 / sec</option>
                                        <option value="12">12 / sec</option>
                                        <option value="15">15 / sec</option>
                                        <option value="20">20 / sec</option>
                                    </select>
                                </div>
                                <div class="control">
                                    <label for="apples">Apples on board</label>
                                    <select id="apples">
                                        <option selected value="1">1</option>
                                        <option value="3">3</option>
                                        <option value="5">5</option>
                                        <option value="10">10</option>
                                    </select>
                                </div>
                                <div class="control">
                                    <label for="walls">Walls</label>
                                    <select id="walls">
                                        <option value="wrap">Wrap around edges</option>
                                        <option selected value="walls">Solid walls (no wrap)</option>
                                    </select>
                                </div>
                                <p class="hint restart-note">
                                    Applying these settings will restart your current game.
                                </p>
                            </div>
                            <div class="tab-content" id="visuals">
                                <div class="control setting-row" data-desktop-only>
                                    <label for="boardSize">Board Size</label>
                                    <select id="boardSize">
                                        <option value="small">Small</option>
                                        <option selected value="medium">Medium</option>
                                        <option value="large">Large</option>
                                    </select>
                                </div>
                                <div class="control">
                                    <label for="theme">Theme</label>
                                    <select id="theme">
                                        <option value="cyber-grid">Cyber-Grid (Synthwave)</option>
                                        <option value="e-ink">E-Ink (Minimalist)</option>
                                        <option value="classic-handheld">Classic Handheld (Game Boy)</option>
                                        <option selected value="living-forest">Living Forest (Organic)</option>
                                        <option value="terminal">Terminal (Hacker Ethos)</option>
                                    </select>
                                </div>
                                <div class="control">
                                    <label for="snakeStyle">Snake style</label>
                                    <select id="snakeStyle">
                                        <option selected value="tube">Full (tube)</option>
                                        <option value="blocks">Blocks (classic)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="tab-content" id="sound">
                                <div class="control">
                                    <label for="master-volume">Master Volume</label>
                                    <input
                                        id="master-volume"
                                        max="1"
                                        min="0"
                                        step="0.05"
                                        type="range"
                                        value="0.4">
                                </div>
                                <div class="control">
                                    <label for="sfx-volume">Snake Movement</label>
                                    <input
                                        id="sfx-volume"
                                        max="1"
                                        min="0"
                                        step="0.05"
                                        type="range"
                                        value="1.0">
                                </div>
                            </div>
                        </div>
                        <div class="settings-footer">
                            <p class="desktop-controls">
                                <b>Controls:</b> Arrow keys / WASD to move &middot; Space/P to pause &middot; R to restart.
                            </p>
                            <p class="mobile-controls">
                                <b>Controls:</b> Swipe to move &middot; Use on-screen buttons to pause or restart.
                            </p>
                        </div>
                        <div class="control">
                            <label>&nbsp;</label>
                            <div class="settings-actions">
                                <button id="backBtn">Back to Game</button>
                                <button id="applyBtn">Apply &amp; Restart</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div
                aria-live="polite"
                class="update-overlay hidden"
                id="updateOverlay"
                role="status">
                <div class="update-card">
                    <div aria-hidden="true" class="loader"></div>
                    <div id="updateOverlayText">Update available, fetching update...</div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php
$content = (string) ob_get_clean();

require dirname(__DIR__) . '/layouts/base.php';
