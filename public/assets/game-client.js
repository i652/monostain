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
      command_mortgage: "Залог карточки",
      command_redeem_mortgage: "Выкуп из залога",
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
  const financeEventTypes = new Set(["command_pay", "command_buy", "command_sell", "command_pay_bail", "command_pay_rent", "command_buyout", "command_build", "command_sell_building", "command_trade", "command_mortgage", "command_redeem_mortgage", "property_bought", "property_sold", "trade_completed", "tax_paid", "rent_paid"]);
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
  const buyoutOfferInput = document.querySelector(".main-action .action.rent .buyout-offer-input");
  let currentRentDue = 0;
  let currentBuyoutMin = 0;
  let pendingBuildType = "";
  let buildablePositions = new Set();
  let autoEndTurnInFlight = false;
  let suppressTransientModals = true;
  let boardRotationDeg = 0;
  let buyoutInputVisible = false;
  renderTokens();
  const rotateLeftBtn = document.querySelector(".board-rotate-left");
  const rotateRightBtn = document.querySelector(".board-rotate-right");
  const boardEl = document.querySelector("#board");
  const applyBoardRotation = () => {
    if (!boardEl) return;
    boardEl.style.transform = `rotate(${boardRotationDeg}deg)`;
    boardEl.style.transformOrigin = "50% 50%";
  };
  if (rotateLeftBtn) {
    rotateLeftBtn.addEventListener("click", () => {
      boardRotationDeg -= 90;
      applyBoardRotation();
    });
  }
  if (rotateRightBtn) {
    rotateRightBtn.addEventListener("click", () => {
      boardRotationDeg += 90;
      applyBoardRotation();
    });
  }

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
    if (type === "command_buy" || type === "command_pay_bail" || type === "command_build" || type === "command_sell_building" || type === "command_mortgage" || type === "command_redeem_mortgage") {
      if (Number.isFinite(Number(payload.cash))) setPlayerCash(actorId, payload.cash);
      return;
    }
    if (type === "command_trade") {
      if (Number.isFinite(Number(payload.initiator_cash))) setPlayerCash(Number(payload.initiator_player_id || actorId), payload.initiator_cash);
      if (Number.isFinite(Number(payload.target_cash))) setPlayerCash(Number(payload.target_player_id || 0), payload.target_cash);
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
        } else if (["command_buy", "command_sell", "command_pay_bail", "command_build", "command_sell_building", "command_buyout", "command_mortgage", "command_redeem_mortgage"].includes(eventType) && Number.isFinite(Number(payload.cash))) {
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
    if (eventObj.event_type === "command_mortgage") {
      return `Карточка #${payload.position} заложена банку (+${payload.credit || 0})`;
    }
    if (eventObj.event_type === "command_redeem_mortgage") {
      return `Карточка #${payload.position} выкуплена из залога (-${payload.redeem_cost || 0})`;
    }
    if (eventObj.event_type === "command_trade") {
      return payload.trade_approved ? "Обмен карточками завершён" : "Обмен отклонён";
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
      rollDiceVisual(Number(dice[0] || 1), Number(dice[1] || 1), actorId);
      if (payload.offer_purchase) {
        currentRentDue = 0;
        currentBuyoutMin = 0;
        if (rentAction) rentAction.classList.add("hidden");
        if (buyoutOfferInput) buyoutOfferInput.classList.add("hidden");
        buyoutInputVisible = false;
        if (buildAction) buildAction.classList.add("hidden");
        if (rollAction) rollAction.classList.add("hidden");
        if (endAction) endAction.classList.add("hidden");
        if (purchaseAction) purchaseAction.classList.remove("hidden");
        const purchaseBtn = document.querySelector(".purchase-property");
        const posFromPayload = Number(payload.position || -1);
        const metaFromPos = posFromPayload >= 0 ? boardMeta[posFromPayload] : null;
        const shownName = String((metaFromPos && metaFromPos.name) || payload.space_name || "");
        const shownPrice = Number((metaFromPos && metaFromPos.price) || payload.price || 0);
        if (purchaseName) purchaseName.textContent = shownName;
        if (purchaseBtn) purchaseBtn.textContent = `Купить ${shownName} за ${shownPrice}`;
      } else {
        if (purchaseAction) purchaseAction.classList.add("hidden");
        if (payload.rent_due) {
          currentRentDue = Number(payload.rent_due || 0);
          currentBuyoutMin = Number(payload.buyout_min || 0);
          const payRentBtn = document.querySelector(".pay-rent");
          if (payRentBtn) payRentBtn.textContent = `Оплатить ренту: M ${currentRentDue}`;
          if (rentAction) rentAction.classList.remove("hidden");
          if (buildAction) buildAction.classList.add("hidden");
          if (rollAction) rollAction.classList.add("hidden");
          if (endAction) endAction.classList.add("hidden");
        } else {
          if (rentAction) rentAction.classList.add("hidden");
          if (buyoutOfferInput) buyoutOfferInput.classList.add("hidden");
          buyoutInputVisible = false;
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
      if (buyoutOfferInput) buyoutOfferInput.classList.add("hidden");
      buyoutInputVisible = false;
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
        if (buyoutOfferInput) buyoutOfferInput.classList.add("hidden");
        buyoutInputVisible = false;
        if (endAction) endAction.classList.remove("hidden");
      } catch (err) {
        showPopup(toRuError(err.message || "Не удалось оплатить ренту"));
      }
    });
  }
  const buyoutBtn = document.querySelector(".buyout-property");
  if (buyoutBtn) {
    buyoutBtn.addEventListener("click", async () => {
      if (!buyoutInputVisible) {
        if (buyoutOfferInput) {
          const actorPlayerId = Number(playerIdsInOrder[controlIndex] || selfPlayerId || 0);
          const actorPlayer = players.find((p) => Number(p.id || 0) === actorPlayerId);
          const cash = Number((actorPlayer && actorPlayer.cash) || 0);
          buyoutOfferInput.classList.remove("hidden");
          buyoutOfferInput.min = String(Math.max(0, Number(currentBuyoutMin || 0)));
          buyoutOfferInput.max = String(Math.max(0, cash));
          buyoutOfferInput.value = String(Math.max(0, Number(currentBuyoutMin || 0)));
          buyoutOfferInput.focus();
        }
        buyoutInputVisible = true;
        return;
      }
      const actorPlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
      const offer = Number((buyoutOfferInput && buyoutOfferInput.value) || currentBuyoutMin || 0);
      const actorPlayer = players.find((p) => Number(p.id || 0) === Number(actorPlayerId || 0));
      const actorCash = Number((actorPlayer && actorPlayer.cash) || 0);
      if (offer < Number(currentBuyoutMin || 0)) {
        showPopup(`Сумма выкупа не может быть меньше ${currentBuyoutMin}`);
        return;
      }
      if (offer > actorCash) {
        showPopup(`Сумма выкупа не может превышать ваш баланс (${actorCash})`);
        return;
      }
      const approved = await askOwnerBuyoutDecision(offer);
      try {
        const ev = await postJson("/api/v1/game/" + gameId + "/commands", {
          action: "buyout",
          as_player_id: actorPlayerId,
          offer_amount: offer,
          approved_by_owner: approved,
          client_msg_id: "buyout_" + Date.now(),
        });
        ingestServerEvent(ev);
        await reloadPropertyState();
        if (buyoutOfferInput) buyoutOfferInput.classList.add("hidden");
        buyoutInputVisible = false;
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
    const activePlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
    const own = propertyState.filter((ps) => Number(ps.owner_player_id || 0) === Number(activePlayerId));
    const others = players.filter((p) => Number(p.id || 0) !== Number(activePlayerId));
    const options = others.map((p) => `<option value="${Number(p.id || 0)}">${escapeHtml(String(p.nickname_snapshot || "Игрок"))}</option>`).join("");
    const ownCards = own.map((ps) => {
      const pos = Number(ps.cell_position || -1);
      const meta = boardMeta[pos] || { name: "#" + pos };
      const hasGroup = meta.group !== null && meta.group !== undefined;
      const colorClass = hasGroup ? groupColorClass[Number(meta.group)] || "" : "asset-card__bar--neutral";
      return `<button type="button" class="asset-card trade-asset-card" data-trade-pos="${pos}" data-trade-side="offer"><span class="asset-card__bar ${colorClass}"></span><span class="asset-card__name">${escapeHtml(meta.name)}</span><span class="trade-check"><input type="checkbox" data-side="offer" value="${pos}"></span></button>`;
    }).join("");
    box.innerHTML = `
      <div class="ledger-row">
        <div class="ledger-row__desc">
          <div class="trade-grid">
            <div>
              <div class="trade-list">${ownCards || "Нет карточек"}</div>
              <input id="trade-cash-offer" class="password-inline" type="number" min="0" step="1" value="" placeholder="Доплата (необязательно)" style="max-width:240px;min-width:200px;height:36px;margin-top:8px">
            </div>
            <div>
              <div class="trade-target-head">
                <strong>Игрок для обмена</strong>
                <select id="trade-target-player" class="password-inline" style="max-width:220px;min-width:180px;height:36px">${options}</select>
              </div>
              <div id="trade-target-cards" class="trade-list"></div>
            </div>
          </div>
          <button id="trade-submit" class="btn btn-outline" style="margin-top:10px">Предложить обмен</button>
        </div>
      </div>
    `;
    const targetSelect = box.querySelector("#trade-target-player");
    const targetCardsWrap = box.querySelector("#trade-target-cards");
    const cashInput = box.querySelector("#trade-cash-offer");
    const bindTradeCardClicks = (root) => {
      root.querySelectorAll(".trade-asset-card").forEach((btn) => {
        if (btn.dataset.bound === "1") return;
        btn.dataset.bound = "1";
        btn.addEventListener("click", (evt) => {
          const cb = btn.querySelector('input[type="checkbox"]');
          if (evt.target.closest("input")) return;
          if (cb) cb.checked = !cb.checked;
          const pos = Number(btn.getAttribute("data-trade-pos") || -1);
          if (pos >= 0) showDeedPopup({ ...(boardMeta[pos] || {}), position: pos }, pos);
        });
      });
    };
    const refreshTargetCards = () => {
      if (!targetCardsWrap || !targetSelect) return;
      const targetId = Number(targetSelect.value || 0);
      const cards = propertyState.filter((ps) => Number(ps.owner_player_id || 0) === targetId).map((ps) => {
        const pos = Number(ps.cell_position || -1);
        const meta = boardMeta[pos] || { name: "#" + pos };
        const hasGroup = meta.group !== null && meta.group !== undefined;
        const colorClass = hasGroup ? groupColorClass[Number(meta.group)] || "" : "asset-card__bar--neutral";
        return `<button type="button" class="asset-card trade-asset-card" data-trade-pos="${pos}" data-trade-side="request"><span class="asset-card__bar ${colorClass}"></span><span class="asset-card__name">${escapeHtml(meta.name)}</span><span class="trade-check"><input type="checkbox" data-side="request" value="${pos}"></span></button>`;
      }).join("");
      targetCardsWrap.innerHTML = cards || "Нет карточек";
      bindTradeCardClicks(targetCardsWrap);
    };
    if (targetSelect) {
      targetSelect.addEventListener("change", refreshTargetCards);
      refreshTargetCards();
    }
    bindTradeCardClicks(box);
    const submitBtn = box.querySelector("#trade-submit");
    if (submitBtn) {
      submitBtn.addEventListener("click", async () => {
        const targetPlayerId = Number((targetSelect && targetSelect.value) || 0);
        const offerPositions = Array.from(box.querySelectorAll('input[data-side="offer"]:checked')).map((el) => Number(el.value || -1)).filter((x) => x >= 0);
        const requestPositions = Array.from(box.querySelectorAll('input[data-side="request"]:checked')).map((el) => Number(el.value || -1)).filter((x) => x >= 0);
        const cashOffer = Math.max(0, Number((cashInput && cashInput.value) || 0));
        if (!targetPlayerId || offerPositions.length === 0 || requestPositions.length === 0) {
          showPopup("Выберите игрока и карточки для обмена");
          return;
        }
        const approved = await askOwnerTradeDecision(targetPlayerId, offerPositions, requestPositions, cashOffer);
        try {
          const ev = await postJson("/api/v1/game/" + gameId + "/commands", {
            action: "trade",
            as_player_id: activePlayerId,
            target_player_id: targetPlayerId,
            offer_positions: offerPositions,
            request_positions: requestPositions,
            cash_offer: cashOffer,
            approved_by_target: approved,
            client_msg_id: "trade_" + Date.now(),
          });
          ingestServerEvent(ev);
          renderTrade();
        } catch (err) {
          showPopup(toRuError(err.message || "Не удалось выполнить обмен"));
        }
      });
    }
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
        if (e.event_type !== "command_buy" && e.event_type !== "command_sell" && e.event_type !== "command_build" && e.event_type !== "command_sell_building" && e.event_type !== "command_mortgage" && e.event_type !== "command_redeem_mortgage" && e.event_type !== "command_trade") continue;
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
          let nextHouses = Number(payload.houses ?? current.houses ?? 0);
          if (e.event_type === "command_sell_building" && payload.sell_type === "hotel" && !Boolean(payload.has_hotel)) {
            nextHouses = Math.max(4, nextHouses);
          }
          propertyState[idx] = {
            ...current,
            houses: nextHouses,
            has_hotel: Boolean(payload.has_hotel ?? current.has_hotel ?? false),
            mortgaged: Boolean(payload.mortgaged ?? current.mortgaged ?? false),
          };
        } else if (e.event_type === "command_mortgage" || e.event_type === "command_redeem_mortgage") {
          const idx = propertyState.findIndex((x) => Number(x.cell_position || -1) === position);
          if (idx < 0) continue;
          propertyState[idx] = { ...propertyState[idx], mortgaged: Boolean(payload.mortgaged) };
        } else if (e.event_type === "command_trade" && payload.trade_approved) {
          const initiator = Number(payload.initiator_player_id || 0);
          const target = Number(payload.target_player_id || 0);
          const offers = Array.isArray(payload.offer_positions) ? payload.offer_positions.map(Number) : [];
          const requests = Array.isArray(payload.request_positions) ? payload.request_positions.map(Number) : [];
          offers.forEach((p) => {
            const idx = propertyState.findIndex((x) => Number(x.cell_position || -1) === p);
            if (idx >= 0) propertyState[idx] = { ...propertyState[idx], owner_player_id: target, mortgaged: false };
          });
          requests.forEach((p) => {
            const idx = propertyState.findIndex((x) => Number(x.cell_position || -1) === p);
            if (idx >= 0) propertyState[idx] = { ...propertyState[idx], owner_player_id: initiator, mortgaged: false };
          });
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
      let nextHouses = Number(payload.houses ?? current.houses ?? 0);
      if (type === "command_sell_building" && payload.sell_type === "hotel" && !Boolean(payload.has_hotel)) {
        nextHouses = Math.max(4, nextHouses);
      }
      propertyState[idx] = {
        ...current,
        houses: nextHouses,
        has_hotel: Boolean(payload.has_hotel ?? current.has_hotel ?? false),
        mortgaged: Boolean(payload.mortgaged ?? current.mortgaged ?? false),
      };
      renderRightPanel();
      return;
    }
    if (type === "command_mortgage" || type === "command_redeem_mortgage") {
      const idx = propertyState.findIndex((x) => Number(x.cell_position || -1) === position);
      if (idx < 0) return;
      propertyState[idx] = {
        ...propertyState[idx],
        mortgaged: Boolean(payload.mortgaged),
      };
      renderRightPanel();
      return;
    }
    if (type === "command_trade" && payload.trade_approved) {
      const initiator = Number(payload.initiator_player_id || 0);
      const target = Number(payload.target_player_id || 0);
      const offers = Array.isArray(payload.offer_positions) ? payload.offer_positions.map(Number) : [];
      const requests = Array.isArray(payload.request_positions) ? payload.request_positions.map(Number) : [];
      offers.forEach((pos) => {
        const idx = propertyState.findIndex((x) => Number(x.cell_position || -1) === pos);
        if (idx >= 0) propertyState[idx] = { ...propertyState[idx], owner_player_id: target, mortgaged: false };
      });
      requests.forEach((pos) => {
        const idx = propertyState.findIndex((x) => Number(x.cell_position || -1) === pos);
        if (idx >= 0) propertyState[idx] = { ...propertyState[idx], owner_player_id: initiator, mortgaged: false };
      });
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
        const stack = document.createElement("span");
        stack.className = "build-icon-stack";
        const icon = document.createElement("span");
        icon.className = "build-icon house";
        icon.style.setProperty("--owner-color", ownerColor);
        const count = document.createElement("span");
        count.className = "build-icon-count";
        count.textContent = String(houses);
        stack.appendChild(icon);
        stack.appendChild(count);
        box.appendChild(stack);
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
      const canQuickBuildHotel = Number(ownerId) === Number(playerIdsInOrder[controlIndex] || selfPlayerId)
        && !hasHotel
        && Number(houses) >= 4
        && canAffordAnyBuild(ownerId, "hotel")
        && getBuildablePositions(ownerId, "hotel").has(pos);
      if (canQuickBuildHotel) {
        const plusHotel = document.createElement("button");
        plusHotel.type = "button";
        plusHotel.className = "build-plus";
        plusHotel.textContent = "+";
        plusHotel.title = "Построить отель";
        plusHotel.addEventListener("click", async (evt) => {
          evt.preventDefault();
          evt.stopPropagation();
          try {
            const ev = await postJson("/api/v1/game/" + gameId + "/commands", {
              action: "build",
              build_type: "hotel",
              position: pos,
              as_player_id: ownerId,
              client_msg_id: "quick_build_hotel_" + pos + "_" + Date.now(),
            });
            ingestServerEvent(ev);
            setBoardStatus(`${playerNameById.get(ownerId) || "Игрок"} построил отель на позиции ${pos}`, 1400);
          } catch (err) {
            showPopup(toRuError(err.message || "Не удалось построить отель"));
          }
        });
        box.appendChild(plusHotel);
      }
      space.appendChild(box);
    });
    updateBoardOwnershipStatuses();
  }

  function updateBoardOwnershipStatuses() {
    document.querySelectorAll("#board .space-owner-status").forEach((el) => el.remove());
    document.querySelectorAll('#board .space[data-pos]').forEach((space) => {
      const pos = Number(space.getAttribute("data-pos") || -1);
      if (pos < 0) return;
      const meta = boardMeta[pos] || {};
      const ownable = Number(meta.price || 0) > 0 || ["property", "railroad", "utility"].includes(String(meta.type || ""));
      if (!ownable) return;
      const state = propertyState.find((ps) => Number(ps.cell_position || -1) === pos);
      const status = document.createElement("div");
      status.className = "space-owner-status";
      const row = space.closest(".row");
      if (row && row.classList.contains("north")) status.classList.add("space-owner-status--north");
      else if (row && row.classList.contains("south")) status.classList.add("space-owner-status--south");
      else if (row && row.classList.contains("west")) status.classList.add("space-owner-status--west");
      else if (row && row.classList.contains("east")) status.classList.add("space-owner-status--east");
      else {
        const cls = space.className || "";
        if (cls.includes(" nw")) status.classList.add("space-owner-status--north");
        else if (cls.includes(" ne")) status.classList.add("space-owner-status--east");
        else if (cls.includes(" sw")) status.classList.add("space-owner-status--west");
        else status.classList.add("space-owner-status--south");
      }
      if (!state || Number(state.owner_player_id || 0) <= 0) {
        status.textContent = "Свободно";
      } else if (Boolean(state.mortgaged)) {
        status.textContent = "Заложено";
      } else {
        status.textContent = playerNameById.get(Number(state.owner_player_id || 0)) || "Куплено";
      }
      space.appendChild(status);
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

  function canSellCard(position) {
    const state = propertyState.find((x) => Number(x.cell_position || -1) === Number(position));
    if (!state) return false;
    const ownerId = Number(state.owner_player_id || 0);
    const activePlayerId = Number(playerIdsInOrder[controlIndex] || selfPlayerId);
    return ownerId > 0 && ownerId === activePlayerId;
  }

  function canMortgageCard(position) {
    const state = propertyState.find((x) => Number(x.cell_position || -1) === Number(position));
    if (!state) return false;
    const ownerId = Number(state.owner_player_id || 0);
    const activePlayerId = Number(playerIdsInOrder[controlIndex] || selfPlayerId);
    return ownerId > 0 && ownerId === activePlayerId && !Boolean(state.mortgaged);
  }

  function canRedeemCard(position) {
    const state = propertyState.find((x) => Number(x.cell_position || -1) === Number(position));
    if (!state) return false;
    const ownerId = Number(state.owner_player_id || 0);
    const activePlayerId = Number(playerIdsInOrder[controlIndex] || selfPlayerId);
    return ownerId > 0 && ownerId === activePlayerId && Boolean(state.mortgaged);
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
          <div class="deed-build-row">
            <span class="deed-build-label">Дома:</span>
            <span class="deed-build-right"><span class="deed-underline">${deedMoney(buildCost)}</span><span class="deed-build-sub">Каждый</span></span>
          </div>
          <div class="deed-build-row">
            <span class="deed-build-label">Отели:</span>
            <span class="deed-build-right"><span class="deed-underline">${deedMoney(buildCost)}</span><span class="deed-build-sub">(плюс 4 дома)</span></span>
          </div>
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

  function renderDeedCardHtml(meta, withCloseButton, position) {
    const isStreet = meta.group !== null && meta.group !== undefined;
    let html = isStreet ? buildStreetDeedHtml(meta, withCloseButton) : buildGenericDeedHtml(meta, withCloseButton);
    if (position !== undefined && canSellCard(position)) {
      if (canRedeemCard(position)) {
        html += `<button type="button" class="deed-redeem-btn" data-redeem-position="${Number(position)}">Выкупить</button>`;
      } else if (canMortgageCard(position)) {
        html += `<button type="button" class="deed-mortgage-btn" data-mortgage-position="${Number(position)}">Заложить</button>`;
      }
      html += `<button type="button" class="deed-sell-btn" data-sell-position="${Number(position)}">Продать</button>`;
    }
    return html;
  }

  function showDeedPopup(meta, position) {
    const wrap = document.querySelector("#space-overlay");
    if (!wrap) return;
    wrap.innerHTML = `<div class="modal-body type-ok modal-body--deed">${renderDeedCardHtml(meta, true, position)}</div>`;
    wrap.classList.remove("hidden", "hide");
    wrap.classList.add("show");
    const close = () => {
      wrap.classList.remove("show");
      wrap.classList.add("hide");
      window.setTimeout(() => wrap.classList.add("hidden"), 180);
    };
    wrap.onclick = async (evt) => {
      if (evt.target === wrap || evt.target.closest(".deed-inline-close")) {
        close();
        return;
      }
      const sellBtn = evt.target.closest(".deed-sell-btn");
      const mortgageBtn = evt.target.closest(".deed-mortgage-btn");
      const redeemBtn = evt.target.closest(".deed-redeem-btn");
      if (mortgageBtn) {
        const mortgagePosition = Number(mortgageBtn.getAttribute("data-mortgage-position") || -1);
        if (mortgagePosition < 0) return;
        try {
          const actorPlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
          const ev = await postJson("/api/v1/game/" + gameId + "/commands", {
            action: "mortgage",
            position: mortgagePosition,
            as_player_id: actorPlayerId,
            client_msg_id: "mortgage_" + mortgagePosition + "_" + Date.now(),
          });
          ingestServerEvent(ev);
          close();
        } catch (err) {
          showPopup(toRuError(err.message || "Не удалось заложить карточку"));
        }
        return;
      }
      if (redeemBtn) {
        const redeemPosition = Number(redeemBtn.getAttribute("data-redeem-position") || -1);
        if (redeemPosition < 0) return;
        try {
          const actorPlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
          const ev = await postJson("/api/v1/game/" + gameId + "/commands", {
            action: "redeem_mortgage",
            position: redeemPosition,
            as_player_id: actorPlayerId,
            client_msg_id: "redeem_" + redeemPosition + "_" + Date.now(),
          });
          ingestServerEvent(ev);
          close();
        } catch (err) {
          showPopup(toRuError(err.message || "Не удалось выкупить карточку из залога"));
        }
        return;
      }
      if (sellBtn) {
        const sellPosition = Number(sellBtn.getAttribute("data-sell-position") || -1);
        if (sellPosition < 0) return;
        const confirmed = window.confirm("Продать карточку со всеми строениями?");
        if (!confirmed) return;
        const actorPlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
        try {
          const ev = await postJson("/api/v1/game/" + gameId + "/commands", {
            action: "sell",
            position: sellPosition,
            as_player_id: actorPlayerId,
            client_msg_id: "sell_card_" + sellPosition + "_" + Date.now(),
          });
          ingestServerEvent(ev);
          close();
        } catch (err) {
          showPopup(toRuError(err.message || "Не удалось продать карточку"));
        }
      }
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
    panel.innerHTML = renderDeedCardHtml(meta, true, Number(meta.position ?? -1));
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
      showDeedPopup({ ...meta, position: pos }, pos);
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

  async function askOwnerTradeDecision(targetPlayerId, offerPositions, requestPositions, cashOffer) {
    const offerNames = offerPositions.map((pos) => (boardMeta[pos] && boardMeta[pos].name) || ("#" + pos));
    const requestNames = requestPositions.map((pos) => (boardMeta[pos] && boardMeta[pos].name) || ("#" + pos));
    const root = document.querySelector("#modal-overlay");
    if (!root) return false;
    const titleEl = root.querySelector(".modal-title");
    const contentEl = root.querySelector(".modal-content");
    const footerEl = root.querySelector(".modal-footer");
    if (!titleEl || !contentEl || !footerEl) return false;
    return new Promise((resolve) => {
      titleEl.textContent = "Предложение обмена";
      const cashPart = Number(cashOffer || 0) > 0 ? ` + ${cashOffer}` : "";
      contentEl.textContent = `${playerNameById.get(playerIdsInOrder[controlIndex] || selfPlayerId) || "Игрок"} предлагает: ${offerNames.join(", ")}${cashPart} за ${requestNames.join(", ")}.`;
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

function rollDiceVisual(d1, d2, actorId) {
  const dieOne = document.querySelector("#dice1");
  const dieTwo = document.querySelector("#dice2");
  if (dieOne) {
    setDiceFace(dieOne, Math.max(1, Math.min(6, d1)));
  }
  if (dieTwo) {
    setDiceFace(dieTwo, Math.max(1, Math.min(6, d2)));
  }
  if (Number(actorId || 0) === Number(document.querySelector(".game-room")?.getAttribute("data-player-id") || 0)) {
    const total = Number(d1 || 0) + Number(d2 || 0);
    const notify = document.querySelector("#game-notify");
    if (notify) {
      notify.hidden = false;
      notify.textContent = `Выпало: ${total}`;
      window.setTimeout(() => { notify.hidden = true; }, 2000);
    }
  }
  animateDiceChaos(d1, d2);
}

function setDiceFace(dieEl, value) {
  const spinsX = 360 * (2 + Math.floor(Math.random() * 3));
  const spinsY = 360 * (2 + Math.floor(Math.random() * 3));
  const base = {
    1: "rotateX(0deg) rotateY(0deg)",
    2: "rotateY(180deg)",
    3: "rotateY(90deg)",
    4: "rotateX(-90deg)",
    5: "rotateX(90deg)",
    6: "rotateY(-90deg)",
  };
  dieEl.style.transform = `rotateX(${spinsX}deg) rotateY(${spinsY}deg) ${base[value] || base[1]}`;
}

function animateDiceChaos(d1, d2) {
  const arena = document.querySelector("#dice-arena");
  const dice = [document.querySelector("#dice1"), document.querySelector("#dice2")].filter(Boolean);
  if (!arena || dice.length === 0) return;
  const b = arena.getBoundingClientRect();
  const size = 100;
  const minX = 0;
  const minY = 0;
  const maxX = Math.max(minX, b.width - size);
  const maxY = Math.max(minY, b.height - size);
  dice.forEach((die, idx) => {
    setDiceFace(die, idx === 0 ? d1 : d2);
    let vx = (Math.random() * 14) - 7;
    let vy = (Math.random() * 14) - 7;
    let x = Math.random() * Math.max(1, (maxX - minX)) + minX;
    let y = Math.random() * Math.max(1, (maxY - minY)) + minY;
    die.style.left = x + "px";
    die.style.top = y + "px";
    let steps = 0;
    const tick = () => {
      x += vx;
      y += vy;
      if (x <= minX || x >= maxX) vx = -vx * 0.88;
      if (y <= minY || y >= maxY) vy = -vy * 0.88;
      x = Math.max(minX, Math.min(maxX, x));
      y = Math.max(minY, Math.min(maxY, y));
      die.style.left = x + "px";
      die.style.top = y + "px";
      steps += 1;
      if (steps < 16) window.setTimeout(tick, 70);
    };
    tick();
  });
}
