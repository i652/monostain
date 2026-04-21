<?php
declare(strict_types=1);
use Stain\Services\PostService;

$canonical = $canonical ?? (rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . PostService::postPublicPath($post));
$formatDate = static function (?string $raw): string {
    if ($raw === null || $raw === '') {
        return '';
    }
    $dt = new DateTimeImmutable($raw);
    return $dt->format('d.m.Y \в H:i');
};
$catSlug = (string) ($post['category_slug'] ?? 'news');
$catName = (string) ($post['category_name'] ?? $catSlug);
$previewHtml = (string) ($post['preview_text'] ?? '');
$fullHtml = (string) ($post['full_text'] ?? '');
$postBodyHtml = '';
if ($previewHtml !== '' && $fullHtml !== '') {
    $postBodyHtml = $previewHtml === $fullHtml ? $fullHtml : ($previewHtml . $fullHtml);
} elseif ($fullHtml !== '') {
    $postBodyHtml = $fullHtml;
} else {
    $postBodyHtml = $previewHtml;
}
ob_start();
?>
<article class="post-full" data-post-slug="<?= htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8') ?>" data-category-slug="<?= htmlspecialchars($catSlug, ENT_QUOTES, 'UTF-8') ?>">
  <header class="post-full-head">
    <h1 class="post-full-title"><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <button class="btn btn-outline js-share post-full-share" type="button">Поделиться</button>
  </header>
  <?php
    $authorName = trim((string) ($post['author_nickname'] ?? ''));
  ?>
  <?php if ($authorName !== ''): ?>
    <p class="meta post-meta-author">От <?= htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>
  <div class="post-body"><?= \Stain\Util\HtmlPublicContent::enhanceImagesForPublic($postBodyHtml) ?></div>
  <div class="post-card-foot post-full-foot">
    <small class="date-pill"><?= htmlspecialchars($formatDate((string) ($post['published_at'] ?? $post['created_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></small>
    <a class="post-category-pill" href="/<?= htmlspecialchars($catSlug, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?></a>
  </div>
  <?php if (($viewer['role'] ?? '') === 'admin'): ?>
    <div class="post-actions">
      <a class="btn btn-outline btn-edit" href="/panel/posts/<?= (int) $post['id'] ?>/edit">Редактировать</a>
      <form method="post" action="/panel/posts/<?= (int) $post['id'] ?>/delete" data-confirm="Удалить пост?">
        <button class="btn btn-danger" type="submit">Удалить</button>
      </form>
    </div>
  <?php endif; ?>
  <div class="post-nav">
    <div class="post-nav-left">
      <?php if ($newerPost !== null): ?>
        <a href="<?= htmlspecialchars(PostService::postPublicPath($newerPost), ENT_QUOTES, 'UTF-8') ?>">← <?= htmlspecialchars($newerPost['title'], ENT_QUOTES, 'UTF-8') ?></a>
      <?php endif; ?>
    </div>
    <div class="post-nav-right">
      <?php if ($olderPost !== null): ?>
        <a href="<?= htmlspecialchars(PostService::postPublicPath($olderPost), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($olderPost['title'], ENT_QUOTES, 'UTF-8') ?> →</a>
      <?php endif; ?>
    </div>
  </div>
</article>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
