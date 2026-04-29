<?php
declare(strict_types=1);

use MyFrancis\Core\Application;
use MyFrancis\Core\Request;

/** @var \MyFrancis\Config\AppConfig $app */
$pageTitle = isset($title) && is_string($title) && $title !== ''
    ? $title . ' | ' . $app->name
    : $app->name;

$activeNav = isset($activeNav) && is_string($activeNav) ? $activeNav : '';
$pageClass = isset($pageClass) && is_string($pageClass) ? trim($pageClass) : '';
$styles = isset($styles) && is_array($styles) ? $styles : [];
$scripts = isset($scripts) && is_array($scripts) ? $scripts : [];
$bodyAttributes = isset($bodyAttributes) && is_array($bodyAttributes) ? $bodyAttributes : [];
$content = isset($content) && is_string($content) ? $content : '';

$bodyClass = trim('site-body ' . $pageClass);
$bodyAttributeString = '';
$currentRequest = null;
$authenticatedUser = null;
$isAuthenticated = false;
$flashMessage = session_flash('status');
$flashLevel = session_flash('status_level', 'info');

$application = Application::getInstance();

if ($application instanceof Application) {
    $requestService = $application->container()->get(Request::class);

    if ($requestService instanceof Request) {
        $currentRequest = $requestService;
        $candidateUser = $requestService->attribute('auth.user');
        $authenticatedUser = is_array($candidateUser) ? $candidateUser : null;
        $isAuthenticated = $requestService->attribute('auth.check', false) === true;
    }
}

foreach ($bodyAttributes as $name => $value) {
    if (! is_string($name) || $name === '') {
        continue;
    }

    $bodyAttributeString .= sprintf(' %s="%s"', e($name), e((string) $value));
}

