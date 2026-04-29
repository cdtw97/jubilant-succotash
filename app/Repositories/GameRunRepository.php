<?php
declare(strict_types=1);

namespace App\Repositories;

use MyFrancis\Database\Repository;

final class GameRunRepository extends Repository
{
    /**
     * @param array{
     *     theme: string,
     *     board_size: string,
     *     grid_size: int,
     *     speed_level: int,
     *     apple_type: string,
     *     apple_count: int,
     *     walls_enabled: bool,
     *     snake_style: string,
     *     score: int,
     *     snake_length: int,
     *     duration_seconds: int,
     *     ended_at: string
     * } $run
     */
    public function createRun(int $userId, array $run): int
    {
        $this->execute(
            'INSERT INTO game_runs (
                user_id,
                theme,
                board_size,
                grid_size,
                speed_level,
                apple_type,
                apple_count,
                walls_enabled,
                snake_style,
                score,
                snake_length,
                duration_seconds,
                ended_at,
                created_at
            ) VALUES (
                :user_id,
                :theme,
                :board_size,
                :grid_size,
                :speed_level,
                :apple_type,
                :apple_count,
                :walls_enabled,
                :snake_style,
                :score,
                :snake_length,
                :duration_seconds,
                :ended_at,
                CURRENT_TIMESTAMP
            )',
            [
                ':user_id' => $userId,
                ':theme' => $run['theme'],
                ':board_size' => $run['board_size'],
                ':grid_size' => $run['grid_size'],
                ':speed_level' => $run['speed_level'],
                ':apple_type' => $run['apple_type'],
                ':apple_count' => $run['apple_count'],
                ':walls_enabled' => $run['walls_enabled'],
                ':snake_style' => $run['snake_style'],
                ':score' => $run['score'],
                ':snake_length' => $run['snake_length'],
                ':duration_seconds' => $run['duration_seconds'],
                ':ended_at' => $run['ended_at'],
            ],
        );

        return (int) $this->database->lastInsertId();
    }

    /**
     * @return array{
     *     total_runs: int,
     *     best_score: int,
     *     best_length: int,
     *     longest_duration_seconds: int,
     *     average_score: float,
     *     total_duration_seconds: int,
     *     last_played_at: string|null
     * }
     */
    public function findPersonalSummary(int $userId): array
    {
        $row = $this->fetchOne(
            'SELECT
                COUNT(*) AS total_runs,
                COALESCE(MAX(score), 0) AS best_score,
                COALESCE(MAX(snake_length), 0) AS best_length,
                COALESCE(MAX(duration_seconds), 0) AS longest_duration_seconds,
                COALESCE(AVG(score), 0) AS average_score,
                COALESCE(SUM(duration_seconds), 0) AS total_duration_seconds,
                MAX(ended_at) AS last_played_at
             FROM game_runs
             WHERE user_id = :user_id',
            [':user_id' => $userId],
        ) ?? [];

        return [
            'total_runs' => (int) ($row['total_runs'] ?? 0),
            'best_score' => (int) ($row['best_score'] ?? 0),
            'best_length' => (int) ($row['best_length'] ?? 0),
            'longest_duration_seconds' => (int) ($row['longest_duration_seconds'] ?? 0),
            'average_score' => round((float) ($row['average_score'] ?? 0.0), 2),
            'total_duration_seconds' => (int) ($row['total_duration_seconds'] ?? 0),
            'last_played_at' => isset($row['last_played_at']) && is_string($row['last_played_at'])
                ? $row['last_played_at']
                : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findRecentByUserId(int $userId, int $limit = 12): array
    {
        $safeLimit = $this->normalizeLimit($limit, 12, 1, 50);

        return $this->fetchAll(
            "SELECT
                id,
                theme,
                board_size,
                grid_size,
                speed_level,
                apple_type,
                apple_count,
                walls_enabled,
                snake_style,
                score,
                snake_length,
                duration_seconds,
                ended_at
             FROM game_runs
             WHERE user_id = :user_id
             ORDER BY ended_at DESC, id DESC
             LIMIT {$safeLimit}",
            [':user_id' => $userId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findTopRunsByUserId(int $userId, int $limit = 5): array
    {
        $safeLimit = $this->normalizeLimit($limit, 5, 1, 25);

        return $this->fetchAll(
            "SELECT
                id,
                theme,
                board_size,
                score,
                snake_length,
                duration_seconds,
                ended_at
             FROM game_runs
             WHERE user_id = :user_id
             ORDER BY score DESC, snake_length DESC, duration_seconds DESC, ended_at DESC
             LIMIT {$safeLimit}",
            [':user_id' => $userId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findGlobalTopRuns(int $limit = 10): array
    {
        $safeLimit = $this->normalizeLimit($limit, 10, 1, 50);

        return $this->fetchAll(
            "SELECT
                game_runs.id,
                game_runs.user_id,
                users.username,
                game_runs.theme,
                game_runs.board_size,
                game_runs.score,
                game_runs.snake_length,
                game_runs.duration_seconds,
                game_runs.ended_at
             FROM game_runs
             INNER JOIN users ON users.id = game_runs.user_id
             ORDER BY game_runs.score DESC, game_runs.snake_length DESC, game_runs.duration_seconds DESC, game_runs.ended_at DESC
             LIMIT {$safeLimit}"
        );
    }

    public function findUserBestScore(int $userId): int
    {
        $row = $this->fetchOne(
            'SELECT best_score
             FROM user_high_scores
             WHERE user_id = :user_id
             LIMIT 1',
            [':user_id' => $userId],
        );

        if ($row === null) {
            return 0;
        }

        return (int) ($row['best_score'] ?? 0);
    }

    private function normalizeLimit(int $limit, int $fallback, int $min, int $max): int
    {
        if ($limit < $min || $limit > $max) {
            return $fallback;
        }

        return $limit;
    }
}
