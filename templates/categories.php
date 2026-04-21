<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/panel/categories';
ob_start();
?>
<section class="panel-grid">
  <h1>Панель управления</h1>
  <div class="panel-nav">
    <div class="segmented" role="tablist" aria-label="Admin sections">
      <input type="radio" name="panel_section" id="panel_posts" value="posts">
      <label for="panel_posts">Посты</label>
      <input type="radio" name="panel_section" id="panel_pages" value="pages">
      <label for="panel_pages">Страницы</label>
      <input type="radio" name="panel_section" id="panel_categories" value="categories" checked>
      <label for="panel_categories">Категории</label>
      <input type="radio" name="panel_section" id="panel_users" value="users">
      <label for="panel_users">Пользователи</label>
      <input type="radio" name="panel_section" id="panel_media" value="media">
      <label for="panel_media">Медиа</label>
    </div>
  </div>

  <div class="panel-block">
    <div class="panel-head">
      <h2>Категории</h2>
      <a class="btn btn-success" href="/panel/categories/new">Новая категория</a>
    </div>
    <div class="panel-list">
      <?php if (($categories ?? []) === []): ?>
        <div class="meta">Категорий пока нет.</div>
      <?php else: ?>
        <?php foreach ($categories as $c): ?>
          <div class="panel-list-item panel-list-item--row">
            <div class="panel-list-item__text">
              <strong><?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
              <div class="meta"><?= htmlspecialchars((string) ($c['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · постов: <?= (int) ($c['cnt'] ?? 0) ?></div>
            </div>
            <div class="panel-list-actions panel-list-actions--categories">
              <?php if (($c['slug'] ?? '') !== 'news'): ?>
                <a class="btn btn-outline" href="/panel/categories/<?= (int) ($c['id'] ?? 0) ?>/edit">Редактировать</a>
                <form method="post" action="/panel/categories/<?= (int) ($c['id'] ?? 0) ?>/delete" data-confirm="Удалить категорию? Посты перенесутся в «Новости».">
                  <button class="btn btn-danger" type="submit">Удалить</button>
                </form>
              <?php else: ?>
                <button class="btn btn-outline" type="button" disabled>Редактировать</button>
                <button class="btn btn-danger" type="button" disabled>Удалить</button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
