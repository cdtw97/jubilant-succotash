<?php
declare(strict_types=1);

$title = $title ?? 'Register';
$pageClass = 'auth-page';
$activeNav = '';
$errors = isset($errors) && is_array($errors) ? $errors : [];
$old = isset($old) && is_array($old) ? $old : [];

ob_start();
?>
<main class="page-shell auth-shell container-xxl px-0">
    <section class="hero-card hero-card--grass auth-hero">
        <div class="page-kicker">New Account</div>
        <h1 class="page-title">Create your Snake profile.</h1>
        <p class="page-lead">Register once to lock in your best runs, compare sessions over time, and keep telemetry flowing into a single account.</p>
    </section>

    <section class="card-grid auth-grid">
        <article class="content-card content-card--white auth-form-card">
            <h2>Register</h2>

            <?php if (isset($errors['form'])): ?>
                <div class="form-alert form-alert--error"><?= e((string) $errors['form']) ?></div>
            <?php endif; ?>

            <form action="<?= e(route('auth.register.store')) ?>" class="auth-form" method="post" novalidate>
                <?= csrf_field() ?>

                <label class="auth-field" for="username">
                    <span>Username</span>
                    <input
                        autocomplete="username"
                        id="username"
                        maxlength="32"
                        name="username"
                        required
                        type="text"
                        value="<?= e((string) ($old['username'] ?? '')) ?>"
                    >
                    <?php if (isset($errors['username'])): ?>
                        <small><?= e((string) $errors['username']) ?></small>
                    <?php endif; ?>
                </label>

                <label class="auth-field" for="email">
                    <span>Email address</span>
                    <input
                        autocomplete="email"
                        id="email"
                        maxlength="254"
                        name="email"
                        required
                        type="email"
                        value="<?= e((string) ($old['email'] ?? '')) ?>"
                    >
                    <?php if (isset($errors['email'])): ?>
                        <small><?= e((string) $errors['email']) ?></small>
                    <?php endif; ?>
                </label>

                <label class="auth-field" for="password">
                    <span>Password</span>
                    <input
                        autocomplete="new-password"
                        id="password"
                        minlength="12"
                        name="password"
                        required
                        type="password"
                    >
                    <?php if (isset($errors['password'])): ?>
                        <small><?= e((string) $errors['password']) ?></small>
                    <?php else: ?>
                        <small>Use at least 12 characters.</small>
                    <?php endif; ?>
                </label>

                <label class="auth-field" for="password_confirmation">
                    <span>Confirm password</span>
                    <input
                        autocomplete="new-password"
                        id="password_confirmation"
                        minlength="12"
                        name="password_confirmation"
                        required
                        type="password"
                    >
                    <?php if (isset($errors['password_confirmation'])): ?>
                        <small><?= e((string) $errors['password_confirmation']) ?></small>
                    <?php endif; ?>
                </label>

                <div class="auth-actions">
                    <button class="action-button action-button--primary" type="submit">Create account</button>
                    <a class="action-button action-button--secondary" href="<?= e(route('auth.login')) ?>">Already registered</a>
                </div>
            </form>
        </article>

        <article class="content-card content-card--grass auth-copy-card">
            <h2>Included with every account</h2>
            <ul class="feature-list">
                <li>Telemetry-linked run storage for completed games</li>
                <li>A private profile showing personal bests and recent sessions</li>
                <li>Immediate access to your saved scores from the Snake HUD and profile area</li>
            </ul>
        </article>
    </section>
</main>
<?php
$content = (string) ob_get_clean();

require dirname(__DIR__) . '/layouts/base.php';
