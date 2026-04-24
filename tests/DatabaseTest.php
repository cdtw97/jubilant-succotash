<?php
declare(strict_types=1);

namespace Tests;

use MyFrancis\Config\DatabaseConfig;
use MyFrancis\Database\Database;
use PDO;
use RuntimeException;

final class DatabaseTest extends FrameworkTestCase
{
    public function testDsnContainsUtf8mb4Charset(): void
    {
        $config = new DatabaseConfig('localhost', 3306, 'myfrancis', 'root', 'secret', 'utf8mb4');

        self::assertStringContainsString('charset=utf8mb4', $config->dsn());
    }

    public function testQueryBindsParametersAndFetchMethodsReturnArrays(): void
    {
        $database = $this->sqliteDatabase();
        $database->query('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL)');
        $database->execute('INSERT INTO users (email) VALUES (:email)', ['email' => 'user@example.com']);
        $database->execute('INSERT INTO users (email) VALUES (:email)', ['email' => 'owner@example.com']);

        $rows = $database->fetchAll('SELECT id, email FROM users WHERE email LIKE :email ORDER BY id ASC', ['email' => '%@example.com']);
        $row = $database->fetchOne('SELECT id, email FROM users WHERE email = :email', ['email' => 'user@example.com']);

        self::assertCount(2, $rows);
        self::assertArrayHasKey('email', $rows[0]);
        self::assertSame('user@example.com', $row['email'] ?? null);
    }

    public function testExecuteReturnsAffectedRowCount(): void
    {
        $database = $this->sqliteDatabase();
        $database->query('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL)');
        $database->execute('INSERT INTO users (email) VALUES (:email)', ['email' => 'user@example.com']);

        $affectedRows = $database->execute(
            'UPDATE users SET email = :updated_email WHERE email = :email',
            ['updated_email' => 'owner@example.com', 'email' => 'user@example.com'],
        );

        self::assertSame(1, $affectedRows);
    }

    public function testTransactionCommitsOnSuccess(): void
    {
        $database = $this->sqliteDatabase();
        $database->query('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');

        $database->transaction(static function (Database $transactionalDatabase): void {
            $transactionalDatabase->execute('INSERT INTO items (name) VALUES (:name)', ['name' => 'committed']);
        });

        $item = $database->fetchOne('SELECT name FROM items WHERE name = :name', ['name' => 'committed']);

        self::assertSame('committed', $item['name'] ?? null);
    }

    public function testTransactionRollsBackOnException(): void
    {
        $database = $this->sqliteDatabase();
        $database->query('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');

        $caught = false;

        try {
            $database->transaction(static function (Database $transactionalDatabase): void {
                $transactionalDatabase->execute('INSERT INTO items (name) VALUES (:name)', ['name' => 'rolled-back']);

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            $caught = true;
        }

        self::assertTrue($caught);
        $item = $database->fetchOne('SELECT name FROM items WHERE name = :name', ['name' => 'rolled-back']);

        self::assertNull($item);
    }

    public function testDatabaseErrorsAreNotEchoed(): void
    {
        $database = $this->sqliteDatabase();
        ob_start();

        try {
            $database->query('SELECT * FROM missing_table');
            self::fail('Expected missing table query to fail.');
        } catch (\Throwable) {
            $output = ob_get_clean();

            self::assertSame('', $output);
        }
    }

    public function testNoUnsafeQueryBuilderIsIntroducedAndDocWarnsAboutIdentifiers(): void
    {
        $reflectionMethod = new \ReflectionMethod(Database::class, 'query');
        $docComment = $reflectionMethod->getDocComment() ?: '';

        $methods = get_class_methods(Database::class);

        self::assertNotContains('table', $methods);
        self::assertNotContains('queryBuilder', $methods);
        self::assertStringContainsString('cannot be parameter-bound', $docComment);
        self::assertStringContainsString('allowlist identifiers', $docComment);
    }

    private function sqliteDatabase(): Database
    {
        if (! extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for database tests.');
        }

        $config = new DatabaseConfig('localhost', 3306, 'myfrancis', 'root', '', 'utf8mb4');
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return new Database($config, $pdo);
    }
}
