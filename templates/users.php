<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/panel/users';
$error = isset($_GET['error']) ? (string) $_GET['error'] : '';
ob_start();
?>
<section class="panel-grid panel-grid--users">
  <h1>Панель управления</h1>
  <div class="panel-nav">
    <div class="segmented" role="tablist" aria-label="Разделы панели">
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
    <?php if ($error !== ''): ?>
      <p class="form-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <div class="panel-head">
      <h2>Пользователи</h2>
      <a class="btn btn-success" href="/panel/users/new">Новый пользователь</a>
    </div>
    <div class="panel-list">
      <?php foreach ($users as $u): ?>
        <?php
          $uid = (int) $u['id'];
        ?>
        <div class="panel-list-item">
          <div><strong><?= htmlspecialchars((string) ($u['nickname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></div>
          <div class="meta"><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?> · id: <?= $uid ?> · роль: <?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?></div>
          <div class="role-row">
            <form method="post" action="/panel/users/<?= $uid ?>/role" class="role-form role-form-main">
              <select name="role" class="role-select">
                <option value="author" <?= $u['role'] === 'author' ? 'selected' : '' ?>>author</option>
                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
              </select>
              <button class="btn btn-outline" type="submit">Сохранить</button>
            </form>
            <a class="btn btn-outline" href="#pwd-u-<?= $uid ?>">Сменить пароль</a>
          <?php
            $canDelete = $uid !== (int) ($viewer['sub'] ?? 0);
            if ($canDelete && ($u['role'] ?? '') === 'admin') {
                $adminCount = 0;
                foreach ($users as $x) {
                    if (($x['role'] ?? '') === 'admin') {
                        $adminCount++;
                    }
                }
                $canDelete = $adminCount > 1;
            }
          ?>
          <?php if ($canDelete): ?>
            <form method="post" action="/panel/users/<?= $uid ?>/delete" class="role-form role-form--delete" data-confirm="Удалить этого пользователя?">
              <button class="btn btn-danger" type="submit">Удалить</button>
            </form>
          <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php foreach ($users as $u): ?>
      <?php
        $uid = (int) $u['id'];
        $roleField = $u['role'] === 'admin' ? 'admin' : 'author';
      ?>
      <div id="pwd-u-<?= $uid ?>" class="pwd-overlay" role="dialog" aria-modal="true" aria-labelledby="pwd-title-<?= $uid ?>">
        <a href="#" class="pwd-overlay__backdrop" aria-label="Закрыть"></a>
        <div class="pwd-popup">
          <a href="#" class="pwd-popup__close" aria-label="Закрыть">
            <svg viewBox="0 0 24 24" width="20" height="20" focusable="false" aria-hidden="true">
              <path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linecap="round"/>
            </svg>
          </a>
          <h2 id="pwd-title-<?= $uid ?>" class="pwd-popup__title">Смена пароля</h2>
          <p class="meta"><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></p>
          <form method="post" action="/panel/users/<?= $uid ?>/role" class="quick-form">
            <input type="hidden" name="role" value="<?= htmlspecialchars($roleField, ENT_QUOTES, 'UTF-8') ?>">
            <input class="password-inline" type="password" name="password" placeholder="Новый пароль" minlength="8" required autocomplete="new-password">
            <input class="password-inline" type="password" name="password_confirm" placeholder="Подтверждение пароля" minlength="8" required autocomplete="new-password">
            <button class="btn btn-outline" type="submit">Сохранить пароль</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
