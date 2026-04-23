<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/panel/game-boards';
$notice = isset($_GET['notice']) ? (string) $_GET['notice'] : '';
ob_start();
?>
<section class="panel-grid">
  <h1>Конструктор карт</h1>
  <div class="panel-block">
    <?php if ($notice !== ''): ?>
      <p class="form-ok"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <div style="display:flex;justify-content:flex-end;margin-bottom:10px">
      <a class="btn btn-outline" href="/panel/game-boards/new">Новая карта</a>
    </div>
    <div class="panel-list" style="margin-top:6px">
      <?php foreach ($templates as $tpl): ?>
        <div class="panel-list-item">
          <strong><a href="/panel/game-boards/<?= (int) ($tpl['id'] ?? 0) ?>/edit"><?= htmlspecialchars((string) ($tpl['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a></strong>
          <div class="meta">Статус: <?= (bool) ($tpl['is_published'] ?? false) ? 'published' : 'draft' ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
