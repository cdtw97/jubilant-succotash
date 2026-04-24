<?php
declare(strict_types=1);

namespace MyFrancis\Config;

use MyFrancis\Support\Env;

readonly class DatabaseConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password,
        public string $charset,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            host: Env::string('DB_HOST', 'localhost'),
            port: Env::int('DB_PORT', 3306),
            database: Env::string('DB_DATABASE', 'myfrancis'),
            username: Env::string('DB_USERNAME', 'root'),
            password: Env::string('DB_PASSWORD', ''),
            charset: Env::string('DB_CHARSET', 'utf8mb4'),
        );
    }

    public function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset,
        );
    }
}
