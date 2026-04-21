<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/panel/categories/' . (int) ($category['id'] ?? 0) . '/edit';
$error = isset($_GET['error']) ? (string) $_GET['error'] : '';
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
      <h2>Редактировать категорию</h2>
      <a class="btn btn-outline" href="/panel/categories">Назад</a>
    </div>
    <?php if ($error !== ''): ?>
      <p class="form-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/panel/categories/<?= (int) ($category['id'] ?? 0) ?>/edit" class="panel-form">
      <label>Название</label>
      <input type="text" name="name" required value="<?= htmlspecialchars((string) ($category['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      <label>Slug (для URL)</label>
      <input type="text" name="slug" required value="<?= htmlspecialchars((string) ($category['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      <button class="btn btn-success" type="submit">Сохранить</button>
    </form>
  </div>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
