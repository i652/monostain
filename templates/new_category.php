<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/panel/categories/new';
$error = isset($_GET['error']) ? (string) $_GET['error'] : '';
ob_start();
?>
<section class="panel-grid">
  <h1>Новая категория</h1>
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
    <?php if ($error !== ''): ?>
      <p class="form-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/panel/categories" class="quick-form panel-form">
      <label for="cat_name">Название</label>
      <input id="cat_name" type="text" name="name" placeholder="Например, Обзоры" required>
      <label for="cat_slug">Slug (URL)</label>
      <input id="cat_slug" type="text" name="slug" placeholder="obzory" required>
      <button type="submit" class="btn btn-outline">Создать</button>
    </form>
  </div>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
