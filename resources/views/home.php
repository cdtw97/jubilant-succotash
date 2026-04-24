<?php
declare(strict_types=1);

$title = $title ?? 'Home';
$activeNav = 'home';
$pageClass = 'home-page funnel-home';

ob_start();
?>
<main class="page-shell funnel-shell">
    <section class="hero-card hero-card--grass funnel-hero">
        <div class="funnel-hero__content">
            <div class="page-kicker">Precision Engineered Arcade Rush</div>
            <h1 class="page-title funnel-title">Command the board. Own the run.</h1>
            <p class="page-lead funnel-lead">
                <?= e($text ?? 'A Google Snake-inspired arcade build with fast restarts, five curated themes, tuned controls, and a polished game shell that makes jumping in feel instant.') ?>
            </p>

            <div class="feature-chip-row">
                <span class="feature-chip">5 soul-shifting themes</span>
                <span class="feature-chip">Ultra-Fluid movement</span>
                <span class="feature-chip">Your Rules, Your Run</span>
                <span class="feature-chip">Swipe + keyboard precision</span>
            </div>

            <div class="hero-actions">
                <a class="action-button action-button--primary" href="<?= e(route('snake.show')) ?>">Launch Run</a>
                <a class="action-button action-button--secondary" href="#home-features">See What Changes The Game</a>
            </div>

            <div class="hero-proof">
                <div class="hero-proof__item">
                    <strong>Change the soul</strong>
                    <span>Cyber-Grid, E-Ink, Classic Handheld, Living Forest, or Terminal in one tap.</span>
                </div>
                <div class="hero-proof__item">
                    <strong>Own the rules</strong>
                    <span>Speed, grid size, walls, apples, and style all bend to your run.</span>
                </div>
            </div>
        </div>

        <aside class="funnel-hero__panel" aria-label="Game preview">
            <div class="preview-card">
                <div class="preview-card__header">
                    <span class="preview-pill">System Live</span>
                    <span class="preview-dots" aria-hidden="true"></span>
                </div>

                <div class="preview-stat-grid">
                    <div class="preview-stat">
                        <strong>5</strong>
                        <span>Theme worlds</span>
                    </div>
                    <div class="preview-stat">
                        <strong>2</strong>
                        <span>Control styles</span>
                    </div>
                    <div class="preview-stat">
                        <strong>7</strong>
                        <span>Speed levels</span>
                    </div>
                    <div class="preview-stat">
                        <strong>Always</strong>
                        <span>Another run</span>
                    </div>
                </div>

                <div class="preview-board" aria-hidden="true">
                    <?php for ($row = 0; $row < 6; $row++): ?>
                        <?php for ($column = 0; $column < 6; $column++): ?>
                            <?php
                            $classes = ['preview-board__cell'];
                            if (($row === 2 && $column >= 1 && $column <= 3) || ($row === 3 && $column === 3)) {
                                $classes[] = 'is-snake';
                            }
                            if ($row === 2 && $column === 3) {
                                $classes[] = 'is-head';
                            }
                            if ($row === 1 && $column === 4) {
                                $classes[] = 'is-food';
                            }
                            ?>
                            <span class="<?= e(implode(' ', $classes)) ?>"></span>
                        <?php endfor; ?>
                    <?php endfor; ?>
                </div>

                <div class="preview-highlights">
                    <div class="preview-highlight">
                        <strong>Ultra-Fluid Movement</strong>
                        <span>Smooth turns, instant restarts, and clean controls keep the chase locked in.</span>
                    </div>
                    <div class="preview-highlight">
                        <strong>Visual Sovereignty</strong>
                        <span>Five distinct moods let you jump from neon chaos to retro focus in a single click.</span>
                    </div>
                </div>
            </div>
        </aside>
    </section>

    <section class="funnel-metrics" aria-label="Snake game highlights">
        <article class="metric-card">
            <strong>One click, new soul</strong>
            <span>Jump from Cyber-Grid to E-Ink, Classic Handheld, Living Forest, or Terminal without breaking stride.</span>
        </article>
        <article class="metric-card">
            <strong>Your Rules, Your Run</strong>
            <span>Dial in grid size, speed, apples, walls, and snake style to build the exact pressure you want.</span>
        </article>
        <article class="metric-card">
            <strong>Precision under pressure</strong>
            <span>Readable HUD, sharp contrast, and fast feedback keep every decision clear when the board tightens.</span>
        </article>
    </section>

    <section class="card-grid marketing-grid" id="home-features">
        <article class="content-card content-card--white marketing-card">
            <div class="card-icon card-icon--blue" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M4 7.5A3.5 3.5 0 0 1 7.5 4h9A3.5 3.5 0 0 1 20 7.5v9a3.5 3.5 0 0 1-3.5 3.5h-9A3.5 3.5 0 0 1 4 16.5Zm4 1.3v2.2h8V8.8Zm0 4.3v2.1h5.2v-2.1Z" fill="currentColor"/>
                </svg>
            </div>
            <h2>Precision Engineered</h2>
            <p>Every move feels immediate, every restart is instant, and every run throws you straight back into the hunt.</p>
        </article>

        <article class="content-card content-card--grass marketing-card">
            <div class="card-icon card-icon--green" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M12 4a8 8 0 1 0 8 8 8 8 0 0 0-8-8Zm-1.5 4.8h3v3.2h3.2v3h-3.2V18h-3v-3h-3.2v-3h3.2Z" fill="currentColor"/>
                </svg>
            </div>
            <h2>Your Rules, Your Run</h2>
            <p>Open the grid, raise the speed, turn walls on or off, and tune the board until it feels like your own personal gauntlet.</p>
        </article>

        <article class="content-card content-card--white marketing-card">
            <div class="card-icon card-icon--gold" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M12 4.5 4.8 8.2v7.1L12 19.5l7.2-4.2V8.2Zm0 3 4.2 2.2L12 11.9 7.8 9.7Zm-4.2 4.1 3.2 1.9v3.7l-3.2-1.9Zm8.4 5.6v-3.7l3.2-1.9v3.7Z" fill="currentColor"/>
                </svg>
            </div>
            <h2>Visual Sovereignty</h2>
            <p>Cyber-Grid, E-Ink, Classic Handheld, Living Forest, and Terminal each give the game a new soul with a single click.</p>
        </article>
    </section>

    <section class="theme-showcase">
        <div class="theme-showcase__intro">
            <div class="page-kicker">Five Theme Worlds</div>
            <h2 class="section-title">Five themes. Five moods. One click changes the soul of the game.</h2>
        </div>
        <div class="theme-pill-grid">
            <span class="theme-pill theme-pill--cyber">Cyber-Grid</span>
            <span class="theme-pill theme-pill--ink">E-Ink</span>
            <span class="theme-pill theme-pill--handheld">Classic Handheld</span>
            <span class="theme-pill theme-pill--forest">Living Forest</span>
            <span class="theme-pill theme-pill--terminal">Terminal</span>
        </div>
    </section>

    <section class="hero-card hero-card--white closing-cta">
        <div class="page-kicker">Board Ready</div>
        <h2 class="section-title">Pick your world, set your rules, and launch the run.</h2>
        <p class="page-lead">This is Snake reimagined for repeat plays: faster starts, sharper style, and total control over how the chase feels.</p>
        <div class="hero-actions">
            <a class="action-button action-button--primary" href="<?= e(route('snake.show')) ?>">Launch Command</a>
            <a class="action-button action-button--secondary" href="<?= e(route('pages.about')) ?>">See The Build Story</a>
        </div>
    </section>
</main>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/base.php';
