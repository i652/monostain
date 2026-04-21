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
        $stmt = $this->pdo->query('SELECT id, email, nickname, role, created_at FROM users ORDER BY created_at DESC');
        return $stmt->fetchAll() ?: [];
    }

    public function updateRole(int $id, string $role): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
        $stmt->execute(['role' => $role, 'id' => $id]);
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
