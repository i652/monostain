<?php
declare(strict_types=1);

namespace Stain\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByNicknameIgnoreCase(string $nickname): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE LOWER(nickname) = LOWER(:nick) LIMIT 1');
        $stmt->execute(['nick' => trim($nickname)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** True if no user has this email. */
    public function isEmailAvailable(string $email): bool
    {
        return $this->findByEmail(strtolower(trim($email))) === null;
    }

    /** True if no user has this nickname (case-insensitive). */
    public function isNicknameAvailable(string $nickname): bool
    {
        return $this->findByNicknameIgnoreCase(trim($nickname)) === null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $email, string $passwordHash, string $role, string $nickname): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash, role, nickname) VALUES (:email, :password_hash, :role, :nickname) RETURNING id, email, nickname, role, created_at'
        );
        $stmt->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => $role,
            'nickname' => $nickname,
        ]);

        return (array) $stmt->fetch();
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT
                u.id,
                u.email,
                u.nickname,
                u.role,
                u.created_at,
                COALESCE(total_games.total_count, 0) AS games_total,
                COALESCE(today_games.today_count, 0) AS games_today
             FROM users u
             LEFT JOIN (
               SELECT created_by, COUNT(*)::int AS total_count
               FROM games
               GROUP BY created_by
             ) total_games ON total_games.created_by = u.id
             LEFT JOIN (
               SELECT created_by, COUNT(*)::int AS today_count
               FROM games
               WHERE created_at::date = CURRENT_DATE
               GROUP BY created_by
             ) today_games ON today_games.created_by = u.id
             ORDER BY u.created_at DESC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function updateRole(int $id, string $role): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
        $stmt->execute(['role' => $role, 'id' => $id]);
    }

    public function updateIdentity(int $id, string $email, string $nickname): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET email = :email, nickname = :nickname WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'email' => strtolower(trim($email)),
            'nickname' => trim($nickname),
        ]);
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $stmt->execute(['password_hash' => $passwordHash, 'id' => $id]);
    }

    public function countByRole(string $role): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*)::int FROM users WHERE role = :role");
        $stmt->execute(['role' => $role]);

        return (int) $stmt->fetchColumn();
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
