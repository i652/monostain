<?php
declare(strict_types=1);

namespace Stain;

use PDO;

final class Database
{
    public static function pdo(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $host = Config::get('DB_HOST', 'postgres');
        $port = Config::get('DB_PORT', '5432');
        $dbName = Config::get('DB_NAME', 'stain');
        $user = Config::get('DB_USER', 'stain');
        $pass = Config::get('DB_PASS', 'stain');

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbName}";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        self::ensureUsersNicknameSchema($pdo);

        return $pdo;
    }

    /**
     * Идемпотентно добавляет nickname и индекс, если БД ещё со старой схемой (без ручного psql).
     */
    private static function ensureUsersNicknameSchema(PDO $pdo): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;

        try {
            $pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS nickname VARCHAR(64)');
            $pdo->exec("UPDATE users SET nickname = 'user_' || id::text WHERE nickname IS NULL OR TRIM(nickname) = ''");
            $pdo->exec('ALTER TABLE users ALTER COLUMN nickname SET NOT NULL');
        } catch (\Throwable $e) {
            error_log('Stain DB migrate nickname (nullable/NOT NULL): ' . $e->getMessage());
        }

        try {
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_nickname_lower ON users (LOWER(nickname))');
        } catch (\Throwable $e) {
            error_log('Stain DB migrate nickname index: ' . $e->getMessage());
        }
    }
}
