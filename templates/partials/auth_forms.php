<?php
declare(strict_types=1);
$authFormUid = $authFormUid ?? 'page';
$radioName = 'auth_section_' . $authFormUid;
$idLogin = $authFormUid . '-auth-login';
$idRegister = $authFormUid . '-auth-register';
?>
<div class="auth-tabs">
  <div class="segmented" role="tablist" aria-label="Auth sections">
    <input type="radio" name="<?= htmlspecialchars($radioName, ENT_QUOTES, 'UTF-8') ?>" id="<?= htmlspecialchars($idLogin, ENT_QUOTES, 'UTF-8') ?>" value="login" checked>
    <label for="<?= htmlspecialchars($idLogin, ENT_QUOTES, 'UTF-8') ?>">Вход</label>
    <input type="radio" name="<?= htmlspecialchars($radioName, ENT_QUOTES, 'UTF-8') ?>" id="<?= htmlspecialchars($idRegister, ENT_QUOTES, 'UTF-8') ?>" value="register">
    <label for="<?= htmlspecialchars($idRegister, ENT_QUOTES, 'UTF-8') ?>">Регистрация</label>
  </div>
</div>

<div class="auth-panels">
  <form method="post" action="/auth/login" class="quick-form auth-panel" data-panel="login">
    <h2>Вход</h2>
    <input type="email" name="email" placeholder="Email" required autocomplete="email">
    <input type="password" name="password" placeholder="Пароль" required autocomplete="current-password">
    <button type="submit" class="btn btn-outline">Войти</button>
  </form>

  <form method="post" action="/auth/register" class="quick-form auth-panel js-auth-register" data-panel="register" hidden autocomplete="off">
    <h2>Регистрация</h2>
    <label class="sr-only" for="<?= htmlspecialchars($idRegister, ENT_QUOTES, 'UTF-8') ?>-nick">Никнейм</label>
    <input id="<?= htmlspecialchars($idRegister, ENT_QUOTES, 'UTF-8') ?>-nick" type="text" name="nickname" placeholder="Никнейм" required maxlength="32" autocomplete="username" data-availability="nickname">
    <p class="form-hint form-error js-reg-nick-msg" hidden></p>
    <label class="sr-only" for="<?= htmlspecialchars($idRegister, ENT_QUOTES, 'UTF-8') ?>-email">Email</label>
    <input id="<?= htmlspecialchars($idRegister, ENT_QUOTES, 'UTF-8') ?>-email" type="email" name="email" placeholder="Email" required autocomplete="email" data-availability="email">
    <p class="form-hint form-error js-reg-email-msg" hidden></p>
    <input type="password" name="password" placeholder="Пароль" required minlength="8" autocomplete="new-password">
    <button type="submit" class="btn btn-outline">Зарегистрироваться</button>
  </form>
</div>
