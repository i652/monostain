<?php
declare(strict_types=1);

namespace Stain\Repositories;

use PDO;

final class CategoryRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findDefaultNewsId(): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM categories WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => 'news']);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new \RuntimeException('Default category "news" missing');
        }

        return (int) $id;
    }

    /** @return list<array{id:int, name:string, slug:string}> */
    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, slug FROM categories ORDER BY name ASC'
        );

        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, slug FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, slug FROM categories WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(string $name, string $slug): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (name, slug) VALUES (:name, :slug) RETURNING id, name, slug'
        );
        $stmt->execute(['name' => $name, 'slug' => $slug]);
        return (array) $stmt->fetch();
    }

    public function createFromDisplayName(string $displayName): array
    {
        $name = trim($displayName);
        if ($name === '') {
            throw new \InvalidArgumentException('Название обязательно');
        }
        $base = $this->slugify($name);
        if ($base === '') {
            throw new \InvalidArgumentException('Некорректное название');
        }
        $slug = $base;
        $n = 2;
        while ($this->findBySlug($slug) !== null) {
            $slug = $base . '-' . $n;
            $n++;
        }

        return $this->create($name, $slug);
    }

    public function createWithNameAndSlug(string $displayName, string $slugInput): array
    {
        $name = trim($displayName);
        if ($name === '') {
            throw new \InvalidArgumentException('Название обязательно');
        }
        $base = $this->slugify(trim($slugInput));
        if ($base === '') {
            throw new \InvalidArgumentException('Некорректный slug для URL');
        }
        $slug = $base;
        $n = 2;
        while ($this->findBySlug($slug) !== null) {
            $slug = $base . '-' . $n;
            $n++;
        }

        return $this->create($name, $slug);
    }

    public function updateWithNameAndSlug(int $id, string $displayName, string $slugInput): void
    {
        $current = $this->findById($id);
        if ($current === null) {
            throw new \InvalidArgumentException('Категория не найдена');
        }
        if (($current['slug'] ?? '') === 'news') {
            throw new \InvalidArgumentException('Категорию «Новости» нельзя редактировать');
        }
        $name = trim($displayName);
        if ($name === '') {
            throw new \InvalidArgumentException('Название обязательно');
        }
        $slug = $this->slugify(trim($slugInput));
        if ($slug === '') {
            throw new \InvalidArgumentException('Некорректный slug для URL');
        }
        $exists = $this->findBySlug($slug);
        if ($exists !== null && (int) ($exists['id'] ?? 0) !== $id) {
            throw new \InvalidArgumentException('Такой slug уже существует');
        }
        $stmt = $this->pdo->prepare('UPDATE categories SET name = :name, slug = :slug WHERE id = :id');
        $stmt->execute([
            'name' => $name,
            'slug' => $slug,
            'id' => $id,
        ]);
    }

    private function slugify(string $raw): string
    {
        $raw = mb_strtolower(trim($raw), 'UTF-8');
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
            'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
            'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
            'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];
        $raw = strtr($raw, $map);
        $raw = preg_replace('/[^a-z0-9-]+/u', '-', $raw) ?? '';
        return trim($raw, '-');
    }

    public function deleteById(int $id, int $reassignToId): void
    {
        if ($id === $reassignToId) {
            throw new \InvalidArgumentException('Cannot reassign to self');
        }
        $current = $this->findById($id);
        if ($current === null) {
            return;
        }
        if (($current['slug'] ?? '') === 'news') {
            throw new \InvalidArgumentException('Нельзя удалить категорию «Новости»');
        }
        $this->pdo->beginTransaction();
        try {
            $u = $this->pdo->prepare('UPDATE posts SET category_id = :to WHERE content_type = :ctype AND category_id = :from');
            $u->execute(['to' => $reassignToId, 'ctype' => 'post', 'from' => $id]);
            $d = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
            $d->execute(['id' => $id]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function countPostsInCategory(int $categoryId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)::int FROM posts WHERE content_type = 'post' AND category_id = :cid"
        );
        $stmt->execute(['cid' => $categoryId]);

        return (int) $stmt->fetchColumn();
    }
}
