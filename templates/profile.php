<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/profile';
$error = isset($_GET['error']) ? (string) $_GET['error'] : '';
$notice = isset($_GET['notice']) ? (string) $_GET['notice'] : '';
ob_start();
?>
<section class="panel-grid">
  <h1>Профиль</h1>
  <div class="panel-block">
    <?php if ($error !== ''): ?>
      <p class="form-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($notice !== ''): ?>
      <p class="form-ok"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/profile" class="quick-form panel-form">
      <label for="profile_nickname">Никнейм</label>
      <input id="profile_nickname" type="text" name="nickname" required maxlength="32" value="<?= htmlspecialchars((string) ($user['nickname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      <label for="profile_email">Email</label>
      <input id="profile_email" type="email" name="email" required value="<?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      <a class="btn btn-outline" href="#pwd-profile">Сменить пароль</a>
      <button class="btn btn-outline" type="submit">Сохранить профиль</button>
    </form>
  </div>
</section>

<div id="pwd-profile" class="pwd-overlay" role="dialog" aria-modal="true" aria-labelledby="pwd-profile-title">
  <a href="#" class="pwd-overlay__backdrop" aria-label="Закрыть"></a>
  <div class="pwd-popup">
    <a href="#" class="pwd-popup__close" aria-label="Закрыть">
      <svg viewBox="0 0 24 24" width="20" height="20" focusable="false" aria-hidden="true">
        <path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linecap="round"/>
      </svg>
    </a>
    <h2 id="pwd-profile-title" class="pwd-popup__title">Смена пароля</h2>
    <p class="meta"><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post" action="/profile/password" class="quick-form">
      <input class="password-inline" type="password" name="password" placeholder="Новый пароль" minlength="8" required autocomplete="new-password">
      <input class="password-inline" type="password" name="password_confirm" placeholder="Подтверждение пароля" minlength="8" required autocomplete="new-password">
      <button class="btn btn-outline" type="submit">Сохранить пароль</button>
    </form>
  </div>
</div>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
