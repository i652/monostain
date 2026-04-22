<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/game/invites';
ob_start();
?>
<section class="panel-grid">
  <h1>Приглашения</h1>
  <div class="panel-block">
    <?php if ($invites === []): ?>
      <p>У вас нет активных приглашений.</p>
    <?php else: ?>
      <div class="panel-list">
      <?php foreach ($invites as $invite): ?>
        <div class="panel-list-item">
          <div class="meta">Токен: <code><?= htmlspecialchars((string) $invite['token'], ENT_QUOTES, 'UTF-8') ?></code></div>
          <button class="btn btn-outline js-accept-invite" data-token="<?= htmlspecialchars((string) $invite['token'], ENT_QUOTES, 'UTF-8') ?>">Принять</button>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
