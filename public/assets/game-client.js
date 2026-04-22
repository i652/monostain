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
  const processedEventSeq = new Set();
  const financeEventTypes = new Set(["command_pay", "property_bought", "property_sold", "trade_completed", "tax_paid", "rent_paid"]);
  const allEvents = [];
  let sinceSeq = 0;
  let notifyTimer = null;

  const tabs = Array.from(document.querySelectorAll(".game-tab"));
  const panels = Array.from(document.querySelectorAll(".game-panel"));
  const rollAction = document.querySelector(".main-action .action.roll");
  const endAction = document.querySelector(".main-action .action.end");
  const purchaseAction = document.querySelector(".main-action .action.purchase");
  const purchaseName = document.querySelector(".main-action .action.purchase .name");
  const purchaseCost = document.querySelector(".main-action .action.purchase .cost-amount");
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

  function isFinanceEvent(eventObj) {
    if (financeEventTypes.has(String(eventObj.event_type || ""))) return true;
    const payload = parsePayload(eventObj);
    return Number(payload.amount || 0) > 0;
  }

  function renderFinanceLedger() {
    const box = document.querySelector("#ledger-box");
    if (!box) return;
    box.innerHTML = "";
    allEvents
      .filter(isFinanceEvent)
      .sort((a, b) => Number(b.event_seq || 0) - Number(a.event_seq || 0))
      .forEach((eventObj) => {
        const row = document.createElement("div");
        row.className = "ledger-row";
        row.innerHTML = `<span class="ledger-row__title">${escapeHtml(actorName(eventObj))}</span><span class="ledger-row__desc">${escapeHtml(describeEvent(eventObj))}</span><span class="ledger-row__meta">${escapeHtml(formatTimestampRu(eventObj.created_at || ""))}</span>`;
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
      return `${actorName(eventObj)} перевёл ${payload.amount} игроку #${payload.to_player_id}`;
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
    events.forEach(appendTimeline);
    renderFinanceLedger();
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
      const payload = typeof e.payload_json === "string" ? JSON.parse(e.payload_json) : e.payload_json;
      const actorId = Number(e.actor_player_id || 0);
      const chip = document.querySelector('.player-chip[data-player-id="' + actorId + '"]');
      if (!chip || !payload) return;
      const spans = chip.querySelectorAll("span");
      if (spans[0]) spans[0].textContent = "💰 " + payload.cash;
      if (spans[1]) spans[1].textContent = "📍 " + payload.position;
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
        if (rollAction) rollAction.classList.add("hidden");
        if (endAction) endAction.classList.add("hidden");
        if (purchaseAction) purchaseAction.classList.remove("hidden");
        if (purchaseName) purchaseName.textContent = String(payload.space_name || "");
        if (purchaseCost) purchaseCost.textContent = String(Number(payload.price || 0));
      } else {
        if (purchaseAction) purchaseAction.classList.add("hidden");
        if (rollAction) rollAction.classList.add("hidden");
        if (endAction) endAction.classList.remove("hidden");
      }
      const fromPos = Number(payload.from_position ?? payload.position ?? 0);
      const toPos = Number(payload.position || 0);
      animateMove(actorId, fromPos, toPos);
    });
    events.forEach((e) => {
      if (e.event_type !== "command_end_turn") return;
      if (rollAction) rollAction.classList.remove("hidden");
      if (endAction) endAction.classList.add("hidden");
      if (purchaseAction) purchaseAction.classList.add("hidden");
      if (playerIdsInOrder.length > 0) {
        controlIndex = (controlIndex + 1) % playerIdsInOrder.length;
        renderRightPanel();
      }
    });
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
        await postJson("/api/v1/game/" + gameId + "/commands", payload);
        if (action === "buy" || action === "sell") {
          await reloadPropertyState();
        }
      } catch (err) {
        if (action === "roll" && rollAction) rollAction.classList.remove("hidden");
        showPopup(toRuError(err.message || "Не удалось выполнить команду"));
      }
    });
  });

  const purchaseBtn = document.querySelector(".purchase-property");
  if (purchaseBtn) {
    purchaseBtn.addEventListener("click", async () => {
      const actorPlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
      try {
        await postJson("/api/v1/game/" + gameId + "/commands", {
          action: "buy",
          as_player_id: actorPlayerId,
          client_msg_id: "buy_" + Date.now(),
        });
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
      box.textContent = "Сделок пока нет.";
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
      const token = document.createElement("div");
      token.className = `token color-${idx % 8}`;
      token.dataset.playerId = String(Number(p.id || 0));
      board.appendChild(token);
      setTokenPosition(Number(p.id || 0), Number(p.position || 0));
    });
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
      window.setTimeout(tick, 140);
    };
    tick();
  }

  function markActiveToken() {
    document.querySelectorAll(".token.active").forEach((el) => el.classList.remove("active"));
    const activePlayerId = playerIdsInOrder[controlIndex] || selfPlayerId;
    const token = document.querySelector(`.token[data-player-id="${activePlayerId}"]`);
    if (token) token.classList.add("active");
    const turnLabel = document.querySelector("#turn-player-label");
    if (turnLabel) turnLabel.textContent = playerNameById.get(activePlayerId) || "—";
  }

  async function reloadPropertyState() {
    try {
      const res = await fetch("/api/v1/game/" + gameId + "/events?since=0", { credentials: "same-origin" });
      const data = await res.json();
      const events = Array.isArray(data.events) ? data.events : [];
      for (const e of events) {
        if (e.event_type !== "command_buy" && e.event_type !== "command_sell") continue;
        const payload = parsePayload(e);
        const position = Number(payload.position || -1);
        if (position < 0) continue;
        if (e.event_type === "command_sell") {
          for (let i = propertyState.length - 1; i >= 0; i--) {
            if (Number(propertyState[i].cell_position || -1) === position) {
              propertyState.splice(i, 1);
            }
          }
        } else {
          const ownerId = Number(payload.buyer_player_id || 0);
          const idx = propertyState.findIndex((x) => Number(x.cell_position || -1) === position);
          const next = { cell_position: position, owner_player_id: ownerId };
          if (idx >= 0) propertyState[idx] = next;
          else propertyState.push(next);
        }
      }
      renderRightPanel();
    } catch (_) {}
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
        const el = document.createElement("span");
        el.className = "asset-card";
        el.textContent = "#" + String(pos);
        assets.appendChild(el);
      });
    });
    markActiveToken();
  }

  refreshFromEvents((() => {
    const box = document.querySelector("#ledger-box");
    try {
      return JSON.parse((box && box.getAttribute("data-initial-events")) || "[]");
    } catch (_) {
      return [];
    }
  })());
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
  pollLoop();
})();
