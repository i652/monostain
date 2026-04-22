<?php
declare(strict_types=1);

namespace Stain;

use PDO;
use PDOException;

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

        $portsToTry = [$port];
        // Частая ситуация в Docker: внутри сети нужен 5432, даже если снаружи проброшен 5434.
        if ($host === 'postgres' && $port !== '5432') {
            $portsToTry[] = '5432';
        }
        $portsToTry = array_values(array_unique($portsToTry));

        $lastException = null;
        foreach ($portsToTry as $tryPort) {
            try {
                $dsn = "pgsql:host={$host};port={$tryPort};dbname={$dbName}";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                break;
            } catch (PDOException $e) {
                $lastException = $e;
                $pdo = null;
            }
        }
        if (!$pdo instanceof PDO) {
            $details = $lastException ? $lastException->getMessage() : 'нет деталей';
            throw new \RuntimeException(
                'Не удалось подключиться к базе данных PostgreSQL. ' .
                'Проверьте DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS и запуск контейнера postgres. ' .
                'Технические детали: ' . $details
            );
        }

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
            $existsStmt = $pdo->query("SELECT to_regclass('public.users') IS NOT NULL");
            $usersTableExists = (bool) $existsStmt->fetchColumn();
            if (!$usersTableExists) {
                return;
            }
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
