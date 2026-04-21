<?php declare(strict_types=1); $viewer = $viewer ?? null; ?>
<!doctype html>
<html lang="<?= htmlspecialchars(\Stain\Config::get('SITE_LANG', 'ru'), ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title ?? 'Stain', ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="description" content="<?= htmlspecialchars($description ?? 'Stain CMS', ENT_QUOTES, 'UTF-8') ?>">
  <link rel="canonical" href="<?= htmlspecialchars(($canonical ?? ''), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/style.css">
  <?php
  $ogUrl = $ogUrl ?? ($canonical ?? '');
  $ogTitle = $ogTitle ?? ($title ?? '');
  $ogDescription = $ogDescription ?? ($description ?? '');
  $ogType = $ogType ?? 'website';
  if (($ogUrl ?? '') !== ''):
  ?>
  <meta property="og:url" content="<?= htmlspecialchars((string) $ogUrl, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:type" content="<?= htmlspecialchars((string) $ogType, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:title" content="<?= htmlspecialchars((string) $ogTitle, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars((string) $ogDescription, ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:locale" content="ru_RU">
  <?php endif; ?>
  <?php if (isset($jsonLd) && $jsonLd !== ''): ?>
  <script type="application/ld+json"><?= $jsonLd ?></script>
  <?php endif; ?>
</head>
<body>
  <?php if (($viewer ?? null) === null && !($hideAuthPopup ?? false)): ?>
    <div id="auth-popup" class="auth-overlay" role="dialog" aria-modal="true" aria-labelledby="auth-popup-title">
      <a href="#" class="auth-overlay__backdrop" aria-label="Закрыть окно авторизации"></a>
      <div class="auth-popup">
        <a class="auth-popup__close" href="#" aria-label="Закрыть">
          <svg class="auth-popup__close-icon" viewBox="0 0 24 24" width="22" height="22" focusable="false" aria-hidden="true">
            <path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linecap="round"/>
          </svg>
        </a>
        <h2 id="auth-popup-title" class="auth-popup__title">Авторизация</h2>
        <div class="auth-popup__content">
          <section class="auth-wrap auth-wrap--modal">
            <?php $authFormUid = 'popup'; require __DIR__ . '/partials/auth_forms.php'; ?>
          </section>
        </div>
      </div>
    </div>
  <?php endif; ?>
  <header class="site-header">
    <a class="brand-wrap" href="/">
      <span class="brand">stain</span>
      <span class="brand-subtitle">Технологичные решения</span>
    </a>
    <nav class="top-nav" id="top-nav">
      <div class="top-nav-center">
        <a class="pill-link pill-link--nav" href="/services.html">Услуги</a>
        <a class="pill-link pill-link--nav" href="/contacts.html">Контакты</a>
        <a class="pill-link pill-link--nav" href="/about.html">О нас</a>
      </div>
      <div class="top-nav-right">
        <?php if (in_array(($viewer['role'] ?? ''), ['admin', 'author'], true)): ?>
          <a class="pill-link pill-link--new-post" href="/panel/posts/new">+ Новый пост</a>
        <?php endif; ?>
        <?php if (($viewer['role'] ?? '') === 'admin'): ?>
          <a class="pill-link pill-link--nav" href="/panel/posts">Панель</a>
        <?php endif; ?>
        <?php if ($viewer !== null): ?>
          <a class="pill-link pill-link--nav" href="/auth/logout">Выход</a>
        <?php else: ?>
          <a class="pill-link pill-link--nav" href="#auth-popup">Вход</a>
        <?php endif; ?>
      </div>
    </nav>
    <button class="burger-btn" id="burger-btn" aria-label="Menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </header>
  <main class="container">
    <?= $content ?? '' ?>
  </main>
  <footer class="footer">
    <div class="footer__addr">
      <h1 class="footer__logo"><a class="footer__logo-link" href="/">stain</a></h1>
      <p class="footer__subtitle">Технологичные решения</p>
      <div class="footer-controls">
        <div class="theme-toggle-wrap" role="group" aria-label="Режим темы">
          <button class="theme-toggle-btn" type="button" data-theme-mode="day" aria-label="Светлая тема" title="День">
            <svg viewBox="0 0 24 24" width="16" height="16" focusable="false" aria-hidden="true">
              <circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/>
              <path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.2 2.2M16.9 16.9l2.2 2.2M19.1 4.9l-2.2 2.2M7.1 16.9l-2.2 2.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </button>
          <button class="theme-toggle-btn" type="button" data-theme-mode="night" aria-label="Тёмная тема" title="Ночь">
            <svg viewBox="0 0 24 24" width="16" height="16" focusable="false" aria-hidden="true">
              <path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>
        <a class="pill-link pill-link--feedback" href="/feedback">Напишите нам</a>
      </div>
    </div>

    <ul class="footer__nav">
      <li class="nav__item">
        <h2 class="nav__title">Навигация</h2>
        <ul class="nav__ul">
          <li><a href="/">Главная</a></li>
          <li><a href="/services.html">Услуги</a></li>
          <li><a href="/contacts.html">Контакты</a></li>
        </ul>
      </li>
      <li class="nav__item">
        <h2 class="nav__title">Информация</h2>
        <ul class="nav__ul">
          <li><a href="/about.html">О проекте</a></li>
          <li><a href="/rekvizity.html">Реквизиты</a></li>
          <li><a href="/site-map">Карта сайта</a></li>
        </ul>
      </li>
      <li class="nav__item">
        <h2 class="nav__title">Социальные сети</h2>
        <ul class="nav__ul">
          <li><a href="https://vk.com/">ВКонтакте</a></li>
          <li><a href="https://max.ru/">MAX</a></li>
          <li><a href="https://max.ru/">Telegram</a></li>
        </ul>
      </li>
    </ul>

    <div class="legal">
      <p>&copy; 2023 - 2026 stain. Все права защищены.</p>
      <div class="legal__links">
        <span>Сделано с <span class="heart" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" focusable="false"><path d="M12 21s-6.7-4.35-9.2-7.73C.97 10.86 1.2 7.54 3.52 5.9a5.05 5.05 0 0 1 6.48.69L12 8.65l2-2.06a5.05 5.05 0 0 1 6.48-.69c2.32 1.64 2.55 4.96.72 7.37C18.7 16.65 12 21 12 21Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></span> в России</span>
      </div>
    </div>
  </footer>
  <script src="/assets/ui-dialog.js"></script>
  <?php if (($viewer ?? null) === null): ?>
  <script src="/assets/auth-register.js" defer></script>
  <?php endif; ?>
  <script src="/assets/load-more.js"></script>
  <script src="/assets/editor.js"></script>
  <script src="/assets/draft-post.js"></script>
  <script src="/assets/auth-popup.js"></script>
  <script src="/assets/theme.js"></script>
</body>
</html>
