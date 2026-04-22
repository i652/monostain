<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/panel/users/new';
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
      <input type="radio" name="panel_section" id="panel_categories" value="categories">
      <label for="panel_categories">Категории</label>
      <input type="radio" name="panel_section" id="panel_users" value="users" checked>
      <label for="panel_users">Пользователи</label>
      <input type="radio" name="panel_section" id="panel_media" value="media">
      <label for="panel_media">Медиа</label>
    </div>
  </div>

  <div class="panel-block">
    <div class="panel-head">
      <h2>Новый пользователь</h2>
      <a class="btn btn-outline" href="/panel/users">К списку</a>
    </div>
    <?php if ($error !== ''): ?>
      <p class="form-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/panel/users/new" class="quick-form panel-form">
      <label for="user_nickname">Никнейм</label>
      <input id="user_nickname" type="text" name="nickname" required maxlength="32" autocomplete="off" placeholder="Уникальный ник">
      <label for="user_email">Email</label>
      <input id="user_email" type="email" name="email" required autocomplete="off">
      <label for="user_password">Пароль</label>
      <input id="user_password" type="password" name="password" required minlength="8" autocomplete="new-password">
      <label for="user_role">Роль</label>
      <select id="user_role" name="role">
        <option value="player">player</option>
        <option value="author">author</option>
        <option value="admin">admin</option>
      </select>
      <button type="submit" class="btn btn-outline">Создать</button>
    </form>
  </div>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
