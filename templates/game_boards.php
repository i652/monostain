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
    <form method="post" action="/panel/game-boards" class="quick-form panel-form">
      <label for="board_name">Название карты</label>
      <input id="board_name" type="text" name="name" maxlength="120" required>
      <label class="check-row">
        <input type="checkbox" name="is_published" value="1">
        Сразу опубликовать
      </label>
      <button class="btn btn-outline" type="submit">Создать карту</button>
    </form>
    <div class="panel-list" style="margin-top:16px">
      <?php foreach ($templates as $tpl): ?>
        <div class="panel-list-item">
          <strong><?= htmlspecialchars((string) ($tpl['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
          <div class="meta">Статус: <?= (bool) ($tpl['is_published'] ?? false) ? 'published' : 'draft' ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
