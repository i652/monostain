<?php
declare(strict_types=1);
use Stain\Services\PostService;

$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/site-map';
ob_start();
?>
<section class="quick-create">
  <h1>Карта сайта</h1>
  <h2>Информация</h2>
  <ul class="site-map-list">
    <?php foreach ($pages as $p): ?>
      <li><a href="<?= htmlspecialchars($p['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?></a></li>
    <?php endforeach; ?>
  </ul>
  <h2>Посты</h2>
  <ul class="site-map-list">
    <?php foreach ($posts as $p): ?>
      <li><a href="<?= htmlspecialchars(PostService::postPublicPath($p), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?></a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
