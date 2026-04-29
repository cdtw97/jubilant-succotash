CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(32) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_username_unique (username),
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    theme VARCHAR(32) NOT NULL,
    board_size VARCHAR(16) NOT NULL,
    grid_size SMALLINT UNSIGNED NOT NULL,
    speed_level SMALLINT UNSIGNED NOT NULL,
    apple_type VARCHAR(32) NOT NULL DEFAULT 'standard',
    apple_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    walls_enabled TINYINT(1) NOT NULL DEFAULT 1,
    snake_style VARCHAR(16) NOT NULL DEFAULT 'tube',
    score INT UNSIGNED NOT NULL DEFAULT 0,
    snake_length INT UNSIGNED NOT NULL DEFAULT 0,
    duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    ended_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY game_runs_user_id_index (user_id),
    KEY game_runs_score_index (score),
    KEY game_runs_ended_at_index (ended_at),
    KEY game_runs_user_score_index (user_id, score, ended_at),
    CONSTRAINT game_runs_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP VIEW IF EXISTS user_high_scores;

CREATE VIEW user_high_scores AS
SELECT
    game_runs.user_id,
    users.username,
    MAX(game_runs.score) AS best_score,
    MAX(game_runs.snake_length) AS best_length,
    MAX(game_runs.duration_seconds) AS longest_duration_seconds,
    COUNT(game_runs.id) AS total_runs,
    MAX(game_runs.ended_at) AS last_played_at
FROM game_runs
INNER JOIN users ON users.id = game_runs.user_id
GROUP BY game_runs.user_id, users.username;
