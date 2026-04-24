<?php
declare(strict_types=1);
$isNew = !is_array($boardTemplate ?? null);
$tplId = (int) (($boardTemplate['id'] ?? 0));
$canonical = rtrim(\Stain\Config::get('APP_URL', 'http://localhost:8080'), '/') . ($isNew ? '/panel/game-boards/new' : ('/panel/game-boards/' . $tplId . '/edit'));
$notice = isset($_GET['notice']) ? (string) $_GET['notice'] : '';
$cellsByPos = [];
foreach (($boardCells ?? []) as $cell) {
    $extra = $cell['extra_json'] ?? [];
    if (is_string($extra)) {
        $decoded = json_decode($extra, true);
        $extra = is_array($decoded) ? $decoded : [];
    }
    $x = (int) ($extra['x'] ?? -1);
    $y = (int) ($extra['y'] ?? -1);
    $pos = ($x >= 0 && $y >= 0) ? (($y * 11) + $x) : (int) ($cell['position'] ?? -1);
    if ($pos < 0) continue;
    $cellsByPos[$pos] = $cell;
}
$catalogJson = json_encode($cardCatalog ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$cellsJson = json_encode(array_values($cellsByPos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
ob_start();
?>
<section class="panel-grid">
  <h1><?= $isNew ? 'Новая карта' : 'Редактирование карты' ?></h1>
  <div class="panel-block">
    <?php if ($notice !== ''): ?>
      <p class="form-ok"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="<?= $isNew ? '/panel/game-boards/new' : ('/panel/game-boards/' . $tplId . '/edit') ?>" id="board-editor-form" class="quick-form panel-form">
      <label for="board_name">Название карты</label>
      <input id="board_name" type="text" name="name" maxlength="120" required value="<?= htmlspecialchars((string) ($boardTemplate['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      <label class="check-row board-publish-row">
        <input type="checkbox" name="is_published" value="1" <?= !empty($boardTemplate['is_published']) ? 'checked' : '' ?>>
        <span>Опубликовать</span>
      </label>
      <input type="hidden" name="cells_json" id="cells-json-input" value='<?= htmlspecialchars((string) $cellsJson, ENT_QUOTES, 'UTF-8') ?>'>
      <div class="editor-wizard" id="editor-wizard">
        <div class="editor-wizard__title">Пошаговый режим</div>
        <div class="editor-wizard__steps">
          <span class="wiz-step" data-step="1">1) Выберите клетку</span>
          <span class="wiz-step" data-step="2">2) Выберите карточку</span>
          <span class="wiz-step" data-step="3">3) Выберите ориентацию</span>
          <span class="wiz-step" data-step="4">4) Готово</span>
        </div>
        <p class="editor-wizard__hint" id="editor-wizard-hint">Нажмите на «+» или на уже добавленную карточку.</p>
      </div>
      <div class="board-editor-layout">
        <div class="board-grid-wrap">
          <div class="board-grid-11" id="board-grid-11"></div>
        </div>
        <aside class="board-picker" id="board-picker">
          <h3>Карточки</h3>
          <div class="board-picker__list" id="board-picker-list"></div>
          <div class="cell-orient-box" id="cell-orient-box">
            <h4>Ориентация клетки</h4>
            <div class="cell-orient-buttons">
              <button type="button" class="cell-orient-btn" data-orient="north">↑ Верх (north)</button>
              <button type="button" class="cell-orient-btn" data-orient="south">↓ Низ (south)</button>
              <button type="button" class="cell-orient-btn" data-orient="west">← Лево (west)</button>
              <button type="button" class="cell-orient-btn" data-orient="east">→ Право (east)</button>
            </div>
            <p class="form-help" id="cell-orient-help">Выберите клетку на сетке, затем задайте ориентацию.</p>
          </div>
        </aside>
      </div>
      <button class="btn btn-outline" type="submit">Сохранить карту</button>
    </form>
  </div>
