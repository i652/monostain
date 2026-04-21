<?php
declare(strict_types=1);

namespace Stain\Repositories;

use PDO;

final class MediaRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO media (post_id, owner_id, original_name, stored_name, stored_path, kind, mime_type, size_bytes)
             VALUES (:post_id, :owner_id, :original_name, :stored_name, :stored_path, :kind, :mime_type, :size_bytes)
             RETURNING *'
        );
        $stmt->execute($data);
        return (array) $stmt->fetch();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM media WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function attachToPost(int $postId, array $mediaIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $mediaIds)));
        $ids = array_filter($ids, static fn (int $id) => $id > 0);
        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE media SET post_id = ? WHERE id IN ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$postId], $ids));
    }

    public function deleteById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('DELETE FROM media WHERE id = :id RETURNING *');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listByPostId(int $postId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM media WHERE post_id = :post_id');
        $stmt->execute(['post_id' => $postId]);

        return $stmt->fetchAll() ?: [];
    }

    public function deleteByPostId(int $postId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM media WHERE post_id = :post_id');
        $stmt->execute(['post_id' => $postId]);
    }

    /** @return list<array<string, mixed>> */
    public function listAllByNewest(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, post_id, original_name, stored_path, kind, mime_type, size_bytes, created_at
             FROM media ORDER BY id DESC'
        );

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listAllByNewestPaged(int $limit, int $offset): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $stmt = $this->pdo->prepare(
            'SELECT id, post_id, original_name, stored_path, kind, mime_type, size_bytes, created_at
             FROM media ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*)::int FROM media');
        $n = $stmt->fetchColumn();

        return (int) $n;
    }
}
