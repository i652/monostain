<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/404';
ob_start();
?>
<section class="not-found">
  <h1>404</h1>
  <p>Запрошенная страница не найдена.</p>
  <p><a href="/">Вернуться на главную</a></p>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