</section>
<style>
.board-editor-layout{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:14px;align-items:start}
.board-publish-row{display:inline-flex !important;align-items:center;gap:8px;white-space:nowrap}
.board-publish-row span{display:inline !important}
.editor-wizard{border:1px solid var(--line-mid);border-radius:10px;padding:10px 12px;background:var(--color_surface)}
.editor-wizard__title{font-weight:800;margin-bottom:6px}
.editor-wizard__steps{display:flex;gap:8px;flex-wrap:wrap}
.wiz-step{padding:4px 8px;border-radius:999px;border:1px solid var(--line-mid);font-size:.9rem}
.wiz-step.is-active{background:#dff3e3;border-color:#2f9e44;font-weight:700}
.editor-wizard__hint{margin:8px 0 0}
.board-grid-wrap{overflow:auto;border:1px solid var(--line-mid);border-radius:12px;background:transparent;padding:8px}
.board-grid-11{display:grid;grid-template-columns:repeat(11,84px);grid-template-rows:repeat(11,84px);gap:7px;justify-content:start}
.board-grid-cell{border:1px dashed #1f2937;border-radius:8px;background:#fff;position:relative;padding:4px;display:flex;align-items:center;justify-content:center}
.board-grid-cell--active{outline:3px solid #2f9e44;outline-offset:-2px}
.board-grid-cell--filled{background:#eef9ee}
.board-grid-plus{border:1px dashed #1f2937;background:#fff;border-radius:999px;width:26px;height:26px;cursor:pointer}
.board-grid-card{width:100%;height:100%;border:1px solid #111;border-radius:6px;background:#fff;display:flex;flex-direction:column;justify-content:space-between;padding:2px;position:relative}
.board-grid-card__bar{height:8px;border:1px solid #111}
.board-grid-card__name{font-size:.54rem;line-height:1.05;font-weight:700;text-transform:uppercase;word-break:break-word}
.board-grid-card__remove{position:absolute;inset:0;background:rgba(0,0,0,.45);color:#fff;border:none;opacity:0;cursor:pointer;font-weight:700}
.board-grid-card:hover .board-grid-card__remove{opacity:1}
.board-picker{border:1px solid var(--line-mid);border-radius:12px;background:#fff;padding:10px}
.board-picker h3{margin:0 0 8px}
.board-picker__list{display:flex;flex-wrap:wrap;gap:6px;max-height:560px;overflow:auto}
.board-picker__list.is-disabled{opacity:.35;pointer-events:none}
.cell-orient-box{margin-top:12px;border-top:1px solid var(--line-mid);padding-top:10px}
.cell-orient-box.is-disabled{opacity:.45;pointer-events:none}
.cell-orient-box h4{margin:0 0 8px;font-size:1rem}
.cell-orient-buttons{display:grid;gap:6px}
.cell-orient-btn{appearance:none;border:1px solid #090a0e;background:#fff;border-radius:8px;padding:10px 8px;font-size:1rem;font-weight:700;cursor:pointer;text-align:left}
.cell-orient-btn.is-active{background:#dff3e3}
.picker-card{width:64px;height:84px;border:1px solid #111;background:#fff;border-radius:6px;padding:3px;display:flex;flex-direction:column;justify-content:space-between;cursor:pointer}
.picker-card__bar{height:10px;border:1px solid #111}
.picker-card__name{font-size:.52rem;line-height:1.05;font-weight:700;text-transform:uppercase;word-break:break-word}
.picker-card--disabled{opacity:.4;pointer-events:none}
@media (max-width: 980px){.board-editor-layout{grid-template-columns:1fr}}
</style>
<script>
(() => {
  const catalog = <?= $catalogJson ?: '[]' ?>;
  const input = document.querySelector("#cells-json-input");
  const grid = document.querySelector("#board-grid-11");
  const picker = document.querySelector("#board-picker-list");
  const orientBox = document.querySelector("#cell-orient-box");
  const wizard = document.querySelector("#editor-wizard");
  const wizardHint = document.querySelector("#editor-wizard-hint");
  if (!input || !grid || !picker || !orientBox || !wizard || !wizardHint) return;
  let cells = [];
  try { cells = JSON.parse(input.value || "[]"); } catch (_) { cells = []; }
  const byPos = new Map();
  const reusablePositions = [];
  cells.forEach((c) => {
    let extra = c.extra_json || {};
    if (typeof extra === "string") {
      try { extra = JSON.parse(extra); } catch (_) { extra = {}; }
    }
    const x = Number((extra && extra.x) ?? -1);
    const y = Number((extra && extra.y) ?? -1);
    const slot = (x >= 0 && y >= 0) ? (y * 11 + x) : Number(c.position || -1);
    byPos.set(slot, {
      position: Number(c.position || 0),
      cell_type: String(c.cell_type || "property"),
      title: String(c.title || ""),
      buy_price: Number(c.buy_price || 0),
      rent_rules: {},
      extra_json: { x, y, group: extra?.group ?? null, orientation: String(extra?.orientation || "") }
    });
  });
  let activePos = -1;

  const colorByGroup = {0:"#955436",1:"#aae0fa",2:"#d93a96",3:"#f7941d",4:"#ed1b24",5:"#fef200",6:"#1fb25a",7:"#0072bb"};
  const unlimitedTypes = new Set(["chance","chest","free","tax"]);
  const nextPosition = () => {
    if (reusablePositions.length > 0) return Number(reusablePositions.shift() || 0);
    let maxPos = -1;
    byPos.forEach((cell) => { maxPos = Math.max(maxPos, Number(cell.position || -1)); });
    return maxPos + 1;
  };

  const inferOrientation = (slot) => {
    const x = slot % 11;
    const y = Math.floor(slot / 11);
    const hasN = byPos.has(slot - 11);
    const hasS = byPos.has(slot + 11);
    const hasW = (x > 0) && byPos.has(slot - 1);
    const hasE = (x < 10) && byPos.has(slot + 1);
    if (hasS && !hasN) return "south";
    if (hasN && !hasS) return "north";
    if (hasW && !hasE) return "west";
    if (hasE && !hasW) return "east";
    if (hasW && hasE) return x <= 5 ? "east" : "west";
    if (hasN && hasS) return y <= 5 ? "north" : "south";
    if (y === 0) return "north";
    if (y === 10) return "south";
    if (x === 0) return "west";
    if (x === 10) return "east";
    return y <= 5 ? "north" : "south";
  };

  const countUsage = () => {
    const used = new Map();
    byPos.forEach((cell) => {
      const key = String(cell.title || "") + "|" + String(cell.cell_type || "");
      used.set(key, (used.get(key) || 0) + 1);
    });
    return used;
  };

  const renderPicker = () => {
    picker.innerHTML = "";
    const used = countUsage();
    catalog.forEach((card) => {
      const key = String(card.title || "") + "|" + String(card.cell_type || "");
      const usage = used.get(key) || 0;
      const limitReached = !card.unlimited && usage > 0;
      const el = document.createElement("button");
      el.type = "button";
      el.className = "picker-card" + (limitReached ? " picker-card--disabled" : "");
      const barColor = card.group === null || card.group === undefined ? "#fff" : (colorByGroup[Number(card.group)] || "#fff");
      el.innerHTML = `<span class="picker-card__bar" style="background:${barColor}"></span><span class="picker-card__name">${String(card.title || "")}</span>`;
      if (!limitReached) {
        el.addEventListener("click", () => {
          if (activePos < 0) return;
          const x = activePos % 11;
          const y = Math.floor(activePos / 11);
          byPos.set(activePos, {
            position: nextPosition(),
            cell_type: String(card.cell_type || "property"),
            title: String(card.title || ""),
            buy_price: Number(card.buy_price || 0),
            rent_rules: {},
            extra_json: { x, y, group: card.group ?? null, orientation: inferOrientation(activePos) }
          });
          sync();
        });
      }
      picker.appendChild(el);
    });
  };

  const updateOrientationPanel = () => {
    const help = document.querySelector("#cell-orient-help");
    const selected = byPos.get(activePos);
    const current = selected?.extra_json?.orientation || "";
    orientBox.querySelectorAll(".cell-orient-btn").forEach((btn) => {
      btn.classList.toggle("is-active", String(btn.dataset.orient || "") === String(current || ""));
    });
    if (help) {
      help.textContent = selected
        ? `Клетка выбрана. Текущая ориентация: ${current || "не задана"}`
        : "Выберите клетку на сетке, затем задайте ориентацию.";
    }
  };

  const updateWizard = () => {
    const selected = byPos.get(activePos);
    const hasCell = Boolean(selected);
    const hasOrientation = Boolean(selected?.extra_json?.orientation);
    let step = 1;
    let hint = "Нажмите на «+» или на уже добавленную карточку.";
    if (activePos >= 0 && !hasCell) {
      step = 2;
      hint = "Теперь выберите карточку справа.";
    } else if (hasCell && !hasOrientation) {
      step = 3;
      hint = "Задайте ориентацию кнопками: верх / низ / лево / право.";
    } else if (hasCell && hasOrientation) {
      step = 4;
      hint = "Готово. Можно сохранить карту или выбрать следующую клетку.";
    }
    wizard.querySelectorAll(".wiz-step").forEach((el) => {
      el.classList.toggle("is-active", Number(el.dataset.step || 0) === step);
    });
    wizardHint.textContent = hint;
    picker.classList.toggle("is-disabled", activePos < 0);
    orientBox.classList.toggle("is-disabled", !hasCell);
  };

  const renderGrid = () => {
    grid.innerHTML = "";
    for (let pos = 0; pos < 121; pos += 1) {
      const x = pos % 11;
      const y = Math.floor(pos / 11);
      const cell = document.createElement("div");
      const item = byPos.get(pos);
      cell.className = "board-grid-cell" + (item ? " board-grid-cell--filled" : "") + (activePos === pos ? " board-grid-cell--active" : "");
      if (!item) {
        const plus = document.createElement("button");
        plus.type = "button";
        plus.className = "board-grid-plus";
        plus.textContent = "+";
        plus.addEventListener("click", () => { activePos = pos; updateOrientationPanel(); updateWizard(); renderGrid(); });
        cell.addEventListener("click", () => { activePos = pos; updateOrientationPanel(); updateWizard(); renderGrid(); });
        cell.appendChild(plus);
      } else {
        const group = item?.extra_json?.group;
        const barColor = group === null || group === undefined ? "#fff" : (colorByGroup[Number(group)] || "#fff");
        const card = document.createElement("div");
        card.className = "board-grid-card";
        card.innerHTML = `<span class="board-grid-card__bar" style="background:${barColor}"></span><span class="board-grid-card__name">${String(item.title || "")}</span><button type="button" class="board-grid-card__remove">Удалить</button>`;
        const rm = card.querySelector(".board-grid-card__remove");
        if (rm) rm.addEventListener("click", () => {
          const removed = byPos.get(pos);
          if (removed) reusablePositions.push(Number(removed.position || 0));
          byPos.delete(pos);
          if (activePos === pos) activePos = -1;
          sync();
        });
        card.addEventListener("click", (evt) => {
          if (evt.target.closest(".board-grid-card__remove")) return;
          activePos = pos;
          updateOrientationPanel();
          updateWizard();
          renderGrid();
        });
        cell.appendChild(card);
      }
      cell.dataset.x = String(x);
      cell.dataset.y = String(y);
      grid.appendChild(cell);
    }
  };

  const sync = () => {
    const out = [];
    byPos.forEach((v, k) => {
      const x = k % 11;
      const y = Math.floor(k / 11);
      out.push({
        position: Number(v.position || 0),
        cell_type: String(v.cell_type || "property"),
        title: String(v.title || ""),
        buy_price: Number(v.buy_price || 0),
        rent_rules: {},
        extra_json: { x, y, group: v?.extra_json?.group ?? null, orientation: v?.extra_json?.orientation ?? "" }
      });
    });
    input.value = JSON.stringify(out);
    renderGrid();
    renderPicker();
    updateOrientationPanel();
    updateWizard();
  };
  orientBox.querySelectorAll(".cell-orient-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      if (activePos < 0 || !byPos.has(activePos)) return;
      const item = byPos.get(activePos) || {};
      item.extra_json = item.extra_json || {};
      item.extra_json.orientation = String(btn.dataset.orient || "");
      byPos.set(activePos, item);
      sync();
    });
  });
  sync();
})();
</script>
<?php
$content = (string) ob_get_clean();
require __DIR__ . '/layout.php';
