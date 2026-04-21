<?php
declare(strict_types=1);

namespace Stain\Services;

use Stain\Config;
use Stain\Exceptions\MediaInUseException;
use Stain\Repositories\MediaRepository;
use Stain\Repositories\PostRepository;

final class MediaService
{
    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'video/mp4',
        'video/webm',
        'application/pdf',
    ];

    public function __construct(
        private readonly MediaRepository $media,
        private readonly PostRepository $posts
    ) {}

    public function store(array $actor, array $file): array
    {
        return $this->storeInternal($actor, null, $file);
    }

    public function storeForPost(array $actor, int $postId, array $file): array
    {
        return $this->storeInternal($actor, $postId, $file);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, has_more: bool}
     */
    public function listSliceForAdmin(array $actor, int $offset, int $limit): array
    {
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }

        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $total = $this->media->countAll();
        $items = $this->media->listAllByNewestPaged($limit, $offset);
        $items = array_map(fn (array $row): array => $this->enrichMediaItemWithSource($row), $items);
        $loaded = count($items);
        $hasMore = $offset + $loaded < $total;

        return [
            'items' => $items,
            'total' => $total,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Привязка к посту/странице: сначала media.post_id, иначе первый материал с /media/{id} в HTML.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function enrichMediaItemWithSource(array $item): array
    {
        $mid = (int) ($item['id'] ?? 0);
        $postId = (int) ($item['post_id'] ?? 0);
        if ($postId > 0) {
            $row = $this->posts->findById($postId);
            if ($row !== null) {
                $this->applySourceFromPostRow($item, $row);

                return $item;
            }
        }

        $refs = $this->posts->findContentReferencingMediaId($mid);
        if ($refs !== []) {
            $r = $refs[0];
            $rid = (int) ($r['id'] ?? 0);
            $row = $rid > 0 ? $this->posts->findById($rid) : null;
            if ($row !== null) {
                $this->applySourceFromPostRow($item, $row);
                if (count($refs) > 1) {
                    $item['source_extra_count'] = count($refs) - 1;
                }
            }
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $row
     */
    private function applySourceFromPostRow(array &$item, array $row): void
    {
        $ctype = (string) ($row['content_type'] ?? 'post');
        $title = (string) ($row['title'] ?? '');
        $item['source_title'] = $title;
        $item['source_title_display'] = $this->clipTitleForDisplay($title, 20);
        $item['source_url'] = $this->publicUrlForContent($row);
        $item['source_kind'] = $ctype === 'page' ? 'page' : 'post';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function publicUrlForContent(array $row): string
    {
        $ctype = (string) ($row['content_type'] ?? 'post');
        if ($ctype === 'page') {
            return '/' . ltrim((string) ($row['slug'] ?? ''), '/');
        }

        return PostService::postPublicPath($row);
    }

    private function clipTitleForDisplay(string $title, int $max): string
    {
        $t = trim($title);
        if ($t === '') {
            return '';
        }
        if (mb_strlen($t) <= $max) {
            return $t;
        }

        return mb_substr($t, 0, $max) . '...';
    }

    public function delete(array $actor, int $mediaId): void
    {
        if (($actor['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Forbidden');
        }

        $refs = $this->posts->findContentReferencingMediaId($mediaId);
        if ($refs !== []) {
            throw new MediaInUseException(
                'Файл используется в тексте поста или страницы. Уберите вставку из редактора и сохраните материал.',
                $refs
            );
        }

        $item = $this->media->deleteById($mediaId);
        if ($item === null) {
            throw new \InvalidArgumentException('Media not found');
        }

        $storedPath = (string) ($item['stored_path'] ?: $item['stored_name']);
        $fullPath = dirname(__DIR__, 2) . '/storage/media/' . $storedPath;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function storeInternal(array $actor, ?int $postId, array $file): array
    {
        if (!in_array(($actor['role'] ?? ''), ['admin', 'author'], true)) {
            throw new \RuntimeException('Forbidden');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Upload failed');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $mime = (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp);
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            throw new \InvalidArgumentException('File type is not allowed');
        }

        $maxImage = (int) Config::get('UPLOAD_MAX_IMAGE_BYTES', '5242880');
        $maxVideo = (int) Config::get('UPLOAD_MAX_VIDEO_BYTES', '524288000');
        $maxPdf = (int) Config::get('UPLOAD_MAX_PDF_BYTES', '20971520');
        if ($mime === 'application/pdf') {
            $max = $maxPdf;
        } elseif (str_starts_with($mime, 'video/')) {
            $max = $maxVideo;
        } else {
            $max = $maxImage;
        }
        if ($size <= 0 || $size > $max) {
            throw new \InvalidArgumentException('File size is not allowed');
        }

        $extMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'application/pdf' => 'pdf',
        ];
        $ext = $extMap[$mime];
        $storedName = bin2hex(random_bytes(24)) . '.' . $ext;

        if ($mime === 'application/pdf') {
            $kind = 'file';
            $subDir = 'files';
        } elseif (str_starts_with($mime, 'image/')) {
            $kind = 'image';
            $subDir = 'images';
        } else {
            $kind = 'video';
            $subDir = 'video';
        }
        $targetDir = dirname(__DIR__, 2) . '/storage/media/' . $subDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0750, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Cannot create media directory');
        }

        $targetPath = $targetDir . '/' . $storedName;
        if (!move_uploaded_file($tmp, $targetPath)) {
            throw new \RuntimeException('Cannot move uploaded file');
        }

        return $this->media->create([
            'post_id' => $postId,
            'owner_id' => (int) $actor['sub'],
            'original_name' => (string) ($file['name'] ?? 'upload'),
            'stored_name' => $storedName,
            'stored_path' => $subDir . '/' . $storedName,
            'kind' => $kind,
            'mime_type' => $mime,
            'size_bytes' => $size,
        ]);
    }
}
