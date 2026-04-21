<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/';
$viewer = $viewer ?? null;
$hasMore = $hasMore ?? false;
$formatDate = static function (?string $raw): string {
    if ($raw === null || $raw === '') {
        return '';
    }
    $dt = new DateTimeImmutable($raw);
    return $dt->format('d.m.Y \в H:i');
};
ob_start();
?>

<section class="post-list" id="post-list" data-category-slug="">
  <?php foreach ($posts as $idx => $item): ?>
    <?php
    $mod = (int) $idx % 3;
    $cardClass = $mod === 0 ? 'post-card post-card--wide' : 'post-card post-card--narrow';
    $catSlug = (string) ($item['category_slug'] ?? 'news');
    $href = '/' . htmlspecialchars($catSlug, ENT_QUOTES, 'UTF-8') . '/' . htmlspecialchars($item['slug'], ENT_QUOTES, 'UTF-8');
    ?>
    <article class="<?= htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8') ?>" data-post-id="<?= (int) $item['id'] ?>" data-post-slug="<?= htmlspecialchars($item['slug'], ENT_QUOTES, 'UTF-8') ?>" data-category-slug="<?= htmlspecialchars($catSlug, ENT_QUOTES, 'UTF-8') ?>">
      <div class="post-card-head">
        <h2 class="post-title"><a class="post-link" href="<?= $href ?>"><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
        <?php if (($viewer['role'] ?? '') === 'admin'): ?>
          <a class="btn btn-outline btn-edit" href="/panel/posts/<?= (int) $item['id'] ?>/edit">Редактировать</a>
        <?php endif; ?>
      </div>
      <div class="post-preview"><?= $item['preview_text'] ?? '' ?></div>
      <div class="post-card-foot">
        <small class="date-pill"><?= htmlspecialchars($formatDate((string) ($item['published_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></small>
        <a class="post-category-pill" href="/<?= htmlspecialchars($catSlug, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($item['category_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
      </div>
    </article>
  <?php endforeach; ?>
</section>

<?php if ($hasMore): ?>
  <div class="section padding-top-bottom background-dark">
    <div class="container load-more-wrap">
      <button id="load-more" class="blog-nav" type="button" aria-controls="post-list" aria-busy="false">Загрузить ещё</button>
    </div>
  </div>
<?php endif; ?>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
