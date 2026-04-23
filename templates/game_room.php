<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/game/' . rawurlencode((string) ($gameView['game']['id'] ?? ''));
$game = $gameView['game'];
$players = $gameView['players'];
$events = $gameView['events'];
$propertyState = $gameView['property_state'] ?? [];
$chat = $gameView['chat'];
$self = $gameView['self'];
$playersJson = json_encode($players, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$propertyStateJson = json_encode($propertyState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
ob_start();
?>
<section class="game-room" data-game-id="<?= htmlspecialchars((string) $game['id'], ENT_QUOTES, 'UTF-8') ?>" data-player-id="<?= (int) $self['id'] ?>" data-players='<?= htmlspecialchars((string) $playersJson, ENT_QUOTES, 'UTF-8') ?>' data-property-state='<?= htmlspecialchars((string) $propertyStateJson, ENT_QUOTES, 'UTF-8') ?>'>
  <header class="game-room__header">
    <h1>Игровая комната</h1>
    <div class="game-room__tools">
      <button class="btn btn-outline" id="game-invite-link">Пригласить по ссылке</button>
      <button class="btn btn-outline" id="game-invite-nick">Пригласить по никнейму</button>
    </div>
  </header>

  <div class="game-toolbar-row">
    <div class="main-action main-action--top">
      <div class="action roll"><button class="roll-dice js-game-command" data-action="roll">Бросить кубики</button></div>
      <div class="action end hidden"><button class="end-turn js-game-command" data-action="end_turn">Завершить ход</button></div>
      <div class="action purchase hidden">
        <div class="full-width">Стоимость: <strong><span class="money cost-amount">0</span></strong></div>
        <button class="purchase-property">Купить <span class="name"></span></button>
        <div class="or">или</div>
        <button class="skip-property">Пропустить</button>
      </div>
      <div class="action rent hidden">
        <div class="full-width">Рента: <strong><span class="money rent-amount">0</span></strong></div>
        <button class="pay-rent">Оплатить ренту</button>
        <div class="or">или</div>
        <button class="buyout-property">Выкупить клетку</button>
        <input type="number" class="buyout-offer-input hidden" min="0" step="1" aria-label="Сумма выкупа">
      </div>
      <div class="action build hidden">
        <button class="build-house">Построить дом</button>
        <button class="build-hotel">Построить отель</button>
      </div>
      <div class="action roll-pay hidden">
        <button class="roll-dice try-doubles js-game-command" data-action="roll">Бросить кубики</button>
        <span class="or">или</span>
        <button class="pay-bail">Оплатить залог</button>
      </div>
      <div class="action pay-bail hidden">
        <button class="pay-bail">Оплатить залог</button>
      </div>
      <div class="action moving hidden">Движение...</div>
    </div>
    <nav class="game-tabs" role="tablist" aria-label="Вкладки игры">
      <button class="btn btn-outline game-tab is-active" data-tab="board">Поле</button>
      <button class="btn btn-outline game-tab" data-tab="ledger">Бухгалтерия</button>
      <button class="btn btn-outline game-tab" data-tab="timeline">Хронология</button>
      <button class="btn btn-outline game-tab" data-tab="stats">Статистика</button>
      <button class="btn btn-outline game-tab" data-tab="trade">Сделки</button>
      <button class="btn btn-outline game-tab" data-tab="rules">Правила</button>
      <button class="btn btn-outline game-tab" data-tab="history">История</button>
    </nav>
  </div>

  <div class="game-panels">
    <section class="game-panel is-active" data-panel="board">
      <div class="mono-board">
        <div class="mono-board__main">
          <div class="board" id="board">
            <div class="corner space nw" id="corner-free-nw" data-pos="20"><div class="container"><div class="label">Бесплатная</div><div class="symbol parking">P</div><div class="label">парковка</div></div></div>
            <div class="row horizontal north">
              <div class="space property" data-group="4" id="prop-kentucky" data-pos="21"><div class="container"><div class="name">Кентукки-авеню</div><div class="cost money">220</div></div></div>
              <div class="space property" data-group="4" id="prop-indiana" data-pos="22"><div class="container"><div class="name">Индиана-авеню</div><div class="cost money">220</div></div></div>
              <div class="space deck chance color-1" data-deck="chance" id="chance-2" data-pos="23"><div class="container"><div class="label">Шанс</div></div></div>
              <div class="space property" data-group="4" id="prop-illinois" data-pos="24"><div class="container"><div class="name">Иллинойс-авеню</div><div class="cost money">240</div></div></div>
              <div class="space railroad" data-railroad="2" id="railroad-bando" data-pos="25"><div class="container"><div class="name">Ж/д B&O</div><div class="cost money">200</div></div></div>
              <div class="space property" data-group="5" id="prop-atlantic" data-pos="26"><div class="container"><div class="name">Атлантик-авеню</div><div class="cost money">260</div></div></div>
              <div class="space property" data-group="5" id="prop-ventnor" data-pos="27"><div class="container"><div class="name">Вентнор-авеню</div><div class="cost money">260</div></div></div>
              <div class="space utility waterworks" data-util="1" id="util-water" data-pos="28"><div class="container"><div class="name">Водоканал</div><div class="cost money">150</div></div></div>
              <div class="space property" data-group="5" id="prop-marvin" data-pos="29"><div class="container"><div class="name">Мэрвин-гарденс</div><div class="cost money">280</div></div></div>
            </div>
            <div class="corner space ne" id="corner-busted-ne" data-pos="30"><div class="container"><div class="label med go-to">Идите в</div><div class="symbol busted">J</div><div class="label med jail">тюрьму</div></div></div>
            <div class="row vertical west">
              <div class="space property" data-group="3" id="prop-newyork" data-pos="19"><div class="container"><div class="name">Нью-Йорк-авеню</div><div class="cost money">200</div></div></div>
              <div class="space property" data-group="3" id="prop-tennessee" data-pos="18"><div class="container"><div class="name">Теннесси-авеню</div><div class="cost money">180</div></div></div>
              <div class="space deck chest" data-deck="chest" id="chest-2" data-pos="17"><div class="container"><div class="label">Казна</div></div></div>
              <div class="space property" data-group="3" id="prop-stjames" data-pos="16"><div class="container"><div class="name">Сент-Джеймс-плейс</div><div class="cost money">180</div></div></div>
              <div class="space railroad" data-railroad="1" id="railroad-pennsylvania" data-pos="15"><div class="container"><div class="name tighter">Пенсильвания Ж/Д</div><div class="cost money">200</div></div></div>
              <div class="space property" data-group="2" id="prop-virginia" data-pos="14"><div class="container"><div class="name">Вирджиния-авеню</div><div class="cost money">160</div></div></div>
              <div class="space property" data-group="2" id="prop-states" data-pos="13"><div class="container"><div class="name">Стейтс-авеню</div><div class="cost money">140</div></div></div>
              <div class="space utility electric" data-util="0" id="util-elec" data-pos="12"><div class="container"><div class="name">Электрокомпания</div><div class="cost money">150</div></div></div>
              <div class="space property" data-group="2" id="prop-stcharles" data-pos="11"><div class="container"><div class="name">Сент-Чарльз-плейс</div><div class="cost money">140</div></div></div>
            </div>
            <div class="center">
              <div class="deck-outline chest"></div>
              <div class="logo">Монополия</div>
              <div class="deck-outline chance"></div>
              <div class="player-info">
                <div class="current"><span class="value" id="board-status-text">Ожидание хода</span></div>
              </div>
              <div class="dice">
                <div class="dice-group">
                  <div class="die left" id="die-1" data-value="0"><div class="pip a"></div><div class="space"></div><div class="pip e"></div><div class="pip b"></div><div class="pip d"></div><div class="pip f"></div><div class="pip c"></div><div class="space"></div><div class="pip g"></div></div>
                  <div class="die right" id="die-2" data-value="0"><div class="pip a"></div><div class="space"></div><div class="pip e"></div><div class="pip b"></div><div class="pip d"></div><div class="pip f"></div><div class="pip c"></div><div class="space"></div><div class="pip g"></div></div>
                </div>
                <div class="dice-status hidden">Выпало:</div>
                <div class="dice-value hidden">0</div>
              </div>
            </div>
            <div class="row vertical east">
              <div class="space property" data-group="6" id="prop-pacific" data-pos="31"><div class="container"><div class="name">Пасифик-авеню</div><div class="cost money">300</div></div></div>
              <div class="space property" data-group="6" id="prop-northcarolina" data-pos="32"><div class="container"><div class="name">Сев. Каролина-авеню</div><div class="cost money">300</div></div></div>
              <div class="space deck chest" data-deck="chest" id="chest-3" data-pos="33"><div class="container"><div class="label">Казна</div></div></div>
              <div class="space property" data-group="6" id="prop-pennsylvania" data-pos="34"><div class="container"><div class="name tighter">Пенсильвания-авеню</div><div class="cost money">320</div></div></div>
              <div class="space railroad" data-railroad="3" id="railroad-shortline" data-pos="35"><div class="container"><div class="name">Короткая линия</div><div class="cost money">200</div></div></div>
              <div class="space deck chance color-2" data-deck="chance" id="chance-3" data-pos="36"><div class="container"><div class="label">Шанс</div></div></div>
              <div class="space property" data-group="7" id="prop-parkplace" data-pos="37"><div class="container"><div class="name">Парк-плейс</div><div class="cost money">350</div></div></div>
              <div class="space tax luxury" data-tax="luxury" id="tax-luxury" data-pos="38"><div class="container"><div class="label">Налог на роскошь</div><div class="cost">Плати <div class="money">200</div></div></div></div>
              <div class="space property" data-group="7" id="prop-boardwalk" data-pos="39"><div class="container"><div class="name">Бродвей</div><div class="cost money">400</div></div></div>
            </div>
            <div class="corner space sw" id="corner-jail-sw" data-pos="10"><div class="subcorner"><div class="container"><div class="label in">В</div><div class="window"><div class="bar"></div><div class="bar"></div><div class="bar"></div><div class="person">!</div></div><div class="label jail">Тюрьме</div></div></div><div class="label just">Просто</div><div class="label visiting">в гостях</div></div>
            <div class="row horizontal south">
              <div class="space property" data-group="1" id="prop-connecticut" data-pos="9"><div class="container"><div class="name">Коннектикут-авеню</div><div class="cost money">120</div></div></div>
              <div class="space property" data-group="1" id="prop-vermont" data-pos="8"><div class="container"><div class="name">Вермонт-авеню</div><div class="cost money">100</div></div></div>
              <div class="space deck chance color-0" data-deck="chance" id="chance-1" data-pos="7"><div class="container"><div class="label">Шанс</div></div></div>
              <div class="space property" data-group="1" id="prop-oriental" data-pos="6"><div class="container"><div class="name">Ориентал-авеню</div><div class="cost money">100</div></div></div>
              <div class="space railroad" data-railroad="0" id="railroad-reading" data-pos="5"><div class="container"><div class="name">Читающая Ж/Д</div><div class="cost money">200</div></div></div>
              <div class="space tax income" data-tax="income" id="tax-income" data-pos="4"><div class="container"><div class="label">Подоходный налог</div><div class="cost">Плати <div class="money">200</div></div></div></div>
              <div class="space property" data-group="0" id="prop-baltic" data-pos="3"><div class="container"><div class="name">Балтик-авеню</div><div class="cost money">60</div></div></div>
              <div class="space deck chest" data-deck="chest" id="chest-1" data-pos="2"><div class="container"><div class="label">Казна</div></div></div>
              <div class="space property" data-group="0" id="prop-mediterranean" data-pos="1"><div class="container"><div class="name">Средиземноморский пр.</div><div class="cost money">60</div></div></div>
            </div>
            <div class="corner space se" id="corner-go-se" data-pos="0"><div class="container"><div class="text">Получите <span class="cost money">200</span> за проход старта</div><div class="go">GO</div></div><div class="symbol arrow">←</div></div>
            <div id="modal-overlay" class="modal-overlay hide hidden"><div class="modal-body type-ok"><button class="close"><span class="sr-only">Close modal</span></button><div class="modal-header"><h5 class="modal-title"></h5></div><div class="modal-content"></div><div class="modal-footer"></div></div></div>
            <div id="card-overlay" class="modal-overlay in-deck hide hidden"><div class="card-body"><div class="card-header"><h5 class="card-title"></h5></div><div class="card-content"></div><div class="card-footer"></div></div></div>
            <div id="space-overlay" class="modal-overlay hide hidden"></div>
          </div>
        </div>
        <aside class="mono-board__players tabletop">
          <?php foreach ($players as $p): ?>
            <div class="player-chip" data-player-id="<?= (int) $p['id'] ?>">
              <strong><?= htmlspecialchars((string) $p['nickname_snapshot'], ENT_QUOTES, 'UTF-8') ?></strong>
              <span>Баланс: <?= (int) $p['cash'] ?></span>
              <span>Позиция: <?= (int) $p['position'] ?></span>
              <div class="player-assets"></div>
            </div>
          <?php endforeach; ?>
          <div class="tabletop-card-closeup hidden" id="tabletop-card-closeup">
            <div class="tabletop-card-closeup__head">
              <strong id="tabletop-card-closeup-title">Карточка</strong>
              <button type="button" class="tabletop-card-closeup__close" id="tabletop-card-closeup-close" aria-label="Закрыть">✕</button>
            </div>
            <div class="tabletop-card-closeup__body" id="tabletop-card-closeup-body"></div>
          </div>
        </aside>
      </div>
      <div class="chat-box">
        <div class="chat-log" id="chat-log">
          <?php foreach ($chat as $m): ?>
            <div class="chat-msg">
              <button type="button" class="chat-msg__author"><?= htmlspecialchars((string) ($m['from_name'] ?? 'Система'), ENT_QUOTES, 'UTF-8') ?></button>
              <span class="chat-msg__text"><?= htmlspecialchars((string) ($m['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
              <span class="chat-msg__time"><?= htmlspecialchars((string) ($m['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <form id="chat-form" class="chat-form">
          <input type="text" class="password-inline" name="message" maxlength="500" required placeholder="Сообщение в чат">
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
    <section class="game-panel" data-panel="rules"><div id="rules-box">
      <div class="timeline-row"><span class="timeline-row__actor">Цель</span><span class="timeline-row__text">Разорить соперников и остаться последним платёжеспособным игроком.</span><span class="timeline-row__time">Правила</span></div>
      <div class="timeline-row"><span class="timeline-row__actor">Ход</span><span class="timeline-row__text">Игрок бросает 2 кубика, двигается, затем выполняет действие клетки.</span><span class="timeline-row__time">Правила</span></div>
      <div class="timeline-row"><span class="timeline-row__actor">Тюрьма</span><span class="timeline-row__text">До 3 попыток выбросить дубль; можно оплатить залог 50 и продолжить игру.</span><span class="timeline-row__time">Правила</span></div>
      <div class="timeline-row"><span class="timeline-row__actor">Строительство</span><span class="timeline-row__text">Дома/отели строятся только при полном наборе цвета. Отель доступен после 4 домов.</span><span class="timeline-row__time">Правила</span></div>
      <div class="timeline-row"><span class="timeline-row__actor">Рента</span><span class="timeline-row__text">На чужой клетке рента оплачивается кнопкой «Оплатить ренту».</span><span class="timeline-row__time">Правила</span></div>
      <div class="timeline-row"><span class="timeline-row__actor">Ваша корректировка</span><span class="timeline-row__text">Вместо ренты можно выкупить клетку: сумма не меньше (цена клетки + стоимость построек) × 2.</span><span class="timeline-row__time">Monostain</span></div>
    </div></section>
    <section class="game-panel" data-panel="history"><div id="history-box" class="timeline-box"></div></section>
  </div>
</section>

<div id="game-notify" class="game-notify" hidden></div>

<div id="game-invite-modal" class="pwd-overlay" role="dialog" aria-modal="true" aria-labelledby="invite-title">
  <a href="#" class="pwd-overlay__backdrop js-invite-close" aria-label="Закрыть"></a>
  <div class="pwd-popup">
    <a href="#" class="pwd-popup__close js-invite-close" aria-label="Закрыть">
      <svg viewBox="0 0 24 24" width="20" height="20" focusable="false" aria-hidden="true">
        <path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="1.35" stroke-linecap="round"/>
      </svg>
    </a>
    <h2 id="invite-title" class="pwd-popup__title">Пригласить по никнейму</h2>
    <form id="invite-nick-form" class="quick-form">
      <input id="invite-nickname" class="password-inline" type="text" maxlength="64" placeholder="Никнейм пользователя" autocomplete="off" required>
      <small id="invite-nickname-hint" class="form-help"></small>
      <button class="btn btn-outline" id="invite-send-btn" type="submit" disabled>Отправить приглашение</button>
    </form>
  </div>
</div>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
