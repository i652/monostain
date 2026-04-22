<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/game';
ob_start();
$formatRuDate = static function (string $raw): string {
  try {
    $d = new DateTimeImmutable($raw);
  } catch (Throwable) {
    return $raw;
  }
  $now = new DateTimeImmutable('now');
  $months = [1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
  $day = (int) $d->format('j');
  $month = $months[(int) $d->format('n')] ?? $d->format('m');
  $time = $d->format('H:i');
  if ($d->format('Y') === $now->format('Y')) {
    return $day . ' ' . $month . ' в ' . $time;
  }
  return $day . ' ' . $month . ' ' . $d->format('Y') . ' года в ' . $time;
};
?>
<section class="panel-grid">
  <h1>Игры</h1>
  <div class="panel-block">
    <?php if ($games === []): ?>
      <p>Пока нет игр. <a href="/game/new">Создать первую</a>.</p>
    <?php else: ?>
      <div class="panel-list">
      <?php foreach ($games as $game): ?>
        <div class="panel-list-item">
          <div class="post-card-head">
            <a class="btn btn-outline" href="/game/<?= htmlspecialchars((string) ($game['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">Открыть</a>
          </div>
          <div class="meta">
            Статус: <?= htmlspecialchars((string) ($game['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?> ·
            Игроков: <?= (int) ($game['players_count'] ?? 0) ?> ·
            Финальные монеты: <?= (int) ($game['final_cash'] ?? 0) ?> ·
            <?= htmlspecialchars($formatRuDate((string) ($game['created_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
