<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/' . $page['slug'];
$viewer = $viewer ?? null;
ob_start();
?>
<div class="section padding-top-big padding-bottom over-hide">
  <div class="container">
    <article class="post-full">
      <h1><?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?></h1>
      <div class="post-body"><?= \Stain\Util\HtmlPublicContent::enhanceImagesForPublic((string) ($page['full_text'] ?? '')) ?></div>
    </article>
  </div>
</div>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
