(function () {
  const room = document.querySelector(".game-room");
  const newGameForm = document.querySelector("#new-game-form");
  const inviteButtons = document.querySelectorAll(".js-accept-invite");

  async function postJson(url, body) {
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify(body || {}),
    });
    const payload = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new Error(payload.error || payload.message || "Не удалось выполнить запрос");
    }
    return payload;
  }
  async function showPopup(message) {
    showNotify(String(message || "Произошла ошибка"));
  }
  function toRuError(message) {
    const text = String(message || "");
    if (text.includes("The request is not allowed by the user agent")) {
      return "Браузер запретил доступ к буферу обмена";
    }
    if (text.includes("Failed to fetch")) {
      return "Не удалось выполнить запрос к серверу";
    }
    return text || "Произошла ошибка";
  }

  function eventTypeRu(type) {
    const map = {
      game_created: "Игра создана",
      bot_added: "Добавлен бот",
      player_joined: "Игрок присоединился",
      invite_created: "Создано приглашение",
      chat_sent: "Отправлено сообщение",
      command_roll: "Бросок кубиков",
      command_end_turn: "Завершение хода",
      command_pay: "Перевод средств",
      command_pay_bail: "Оплачен залог",
      command_buy: "Покупка собственности",
      command_sell: "Продажа собственности",
      command_pay_rent: "Оплата ренты",
      command_buyout: "Выкуп собственности",
      command_build: "Строительство",
      command_sell_building: "Продажа постройки",
    };
    return map[type] || "Событие";
  }

  function formatTimestampRu(input) {
    const d = new Date(input);
    if (Number.isNaN(d.getTime())) return "";
    const now = new Date();
    const sameDay =
      d.getFullYear() === now.getFullYear() &&
      d.getMonth() === now.getMonth() &&
      d.getDate() === now.getDate();
    const time = d.toLocaleTimeString("ru-RU", { hour: "2-digit", minute: "2-digit", second: "2-digit" });
    if (sameDay) return time;
    const sameYear = d.getFullYear() === now.getFullYear();
    const month = d.toLocaleString("ru-RU", { month: "long" });
    if (sameYear) return `${d.getDate()} ${month} в ${time}`;
    return `${d.getDate()} ${month} ${d.getFullYear()} года в ${time}`;
  }

  if (newGameForm) {
    newGameForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      const status = document.querySelector("#new-game-status");
      const formData = new FormData(newGameForm);
      try {
        status.textContent = "Создаём игру...";
        const result = await postJson("/api/v1/game", {
          max_players: Number(formData.get("max_players") || 8),
          allow_bots: formData.get("allow_bots") === "on" ? true : false,
        });
        window.location.href = "/game/" + result.game.id;
      } catch (err) {
        status.textContent = err.message;
      }
    });
  }

  inviteButtons.forEach((btn) => {
    btn.addEventListener("click", async () => {
      const token = btn.getAttribute("data-token");
      if (!token) return;
      try {
        const data = await postJson("/api/v1/game/join", { token: token });
        window.location.href = "/game/" + data.game_id;
      } catch (err) {
        showPopup(err.message || "Не удалось принять приглашение");
      }
    });
  });

  if (!room) return;
  const gameId = room.getAttribute("data-game-id");
  const selfPlayerId = Number(room.getAttribute("data-player-id") || 0);
  const playersRaw = room.getAttribute("data-players") || "[]";
  const propertyRaw = room.getAttribute("data-property-state") || "[]";
  const players = (() => {
    try {
      return JSON.parse(playersRaw);
    } catch (_) {
      return [];
    }
  })();
  const propertyState = (() => {
    try {
      return JSON.parse(propertyRaw);
    } catch (_) {
      return [];
    }
  })();
  let controlIndex = 0;
  const playerIdsInOrder = players.map((p) => Number(p.id || 0)).filter((v) => v > 0);
  const playerNameById = new Map(players.map((p) => [Number(p.id || 0), String(p.nickname_snapshot || "Игрок")]));
  const boardMeta = {
    0: { name: "Старт", price: 0, rent: 0, group: null, type: "go", details: "Получите 200 при проходе." },
    1: { name: "Средиземноморский пр.", price: 60, rent: 2, group: 0 },
    2: { name: "Казна", price: 0, rent: 0, group: null, type: "chest", details: "Вытяните карту «Общественная казна» и выполните ее указание: перемещение, выплата, получение денег, тюрьма или ремонт собственности." },
    3: { name: "Балтик-авеню", price: 60, rent: 4, group: 0 },
    4: { name: "Подоходный налог", price: 0, rent: 200, group: null, type: "tax", details: "Оплатите налог 200." },
    5: { name: "Читающая Ж/Д", price: 200, rent: 25, group: null },
    6: { name: "Ориентал-авеню", price: 100, rent: 6, group: 1 },
    7: { name: "Шанс", price: 0, rent: 0, group: null, type: "chance", details: "Вытяните карту «Шанс»: она может отправить на другую клетку, выдать/списать деньги или отправить в тюрьму." },
    8: { name: "Вермонт-авеню", price: 100, rent: 6, group: 1 },
    9: { name: "Коннектикут-авеню", price: 120, rent: 8, group: 1 },
    10: { name: "Тюрьма (в гостях)", price: 0, rent: 0, group: null, type: "jail", details: "Просто в гостях." },
    11: { name: "Сент-Чарльз-плейс", price: 140, rent: 10, group: 2 },
    12: { name: "Электрокомпания", price: 150, rent: 10, group: null },
    13: { name: "Стейтс-авеню", price: 140, rent: 10, group: 2 },
    14: { name: "Вирджиния-авеню", price: 160, rent: 12, group: 2 },
    15: { name: "Пенсильвания Ж/Д", price: 200, rent: 25, group: null },
    16: { name: "Сент-Джеймс-плейс", price: 180, rent: 14, group: 3 },
    17: { name: "Казна", price: 0, rent: 0, group: null, type: "chest", details: "Вытяните карту «Общественная казна» и выполните ее указание: перемещение, выплата, получение денег, тюрьма или ремонт собственности." },
    18: { name: "Теннесси-авеню", price: 180, rent: 14, group: 3 },
    19: { name: "Нью-Йорк-авеню", price: 200, rent: 16, group: 3 },
    20: { name: "Бесплатная парковка", price: 0, rent: 0, group: null, type: "free", details: "Нейтральная клетка." },
    21: { name: "Кентукки-авеню", price: 220, rent: 18, group: 4 },
    22: { name: "Индиана-авеню", price: 220, rent: 18, group: 4 },
    23: { name: "Шанс", price: 0, rent: 0, group: null, type: "chance", details: "Вытяните карту «Шанс»: она может отправить на другую клетку, выдать/списать деньги или отправить в тюрьму." },
    24: { name: "Иллинойс-авеню", price: 240, rent: 20, group: 4 },
    25: { name: "Ж/д B&O", price: 200, rent: 25, group: null },
    26: { name: "Атлантик-авеню", price: 260, rent: 22, group: 5 },
    27: { name: "Вентнор-авеню", price: 260, rent: 22, group: 5 },
    28: { name: "Водоканал", price: 150, rent: 10, group: null },
    29: { name: "Мэрвин-гарденс", price: 280, rent: 24, group: 5 },
    30: { name: "Идите в тюрьму", price: 0, rent: 0, group: null, type: "go_to_jail", details: "Немедленно отправляйтесь в тюрьму." },
    31: { name: "Пасифик-авеню", price: 300, rent: 26, group: 6 },
    32: { name: "Сев. Каролина-авеню", price: 300, rent: 26, group: 6 },
    33: { name: "Казна", price: 0, rent: 0, group: null, type: "chest", details: "Вытяните карту «Общественная казна» и выполните ее указание: перемещение, выплата, получение денег, тюрьма или ремонт собственности." },
    34: { name: "Пенсильвания-авеню", price: 320, rent: 28, group: 6 },
    35: { name: "Короткая линия", price: 200, rent: 25, group: null },
    36: { name: "Шанс", price: 0, rent: 0, group: null, type: "chance", details: "Вытяните карту «Шанс»: она может отправить на другую клетку, выдать/списать деньги или отправить в тюрьму." },
    37: { name: "Парк-плейс", price: 350, rent: 35, group: 7 },
    38: { name: "Налог на роскошь", price: 0, rent: 200, group: null, type: "tax", details: "Оплатите налог 200." },
    39: { name: "Бродвей", price: 400, rent: 50, group: 7 },
  };
  const groupBuildCost = { 0: 50, 1: 50, 2: 100, 3: 100, 4: 150, 5: 150, 6: 200, 7: 200 };
  const tokenColors = ["#e11d48", "#2563eb", "#f59e0b", "#16a34a", "#7c3aed", "#0ea5e9", "#ef4444", "#84cc16"];
  const playerColorById = new Map();
  const processedEventSeq = new Set();
  const financeEventTypes = new Set(["command_pay", "command_buy", "command_sell", "command_pay_bail", "command_pay_rent", "command_buyout", "command_build", "command_sell_building", "property_bought", "property_sold", "trade_completed", "tax_paid", "rent_paid"]);
  const groupColorClass = { 0: "group-0", 1: "group-1", 2: "group-2", 3: "group-3", 4: "group-4", 5: "group-5", 6: "group-6", 7: "group-7" };
  const allEvents = [];
  let sinceSeq = 0;
  let notifyTimer = null;

  const tabs = Array.from(document.querySelectorAll(".game-tab"));
  const panels = Array.from(document.querySelectorAll(".game-panel"));
  const rollAction = document.querySelector(".main-action .action.roll");
  const endAction = document.querySelector(".main-action .action.end");
  const purchaseAction = document.querySelector(".main-action .action.purchase");
  const rentAction = document.querySelector(".main-action .action.rent");
  const buildAction = document.querySelector(".main-action .action.build");
  const buildHouseBtn = document.querySelector(".build-house");
  const buildHotelBtn = document.querySelector(".build-hotel");
  const purchaseName = document.querySelector(".main-action .action.purchase .name");
  const purchaseCost = document.querySelector(".main-action .action.purchase .cost-amount");
  const rentAmount = document.querySelector(".main-action .action.rent .rent-amount");
  let currentRentDue = 0;
  let currentBuyoutMin = 0;
  let pendingBuildType = "";
  let buildablePositions = new Set();
  let autoEndTurnInFlight = false;
  let suppressTransientModals = true;
  renderTokens();
  renderRightPanel();
  tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      const target = tab.getAttribute("data-tab");
      tabs.forEach((x) => x.classList.remove("is-active"));
      panels.forEach((x) => x.classList.remove("is-active"));
      tab.classList.add("is-active");
      const panel = document.querySelector('.game-panel[data-panel="' + target + '"]');
      if (panel) panel.classList.add("is-active");
    });
  });

  function appendTimeline(eventObj) {
    const box = document.querySelector("#timeline-box");
    if (!box) return;
    if (processedEventSeq.has(Number(eventObj.event_seq || 0))) return;
    processedEventSeq.add(Number(eventObj.event_seq || 0));
    allEvents.push(eventObj);
    renderTimeline();
  }

  function renderTimeline() {
    const box = document.querySelector("#timeline-box");
    if (!box) return;
    box.innerHTML = "";
    allEvents
      .slice()
      .sort((a, b) => Number(b.event_seq || 0) - Number(a.event_seq || 0))
      .forEach((eventObj) => {
        if (eventObj.event_type === "chat_sent") return;
        const line = document.createElement("div");
        line.className = "timeline-row";
        const ts = formatTimestampRu(eventObj.created_at || "");
        const actor = actorName(eventObj);
        line.innerHTML = `<span class="timeline-row__actor">${escapeHtml(actor)}</span><span class="timeline-row__text">${escapeHtml(describeEvent(eventObj))}</span><span class="timeline-row__time">${escapeHtml(ts)}</span>`;
        box.appendChild(line);
      });
  }

  function actorName(eventObj) {
    const payload = parsePayload(eventObj);
    const byPayload = String(payload.author_nickname || "").trim();
    if (byPayload !== "") return byPayload;
    const byId = playerNameById.get(Number(eventObj.actor_player_id || 0));
    return byId || "Система";
  }

  function parsePayload(eventObj) {
    try {
      return typeof eventObj.payload_json === "string" ? JSON.parse(eventObj.payload_json || "{}") : (eventObj.payload_json || {});
    } catch (_) {
      return {};
    }
  }

  function updatePlayerChipRow(playerId) {
    const id = Number(playerId || 0);
    if (id <= 0) return;
    const chip = document.querySelector('.player-chip[data-player-id="' + id + '"]');
    if (!chip) return;
    const p = players.find((x) => Number(x.id || 0) === id);
    const spans = chip.querySelectorAll("span");
    if (spans[0]) spans[0].textContent = "Баланс: " + Number((p && p.cash) || 0);
    if (spans[1]) spans[1].textContent = "Позиция: " + Number((p && p.position) || 0);
  }

  function setPlayerCash(playerId, cash) {
    const p = players.find((x) => Number(x.id || 0) === Number(playerId));
    if (p) p.cash = Number(cash);
    updatePlayerChipRow(playerId);
  }

  function setPlayerPosition(playerId, pos) {
    const p = players.find((x) => Number(x.id || 0) === Number(playerId));
    if (p) p.position = Number(pos);
    updatePlayerChipRow(playerId);
  }

  /** Обновляет локальный cash/position по событию из БД (poll или ответ POST). */
  function applyEconomyFromEvent(eventObj) {
    const type = String(eventObj.event_type || "");
    const payload = parsePayload(eventObj);
    const actorId = Number(eventObj.actor_player_id || 0);
    if (type === "command_roll" && Number.isFinite(Number(payload.cash))) {
      setPlayerCash(actorId, payload.cash);
      if (Number.isFinite(Number(payload.position))) setPlayerPosition(actorId, payload.position);
      return;
    }
    if (type === "command_pay_rent") {
      if (Number.isFinite(Number(payload.cash))) setPlayerCash(actorId, payload.cash);
      const toId = Number(payload.to_player_id || 0);
      const rent = Number(payload.rent_paid || 0);
      if (toId > 0) {
        if (Number.isFinite(Number(payload.owner_cash))) {
          setPlayerCash(toId, payload.owner_cash);
        } else if (rent > 0) {
          const p = players.find((x) => Number(x.id || 0) === toId);
          setPlayerCash(toId, Number((p && p.cash) || 0) + rent);
        }
      }
      return;
    }
    if (type === "command_pay") {
      const fromId = Number(payload.from_player_id || actorId);
      if (Number.isFinite(Number(payload.from_cash))) setPlayerCash(fromId, payload.from_cash);
      const toId = Number(payload.to_player_id || 0);
      if (toId > 0 && Number.isFinite(Number(payload.to_cash))) setPlayerCash(toId, payload.to_cash);
      return;
    }
    if (type === "command_buy" || type === "command_pay_bail" || type === "command_build" || type === "command_sell_building") {
      if (Number.isFinite(Number(payload.cash))) setPlayerCash(actorId, payload.cash);
      return;
    }
    if (type === "command_sell" && Number.isFinite(Number(payload.cash))) {
      setPlayerCash(actorId, payload.cash);
      return;
    }
    if (type === "command_buyout") {
      if (Number.isFinite(Number(payload.cash))) setPlayerCash(actorId, payload.cash);
      const sellerId = Number(payload.seller_player_id || 0);
      if (payload.buyout_approved && sellerId > 0 && Number.isFinite(Number(payload.seller_cash))) {
        setPlayerCash(sellerId, payload.seller_cash);
      }
      return;
    }
  }

  function applyEconomyFromEvents(events) {
    (events || []).forEach((ev) => applyEconomyFromEvent(ev));
    refreshCurrentPlayerInfo();
  }

  function ingestServerEvent(result) {
    if (!result || typeof result !== "object") return;
    appendTimeline(result);
    renderFinanceLedger();
    applyEconomyFromEvent(result);
    applyPropertyStateFromEvent(result);
    refreshCurrentPlayerInfo();
  }

  function isFinanceEvent(eventObj) {
    if (financeEventTypes.has(String(eventObj.event_type || ""))) return true;
    const payload = parsePayload(eventObj);
    return Number(payload.amount || 0) > 0;
  }

  function renderFinanceLedger() {
    const box = document.querySelector("#ledger-box");
    if (!box) return;
    box.innerHTML = "";
    const cashByPlayer = new Map(players.map((p) => [Number(p.id || 0), Number(p.cash || 0)]));
    const rows = [];
    allEvents
      .slice()
      .sort((a, b) => Number(a.event_seq || 0) - Number(b.event_seq || 0))
      .forEach((eventObj) => {
        const payload = parsePayload(eventObj);
        const eventType = String(eventObj.event_type || "");
        const actorId = Number(eventObj.actor_player_id || 0);
        const selfBefore = Number(cashByPlayer.get(selfPlayerId) || 0);
        if (eventType === "command_roll" && Number.isFinite(Number(payload.cash))) {
          cashByPlayer.set(actorId, Number(payload.cash));
        } else if (eventType === "command_pay") {
          const amount = Number(payload.amount || 0);
          const toPlayerId = Number(payload.to_player_id || 0);
          if (amount > 0) {
            cashByPlayer.set(actorId, Number(cashByPlayer.get(actorId) || 0) - amount);
            cashByPlayer.set(toPlayerId, Number(cashByPlayer.get(toPlayerId) || 0) + amount);
          }
        } else if (eventType === "command_pay_rent") {
          const rentPaid = Number(payload.rent_paid || 0);
          const toPlayerId = Number(payload.to_player_id || 0);
          if (Number.isFinite(Number(payload.cash))) {
            cashByPlayer.set(actorId, Number(payload.cash));
          } else if (rentPaid > 0) {
            cashByPlayer.set(actorId, Number(cashByPlayer.get(actorId) || 0) - rentPaid);
          }
          if (rentPaid > 0 && toPlayerId > 0) {
            cashByPlayer.set(toPlayerId, Number(cashByPlayer.get(toPlayerId) || 0) + rentPaid);
          }
        } else if (["command_buy", "command_sell", "command_pay_bail", "command_build", "command_sell_building", "command_buyout"].includes(eventType) && Number.isFinite(Number(payload.cash))) {
          cashByPlayer.set(actorId, Number(payload.cash));
          if (eventType === "command_buyout" && payload.buyout_approved) {
            const sellerId = Number(payload.seller_player_id || 0);
            const offerAmount = Number(payload.offer_amount || 0);
            if (sellerId > 0 && offerAmount > 0) {
              cashByPlayer.set(sellerId, Number(cashByPlayer.get(sellerId) || 0) + offerAmount);
            }
          }
        }
        const selfAfter = Number(cashByPlayer.get(selfPlayerId) || 0);
        const relevant = isFinanceEvent(eventObj) && (
          actorId === selfPlayerId ||
          Number(payload.to_player_id || 0) === selfPlayerId ||
          Number(payload.seller_player_id || 0) === selfPlayerId
        );
        if (!relevant) return;
        rows.push({ eventObj, selfBefore, selfAfter });
      });
    rows
      .sort((a, b) => Number(b.eventObj.event_seq || 0) - Number(a.eventObj.event_seq || 0))
      .forEach(({ eventObj, selfBefore, selfAfter }) => {
        const row = document.createElement("div");
        row.className = "ledger-row";
        row.innerHTML = `<span class="ledger-row__title">${escapeHtml(actorName(eventObj))}</span><span class="ledger-row__desc">${escapeHtml(describeEvent(eventObj))}</span><span class="ledger-row__balance">Баланс: ${selfBefore} -> ${selfAfter}</span><span class="ledger-row__meta">${escapeHtml(formatTimestampRu(eventObj.created_at || ""))}</span>`;
        box.appendChild(row);
      });
  }

  function showNotify(message) {
    const box = document.querySelector("#game-notify");
    if (!box) return;
    box.hidden = false;
    box.textContent = message;
    if (notifyTimer) window.clearTimeout(notifyTimer);
    notifyTimer = window.setTimeout(() => {
      box.hidden = true;
    }, 2400);
  }

  function appendChatMessage(author, message, createdAt, pending) {
    const box = document.querySelector("#chat-log");
    if (!box) return;
    const line = document.createElement("div");
    line.className = "chat-msg";
    if (pending) line.classList.add("is-pending");
    const ts = formatTimestampRu(createdAt || new Date().toISOString());
    line.innerHTML = `<button type="button" class="chat-msg__author">${author}</button><span class="chat-msg__text">${escapeHtml(message)}</span><span class="chat-msg__time">${ts}</span>`;
    line.dataset.pending = pending ? "1" : "0";
    box.prepend(line);
    box.scrollTop = 0;
  }
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (ch) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch]));
  }

  function describeEvent(eventObj) {
    const payload = parsePayload(eventObj);
    if (eventObj.event_type === "command_roll") {
      const d = payload.dice || [0, 0];
      return `Бросок ${d[0]} + ${d[1]}, позиция ${payload.position}, баланс ${payload.cash}`;
    }
    if (eventObj.event_type === "command_pay") {
      if (Number(payload.to_player_id || 0) === selfPlayerId) {
        return `Поступление ${payload.amount} от ${actorName(eventObj)}`;
      }
      return `${actorName(eventObj)} перевёл ${payload.amount} игроку #${payload.to_player_id}`;
    }
    if (eventObj.event_type === "command_buy") {
      return `Куплена клетка #${payload.position} за ${payload.price}`;
    }
    if (eventObj.event_type === "command_sell") {
      return `Продана клетка #${payload.position} за ${payload.price}`;
    }
    if (eventObj.event_type === "command_pay_bail") {
      return `Оплачен залог ${payload.bail_paid || 50}`;
    }
    if (eventObj.event_type === "command_pay_rent") {
      if (Number(payload.to_player_id || 0) === selfPlayerId) {
        return `Поступление ренты ${payload.rent_paid || 0} от ${actorName(eventObj)}`;
      }
      return `Оплачена рента ${payload.rent_paid || 0} игроку #${payload.to_player_id || 0}`;
    }
    if (eventObj.event_type === "command_buyout") {
      return `Выкуплена клетка #${payload.position} за ${payload.offer_amount}`;
    }
    if (eventObj.event_type === "command_build") {
      return `Постройка: ${payload.build_type === "hotel" ? "отель" : "дом"} на клетке #${payload.position}`;
    }
    if (eventObj.event_type === "command_sell_building") {
      return `Продана постройка (${payload.sell_type === "hotel" ? "отель" : "дом"}) на клетке #${payload.position}`;
    }
    if (eventObj.event_type === "chat_sent") {
      return `${actorName(eventObj)}: ${payload.message || "Сообщение в чат"}`;
    }
    if (eventObj.event_type === "invite_created") {
      return "Создано приглашение";
    }
    if (eventObj.event_type === "bot_added") {
      return `Добавлен ${payload.bot_name || "бот"}`;
    }
    return eventTypeRu(eventObj.event_type);
  }

  function refreshFromEvents(events) {
    events.forEach((eventObj) => applyPropertyStateFromEvent(eventObj));
    events.forEach(appendTimeline);
    renderFinanceLedger();
    applyEconomyFromEvents(events);
    events.forEach((eventObj) => {
      if (eventObj.event_type !== "chat_sent") return;
      const payload = parsePayload(eventObj);
      appendChatMessage(actorName(eventObj), payload.message || "Сообщение", eventObj.created_at, false);
    });
    renderStats();
    renderTrade();
    renderHistory();
  }

  function refreshPlayers(events) {
    events.forEach((e) => {
      if (e.event_type !== "command_roll") return;
      const payload = parsePayload(e);
      const actorId = Number(e.actor_player_id || 0);
      clearBuildSelection();
      const chip = document.querySelector('.player-chip[data-player-id="' + actorId + '"]');
      if (!chip || !payload) return;
      if (Number.isFinite(Number(payload.cash))) setPlayerCash(actorId, payload.cash);
      if (Number.isFinite(Number(payload.position))) setPlayerPosition(actorId, payload.position);
      const dice = Array.isArray(payload.dice) ? payload.dice : [0, 0];
      const die1 = document.querySelector("#die-1");
      const die2 = document.querySelector("#die-2");
      const diceValue = document.querySelector(".dice-value");
      const diceStatus = document.querySelector(".dice-status");
      if (die1) die1.setAttribute("data-value", String(Number(dice[0] || 0)));
      if (die2) die2.setAttribute("data-value", String(Number(dice[1] || 0)));
      if (diceValue) {
        diceValue.textContent = String(Number(dice[0] || 0) + Number(dice[1] || 0));
        diceValue.classList.remove("hidden");
      }
      if (diceStatus) diceStatus.classList.remove("hidden");
      if (payload.offer_purchase) {
        currentRentDue = 0;
        currentBuyoutMin = 0;
        if (rentAction) rentAction.classList.add("hidden");
        if (buildAction) buildAction.classList.add("hidden");
        if (rollAction) rollAction.classList.add("hidden");
        if (endAction) endAction.classList.add("hidden");
        if (purchaseAction) purchaseAction.classList.remove("hidden");
        if (purchaseName) purchaseName.textContent = String(payload.space_name || "");
        if (purchaseCost) purchaseCost.textContent = String(Number(payload.price || 0));
      } else {
        if (purchaseAction) purchaseAction.classList.add("hidden");
        if (payload.rent_due) {
          currentRentDue = Number(payload.rent_due || 0);
          currentBuyoutMin = Number(payload.buyout_min || 0);
          if (rentAmount) rentAmount.textContent = String(currentRentDue);
          if (rentAction) rentAction.classList.remove("hidden");
          if (buildAction) buildAction.classList.add("hidden");
          if (rollAction) rollAction.classList.add("hidden");
          if (endAction) endAction.classList.add("hidden");
        } else {
          if (rentAction) rentAction.classList.add("hidden");
          const actorPlayerId = Number(e.actor_player_id || 0);
          const buildFlags = getBuildAvailability(actorPlayerId);
          const canHouse = (buildFlags.canHouse || Boolean(payload.can_build_house)) && canAffordAnyBuild(actorPlayerId, "house");
          const canHotel = (buildFlags.canHotel || Boolean(payload.can_build_hotel)) && canAffordAnyBuild(actorPlayerId, "hotel");
          if (buildAction) {
            const anyBuild = canHouse || canHotel;
            buildAction.classList.toggle("hidden", !anyBuild);
          }
          if (buildHouseBtn) buildHouseBtn.classList.toggle("hidden", !canHouse);
          if (buildHotelBtn) buildHotelBtn.classList.toggle("hidden", !canHotel);
          if (!canHouse && !canHotel && buildAction) {
            buildAction.classList.add("hidden");
          }
        }
        if (!payload.rent_due) {
          if (payload.still_in_jail) {
            if (rollAction) rollAction.classList.add("hidden");
            if (endAction) endAction.classList.add("hidden");
            document.querySelectorAll(".action.roll-pay").forEach((el) => el.classList.remove("hidden"));
            showBoardModal("Тюрьма", `Не выпали дубли. Осталось попыток: ${Number(payload.jail_tries_left || 0)}.`);
          } else {
            document.querySelectorAll(".action.roll-pay,.action.pay-bail").forEach((el) => el.classList.add("hidden"));
            if (rollAction) rollAction.classList.add("hidden");
            const actorPlayerId = Number(e.actor_player_id || 0);
            const canEndManually = canAffordAnyBuild(actorPlayerId, "house") || canAffordAnyBuild(actorPlayerId, "hotel");
            if (endAction) endAction.classList.toggle("hidden", !canEndManually);
            if (!canEndManually) {
              triggerAutoEndTurn(actorPlayerId);
            }
          }
        }
      }
      if (!suppressTransientModals) {
        if (payload.sent_to_jail) showBoardModal("Тюрьма", "Игрок отправлен в тюрьму.");
        if (payload.bail_paid) showBoardModal("Тюрьма", `Оплачен залог: ${payload.bail_paid}`);
        if (payload.card) showCardModal("Карточка", String(payload.card));
        if (payload.rent_paid) showBoardModal("Рента", `Оплачена рента: ${payload.rent_paid}`);
        if (payload.buyout_declined) showBoardModal("Выкуп отклонён", "Владелец отказал в продаже. Необходимо оплатить ренту.");
        if (payload.buyout_approved) showBoardModal("Выкуп принят", `Клетка выкуплена за ${payload.offer_amount}`);
        if (payload.tax_paid) showBoardModal("Налог", `Оплачен налог: ${payload.tax_paid}`);
      }
      const fromPos = Number(payload.from_position ?? payload.position ?? 0);
      const toPos = Number(payload.position || 0);
      setBoardStatus(`${actorName(e)} перешёл с позиции ${fromPos} на позицию ${toPos}`, 1400);
      animateMove(actorId, fromPos, toPos);
    });
    events.forEach((e) => {
      if (e.event_type !== "command_end_turn") return;
      clearBuildSelection();
      if (rollAction) rollAction.classList.remove("hidden");
      if (endAction) endAction.classList.add("hidden");
      if (purchaseAction) purchaseAction.classList.add("hidden");
      if (rentAction) rentAction.classList.add("hidden");
      if (buildAction) buildAction.classList.add("hidden");
      if (playerIdsInOrder.length > 0) {
        controlIndex = (controlIndex + 1) % playerIdsInOrder.length;
        renderRightPanel();
      }
      setBoardStatus(`${actorName(e)} завершил ход`, 1000);
    });
    refreshCurrentPlayerInfo();
  }

  async function pollLoop() {
    try {
      const res = await fetch("/api/v1/game/" + gameId + "/events?since=" + sinceSeq, {
        credentials: "same-origin",
      });
      const data = await res.json();
      const events = Array.isArray(data.events) ? data.events : [];
      if (events.length) {
        sinceSeq = Number(events[events.length - 1].event_seq || sinceSeq);
        refreshFromEvents(events);
        refreshPlayers(events);
        renderRightPanel();
      }
    } catch (_) {
      // no-op: polling should continue even after transient errors
    } finally {
      setTimeout(pollLoop, 250);
    }
  }

  document.querySelectorAll(".js-game-command").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const action = btn.getAttribute("data-action");
      if (!action) return;
      const clientMsgId = "m_" + Date.now() + "_" + Math.random().toString(16).slice(2);
      const actorPlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
      const payload = {
        action: action,
        client_msg_id: clientMsgId,
        as_player_id: actorPlayerId,
      };
      if (action === "pay") {
        const targetCandidates = playerIdsInOrder.filter((id) => id !== actorPlayerId);
        if (targetCandidates.length === 0) {
          showPopup("Нет получателя для платежа");
          return;
        }
        payload.to_player_id = targetCandidates[0];
        payload.amount = 100;
      }
      try {
        if (action === "roll") {
          if (rollAction) rollAction.classList.add("hidden");
        }
        const ev = await postJson("/api/v1/game/" + gameId + "/commands", payload);
        ingestServerEvent(ev);
        if (action === "buy" || action === "sell") {
          await reloadPropertyState();
        }
      } catch (err) {
        if (action === "roll" && rollAction) rollAction.classList.remove("hidden");
        showPopup(toRuError(err.message || "Не удалось выполнить команду"));
      }
    });
  });
  document.querySelectorAll(".pay-bail").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const actorPlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
      try {
        const ev = await postJson("/api/v1/game/" + gameId + "/commands", {
          action: "pay_bail",
          as_player_id: actorPlayerId,
          client_msg_id: "bail_" + Date.now(),
        });
        ingestServerEvent(ev);
        document.querySelectorAll(".action.pay-bail,.action.roll-pay").forEach((el) => el.classList.add("hidden"));
        if (rollAction) rollAction.classList.remove("hidden");
      } catch (err) {
        showPopup(toRuError(err.message || "Не удалось оплатить залог"));
      }
    });
  });

  const purchaseBtn = document.querySelector(".purchase-property");
  if (purchaseBtn) {
    purchaseBtn.addEventListener("click", async () => {
      const actorPlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
      try {
        const ev = await postJson("/api/v1/game/" + gameId + "/commands", {
          action: "buy",
          as_player_id: actorPlayerId,
          client_msg_id: "buy_" + Date.now(),
        });
        ingestServerEvent(ev);
        await reloadPropertyState();
        if (purchaseAction) purchaseAction.classList.add("hidden");
        if (endAction) endAction.classList.remove("hidden");
      } catch (err) {
        showPopup(toRuError(err.message || "Не удалось купить клетку"));
      }
    });
  }
  const skipBtn = document.querySelector(".skip-property");
  if (skipBtn) {
    skipBtn.addEventListener("click", () => {
      if (purchaseAction) purchaseAction.classList.add("hidden");
      if (endAction) endAction.classList.remove("hidden");
    });
  }
  const payRentBtn = document.querySelector(".pay-rent");
  if (payRentBtn) {
    payRentBtn.addEventListener("click", async () => {
      const actorPlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
      try {
        const ev = await postJson("/api/v1/game/" + gameId + "/commands", {
          action: "pay_rent",
          as_player_id: actorPlayerId,
          client_msg_id: "rent_" + Date.now(),
        });
        ingestServerEvent(ev);
        if (rentAction) rentAction.classList.add("hidden");
        if (endAction) endAction.classList.remove("hidden");
      } catch (err) {
        showPopup(toRuError(err.message || "Не удалось оплатить ренту"));
      }
    });
  }
  const buyoutBtn = document.querySelector(".buyout-property");
  if (buyoutBtn) {
    buyoutBtn.addEventListener("click", async () => {
      const actorPlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
      const approved = await askOwnerBuyoutDecision(currentBuyoutMin);
      try {
        const ev = await postJson("/api/v1/game/" + gameId + "/commands", {
          action: "buyout",
          as_player_id: actorPlayerId,
          offer_amount: currentBuyoutMin > 0 ? currentBuyoutMin : (currentRentDue * 10),
          approved_by_owner: approved,
          client_msg_id: "buyout_" + Date.now(),
        });
        ingestServerEvent(ev);
        await reloadPropertyState();
        if (approved) {
          if (rentAction) rentAction.classList.add("hidden");
          if (endAction) endAction.classList.remove("hidden");
        } else {
          if (rentAction) rentAction.classList.remove("hidden");
          if (endAction) endAction.classList.add("hidden");
        }
      } catch (err) {
        showPopup(toRuError(err.message || "Не удалось выкупить клетку"));
      }
    });
  }
  if (buildHouseBtn) {
    buildHouseBtn.addEventListener("click", () => {
      if (pendingBuildType === "house") {
        clearBuildSelection();
        setBoardStatus(`${playerNameById.get(playerIdsInOrder[controlIndex] || selfPlayerId) || "Игрок"} завершил режим строительства`, 1200);
        return;
      }
      pendingBuildType = "house";
      const actorPlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
      buildablePositions = getBuildablePositions(actorPlayerId, "house");
      applyBuildSelectionUI();
      buildHouseBtn.textContent = "Завершить строительство";
      setBoardStatus(`${playerNameById.get(actorPlayerId) || "Игрок"} перешёл в режим строительства`, 1200);
      showNotify("Режим строительства: кликните по своей клетке для дома");
    });
  }
  if (buildHotelBtn) {
    buildHotelBtn.addEventListener("click", () => {
      pendingBuildType = "hotel";
      const actorPlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
      buildablePositions = getBuildablePositions(actorPlayerId, "hotel");
      applyBuildSelectionUI();
      setBoardStatus(`${playerNameById.get(actorPlayerId) || "Игрок"} перешёл в режим строительства`, 1200);
      showNotify("Режим строительства: кликните по своей клетке для отеля");
    });
  }

  const chatForm = document.querySelector("#chat-form");
  if (chatForm) {
    chatForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const fd = new FormData(chatForm);
      const message = (fd.get("message") || "").toString().trim();
      if (!message) return;
      try {
        appendChatMessage(playerNameById.get(selfPlayerId) || "Вы", message, new Date().toISOString(), true);
        await postJson("/api/v1/game/" + gameId + "/chat", { message: message });
        chatForm.reset();
        const pending = document.querySelector("#chat-log .chat-msg.is-pending");
        if (pending) pending.remove();
      } catch (err) {
        const pending = document.querySelector("#chat-log .chat-msg.is-pending");
        if (pending) pending.remove();
        showPopup(toRuError(err.message || "Не удалось отправить сообщение"));
      }
    });
  }

  const inviteLinkBtn = document.querySelector("#game-invite-link");
  if (inviteLinkBtn) {
    inviteLinkBtn.addEventListener("click", async () => {
      try {
        const invite = await postJson("/api/v1/game/" + gameId + "/invites", {});
        const link = window.location.origin + "/game/invites?token=" + encodeURIComponent(invite.token);
        await copyToClipboard(link);
        showNotify("Ссылка скопирована");
      } catch (err) {
        await showPopup(toRuError(err.message || "Не удалось скопировать ссылку приглашения"));
      }
    });
  }

  const inviteNickBtn = document.querySelector("#game-invite-nick");
  if (inviteNickBtn) {
    inviteNickBtn.addEventListener("click", openInviteModal);
  }

  async function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
      try {
        await navigator.clipboard.writeText(text);
        return;
      } catch (_) {
        // fallback below
      }
    }
    const input = document.createElement("textarea");
    input.value = text;
    input.setAttribute("readonly", "readonly");
    input.style.position = "fixed";
    input.style.left = "-9999px";
    document.body.appendChild(input);
    input.select();
    const ok = document.execCommand("copy");
    document.body.removeChild(input);
    if (!ok) {
      throw new Error("Не удалось скопировать ссылку");
    }
  }

  function openInviteModal() {
    const modal = document.querySelector("#game-invite-modal");
    if (!modal) return;
    const input = document.querySelector("#invite-nickname");
    const hint = document.querySelector("#invite-nickname-hint");
    const submitBtn = document.querySelector("#invite-send-btn");
    if (!input || !hint || !submitBtn) return;
    let checkTimer = null;
    const setState = (ok, msg) => {
      hint.textContent = msg || "";
      hint.style.color = ok ? "#0f9d58" : "#d93025";
      submitBtn.disabled = !ok;
    };
    const doCheck = async () => {
      const nickname = input.value.trim();
      if (nickname.length < 2) {
        setState(false, "Введите никнейм");
        return;
      }
      try {
        const res = await fetch("/api/v1/auth/availability?nickname=" + encodeURIComponent(nickname), { credentials: "same-origin" });
        const data = await res.json();
        if (data.nickname_available) {
          setState(false, "Пользователь с таким именем не найден");
        } else {
          setState(true, "Пользователь найден");
        }
      } catch (_) {
        setState(false, "Не удалось проверить пользователя");
      }
    };
    input.oninput = () => {
      submitBtn.disabled = true;
      hint.textContent = "";
      if (checkTimer) clearTimeout(checkTimer);
      checkTimer = setTimeout(doCheck, 260);
    };
    document.querySelectorAll(".js-invite-close").forEach((el) => {
      el.onclick = (e) => {
        e.preventDefault();
        modal.classList.remove("is-open");
      };
    });
    const form = document.querySelector("#invite-nick-form");
    if (form) {
      form.onsubmit = async (e) => {
        e.preventDefault();
        const nickname = input.value.trim();
        if (!nickname) return;
        try {
          await postJson("/api/v1/game/" + gameId + "/invites", { nickname: nickname });
          modal.classList.remove("is-open");
          showNotify("Приглашение отправлено");
        } catch (err) {
          showPopup(toRuError(err.message || "Не удалось отправить приглашение"));
        }
      };
    }
    input.value = "";
    hint.textContent = "";
    submitBtn.disabled = true;
    modal.classList.add("is-open");
    input.focus();
  }

  function renderStats() {
    const box = document.querySelector("#stats-box");
    if (!box) return;
    const stats = new Map();
    allEvents.forEach((e) => {
      const actor = actorName(e);
      if (!stats.has(actor)) {
        stats.set(actor, { moves: 0, paid: 0, received: 0, messages: 0 });
      }
      const row = stats.get(actor);
      const payload = parsePayload(e);
      if (e.event_type === "command_roll") row.moves += 1;
      if (e.event_type === "command_pay") row.paid += Number(payload.amount || 0);
      if (e.event_type === "chat_sent") row.messages += 1;
      if (e.event_type === "command_pay") {
        const target = playerNameById.get(Number(payload.to_player_id || 0));
        if (target) {
          if (!stats.has(target)) stats.set(target, { moves: 0, paid: 0, received: 0, messages: 0 });
          stats.get(target).received += Number(payload.amount || 0);
        }
      }
    });
    box.innerHTML = "";
    stats.forEach((val, key) => {
      const card = document.createElement("div");
      card.className = "ledger-row";
      card.innerHTML = `<div class="ledger-row__title">${key}</div><div class="ledger-row__desc">Ходы: ${val.moves} · Отправлено: ${val.paid} · Получено: ${val.received} · Сообщения: ${val.messages}</div>`;
      box.appendChild(card);
    });
  }

  function renderTrade() {
    const box = document.querySelector("#trade-box");
    if (!box) return;
    const trades = allEvents.filter((e) => e.event_type === "command_pay");
    if (trades.length === 0) {
      box.innerHTML = '<div class="timeline-row"><span class="timeline-row__actor">Система</span><span class="timeline-row__text">Сделок пока нет.</span><span class="timeline-row__time">—</span></div>';
      return;
    }
    box.innerHTML = "";
    trades.forEach((e) => {
      const payload = parsePayload(e);
      const row = document.createElement("div");
      row.className = "ledger-row";
      row.innerHTML = `<div class="ledger-row__title">${actorName(e)} → ${playerNameById.get(Number(payload.to_player_id || 0)) || "Игрок"}: ${Number(payload.amount || 0)}</div><div class="ledger-row__meta">${formatTimestampRu(e.created_at || "")}</div>`;
      box.appendChild(row);
    });
  }

  function renderHistory() {
    const box = document.querySelector("#history-box");
    if (!box) return;
    box.innerHTML = "";
    allEvents
      .slice()
      .sort((a, b) => Number(b.event_seq || 0) - Number(a.event_seq || 0))
      .forEach((eventObj) => {
        const row = document.createElement("div");
        row.className = "timeline-row";
        row.innerHTML = `<span class="timeline-row__actor">${escapeHtml(actorName(eventObj))}</span><span class="timeline-row__text">${escapeHtml(describeEvent(eventObj))}</span><span class="timeline-row__time">${escapeHtml(formatTimestampRu(eventObj.created_at || ""))}</span>`;
        box.appendChild(row);
      });
  }

  function renderTokens() {
    const board = document.querySelector("#board");
    if (!board) return;
    board.querySelectorAll(".token").forEach((el) => el.remove());
    players.forEach((p, idx) => {
      const color = tokenColors[idx % tokenColors.length];
      playerColorById.set(Number(p.id || 0), color);
      const token = document.createElement("div");
      token.className = `token color-${idx % 8}`;
      token.style.setProperty("--token-color", color);
      token.dataset.playerId = String(Number(p.id || 0));
      board.appendChild(token);
      const chip = document.querySelector(`.player-chip[data-player-id="${Number(p.id || 0)}"]`);
      if (chip) chip.style.setProperty("--active-color", color);
      setTokenPosition(Number(p.id || 0), Number(p.position || 0));
    });
    players.forEach((p) => updatePlayerChipRow(Number(p.id || 0)));
    markActiveToken();
  }

  function setTokenPosition(playerId, pos) {
    const board = document.querySelector("#board");
    const token = document.querySelector(`.token[data-player-id="${playerId}"]`);
    const space = document.querySelector(`#board [data-pos="${pos}"]`);
    if (!board || !token || !space) return;
    const b = board.getBoundingClientRect();
    const s = space.getBoundingClientRect();
    const samePosTokens = Array.from(document.querySelectorAll(`.token`)).filter((t) => {
      const pid = Number(t.dataset.playerId || 0);
      if (pid === playerId) return false;
      const sp = players.find((x) => Number(x.id || 0) === pid);
      return Number(sp && sp.position || 0) === pos;
    }).length;
    const dx = (samePosTokens % 3) * 10 - 8;
    const dy = Math.floor(samePosTokens / 3) * 10 - 8;
    token.style.left = `${(s.left - b.left) + (s.width / 2) + dx}px`;
    token.style.top = `${(s.top - b.top) + (s.height / 2) + dy}px`;
    const pRef = players.find((x) => Number(x.id || 0) === playerId);
    if (pRef) pRef.position = pos;
    markActiveToken();
  }

  function animateMove(playerId, fromPos, toPos) {
    if (fromPos === toPos) {
      setTokenPosition(playerId, toPos);
      return;
    }
    const steps = [];
    let p = fromPos;
    while (p !== toPos) {
      p = (p + 1) % 40;
      steps.push(p);
      if (steps.length > 80) break;
    }
    let i = 0;
    const tick = () => {
      if (i >= steps.length) return;
      setTokenPosition(playerId, steps[i]);
      i += 1;
      window.setTimeout(tick, 980);
    };
    tick();
  }

  function refreshCurrentPlayerInfo() {
    const activePlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
    const statusEl = document.querySelector("#board-status-text");
    if (statusEl && !statusEl.dataset.locked) {
      statusEl.textContent = `${playerNameById.get(activePlayerId) || "Игрок"} ожидает действие`;
    }
  }

  function setBoardStatus(text, lockMs) {
    const statusEl = document.querySelector("#board-status-text");
    if (!statusEl) return;
    statusEl.textContent = String(text || "");
    if (Number(lockMs || 0) > 0) {
      statusEl.dataset.locked = "1";
      window.setTimeout(() => {
        delete statusEl.dataset.locked;
      }, Number(lockMs));
    }
  }

  function markActiveToken() {
    document.querySelectorAll(".token.active").forEach((el) => el.classList.remove("active"));
    document.querySelectorAll(".player-chip.is-current").forEach((el) => el.classList.remove("is-current"));
    const activePlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
    const token = document.querySelector(`.token[data-player-id="${activePlayerId}"]`);
    if (token) token.classList.add("active");
    const chip = document.querySelector(`.player-chip[data-player-id="${activePlayerId}"]`);
    if (chip) chip.classList.add("is-current");
    const turnLabel = document.querySelector("#turn-player-label");
    if (turnLabel) turnLabel.textContent = playerNameById.get(activePlayerId) || "—";
    const buildFlags = getBuildAvailability(activePlayerId);
    if (buildHouseBtn) buildHouseBtn.classList.toggle("hidden", !buildFlags.canHouse);
    if (buildHotelBtn) buildHotelBtn.classList.toggle("hidden", !buildFlags.canHotel);
    if (buildAction && rentAction && rentAction.classList.contains("hidden")) {
      buildAction.classList.toggle("hidden", !(buildFlags.canHouse || buildFlags.canHotel));
    }
    refreshCurrentPlayerInfo();
  }

  async function reloadPropertyState() {
    try {
      const res = await fetch("/api/v1/game/" + gameId + "/events?since=0", { credentials: "same-origin" });
      const data = await res.json();
      const events = Array.isArray(data.events) ? data.events : [];
      for (const e of events) {
        if (e.event_type !== "command_buy" && e.event_type !== "command_sell" && e.event_type !== "command_build" && e.event_type !== "command_sell_building") continue;
        const payload = parsePayload(e);
        const position = Number(payload.position || -1);
        if (position < 0) continue;
        if (e.event_type === "command_sell") {
          for (let i = propertyState.length - 1; i >= 0; i--) {
            if (Number(propertyState[i].cell_position || -1) === position) {
              propertyState.splice(i, 1);
            }
          }
        } else if (e.event_type === "command_buy") {
          const ownerId = Number(payload.buyer_player_id || 0);
          const idx = propertyState.findIndex((x) => Number(x.cell_position || -1) === position);
          const existing = idx >= 0 ? propertyState[idx] : {};
          const next = { ...existing, cell_position: position, owner_player_id: ownerId, houses: Number(existing.houses || 0), has_hotel: Boolean(existing.has_hotel) };
          if (idx >= 0) propertyState[idx] = next;
          else propertyState.push(next);
        } else if (e.event_type === "command_build" || e.event_type === "command_sell_building") {
          const idx = propertyState.findIndex((x) => Number(x.cell_position || -1) === position);
          if (idx < 0) continue;
          const current = propertyState[idx];
          propertyState[idx] = {
            ...current,
            houses: Number(payload.houses ?? current.houses ?? 0),
            has_hotel: Boolean(payload.has_hotel ?? current.has_hotel ?? false),
          };
        }
      }
      renderRightPanel();
    } catch (_) {}
  }

  function applyPropertyStateFromEvent(eventObj) {
    const type = String(eventObj.event_type || "");
    const payload = parsePayload(eventObj);
    const position = Number(payload.position || -1);
    if (position < 0) return;
    if (type === "command_sell") {
      for (let i = propertyState.length - 1; i >= 0; i -= 1) {
        if (Number(propertyState[i].cell_position || -1) === position) {
          propertyState.splice(i, 1);
        }
      }
      renderRightPanel();
      return;
    }
    if (type === "command_buy") {
      const ownerId = Number(payload.buyer_player_id || 0);
      const idx = propertyState.findIndex((x) => Number(x.cell_position || -1) === position);
      const existing = idx >= 0 ? propertyState[idx] : {};
      const next = { ...existing, cell_position: position, owner_player_id: ownerId, houses: Number(existing.houses || 0), has_hotel: Boolean(existing.has_hotel) };
      if (idx >= 0) propertyState[idx] = next;
      else propertyState.push(next);
      renderRightPanel();
      return;
    }
    if (type === "command_build" || type === "command_sell_building") {
      const idx = propertyState.findIndex((x) => Number(x.cell_position || -1) === position);
      if (idx < 0) return;
      const current = propertyState[idx];
      propertyState[idx] = {
        ...current,
        houses: Number(payload.houses ?? current.houses ?? 0),
        has_hotel: Boolean(payload.has_hotel ?? current.has_hotel ?? false),
      };
      renderRightPanel();
    }
  }

  function renderRightPanel() {
    const grouped = new Map();
    propertyState.forEach((ps) => {
      const ownerId = Number(ps.owner_player_id || 0);
      if (ownerId <= 0) return;
      if (!grouped.has(ownerId)) grouped.set(ownerId, []);
      grouped.get(ownerId).push(Number(ps.cell_position || 0));
    });
    document.querySelectorAll(".player-chip").forEach((chip) => {
      const playerId = Number(chip.getAttribute("data-player-id") || 0);
      const assets = chip.querySelector(".player-assets");
      if (!assets) return;
      assets.innerHTML = "";
      const positions = grouped.get(playerId) || [];
      positions.sort((a, b) => a - b).forEach((pos) => {
        const meta = boardMeta[pos] || { name: "#" + String(pos), price: 0, rent: 0, group: null };
        const el = document.createElement("button");
        el.type = "button";
        el.className = "asset-card";
        const hasGroup = meta.group !== null && meta.group !== undefined;
        const colorClass = hasGroup ? groupColorClass[Number(meta.group)] || "" : "asset-card__bar--neutral";
        el.innerHTML = `<span class="asset-card__bar ${colorClass}"></span><span class="asset-card__name">${escapeHtml(meta.name)}</span>`;
        el.addEventListener("click", () => {
          showDeedInPlayerBlock(chip, meta);
        });
        assets.appendChild(el);
      });
      if (!chip.dataset.toggleInit) {
        const name = chip.querySelector("strong");
        if (name) {
          name.addEventListener("click", () => {
            chip.classList.toggle("is-open");
          });
        }
        chip.dataset.toggleInit = "1";
      }
    });
    updateBoardPropertyDecorations();
    markActiveToken();
  }

  function updateBoardPropertyDecorations() {
    document.querySelectorAll("#board .space.property").forEach((space) => {
      const old = space.querySelector(".build-icons");
      if (old) old.remove();
    });
    propertyState.forEach((ps) => {
      const pos = Number(ps.cell_position || -1);
      if (pos < 0) return;
      const space = document.querySelector(`#board .space.property[data-pos="${pos}"]`);
      if (!space) return;
      const ownerId = Number(ps.owner_player_id || 0);
      const ownerColor = playerColorById.get(ownerId) || "#0a66ff";
      const houses = Number(ps.houses || 0);
      const hasHotel = Boolean(ps.has_hotel);
      if (!houses && !hasHotel) return;
      const box = document.createElement("div");
      box.className = "build-icons";
      box.style.pointerEvents = "auto";
      if (hasHotel) {
        const icon = document.createElement("span");
        icon.className = "build-icon hotel";
        icon.style.setProperty("--owner-color", ownerColor);
        box.appendChild(icon);
      } else {
        for (let i = 0; i < houses; i += 1) {
          const icon = document.createElement("span");
          icon.className = "build-icon house";
          icon.style.setProperty("--owner-color", ownerColor);
          box.appendChild(icon);
        }
      }
      const row = space.closest(".row");
      let edge = "south";
      if (row && row.classList.contains("north")) edge = "north";
      else if (row && row.classList.contains("west")) edge = "west";
      else if (row && row.classList.contains("east")) edge = "east";
      box.classList.add("build-icons--" + edge);
      box.addEventListener("click", async (evt) => {
        evt.preventDefault();
        evt.stopPropagation();
        const activePlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
        if (ownerId !== activePlayerId) return;
        try {
          const ev = await postJson("/api/v1/game/" + gameId + "/commands", {
            action: "sell_building",
            position: pos,
            as_player_id: activePlayerId,
            client_msg_id: "sell_building_" + pos + "_" + Date.now(),
          });
          ingestServerEvent(ev);
          setBoardStatus(`${playerNameById.get(activePlayerId) || "Игрок"} продал ${hasHotel ? "отель" : "дом"} на позиции ${pos}`, 1400);
          showNotify("Постройка продана");
        } catch (err) {
          showPopup(toRuError(err.message || "Не удалось продать постройку"));
        }
      });
      const canQuickBuildHouse = Number(ownerId) === Number(playerIdsInOrder[controlIndex] || selfPlayerId)
        && canAffordAnyBuild(ownerId, "house")
        && getBuildablePositions(ownerId, "house").has(pos);
      if (canQuickBuildHouse) {
        const plus = document.createElement("button");
        plus.type = "button";
        plus.className = "build-plus";
        plus.textContent = "+";
        plus.title = "Построить дом";
        plus.addEventListener("click", async (evt) => {
          evt.preventDefault();
          evt.stopPropagation();
          try {
            const ev = await postJson("/api/v1/game/" + gameId + "/commands", {
              action: "build",
              build_type: "house",
              position: pos,
              as_player_id: ownerId,
              client_msg_id: "quick_build_house_" + pos + "_" + Date.now(),
            });
            ingestServerEvent(ev);
            setBoardStatus(`${playerNameById.get(ownerId) || "Игрок"} построил дом на позиции ${pos}`, 1400);
          } catch (err) {
            showPopup(toRuError(err.message || "Не удалось построить дом"));
          }
        });
        box.appendChild(plus);
      }
      space.appendChild(box);
    });
  }

  function getBuildablePositions(ownerId, buildType) {
    const ownedByPos = new Map();
    propertyState.forEach((ps) => {
      if (Number(ps.owner_player_id || 0) === ownerId) {
        ownedByPos.set(Number(ps.cell_position || -1), ps);
      }
    });
    const groupCells = new Map();
    Object.entries(boardMeta).forEach(([posStr, meta]) => {
      if (meta.group === null || meta.group === undefined) return;
      const pos = Number(posStr);
      if (!groupCells.has(meta.group)) groupCells.set(meta.group, []);
      groupCells.get(meta.group).push(pos);
    });
    const result = new Set();
    groupCells.forEach((positions) => {
      if (!positions.every((p) => ownedByPos.has(p))) return;
      positions.forEach((p) => {
        const ps = ownedByPos.get(p) || {};
        const houses = Number(ps.houses || 0);
        const hasHotel = Boolean(ps.has_hotel);
        if (buildType === "house" && !hasHotel && houses < 4) result.add(p);
        if (buildType === "hotel" && !hasHotel && houses >= 4) result.add(p);
      });
    });
    return result;
  }

  function applyBuildSelectionUI() {
    const board = document.querySelector("#board");
    if (!board) return;
    board.classList.toggle("build-selection-active", Boolean(pendingBuildType));
    if (buildHouseBtn) buildHouseBtn.textContent = pendingBuildType === "house" ? "Завершить строительство" : "Построить дом";
    document.querySelectorAll("#board [data-pos]").forEach((el) => {
      const pos = Number(el.getAttribute("data-pos") || -1);
      el.classList.toggle("is-buildable", pendingBuildType !== "" && buildablePositions.has(pos));
    });
  }

  function clearBuildSelection() {
    pendingBuildType = "";
    buildablePositions = new Set();
    applyBuildSelectionUI();
    if (buildHouseBtn) buildHouseBtn.textContent = "Построить дом";
  }

  function getBuildAvailability(ownerId) {
    const byPos = new Map();
    propertyState.forEach((ps) => {
      byPos.set(Number(ps.cell_position || -1), ps);
    });
    const groups = new Map();
    Object.entries(boardMeta).forEach(([posStr, meta]) => {
      const pos = Number(posStr);
      if (meta.group === null || meta.group === undefined) return;
      const entry = byPos.get(pos);
      const ownedByCurrent = entry && Number(entry.owner_player_id || 0) === ownerId;
      if (!groups.has(meta.group)) groups.set(meta.group, []);
      groups.get(meta.group).push({ pos, ownedByCurrent, houses: Number(entry?.houses || 0), hasHotel: Boolean(entry?.has_hotel) });
    });
    let canHouse = false;
    let canHotel = false;
    groups.forEach((cells) => {
      if (!cells.length) return;
      if (!cells.every((x) => x.ownedByCurrent)) return;
      if (cells.some((x) => !x.hasHotel && x.houses < 4)) canHouse = true;
      if (cells.some((x) => !x.hasHotel && x.houses >= 4)) canHotel = true;
    });
    return { canHouse, canHotel };
  }

  function canAffordAnyBuild(ownerId, type) {
    const player = players.find((p) => Number(p.id || 0) === Number(ownerId));
    const cash = Number((player && player.cash) || 0);
    const positions = getBuildablePositions(Number(ownerId), type);
    for (const pos of positions) {
      const meta = boardMeta[pos];
      const cost = Number(groupBuildCost[Number(meta?.group ?? -1)] || 0);
      if (cost > 0 && cash >= cost) return true;
    }
    return false;
  }

  async function triggerAutoEndTurn(actorPlayerId) {
    if (autoEndTurnInFlight) return;
    autoEndTurnInFlight = true;
    try {
      const ev = await postJson("/api/v1/game/" + gameId + "/commands", {
        action: "end_turn",
        as_player_id: actorPlayerId,
        client_msg_id: "auto_end_turn_" + Date.now(),
      });
      ingestServerEvent(ev);
    } catch (_) {
      // no-op
    } finally {
      autoEndTurnInFlight = false;
    }
  }

  function deedMoney(amount) {
    const n = Number(amount);
    return `<span class="deed-money-wrap"><abbr class="deed-currency" title="Условные деньги">M</abbr><span class="deed-money-num">${n}</span></span>`;
  }

  function houseRentRow(n, amount) {
    const word = n === 1 ? "домом" : "домами";
    return `<div class="deed-rent-row">
      <span class="deed-rent-label"><span class="deed-house-glyph"><img src="/assets/images/house.svg" alt="" width="18" height="18"/><span class="deed-house-n">${n}</span></span><span>С ${n} ${word}</span></span>
      <span class="deed-rent-val">${deedMoney(amount)}</span>
    </div>`;
  }

  function hotelRentRow(amount) {
    return `<div class="deed-rent-row">
      <span class="deed-rent-label"><img src="/assets/images/hotel.svg" class="deed-hotel-glyph" alt="" width="22" height="22"/> С отелем</span>
      <span class="deed-rent-val">${deedMoney(amount)}</span>
    </div>`;
  }

  function buildStreetDeedHtml(meta, withCloseButton) {
    const g = Number(meta.group);
    const colorClass = groupColorClass[g] || "";
    const base = Number(meta.rent || 0);
    const buildCost = Number(groupBuildCost[g] || 0);
    const rentColor = base * 2;
    const r1 = base * 3;
    const r2 = base * 5;
    const r3 = base * 8;
    const r4 = base * 12;
    const rH = base * 25;
    return `<div class="deed-card deed-card--street">
      <div class="deed-card__header ${colorClass}">
        ${withCloseButton ? '<button type="button" class="deed-inline-close" aria-label="Закрыть">✕</button>' : ""}
        <div class="deed-card__kicker">КАРТОЧКА СОБСТВЕННОСТИ</div>
        <div class="deed-card__title-name">${escapeHtml(meta.name)}</div>
      </div>
      <div class="deed-card__body">
        <div class="deed-rent-table">
          <div class="deed-rent-row"><span>Аренда</span><span>${deedMoney(base)}</span></div>
          <div class="deed-rent-row"><span>Аренда при полном наборе цвета</span><span>${deedMoney(rentColor)}</span></div>
          ${houseRentRow(1, r1)}
          ${houseRentRow(2, r2)}
          ${houseRentRow(3, r3)}
          ${houseRentRow(4, r4)}
          ${hotelRentRow(rH)}
        </div>
        <div class="deed-build-costs">
          <div>Дома: <span class="deed-underline">${deedMoney(buildCost)}</span> каждый</div>
          <div>Отели: <span class="deed-underline">${deedMoney(buildCost)}</span><span class="deed-foot"> (плюс 4 дома)</span></div>
        </div>
        <div class="deed-price-line">Цена собственности: ${deedMoney(Number(meta.price || 0))}</div>
      </div>
    </div>`;
  }

  function buildGenericDeedHtml(meta, withCloseButton) {
    const details = escapeHtml(String(meta.details || "Клетка действует по правилам Monopoly."));
    const hasPrice = Number(meta.price || 0) > 0;
    const hasEffect = Number(meta.rent || 0) > 0;
    return `<div class="deed-card deed-card--generic">
      <div class="deed-card__header deed-card__header--neutral">
        ${withCloseButton ? '<button type="button" class="deed-inline-close" aria-label="Закрыть">✕</button>' : ""}
        <div class="deed-card__title-name">${escapeHtml(meta.name)}</div>
      </div>
      <div class="deed-card__body">
      <dl class="deed-generic-dl">
        <dt>Тип</dt><dd>${escapeHtml(String(meta.type || "служебная"))}</dd>
        ${hasPrice ? `<dt>Цена / сбор</dt><dd>${deedMoney(meta.price)}</dd>` : ""}
        ${hasEffect ? `<dt>Эффект</dt><dd>${deedMoney(meta.rent)}</dd>` : ""}
      </dl>
      <p class="deed-generic-details">${details}</p>
      </div>
    </div>`;
  }

  function renderDeedCardHtml(meta, withCloseButton) {
    const isStreet = meta.group !== null && meta.group !== undefined;
    return isStreet ? buildStreetDeedHtml(meta, withCloseButton) : buildGenericDeedHtml(meta, withCloseButton);
  }

  function showDeedPopup(meta) {
    const wrap = document.querySelector("#space-overlay");
    if (!wrap) return;
    wrap.innerHTML = `<div class="modal-body type-ok modal-body--deed">${renderDeedCardHtml(meta, true)}</div>`;
    wrap.classList.remove("hidden", "hide");
    wrap.classList.add("show");
    const close = () => {
      wrap.classList.remove("show");
      wrap.classList.add("hide");
      window.setTimeout(() => wrap.classList.add("hidden"), 180);
    };
    wrap.onclick = (evt) => {
      if (evt.target === wrap || evt.target.closest(".deed-inline-close")) close();
    };
  }

  function showDeedInPlayerBlock(chip, meta) {
    if (!chip) return;
    let panel = chip.querySelector(".player-deed-view");
    if (!panel) {
      panel = document.createElement("div");
      panel.className = "player-deed-view";
      chip.appendChild(panel);
    }
    panel.innerHTML = renderDeedCardHtml(meta, true);
    panel.classList.remove("hidden");
    const closeBtn = panel.querySelector(".deed-inline-close");
    if (closeBtn) {
      closeBtn.onclick = () => panel.classList.add("hidden");
    }
    chip.classList.add("is-open");
  }

  function showDeedSidebar(meta) {
    const wrap = document.querySelector("#tabletop-card-closeup");
    if (!wrap) return;
    wrap.classList.remove("hidden");
    const playerId = playerIdsInOrder[controlIndex] || selfPlayerId;
    const chip = document.querySelector(`.player-chip[data-player-id="${playerId}"]`);
    showDeedInPlayerBlock(chip, meta);
  }

  document.querySelectorAll("#board [data-pos]").forEach((cell) => {
    cell.addEventListener("click", async () => {
      const pos = Number(cell.getAttribute("data-pos") || -1);
      if (pos < 0) return;
      if (pendingBuildType) {
        if (!buildablePositions.has(pos)) {
          clearBuildSelection();
          setBoardStatus(`${playerNameById.get(playerIdsInOrder[controlIndex] || selfPlayerId) || "Игрок"} завершил режим строительства`, 1200);
          return;
        }
        const actorPlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
        try {
          const ev = await postJson("/api/v1/game/" + gameId + "/commands", {
            action: "build",
            build_type: pendingBuildType,
            position: pos,
            as_player_id: actorPlayerId,
            client_msg_id: "build_" + pendingBuildType + "_" + Date.now(),
          });
          ingestServerEvent(ev);
          setBoardStatus(`${playerNameById.get(actorPlayerId) || "Игрок"} построил ${pendingBuildType === "hotel" ? "отель" : "дом"} на позиции ${pos}`, 1400);
          showNotify(pendingBuildType === "hotel" ? "Отель построен" : "Дом построен");
        } catch (err) {
          showPopup(toRuError(err.message || "Не удалось построить"));
        } finally {
          clearBuildSelection();
        }
        return;
      }
      const meta = boardMeta[pos];
      if (!meta) return;
      showDeedPopup(meta);
    });
  });

  const boardRoot = document.querySelector("#board");
  if (boardRoot) {
    boardRoot.addEventListener("click", (evt) => {
      if (!pendingBuildType) return;
      if (evt.target.closest(".main-action")) return;
      const cell = evt.target.closest("[data-pos]");
      if (cell) return;
      clearBuildSelection();
      setBoardStatus(`${playerNameById.get(playerIdsInOrder[controlIndex] || selfPlayerId) || "Игрок"} завершил режим строительства`, 1200);
    });
  }

  const closeupCloseBtn = document.querySelector("#tabletop-card-closeup-close");
  if (closeupCloseBtn) {
    closeupCloseBtn.addEventListener("click", () => {
      const w = document.querySelector("#tabletop-card-closeup");
      if (w) w.classList.add("hidden");
    });
  }

  function showBoardModal(title, text) {
    const root = document.querySelector("#modal-overlay");
    if (!root) return;
    const titleEl = root.querySelector(".modal-title");
    const contentEl = root.querySelector(".modal-content");
    if (titleEl) titleEl.textContent = String(title || "Событие");
    if (contentEl) contentEl.textContent = String(text || "");
    root.classList.remove("hidden", "hide");
    root.classList.add("show");
    const close = root.querySelector(".close");
    if (close) {
      close.onclick = () => {
        root.classList.remove("show");
        root.classList.add("hide");
        window.setTimeout(() => root.classList.add("hidden"), 200);
      };
    }
  }

  async function askOwnerBuyoutDecision(offer) {
    const root = document.querySelector("#modal-overlay");
    if (!root) return false;
    const titleEl = root.querySelector(".modal-title");
    const contentEl = root.querySelector(".modal-content");
    const footerEl = root.querySelector(".modal-footer");
    if (!titleEl || !contentEl || !footerEl) return false;
    return new Promise((resolve) => {
      titleEl.textContent = "Предложение выкупа";
      contentEl.textContent = `Владелец соглашается продать клетку за ${offer}?`;
      footerEl.innerHTML = "";
      const agree = document.createElement("button");
      agree.type = "button";
      agree.className = "modal-action";
      agree.textContent = "Согласиться";
      const reject = document.createElement("button");
      reject.type = "button";
      reject.className = "modal-action";
      reject.textContent = "Отказать";
      const closeModal = (result) => {
        root.classList.remove("show");
        root.classList.add("hide");
        window.setTimeout(() => root.classList.add("hidden"), 180);
        resolve(result);
      };
      agree.onclick = () => closeModal(true);
      reject.onclick = () => closeModal(false);
      const close = root.querySelector(".close");
      if (close) close.onclick = () => closeModal(false);
      footerEl.appendChild(reject);
      footerEl.appendChild(agree);
      root.classList.remove("hidden", "hide");
      root.classList.add("show");
    });
  }

  function showCardModal(title, text) {
    const root = document.querySelector("#card-overlay");
    if (!root) return;
    const titleEl = root.querySelector(".card-title");
    const contentEl = root.querySelector(".card-content");
    if (titleEl) titleEl.textContent = String(title || "Карта");
    if (contentEl) contentEl.textContent = String(text || "");
    root.classList.remove("hidden", "hide", "in-deck");
    root.classList.add("show");
    const closeIt = () => {
      root.classList.remove("show");
      root.classList.add("hide");
      window.setTimeout(() => root.classList.add("hidden"), 240);
    };
    root.onclick = closeIt;
  }

  const initialEvents = (() => {
    const box = document.querySelector("#ledger-box");
    try {
      return JSON.parse((box && box.getAttribute("data-initial-events")) || "[]");
    } catch (_) {
      return [];
    }
  })();
  refreshFromEvents(initialEvents);
  if (initialEvents.length) {
    sinceSeq = Math.max(
      sinceSeq,
      ...initialEvents.map((ev) => Number(ev.event_seq || 0)).filter((n) => Number.isFinite(n) && n > 0)
    );
  }
  const staleBoardModal = document.querySelector("#modal-overlay");
  const staleCardModal = document.querySelector("#card-overlay");
  if (staleBoardModal) staleBoardModal.classList.add("hidden");
  if (staleBoardModal) staleBoardModal.classList.remove("show");
  if (staleCardModal) staleCardModal.classList.add("hidden");
  if (staleCardModal) staleCardModal.classList.remove("show");
  const chatLog = document.querySelector("#chat-log");
  if (chatLog) {
    chatLog.querySelectorAll(".chat-msg__time").forEach((el) => {
      const normalized = formatTimestampRu(el.textContent || "");
      if (normalized) el.textContent = normalized;
    });
    chatLog.addEventListener("click", (e) => {
      const btn = e.target.closest(".chat-msg__author");
      if (!btn) return;
      const input = document.querySelector('#chat-form input[name="message"]');
      if (!input) return;
      const nick = btn.textContent || "";
      const prefix = `@${nick} `;
      input.value = prefix;
      input.focus();
      input.setSelectionRange(input.value.length, input.value.length);
    });
  }
  renderRightPanel();
  refreshCurrentPlayerInfo();
  window.setTimeout(() => {
    suppressTransientModals = false;
  }, 600);
  pollLoop();
})();
