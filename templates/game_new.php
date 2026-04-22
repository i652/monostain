<?php
declare(strict_types=1);
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . '/game/new';
ob_start();
?>
<section class="panel-grid">
  <h1>Новая игра</h1>
  <div class="panel-block">
    <form id="new-game-form" class="quick-form panel-form" autocomplete="off">
      <label for="game_max_players">Количество участников (1-8)</label>
      <select id="game_max_players" name="max_players">
        <?php for ($i = 1; $i <= 8; $i++): ?>
          <option value="<?= $i ?>" <?= $i === 2 ? 'selected' : '' ?>><?= $i ?></option>
        <?php endfor; ?>
      </select>
      <label class="check-row">
        <input type="checkbox" name="allow_bots" checked>
        <span>Разрешить ботов</span>
      </label>
      <button class="btn btn-outline" type="submit">Создать игру</button>
      <p id="new-game-status" class="form-help"></p>
    </form>
  </div>
</section>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
