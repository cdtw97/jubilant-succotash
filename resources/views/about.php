<?php
declare(strict_types=1);

$title = $title ?? 'About';
$activeNav = 'about';
$pageClass = 'about-page';

ob_start();
?>
<main class="page-shell container-xxl px-0">
    <section class="hero-card hero-card--white">
        <div class="page-kicker">About</div>
        <h1 class="page-title"><?= e($heading ?? 'About MyFrancis') ?></h1>
        <p class="page-lead">
            <?= e($text ?? 'A lightweight MVC foundation shaped into a bright, game-inspired interface.') ?>
        </p>
    </section>

    <section class="card-grid">
        <article class="content-card content-card--grass">
            <h2>What Changed</h2>
            <ul class="feature-list">
                <li>Shared base layout with a score-bar style navigation header</li>
                <li>Global checkerboard background inspired by the Snake board</li>
                <li>Rounded card surfaces for framework pages and the game shell</li>
            </ul>
        </article>

        <article class="content-card content-card--white">
            <h2>How It Fits</h2>
            <p>The framework keeps the same routing, controller, middleware, and asset conventions while the UI becomes one cohesive system.</p>
            <p>HTML pages stay in the web lane, JSON stays in the controller layer, and the Snake experience now looks like it belongs to the rest of the app.</p>
        </article>
    </section>
</main>
<?php
$content = (string) ob_get_clean();

require __DIR__ . '/layouts/base.php';
