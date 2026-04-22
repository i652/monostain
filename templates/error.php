<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/error';
ob_start();
?>
<section class="not-found">
  <h1>Сервис временно недоступен</h1>
  <p><?= htmlspecialchars((string) ($description ?? 'Произошла ошибка запуска приложения.'), ENT_QUOTES, 'UTF-8') ?></p>
  <p>Проверьте настройки БД и перезапустите проект.</p>
  <p><a href="/">Повторить попытку</a></p>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
