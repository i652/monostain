<?php
declare(strict_types=1);

namespace Stain\Repositories;

use PDO;

final class PostRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO posts (author_id, content_type, slug, title, category_id, body, preview_text, full_text, seo_description, status, created_at, published_at)
             VALUES (:author_id, :content_type, :slug, :title, :category_id, :body, :preview_text, :full_text, :seo_description, :status, :created_at, :published_at)
             RETURNING *'
        );
        $stmt->execute($data);
        return (array) $stmt->fetch();
    }

    public function update(int $id, array $data): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE posts
             SET slug = :slug, title = :title, category_id = :category_id, body = :body, preview_text = :preview_text, full_text = :full_text, seo_description = :seo_description, status = :status, created_at = :created_at, published_at = :published_at, updated_at = NOW()
             WHERE id = :id
             RETURNING *'
        );
        $stmt->execute(array_merge($data, ['id' => $id]));
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.nickname AS author_nickname, c.slug AS category_slug
             FROM posts p
             LEFT JOIN users u ON u.id = p.author_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deleteById(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM posts WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function findPublishedPostByCategoryAndSlug(string $categorySlug, string $postSlug): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.nickname AS author_nickname, c.slug AS category_slug, c.name AS category_name
             FROM posts p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u ON u.id = p.author_id
             WHERE p.slug = :slug AND p.status = 'published' AND p.content_type = 'post' AND c.slug = :category_slug
             LIMIT 1"
        );
        $stmt->execute(['slug' => $postSlug, 'category_slug' => $categorySlug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findPostByCategoryAndSlugForAdmin(string $categorySlug, string $postSlug): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.nickname AS author_nickname, c.slug AS category_slug, c.name AS category_name
             FROM posts p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u ON u.id = p.author_id
             WHERE p.slug = :slug AND p.content_type = 'post' AND c.slug = :category_slug
             LIMIT 1"
        );
        $stmt->execute(['slug' => $postSlug, 'category_slug' => $categorySlug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findPublishedBySlug(string $slug, string $contentType): ?array
    {
        if ($contentType === 'post') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.nickname AS author_nickname, NULL::text AS category_slug
             FROM posts p
             LEFT JOIN users u ON u.id = p.author_id
             WHERE p.slug = :slug AND p.status = 'published' AND p.content_type = :content_type
             LIMIT 1"
        );
        $stmt->execute(['slug' => $slug, 'content_type' => $contentType]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBySlug(string $slug, string $contentType): ?array
    {
        if ($contentType === 'post') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.nickname AS author_nickname, NULL::text AS category_slug
             FROM posts p
             LEFT JOIN users u ON u.id = p.author_id
             WHERE p.slug = :slug AND p.content_type = :content_type
             LIMIT 1"
        );
        $stmt->execute(['slug' => $slug, 'content_type' => $contentType]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findPublishedBySlugOnly(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.nickname AS author_nickname, c.slug AS category_slug
             FROM posts p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u ON u.id = p.author_id
             WHERE p.slug = :slug AND p.status = 'published' AND p.content_type = 'post'
             LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBySlugOnlyForAdmin(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.nickname AS author_nickname, c.slug AS category_slug
             FROM posts p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u ON u.id = p.author_id
             WHERE p.slug = :slug AND p.content_type = 'post'
             LIMIT 1"
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listPublishedPosts(int $limit = 10, int $offset = 0): array
    {
        $this->promoteScheduled();
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.slug, p.title, p.preview_text, p.seo_description, p.published_at,
                    u.nickname AS author_nickname, c.slug AS category_slug, c.name AS category_name
             FROM posts p
             JOIN users u ON u.id = p.author_id
             JOIN categories c ON c.id = p.category_id
             WHERE p.status = 'published' AND p.content_type = 'post'
             ORDER BY p.published_at DESC NULLS LAST, p.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listPublishedPostsInCategory(string $categorySlug, int $limit, int $offset): array
    {
        $this->promoteScheduled();
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.slug, p.title, p.preview_text, p.seo_description, p.published_at,
                    u.nickname AS author_nickname, c.slug AS category_slug, c.name AS category_name
             FROM posts p
             JOIN users u ON u.id = p.author_id
             JOIN categories c ON c.id = p.category_id
             WHERE p.status = 'published' AND p.content_type = 'post' AND c.slug = :cs
             ORDER BY p.published_at DESC NULLS LAST, p.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue('cs', $categorySlug, PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public function existsPublishedPostInCategoryAfterOffset(string $categorySlug, int $offset): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM posts p
             JOIN categories c ON c.id = p.category_id
             WHERE p.content_type = 'post' AND p.status = 'published' AND c.slug = :cs
             ORDER BY p.published_at DESC NULLS LAST, p.created_at DESC
             OFFSET :offset
             LIMIT 1"
        );
        $stmt->bindValue('cs', $categorySlug, PDO::PARAM_STR);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    public function listPublishedPages(int $limit = 200): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, slug, title
             FROM posts
             WHERE content_type = 'page' AND status = 'published'
             ORDER BY created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function listByAuthorOrAll(int $userId, string $role, string $contentType, ?int $limit = null, int $offset = 0): array
    {
        $offset = max(0, $offset);
        if ($role === 'admin') {
            $sql = 'SELECT p.*, c.slug AS category_slug
                    FROM posts p
                    LEFT JOIN categories c ON c.id = p.category_id
                    WHERE p.content_type = :content_type
                    ORDER BY p.created_at DESC';
            if ($limit !== null) {
                $sql .= ' LIMIT :limit OFFSET :offset';
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue('content_type', $contentType, PDO::PARAM_STR);
            if ($limit !== null) {
                $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        }

        $sql = 'SELECT p.*, c.slug AS category_slug
                FROM posts p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.author_id = :author_id AND p.content_type = :content_type
                ORDER BY p.created_at DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('author_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('content_type', $contentType, PDO::PARAM_STR);
        if ($limit !== null) {
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function findAdjacentPublished(int $postId, string $direction): ?array
    {
        $this->promoteScheduled();
        $operator = $direction === 'newer' ? '>' : '<';
        $order = $direction === 'newer' ? 'ASC' : 'DESC';

        $sql = "
            SELECT p.id, p.slug, p.title, c.slug AS category_slug
            FROM posts p
            JOIN categories c ON c.id = p.category_id
            JOIN posts current_post ON current_post.id = :post_id
            WHERE p.content_type = 'post'
              AND p.status = 'published'
              AND (COALESCE(p.published_at, p.created_at) {$operator} COALESCE(current_post.published_at, current_post.created_at))
            ORDER BY COALESCE(p.published_at, p.created_at) {$order}
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['post_id' => $postId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function promoteScheduled(): void
    {
        $this->pdo->exec(
            "UPDATE posts
             SET status = 'published',
                 published_at = COALESCE(published_at, created_at),
                 updated_at = NOW()
             WHERE content_type = 'post'
               AND status = 'draft'
               AND published_at IS NOT NULL
               AND published_at <= NOW()"
        );
    }

    public function existsPublishedPostAfterOffset(int $offset): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1
             FROM posts p
             WHERE p.content_type = 'post' AND p.status = 'published'
             ORDER BY p.published_at DESC NULLS LAST, p.created_at DESC
             OFFSET :offset
             LIMIT 1"
        );
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    public function listCategoriesWithCounts(): array
    {
        $stmt = $this->pdo->query(
            "SELECT c.id, c.name, c.slug, COUNT(p.id)::int AS cnt
             FROM categories c
             LEFT JOIN posts p ON p.category_id = c.id AND p.content_type = 'post'
             GROUP BY c.id
             ORDER BY c.name ASC"
        );

        return $stmt->fetchAll() ?: [];
    }

    public function countPublishedPostsByCategorySlug(string $categorySlug): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)::int
             FROM posts p
             JOIN categories c ON c.id = p.category_id
             WHERE c.slug = :slug AND p.content_type = 'post' AND p.status = 'published'"
        );
        $stmt->execute(['slug' => $categorySlug]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array{slug:string, category_slug:string, updated_at:?string, published_at:?string, created_at:string}>
     */
    public function listSitemapPublishedPosts(): array
    {
        $this->promoteScheduled();
        $stmt = $this->pdo->query(
            "SELECT p.slug, p.updated_at, p.published_at, p.created_at, c.slug AS category_slug
             FROM posts p
             JOIN categories c ON c.id = p.category_id
             WHERE p.content_type = 'post' AND p.status = 'published'
             ORDER BY p.published_at DESC NULLS LAST, p.id DESC"
        );

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array{slug:string, updated_at:?string, published_at:?string, created_at:string}>
     */
    public function listSitemapPublishedPages(): array
    {
        $stmt = $this->pdo->query(
            "SELECT slug, updated_at, published_at, created_at
             FROM posts
             WHERE content_type = 'page' AND status = 'published'
             ORDER BY published_at DESC NULLS LAST, id DESC"
        );

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Категории, в которых есть хотя бы один опубликованный пост (для sitemap).
     *
     * @return list<array{slug:string, last_touch:string}>
     */
    public function listSitemapCategoryArchives(): array
    {
        $this->promoteScheduled();
        $stmt = $this->pdo->query(
            "SELECT c.slug,
                    MAX(GREATEST(p.updated_at, COALESCE(p.published_at, p.created_at), p.created_at)) AS last_touch
             FROM categories c
             INNER JOIN posts p ON p.category_id = c.id AND p.content_type = 'post' AND p.status = 'published'
             GROUP BY c.id, c.slug
             ORDER BY c.slug ASC"
        );

        return $stmt->fetchAll() ?: [];
    }

    /** Максимальная дата изменения публичного контента (для главной в sitemap). */
    public function maxPublicContentLastTouch(): ?string
    {
        $this->promoteScheduled();
        $stmt = $this->pdo->query(
            "SELECT MAX(GREATEST(updated_at, COALESCE(published_at, created_at), created_at))
             FROM posts
             WHERE (content_type = 'post' AND status = 'published')
                OR (content_type = 'page' AND status = 'published')"
        );
        $v = $stmt->fetchColumn();

        return $v !== false && $v !== null && $v !== '' ? (string) $v : null;
    }

    public function categoryArchiveLastModified(string $categorySlug): ?string
    {
        $this->promoteScheduled();
        $stmt = $this->pdo->prepare(
            "SELECT MAX(GREATEST(p.updated_at, COALESCE(p.published_at, p.created_at), p.created_at))
             FROM posts p
             JOIN categories c ON c.id = p.category_id
             WHERE c.slug = :slug AND p.content_type = 'post' AND p.status = 'published'"
        );
        $stmt->execute(['slug' => $categorySlug]);
        $v = $stmt->fetchColumn();

        return $v !== false && $v !== null && $v !== '' ? (string) $v : null;
    }

    /**
     * Посты и страницы, в HTML которых есть точная ссылка /media/{id}.
     *
     * @return list<array{id: int, title: string, slug: string, content_type: string}>
     */
    public function findContentReferencingMediaId(int $mediaId): array
    {
        $like = '%/media/' . $mediaId . '%';
        $stmt = $this->pdo->prepare(
            'SELECT id, title, slug, content_type, preview_text, full_text
             FROM posts
             WHERE preview_text LIKE :like1 OR full_text LIKE :like2'
        );
        $stmt->execute(['like1' => $like, 'like2' => $like]);
        $rows = $stmt->fetchAll() ?: [];
        $out = [];
        foreach ($rows as $row) {
            $p = (string) ($row['preview_text'] ?? '');
            $f = (string) ($row['full_text'] ?? '');
            if (!self::htmlContainsMediaRef($p, $mediaId) && !self::htmlContainsMediaRef($f, $mediaId)) {
                continue;
            }
            $out[] = [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'slug' => (string) $row['slug'],
                'content_type' => (string) $row['content_type'],
            ];
        }

        return $out;
    }

    private static function htmlContainsMediaRef(string $html, int $mediaId): bool
    {
        return (bool) preg_match('#/media/' . $mediaId . '(?=\D|$)#', $html);
    }
}
