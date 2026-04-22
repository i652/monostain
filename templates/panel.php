<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/panel/posts';
ob_start();
?>
<section class="panel-grid">
  <h1>Панель управления</h1>

  <div class="panel-nav">
    <div class="segmented" role="tablist" aria-label="Panel sections">
      <input type="radio" name="panel_section" id="panel_posts" value="posts" <?= ($section ?? 'posts') === 'posts' ? 'checked' : '' ?>>
      <label for="panel_posts">Посты</label>
      <input type="radio" name="panel_section" id="panel_pages" value="pages" <?= ($section ?? 'posts') === 'pages' ? 'checked' : '' ?>>
      <label for="panel_pages">Страницы</label>
      <input type="radio" name="panel_section" id="panel_categories" value="categories">
      <label for="panel_categories">Категории</label>
      <input type="radio" name="panel_section" id="panel_users" value="users">
      <label for="panel_users">Пользователи</label>
      <input type="radio" name="panel_section" id="panel_media" value="media">
      <label for="panel_media">Медиа</label>
      <input type="radio" name="panel_section" id="panel_games" value="games" <?= ($section ?? 'posts') === 'games' ? 'checked' : '' ?>>
      <label for="panel_games">Игры</label>
    </div>
  </div>

  <section class="panel-block panel-section" data-panel="posts" <?= ($section ?? 'posts') !== 'posts' ? 'hidden' : '' ?>>
    <div class="panel-head">
      <h2>Посты</h2>
      <a class="btn btn-success" href="/panel/posts/new">Новый пост</a>
    </div>
    <div id="posts-panel-list" class="panel-list" data-kind="posts"></div>
    <div class="load-more-wrap panel-load-more-wrap">
      <button id="posts-panel-more" class="blog-nav" type="button">Загрузить ещё</button>
    </div>
  </section>

  <section class="panel-block panel-section" data-panel="pages" <?= ($section ?? 'posts') !== 'pages' ? 'hidden' : '' ?>>
    <div class="panel-head">
      <h2>Страницы</h2>
      <a class="btn btn-success" href="/panel/pages/new">Новая страница</a>
    </div>
    <div id="pages-panel-list" class="panel-list" data-kind="pages"></div>
  </section>

  <section class="panel-block panel-section" data-panel="games" <?= ($section ?? 'posts') !== 'games' ? 'hidden' : '' ?>>
    <div class="panel-head">
      <h2>Игры</h2>
      <a class="btn btn-outline" href="/panel/games">Обновить</a>
    </div>
    <?php if (isset($gamesDashboard) && is_array($gamesDashboard)): ?>
      <p class="meta">Создано сегодня: <strong><?= (int) ($gamesDashboard['today_count'] ?? 0) ?></strong></p>
      <p class="meta">Создано за всё время: <strong><?= (int) ($gamesDashboard['all_count'] ?? 0) ?></strong></p>

      <h3>Игры за сегодня</h3>
      <div class="panel-list">
      <?php foreach (($gamesDashboard['today_games'] ?? []) as $g): ?>
        <div class="panel-list-item">
          <div><strong><?= htmlspecialchars((string) ($g['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></div>
          <div class="meta">
            ID: <?= htmlspecialchars((string) ($g['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?> ·
            Создал: <?= htmlspecialchars((string) ($g['creator_nickname'] ?? ''), ENT_QUOTES, 'UTF-8') ?> ·
            <?= htmlspecialchars((string) ($g['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
          </div>
        </div>
      <?php endforeach; ?>
      </div>

      <h3>Игры за всё время</h3>
      <div class="panel-list">
      <?php foreach (($gamesDashboard['all_games'] ?? []) as $g): ?>
        <div class="panel-list-item">
          <div><strong><?= htmlspecialchars((string) ($g['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></div>
          <div class="meta">
            ID: <?= htmlspecialchars((string) ($g['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?> ·
            Создал: <?= htmlspecialchars((string) ($g['creator_nickname'] ?? ''), ENT_QUOTES, 'UTF-8') ?> ·
            <?= htmlspecialchars((string) ($g['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