$navLinks = [
    [
        'key' => 'home',
        'label' => 'Home',
        'url' => route('pages.home'),
        'icon' => '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 11.7 12 5l8 6.7V20a1 1 0 0 1-1 1h-4.8v-5.5H9.8V21H5a1 1 0 0 1-1-1z" fill="currentColor"/></svg>',
    ],
    [
        'key' => 'about',
        'label' => 'About',
        'url' => route('pages.about'),
        'icon' => '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 3.5A8.5 8.5 0 1 1 3.5 12 8.5 8.5 0 0 1 12 3.5Zm0 3a1.3 1.3 0 1 0 1.3 1.3A1.3 1.3 0 0 0 12 6.5Zm1.4 11v-6.8h-2.8v6.8Z" fill="currentColor"/></svg>',
    ],
    [
        'key' => 'play',
        'label' => 'Play',
        'url' => route('snake.show'),
        'icon' => '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 5.5v13a1 1 0 0 0 1.52.85l10-6.5a1 1 0 0 0 0-1.7l-10-6.5A1 1 0 0 0 7 5.5Z" fill="currentColor"/></svg>',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#4f8df6">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/5.3.8/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/theme.css')) ?>">
    <?php foreach ($styles as $href): ?>
        <?php if (is_string($href) && $href !== ''): ?>
            <link rel="stylesheet" href="<?= e($href) ?>">
        <?php endif; ?>
    <?php endforeach; ?>
    <title><?= e($pageTitle) ?></title>
</head>
<body class="<?= e($bodyClass) ?>"<?= $bodyAttributeString ?>>
    <div class="site-board" aria-hidden="true">
        <?php for ($index = 0; $index < 840; $index++): ?>
            <span class="site-board__cell"></span>
        <?php endfor; ?>
    </div>

    <div class="site-layer">
        <header class="scorebar-shell">
            <nav class="scorebar navbar navbar-expand-lg" aria-label="Primary">
                <div class="scorebar__inner container-fluid p-0">
                    <a class="scorebar-brand navbar-brand" href="<?= e(route('pages.home')) ?>">
                        <span class="scorebar-brand__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M7.5 6.2c3.7-1.7 8.2-.1 10 3.6.4.8-.1 1.8-1 1.9l-4 .4-2.4 2.6a1 1 0 0 1-1.4 0L6 11.5A3.5 3.5 0 0 1 7.5 6.2Zm7.8 6.6a1.1 1.1 0 1 0 1.1 1.1 1.1 1.1 0 0 0-1.1-1.1Z" fill="currentColor"/>
                            </svg>
                        </span>
                        <span class="scorebar-brand__text">
                            <strong><?= e($app->name) ?></strong>
                            <small>Google Snake UI</small>
                        </span>
                    </a>

                    <button
                        aria-controls="primaryNavigation"
                        aria-expanded="false"
                        aria-label="Toggle navigation"
                        class="navbar-toggler scorebar-toggler"
                        data-bs-target="#primaryNavigation"
                        data-bs-toggle="collapse"
                        type="button"
                    >
                        <span class="scorebar-toggler__line"></span>
                        <span class="scorebar-toggler__line"></span>
                        <span class="scorebar-toggler__line"></span>
                    </button>

                    <div class="collapse navbar-collapse scorebar-collapse" id="primaryNavigation">
                        <div class="scorebar-nav navbar-nav">
                            <?php foreach ($navLinks as $link): ?>
                                <?php
                                $isActive = $activeNav === $link['key'];
                                $linkClass = 'scorebar-link nav-link' . ($isActive ? ' is-active' : '');
                                ?>
                                <a
                                    class="<?= e($linkClass) ?>"
                                    href="<?= e($link['url']) ?>"
                                    <?= $isActive ? ' aria-current="page"' : '' ?>
                                >
                                    <span class="scorebar-link__icon"><?= $link['icon'] ?></span>
                                    <span><?= e($link['label']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <div class="scorebar-actions">
                            <?php if ($isAuthenticated && is_array($authenticatedUser)): ?>
                                <a class="scorebar-session" href="<?= e(route('user.profile')) ?>">
                                    <span class="scorebar-session__label">Signed In</span>
                                    <strong><?= e((string) ($authenticatedUser['username'] ?? 'Player')) ?></strong>
                                </a>

                                <form action="<?= e(route('auth.logout')) ?>" class="scorebar-logout-form" method="post">
                                    <?= csrf_field() ?>
                                    <button class="scorebar-action scorebar-action--ghost" type="submit">Logout</button>
                                </form>
                            <?php else: ?>
                                <a class="scorebar-action scorebar-action--ghost" href="<?= e(route('auth.login')) ?>">Login</a>
                                <a class="scorebar-action scorebar-action--primary" href="<?= e(route('auth.register')) ?>">Register</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </nav>
        </header>

        <?php if (is_string($flashMessage) && $flashMessage !== ''): ?>
            <?php
            $normalizedFlashLevel = is_string($flashLevel) && $flashLevel !== ''
                ? $flashLevel
                : 'info';
            ?>
            <div class="site-alert site-alert--<?= e($normalizedFlashLevel) ?>">
                <div class="site-alert__inner">
                    <strong><?= e(ucfirst($normalizedFlashLevel)) ?></strong>
                    <span><?= e($flashMessage) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?= $content ?>
    </div>

    <script src="<?= e(asset('vendor/bootstrap/5.3.8/js/bootstrap.bundle.min.js')) ?>"></script>
    <?php foreach ($scripts as $script): ?>
        <?php if (is_string($script) && $script !== ''): ?>
            <script src="<?= e($script) ?>"></script>
        <?php elseif (is_array($script) && isset($script['src']) && is_string($script['src']) && $script['src'] !== ''): ?>
            <?php $type = isset($script['type']) && is_string($script['type']) ? $script['type'] : 'text/javascript'; ?>
            <script src="<?= e($script['src']) ?>" type="<?= e($type) ?>"></script>
        <?php endif; ?>
    <?php endforeach; ?>
</body>
</html>
