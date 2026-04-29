<?php
declare(strict_types=1);

$title = $title ?? 'Profile';
$pageClass = 'profile-page';
$activeNav = '';
$user = isset($user) && is_array($user) ? $user : [];
$summary = isset($summary) && is_array($summary) ? $summary : [];
$recentRuns = isset($recentRuns) && is_array($recentRuns) ? $recentRuns : [];
$personalTopRuns = isset($personalTopRuns) && is_array($personalTopRuns) ? $personalTopRuns : [];
$globalTopRuns = isset($globalTopRuns) && is_array($globalTopRuns) ? $globalTopRuns : [];

$formatDuration = static function (mixed $value): string {
    $seconds = max(0, (int) $value);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remainingSeconds = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }

    return sprintf('%d:%02d', $minutes, $remainingSeconds);
};

$formatDate = static function (mixed $value): string {
    if (! is_string($value) || trim($value) === '') {
        return 'N/A';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y g:i A');
    } catch (Throwable) {
        return 'N/A';
    }
};

ob_start();
?>
<main class="page-shell profile-shell container-xxl px-0">
    <section class="hero-card hero-card--grass profile-hero">
        <div>
            <div class="page-kicker">Player Profile</div>
            <h1 class="page-title">Welcome back, <?= e((string) ($user['username'] ?? 'Player')) ?>.</h1>
            <p class="page-lead">Every completed run now lands here with your personal bests, recent match history, and the latest telemetry captured from the board.</p>
        </div>

        <div class="profile-hero__meta">
            <div class="profile-chip">
                <strong><?= e((string) ($user['email'] ?? '')) ?></strong>
                <span>Email</span>
            </div>
            <div class="profile-chip">
                <strong><?= e($formatDate($user['created_at'] ?? null)) ?></strong>
                <span>Member since</span>
            </div>
            <div class="profile-chip">
                <strong><?= e($formatDate($summary['last_played_at'] ?? null)) ?></strong>
                <span>Last run</span>
            </div>
        </div>
    </section>

    <section class="funnel-metrics profile-metrics" aria-label="Profile statistics">
        <article class="metric-card">
            <strong><?= e((string) ($summary['best_score'] ?? 0)) ?></strong>
            <span>Personal best score</span>
        </article>
        <article class="metric-card">
            <strong><?= e((string) ($summary['best_length'] ?? 0)) ?></strong>
            <span>Longest snake length</span>
        </article>
        <article class="metric-card">
            <strong><?= e((string) ($summary['total_runs'] ?? 0)) ?></strong>
            <span>Total runs logged</span>
        </article>
    </section>

    <section class="card-grid profile-grid">
        <article class="content-card content-card--white">
            <h2>Personal bests</h2>
            <div class="table-shell">
                <table class="profile-table">
                    <tbody>
                        <tr>
                            <th scope="row">Best score</th>
                            <td><?= e((string) ($summary['best_score'] ?? 0)) ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Best length</th>
                            <td><?= e((string) ($summary['best_length'] ?? 0)) ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Longest survival</th>
                            <td><?= e($formatDuration($summary['longest_duration_seconds'] ?? 0)) ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Average score</th>
                            <td><?= e((string) ($summary['average_score'] ?? 0)) ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Total time played</th>
                            <td><?= e($formatDuration($summary['total_duration_seconds'] ?? 0)) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="content-card content-card--white">
            <h2>Top personal runs</h2>
            <?php if ($personalTopRuns === []): ?>
                <p class="empty-state">No runs saved yet. Launch a game and finish a run to start building your history.</p>
            <?php else: ?>
                <div class="table-shell">
                    <table class="profile-table profile-table--compact">
                        <thead>
                            <tr>
                                <th scope="col">Score</th>
                                <th scope="col">Theme</th>
                                <th scope="col">Board</th>
                                <th scope="col">Length</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($personalTopRuns as $run): ?>
                                <tr>
                                    <td><?= e((string) ($run['score'] ?? 0)) ?></td>
                                    <td><?= e((string) ($run['theme'] ?? '')) ?></td>
                                    <td><?= e((string) ($run['board_size'] ?? '')) ?></td>
                                    <td><?= e((string) ($run['snake_length'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
    </section>

    <section class="card-grid profile-grid">
        <article class="content-card content-card--grass">
            <h2>Recent telemetry</h2>
            <?php if ($recentRuns === []): ?>
                <p class="empty-state">Telemetry appears here after your first completed run.</p>
            <?php else: ?>
                <div class="table-shell">
                    <table class="profile-table profile-table--compact">
                        <thead>
                            <tr>
                                <th scope="col">When</th>
                                <th scope="col">Score</th>
                                <th scope="col">Theme</th>
                                <th scope="col">Speed</th>
                                <th scope="col">Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentRuns as $run): ?>
                                <tr>
                                    <td><?= e($formatDate($run['ended_at'] ?? null)) ?></td>
                                    <td><?= e((string) ($run['score'] ?? 0)) ?></td>
                                    <td><?= e((string) ($run['theme'] ?? '')) ?></td>
                                    <td><?= e((string) ($run['speed_level'] ?? 0)) ?></td>
                                    <td><?= e($formatDuration($run['duration_seconds'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>

        <article class="content-card content-card--white">
            <h2>Global leaderboard</h2>
            <?php if ($globalTopRuns === []): ?>
                <p class="empty-state">The global leaderboard will appear once runs are saved.</p>
            <?php else: ?>
                <div class="table-shell">
                    <table class="profile-table profile-table--compact">
                        <thead>
                            <tr>
                                <th scope="col">Player</th>
                                <th scope="col">Score</th>
                                <th scope="col">Theme</th>
                                <th scope="col">Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($globalTopRuns as $run): ?>
                                <tr>
                                    <td><?= e((string) ($run['username'] ?? '')) ?></td>
                                    <td><?= e((string) ($run['score'] ?? 0)) ?></td>
                                    <td><?= e((string) ($run['theme'] ?? '')) ?></td>
                                    <td><?= e($formatDuration($run['duration_seconds'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
    </section>
</main>
<?php
$content = (string) ob_get_clean();

require dirname(__DIR__) . '/layouts/base.php';
