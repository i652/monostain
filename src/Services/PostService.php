<?php
declare(strict_types=1);

namespace Stain\Services;

use Stain\Repositories\CategoryRepository;
use Stain\Repositories\MediaRepository;
use Stain\Repositories\PostRepository;
use Stain\Security\HtmlSanitizer;

final class PostService
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly CategoryRepository $categories,
        private readonly MediaRepository $media,
    ) {}

    public static function postPublicPath(array $post): string
    {
        $cat = (string) ($post['category_slug'] ?? 'news');

        return '/' . $cat . '/' . (string) ($post['slug'] ?? '');
    }

    public function promoteScheduled(): void
    {
        $this->posts->promoteScheduled();
    }

    public function create(array $actor, array $input, string $contentType = 'post'): array
    {
        $isAuthor = (($actor['role'] ?? '') === 'author');
        $title = trim((string) ($input['title'] ?? ''));
        $slug = $this->sanitizePostSlug($title);
        $sanitizer = new HtmlSanitizer();
        $previewText = $sanitizer->sanitize((string) ($input['preview_text'] ?? ''));
        $fullText = $sanitizer->sanitize((string) ($input['full_text'] ?? ''));
        $status = ($input['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $seo = trim((string) ($input['seo_description'] ?? ''));
        $createdAtInput = trim((string) ($input['created_at'] ?? ''));
        $createdAt = $createdAtInput !== '' ? $this->normalizeDate($createdAtInput) : gmdate('Y-m-d H:i:sP');

        if ($title === '' || !$this->hasNonEmptyTextOrMedia($fullText)) {
            throw new \InvalidArgumentException('Заполните заголовок и текст поста');
        }
        if ($contentType === 'post' && !$isAuthor && !$this->hasNonEmptyTextOrMedia($previewText)) {
            throw new \InvalidArgumentException('Для поста заполните поле превью (текст или изображение/видео)');
        }

        $publishedAt = null;
        $finalStatus = $status;
        if ($contentType === 'page') {
            $slug = $this->sanitizePageSlug((string) ($input['slug'] ?? $title));
            $this->assertUniquePageSlug($slug, null);
            $finalStatus = 'published';
            $publishedAt = gmdate('Y-m-d H:i:sP');
        } elseif ($contentType === 'post') {
            $now = time();
            $createdTs = strtotime($createdAt) ?: $now;
            if ($status === 'published' && $createdTs > $now) {
                $finalStatus = 'draft';
                $publishedAt = $createdAt;
            } elseif ($status === 'published') {
                $publishedAt = gmdate('Y-m-d H:i:sP');
            }
        } elseif ($status === 'published') {
            $publishedAt = gmdate('Y-m-d H:i:sP');
        }

        $categoryId = null;
        if ($contentType === 'post') {
            $categoryId = $isAuthor ? $this->categories->findDefaultNewsId() : $this->resolveCategoryId($input);
            if ($isAuthor) {
                $finalStatus = 'draft';
                $publishedAt = null;
            }
        }

        $row = $this->posts->create([
            'author_id' => $contentType === 'post' ? (int) $actor['sub'] : null,
            'content_type' => $contentType,
            'slug' => $slug,
            'title' => $title,
            'category_id' => $categoryId,
            'body' => $fullText,
            'preview_text' => $previewText,
            'full_text' => $fullText,
            'seo_description' => $seo,
            'status' => $finalStatus,
            'created_at' => $createdAt,
            'published_at' => $publishedAt,
        ]);

        return $this->posts->findById((int) $row['id']) ?? $row;
    }

    public function update(array $actor, int $postId, array $input, string $contentType = 'post'): array
    {
        $post = $this->posts->findById($postId);
        if ($post === null || $post['content_type'] !== $contentType) {
            throw new \RuntimeException('Post not found');
        }

        $isOwner = (int) $post['author_id'] === (int) $actor['sub'];
        $isAdmin = ($actor['role'] ?? '') === 'admin';
        $isAuthor = ($actor['role'] ?? '') === 'author';
        if ($contentType === 'post' && !$isOwner && !$isAdmin) {
            throw new \RuntimeException('Forbidden');
        }
        if ($contentType === 'post' && $isAuthor) {
            throw new \RuntimeException('Forbidden');
        }
        if ($contentType === 'page' && !$isAdmin) {
            throw new \RuntimeException('Forbidden');
        }

        $title = trim((string) ($input['title'] ?? $post['title']));
        $slug = $this->sanitizePostSlug($title);
        $sanitizer = new HtmlSanitizer();
        $previewText = $sanitizer->sanitize((string) ($input['preview_text'] ?? $post['preview_text']));
        $fullText = $sanitizer->sanitize((string) ($input['full_text'] ?? $post['full_text']));
        $status = ($input['status'] ?? $post['status']) === 'published' ? 'published' : 'draft';
        $seo = trim((string) ($input['seo_description'] ?? $post['seo_description']));
        $createdAt = trim((string) ($input['created_at'] ?? (string) $post['created_at']));
        $createdAt = $this->normalizeDate($createdAt);

        $publishedAt = $post['published_at'] ?? null;
        $finalStatus = $status;
        if ($contentType === 'page') {
            $slug = $this->sanitizePageSlug((string) ($input['slug'] ?? (string) $post['slug']));
            $this->assertUniquePageSlug($slug, $postId);
            $finalStatus = 'published';
            $publishedAt = $post['published_at'] ?: gmdate('Y-m-d H:i:sP');
        } elseif ($contentType === 'post') {
            $now = time();
            $createdTs = strtotime($createdAt) ?: $now;
            if ($status === 'published' && $createdTs > $now) {
                $finalStatus = 'draft';
                $publishedAt = $createdAt;
            } elseif ($status === 'published') {
                $finalStatus = 'published';
                $publishedAt = $post['published_at'] ?: gmdate('Y-m-d H:i:sP');
            } else {
                $finalStatus = 'draft';
                if ($publishedAt && (strtotime((string) $publishedAt) ?: 0) <= $now) {
                    $publishedAt = null;
                }
            }
        }

        $categoryId = $contentType === 'post' ? $this->resolveCategoryId($input) : null;

        $updated = $this->posts->update($postId, [
            'slug' => $slug,
            'title' => $title,
            'category_id' => $categoryId,
            'body' => $fullText,
            'preview_text' => $previewText,
            'full_text' => $fullText,
            'seo_description' => $seo,
            'status' => $finalStatus,
            'created_at' => $createdAt,
            'published_at' => $publishedAt,
        ]);
        if ($updated === null) {
            throw new \RuntimeException('Failed to update post');
        }

        return $this->posts->findById($postId) ?? $updated;
    }

    /** @param array<string, mixed> $input */
    private function resolveCategoryId(array $input): int
    {
        $id = (int) ($input['category_id'] ?? 0);
        if ($id > 0) {
            $row = $this->categories->findById($id);
            if ($row === null) {
                throw new \InvalidArgumentException('Выберите корректную категорию');
            }

            return $id;
        }

        return $this->categories->findDefaultNewsId();
    }

    public function listForActor(array $actor, string $contentType = 'post', ?int $limit = null, int $offset = 0): array
    {
        return $this->posts->listByAuthorOrAll((int) $actor['sub'], (string) ($actor['role'] ?? 'author'), $contentType, $limit, $offset);
    }

    public function listPublished(int $limit = 10, int $offset = 0): array
    {
        return $this->posts->listPublishedPosts($limit, $offset);
    }

    public function hasMorePublished(int $offset): bool
    {
        return $this->posts->existsPublishedPostAfterOffset($offset);
    }

    /** @return list<array<string, mixed>> */
    public function listPublishedInCategory(string $categorySlug, int $limit = 10, int $offset = 0): array
    {
        return $this->posts->listPublishedPostsInCategory($categorySlug, $limit, $offset);
    }

    public function hasMorePublishedInCategory(string $categorySlug, int $offset): bool
    {
        return $this->posts->existsPublishedPostInCategoryAfterOffset($categorySlug, $offset);
    }

    public function getPublishedPost(string $categorySlug, string $postSlug): ?array
    {
        return $this->posts->findPublishedPostByCategoryAndSlug($categorySlug, $postSlug);
    }

    public function getPostForAdmin(string $categorySlug, string $postSlug): ?array
    {
        return $this->posts->findPostByCategoryAndSlugForAdmin($categorySlug, $postSlug);
    }

    public function getLegacyPublishedPostBySlug(string $postSlug): ?array
    {
        return $this->posts->findPublishedBySlugOnly($postSlug);
    }

    public function getLegacyPostBySlugForAdmin(string $postSlug): ?array
    {
        return $this->posts->findBySlugOnlyForAdmin($postSlug);
    }

    public function getBySlug(string $slug, string $contentType): ?array
    {
        return $this->posts->findBySlug($slug, $contentType);
    }

    public function getById(int $id): ?array
    {
        return $this->posts->findById($id);
    }

    public function delete(array $actor, int $postId): void
    {
        $post = $this->posts->findById($postId);
        if ($post === null) {
            throw new \RuntimeException('Post not found');
        }

        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }

        $this->deleteAttachedMediaFiles($postId);
        $this->posts->deleteById($postId);
    }

    public function getAdjacent(int $postId, string $direction): ?array
    {
        return $this->posts->findAdjacentPublished($postId, $direction);
    }

    public function getPublishedPageBySlug(string $slug): ?array
    {
        return $this->posts->findPublishedBySlug($slug, 'page');
    }

    public function listPublishedPages(int $limit = 200): array
    {
        return $this->posts->listPublishedPages($limit);
    }

    public function listCategoriesWithCounts(): array
    {
        return $this->posts->listCategoriesWithCounts();
    }

    private function sanitizePostSlug(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return '';
        }
        $slug = mb_strtolower($slug, 'UTF-8');
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
            'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
            'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
            'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];
        $slug = strtr($slug, $map);
        $slug = preg_replace('/[^a-z0-9-]+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug === '' ? '' : $slug . '.html';
    }

    private function sanitizePageSlug(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new \InvalidArgumentException('Укажите slug страницы');
        }
        $raw = preg_replace('/\.html$/i', '', $raw) ?? $raw;
        $slug = $this->sanitizePostSlug($raw);
        if ($slug === '') {
            throw new \InvalidArgumentException('Некорректный slug страницы');
        }

        return $slug;
    }

    private function htmlHasEmbeddedMedia(string $html): bool
    {
        return $html !== '' && (bool) preg_match('/<(img|video|picture)\b/i', $html);
    }

    private function hasNonEmptyTextOrMedia(string $html): bool
    {
        if (trim(strip_tags($html)) !== '') {
            return true;
        }

        return $this->htmlHasEmbeddedMedia($html);
    }

    private function assertUniquePageSlug(string $slug, ?int $excludeId): void
    {
        $exists = $this->posts->findBySlug($slug, 'page');
        if ($exists === null) {
            return;
        }
        if ($excludeId !== null && (int) ($exists['id'] ?? 0) === $excludeId) {
            return;
        }

        throw new \InvalidArgumentException('Страница с таким slug уже существует');
    }

    private function normalizeDate(string $raw): string
    {
        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            throw new \InvalidArgumentException('Некорректный формат даты');
        }

        return gmdate('Y-m-d H:i:sP', $timestamp);
    }

    private function deleteAttachedMediaFiles(int $postId): void
    {
        $items = $this->media->listByPostId($postId);
        foreach ($items as $item) {
            $storedPath = (string) (($item['stored_path'] ?? '') ?: ($item['stored_name'] ?? ''));
            if ($storedPath === '') {
                continue;
            }
            $fullPath = dirname(__DIR__, 2) . '/storage/media/' . ltrim($storedPath, '/');
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
        $this->media->deleteByPostId($postId);
    }

    public function countPublishedPostsByCategorySlug(string $slug): int
    {
        return $this->posts->countPublishedPostsByCategorySlug($slug);
    }

    /** @return list<array<string, mixed>> */
    public function listSitemapPublishedPosts(): array
    {
        return $this->posts->listSitemapPublishedPosts();
    }

    /** @return list<array<string, mixed>> */
    public function listSitemapPublishedPages(): array
    {
        return $this->posts->listSitemapPublishedPages();
    }

    /** @return list<array<string, mixed>> */
    public function listSitemapCategoryArchives(): array
    {
        return $this->posts->listSitemapCategoryArchives();
    }

    public function maxPublicContentLastTouch(): ?string
    {
        return $this->posts->maxPublicContentLastTouch();
    }

    public function categoryArchiveLastModified(string $categorySlug): ?string
    {
        return $this->posts->categoryArchiveLastModified($categorySlug);
    }

    public static function clipMetaDescription(string $text, int $maxLen = 160): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        if (mb_strlen($t) <= $maxLen) {
            return $t;
        }

        return mb_substr($t, 0, $maxLen - 1) . '…';
    }
}
