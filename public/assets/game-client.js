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
    if (typeof window.uiAlert === "function") {
      await window.uiAlert(message);
      return;
    }
    console.log(message);
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
  let sinceSeq = 0;

  const tabs = Array.from(document.querySelectorAll(".game-tab"));
  const panels = Array.from(document.querySelectorAll(".game-panel"));
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
    const line = document.createElement("div");
    line.className = "timeline-row";
    const ts = formatTimestampRu(eventObj.created_at || "");
    line.textContent = `${ts} · #${eventObj.event_seq} · ${eventTypeRu(eventObj.event_type)}`;
    box.appendChild(line);
  }

  function describeEvent(eventObj) {
    const payload = typeof eventObj.payload_json === "string"
      ? JSON.parse(eventObj.payload_json || "{}")
      : (eventObj.payload_json || {});
    if (eventObj.event_type === "command_roll") {
      const d = payload.dice || [0, 0];
      return `Бросок ${d[0]} + ${d[1]}, позиция ${payload.position}, баланс ${payload.cash}`;
    }
    if (eventObj.event_type === "command_pay") {
      return `Перевод ${payload.amount} игроку #${payload.to_player_id}`;
    }
    if (eventObj.event_type === "chat_sent") {
      return "Сообщение в чат";
    }
    if (eventObj.event_type === "invite_created") {
      return "Создано приглашение";
    }
    if (eventObj.event_type === "bot_added") {
      return `Добавлен ${payload.bot_name || "бот"}`;
    }
    return eventTypeRu(eventObj.event_type);
  }

  function refreshLedger(events) {
    const box = document.querySelector("#ledger-box");
    if (!box) return;
    let parsed = [];
    try {
      parsed = JSON.parse(box.getAttribute("data-initial-events") || "[]");
    } catch (_) {}
    parsed = parsed.concat(events);
    box.setAttribute("data-initial-events", JSON.stringify(parsed));
    box.innerHTML = "";
    parsed.forEach((eventObj) => {
      const row = document.createElement("div");
      row.className = "ledger-row";
      const left = document.createElement("div");
      left.className = "ledger-row__title";
      left.textContent = eventTypeRu(eventObj.event_type);
      const right = document.createElement("div");
      right.className = "ledger-row__meta";
      right.textContent = formatTimestampRu(eventObj.created_at || "");
      const desc = document.createElement("div");
      desc.className = "ledger-row__desc";
      desc.textContent = describeEvent(eventObj);
      row.appendChild(left);
      row.appendChild(right);
      row.appendChild(desc);
      box.appendChild(row);
    });
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
        refreshLedger(events);
        refreshPlayers(events);
        events.forEach(appendTimeline);
      }
    } catch (_) {
      // no-op: polling should continue even after transient errors
    } finally {
      setTimeout(pollLoop, 800);
    }
  }

  document.querySelectorAll(".js-game-command").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const action = btn.getAttribute("data-action");
      if (!action) return;
      const clientMsgId = "m_" + Date.now() + "_" + Math.random().toString(16).slice(2);
      try {
        await postJson("/api/v1/game/" + gameId + "/commands", {
          action: action,
          client_msg_id: clientMsgId,
        });
      } catch (err) {
        showPopup(err.message || "Не удалось выполнить команду");
      }
    });
  });

  const chatForm = document.querySelector("#chat-form");
  if (chatForm) {
    chatForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const fd = new FormData(chatForm);
      const message = (fd.get("message") || "").toString().trim();
      if (!message) return;
      try {
        await postJson("/api/v1/game/" + gameId + "/chat", { message: message });
        chatForm.reset();
      } catch (err) {
        showPopup(err.message || "Не удалось отправить сообщение");
      }
    });
  }

  const inviteLinkBtn = document.querySelector("#game-invite-link");
  if (inviteLinkBtn) {
    inviteLinkBtn.addEventListener("click", async () => {
      try {
        const invite = await postJson("/api/v1/game/" + gameId + "/invites", {});
        const link = window.location.origin + "/game/invites?token=" + encodeURIComponent(invite.token);
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(link);
          await showPopup("Ссылка приглашения скопирована");
        } else {
          await showPopup("Не удалось скопировать автоматически. Ссылка: " + link);
        }
      } catch (err) {
        await showPopup("Не удалось скопировать ссылку приглашения. Разрешите доступ к буферу обмена или скопируйте вручную.");
      }
    });
  }

  const inviteNickBtn = document.querySelector("#game-invite-nick");
  if (inviteNickBtn) {
    inviteNickBtn.addEventListener("click", async () => {
      let nickname = null;
      if (typeof window.uiPrompt === "function") {
        nickname = await window.uiPrompt("Введите никнейм пользователя:");
      }
      if (!nickname) return;
      try {
        await postJson("/api/v1/game/" + gameId + "/invites", { nickname: nickname });
        await showPopup("Приглашение отправлено");
      } catch (err) {
        await showPopup(err.message || "Не удалось отправить приглашение");
      }
    });
  }

  refreshLedger([]);
  pollLoop();
})();
