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
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
