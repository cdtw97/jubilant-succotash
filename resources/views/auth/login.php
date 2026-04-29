<?php
declare(strict_types=1);

$title = $title ?? 'Login';
$pageClass = 'auth-page';
$activeNav = '';
$errors = isset($errors) && is_array($errors) ? $errors : [];
$old = isset($old) && is_array($old) ? $old : [];

ob_start();
?>
<main class="page-shell auth-shell container-xxl px-0">
    <section class="hero-card hero-card--white auth-hero">
        <div class="page-kicker">Secure Access</div>
        <h1 class="page-title">Log in and make every run count.</h1>
        <p class="page-lead">Sign in to store high scores, build a complete run history, and keep your Snake profile synced every time a match ends.</p>
    </section>

    <section class="card-grid auth-grid">
        <article class="content-card content-card--grass auth-copy-card">
            <h2>What unlocks when you sign in</h2>
            <ul class="feature-list">
                <li>Persistent personal bests across every theme and board setup</li>
                <li>Telemetry-backed recent run history on your profile page</li>
                <li>Secure session-based access with CSRF protection on every form and game event</li>
            </ul>
        </article>

        <article class="content-card content-card--white auth-form-card">
            <h2>Login</h2>

            <?php if (isset($errors['form'])): ?>
                <div class="form-alert form-alert--error"><?= e((string) $errors['form']) ?></div>
            <?php endif; ?>

            <form action="<?= e(route('auth.login.store')) ?>" class="auth-form" method="post" novalidate>
                <?= csrf_field() ?>

                <label class="auth-field" for="identity">
                    <span>Email or username</span>
                    <input
                        autocomplete="username"
                        id="identity"
                        name="identity"
                        required
                        type="text"
                        value="<?= e((string) ($old['identity'] ?? '')) ?>"
                    >
                    <?php if (isset($errors['identity'])): ?>
                        <small><?= e((string) $errors['identity']) ?></small>
                    <?php endif; ?>
                </label>

                <label class="auth-field" for="password">
                    <span>Password</span>
                    <input
                        autocomplete="current-password"
                        id="password"
                        name="password"
                        required
                        type="password"
                    >
                    <?php if (isset($errors['password'])): ?>
                        <small><?= e((string) $errors['password']) ?></small>
                    <?php endif; ?>
                </label>

                <div class="auth-actions">
                    <button class="action-button action-button--primary" type="submit">Login</button>
                    <a class="action-button action-button--secondary" href="<?= e(route('auth.register')) ?>">Create account</a>
                </div>
            </form>
        </article>
    </section>
</main>
<?php
$content = (string) ob_get_clean();

require dirname(__DIR__) . '/layouts/base.php';
