<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/game/' . rawurlencode((string) ($gameView['game']['id'] ?? ''));
$game = $gameView['game'];
$players = $gameView['players'];
$events = $gameView['events'];
$chat = $gameView['chat'];
$self = $gameView['self'];
ob_start();
?>
<section class="game-room" data-game-id="<?= htmlspecialchars((string) $game['id'], ENT_QUOTES, 'UTF-8') ?>" data-player-id="<?= (int) $self['id'] ?>">
  <header class="game-room__header">
    <h1><?= htmlspecialchars((string) $game['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="game-room__tools">
      <button class="btn btn-outline" id="game-invite-link">Пригласить по ссылке</button>
      <button class="btn btn-outline" id="game-invite-nick">Пригласить по никнейму</button>
    </div>
  </header>

  <nav class="game-tabs" role="tablist" aria-label="Вкладки игры">
    <button class="game-tab is-active" data-tab="board">🗺️ Поле</button>
    <button class="game-tab" data-tab="ledger">📒 Бухгалтерия</button>
    <button class="game-tab" data-tab="timeline">🕘 Хронология</button>
    <button class="game-tab" data-tab="stats">📊 Статистика</button>
    <button class="game-tab" data-tab="trade">🤝 Сделки</button>
    <button class="game-tab" data-tab="rules">📜 Правила</button>
    <button class="game-tab" data-tab="history">📚 История</button>
  </nav>

  <div class="game-panels">
    <section class="game-panel is-active" data-panel="board">
      <div class="mono-board">
        <div class="mono-board__main">
          <div class="board" id="board">
            <div class="corner space se" data-pos="0"><div class="container"><div class="go">СТАРТ</div></div></div>
            <div class="row horizontal south">
              <div class="space property" data-pos="1"><div class="container"><div class="name">Средиземноморский пр-т</div></div></div>
              <div class="space deck chest" data-pos="2"><div class="container"><div class="label">Общественная казна</div></div></div>
              <div class="space property" data-pos="3"><div class="container"><div class="name">Балтийский пр-т</div></div></div>
              <div class="space tax income" data-pos="4"><div class="container"><div class="label">Подоходный налог</div></div></div>
              <div class="space railroad" data-pos="5"><div class="container"><div class="name">Чтение Railroad</div></div></div>
              <div class="space property" data-pos="6"><div class="container"><div class="name">Ориентал авеню</div></div></div>
              <div class="space deck chance" data-pos="7"><div class="container"><div class="label">Шанс</div></div></div>
              <div class="space property" data-pos="8"><div class="container"><div class="name">Вермонт авеню</div></div></div>
              <div class="space property" data-pos="9"><div class="container"><div class="name">Коннектикут авеню</div></div></div>
            </div>
            <div class="corner space sw" data-pos="10"><div class="container"><div class="label">Тюрьма</div></div></div>
            <div class="row vertical west">
              <div class="space property" data-pos="19"><div class="container"><div class="name">Нью-Йорк авеню</div></div></div>
              <div class="space property" data-pos="18"><div class="container"><div class="name">Теннесси авеню</div></div></div>
              <div class="space deck chest" data-pos="17"><div class="container"><div class="label">Казна</div></div></div>
              <div class="space property" data-pos="16"><div class="container"><div class="name">St. James Place</div></div></div>
              <div class="space railroad" data-pos="15"><div class="container"><div class="name">Pennsylvania Railroad</div></div></div>
              <div class="space property" data-pos="14"><div class="container"><div class="name">Virginia Avenue</div></div></div>
              <div class="space property" data-pos="13"><div class="container"><div class="name">States Avenue</div></div></div>
              <div class="space utility" data-pos="12"><div class="container"><div class="name">Electric Company</div></div></div>
              <div class="space property" data-pos="11"><div class="container"><div class="name">St. Charles Place</div></div></div>
            </div>
            <div class="center">
              <div class="logo">Monostain-Полия</div>
              <div class="dice">
                <div class="dice-group">
                  <div class="die" id="die-1" data-value="0"><span></span></div>
                  <div class="die" id="die-2" data-value="0"><span></span></div>
                </div>
                <div class="dice-status">Результат броска:</div>
                <div class="dice-value">0</div>
              </div>
            </div>
            <div class="row vertical east">
              <div class="space property" data-pos="31"><div class="container"><div class="name">Pacific Avenue</div></div></div>
              <div class="space property" data-pos="32"><div class="container"><div class="name">North Carolina Avenue</div></div></div>
              <div class="space deck chest" data-pos="33"><div class="container"><div class="label">Казна</div></div></div>
              <div class="space property" data-pos="34"><div class="container"><div class="name">Pennsylvania Avenue</div></div></div>
              <div class="space railroad" data-pos="35"><div class="container"><div class="name">Short Line</div></div></div>
              <div class="space deck chance" data-pos="36"><div class="container"><div class="label">Шанс</div></div></div>
              <div class="space property" data-pos="37"><div class="container"><div class="name">Park Place</div></div></div>
              <div class="space tax luxury" data-pos="38"><div class="container"><div class="label">Налог на роскошь</div></div></div>
              <div class="space property" data-pos="39"><div class="container"><div class="name">Boardwalk</div></div></div>
            </div>
            <div class="corner space ne" data-pos="30"><div class="container"><div class="label">В тюрьму</div></div></div>
            <div class="row horizontal north">
              <div class="space property" data-pos="21"><div class="container"><div class="name">Kentucky Avenue</div></div></div>
              <div class="space property" data-pos="22"><div class="container"><div class="name">Indiana Avenue</div></div></div>
              <div class="space deck chance" data-pos="23"><div class="container"><div class="label">Шанс</div></div></div>
              <div class="space property" data-pos="24"><div class="container"><div class="name">Illinois Avenue</div></div></div>
              <div class="space railroad" data-pos="25"><div class="container"><div class="name">B&O Railroad</div></div></div>
              <div class="space property" data-pos="26"><div class="container"><div class="name">Atlantic Avenue</div></div></div>
              <div class="space property" data-pos="27"><div class="container"><div class="name">Ventnor Avenue</div></div></div>
              <div class="space utility" data-pos="28"><div class="container"><div class="name">Water Works</div></div></div>
              <div class="space property" data-pos="29"><div class="container"><div class="name">Marvin Gardens</div></div></div>
            </div>
          </div>
          <div class="mono-board__actions">
            <button class="btn btn-outline js-game-command" data-action="roll">Бросить кубики</button>
            <button class="btn btn-outline js-game-command" data-action="end_turn">Завершить ход</button>
          </div>
        </div>
        <aside class="mono-board__players">
          <?php foreach ($players as $p): ?>
            <div class="player-chip" data-player-id="<?= (int) $p['id'] ?>">
              <strong><?= htmlspecialchars((string) $p['nickname_snapshot'], ENT_QUOTES, 'UTF-8') ?></strong>
              <span>💰 <?= (int) $p['cash'] ?></span>
              <span>📍 <?= (int) $p['position'] ?></span>
            </div>
          <?php endforeach; ?>
        </aside>
      </div>
      <div class="chat-box">
        <div class="chat-log" id="chat-log">
          <?php foreach ($chat as $m): ?>
            <div class="chat-msg">
              <strong><?= htmlspecialchars((string) ($m['from_name'] ?? 'Система'), ENT_QUOTES, 'UTF-8') ?>:</strong>
              <span><?= htmlspecialchars((string) ($m['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <form id="chat-form" class="chat-form">
          <input type="text" name="message" maxlength="500" required placeholder="Сообщение в чат">
          <button type="submit" class="btn btn-outline">Отправить</button>
        </form>
      </div>
    </section>
    <section class="game-panel" data-panel="ledger">
      <div id="ledger-box" class="ledger-box" data-initial-events="<?= htmlspecialchars(json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>"></div>
    </section>
    <section class="game-panel" data-panel="timeline"><div id="timeline-box" class="timeline-box"></div></section>
    <section class="game-panel" data-panel="stats"><div id="stats-box">Статистика игроков обновляется из событий.</div></section>
    <section class="game-panel" data-panel="trade"><div id="trade-box">Модуль сделок: обмен деньгами и собственностью.</div></section>
    <section class="game-panel" data-panel="rules"><div id="rules-box">Classic rules (MVP+): движение, рента, налоги, тюрьма, ипотека, дома/отели, сделки.</div></section>
    <section class="game-panel" data-panel="history"><div id="history-box">История партии формируется из event log.</div></section>
  </div>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
