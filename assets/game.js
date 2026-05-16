const gid = document.body.dataset.game;
let state = null;
let afkLocalTimer = null;
let afkDeadlineTs = null;
let afkTimerKey = null;
let lastDisprovePromptKey = null;
let lastShownNoticeKey = null;
let endGameShown = false;
let cardsRenderedOnce = false;
let lastCardsKey = '';

const $ = s => document.querySelector(s);
const canvas = $('#mansionCanvas');
const ctx = canvas ? canvas.getContext('2d') : null;
const meta = { cell: 40, ox: 0, oy: 0, clickable: [] };

const cardImagePrefKey = 'cluedo.showCardImages';

function shouldShowCardImages() {
  return localStorage.getItem(cardImagePrefKey) !== '0';
}

function setShowCardImages(value) {
  localStorage.setItem(cardImagePrefKey, value ? '1' : '0');
}

function cardLabel(card) {
  return card?.title || card?.card_name || card?.legacy_name || card?.name || '';
}

function cardLegacyName(card) {
  return card?.legacy_name || card?.card_name || card?.title || '';
}

function cardId(card) {
  return card?.id || card?.card_id || '';
}

function cardsOfType(type) {
  if (!state) {
    return [];
  }

  if (type === 'suspect') {
    return state.suspectCards || [];
  }

  if (type === 'weapon') {
    return state.weaponCards || [];
  }

  if (type === 'room') {
    return state.roomCards || [];
  }

  return [];
}

function cardOption(card) {
  return `<option value="${escapeHtml(cardId(card))}" data-name="${escapeHtml(cardLegacyName(card))}">
    ${escapeHtml(cardLabel(card))}
  </option>`;
}

function selectedCardPayload(select, legacyKey, idKey) {
  const option = select.options[select.selectedIndex];

  return {
    [legacyKey]: option ? option.dataset.name : select.value,
    [idKey]: select.value
  };
}

function api(action, data = {}) {
  const fd = new FormData();
  fd.append('action', action);
  fd.append('game_id', gid);
  Object.entries(data).forEach(([k, v]) => fd.append(k, v));
  return fetch('api.php', { method: 'POST', body: fd }).then(r => r.json());
}
function closeModal() { $('#modal').classList.remove('show'); }
function openModal(title, html) { $('#modalTitle').textContent = title; $('#modalBody').innerHTML = html; $('#modal').classList.add('show'); }
let fatalGameErrorShown = false;

async function refresh() {
  try {
    state = await api('state');
  } catch (e) {
    console.warn('Refresh failed', e);
    return;
  }

  if (!state) {
    return;
  }

  if (state.error) {
    if (!fatalGameErrorShown) {
      fatalGameErrorShown = true;

      showErrorNotification(state.error);

      if (
        state.error === 'Игра не найдена' ||
        state.error === 'Вы не состоите в этой игре'
      ) {
        window.location.href = 'lobby.php';
      }
    }

    return;
  }

  render();
}

function formatTimeLeft(seconds) {
  const safe = Math.max(0, Number(seconds) || 0);
  const mm = String(Math.floor(safe / 60)).padStart(2, '0');
  const ss = String(safe % 60).padStart(2, '0');

  return `${mm}:${ss}`;
}

function stopAfkTimer() {
  if (afkLocalTimer) {
    clearInterval(afkLocalTimer);
    afkLocalTimer = null;
  }

  afkDeadlineTs = null;
  afkTimerKey = null;

  const box = $('#afkTimer');

  if (box) {
    box.textContent = '';
    box.style.display = 'none';
  }
}

function startAfkTimer(limit, age, phase) {
  const box = $('#afkTimer');

  if (!box) {
    return;
  }

  const startedAt = state.game.phase_started_at || '';
  const newKey = `${state.game.id}|${phase}|${startedAt}|${limit}`;

  /**
   * ВАЖНО:
   * Если это та же самая фаза, не пересоздаём таймер.
   * Иначе он будет каждые 2.5 секунды возвращаться на 03:00.
   */
  if (afkTimerKey === newKey && afkLocalTimer) {
    return;
  }

  afkTimerKey = newKey;

  if (afkLocalTimer) {
    clearInterval(afkLocalTimer);
    afkLocalTimer = null;
  }

  const safeLimit = Number(limit) || 0;
  const safeAge = Number(age) || 0;
  const left = Math.max(0, safeLimit - safeAge);

  afkDeadlineTs = Date.now() + left * 1000;

  const label = phase === 'disprove'
    ? 'Автопоказ карты через'
    : 'AFK до автопропуска';

  const tick = () => {
    const secondsLeft = Math.max(
      0,
      Math.ceil((afkDeadlineTs - Date.now()) / 1000)
    );

    box.style.display = 'inline-flex';
    box.textContent = `${label}: ${formatTimeLeft(secondsLeft)}`;

    if (secondsLeft <= 0) {
      clearInterval(afkLocalTimer);
      afkLocalTimer = null;

      setTimeout(refresh, 1000);
    }
  };

  tick();
  afkLocalTimer = setInterval(tick, 1000);
}

function render() {
  const g = state.game, ps = state.players;
  const current = ps.find(p => +p.user_id === +g.current_turn_player_id);

  $('#turnLabel').textContent =
    g.status === 'waiting' ? 'Ожидание старта' : g.status === 'finished' ? 'Игра завершена' : 'Ход: ' + (current ? current.username + ' / ' + current.character_name : '-');

  const phaseNames = {
    join: 'ожидание',
    roll: 'бросок кубиков',
    move: 'движение',
    suggest: 'предположение',
    disprove: 'опровержение',
    accuse: 'обвинение / завершение хода',
    ended: 'конец игры'
  };

  let phaseText =
    'Фаза: ' + (phaseNames[g.phase] || g.phase) +
    ' · кубики: ' + (g.dice_total || 0) +
    ' · карта: ' + (state.board.mapTitle || ('Вариант ' + (state.board.variant + 1)));

  $('#phaseLabel').textContent = phaseText;

  // Включаем таймер AFK
  if (g.status === 'active') {
    const afkLimit =
      g.phase === 'disprove'
        ? state.afkDisproveSeconds
        : state.afkTurnSeconds;

    startAfkTimer(
      afkLimit,
      state.phaseAge || 0,
      g.phase
    );
  } else {
    stopAfkTimer();
  }


  $('#startBtn').style.display = g.status === 'waiting' ? 'inline-flex' : 'none';
  ['rollBtn', 'secretPassageBtn', 'suggestBtn', 'accuseBtn', 'endBtn'].forEach(id => {
    const btn = $('#' + id);

    if (!btn) {
      return;
    }

    btn.style.display =
      (+g.current_turn_player_id === +CURRENT_USER_ID && g.status === 'active')
        ? 'inline-flex'
        : 'none';
  });

  const surrenderBtn = $('#surrenderBtn');
  if (surrenderBtn) {
    surrenderBtn.style.display = g.status === 'active' ? 'inline-flex' : 'none';
  }

  $('#rollBtn').disabled = g.phase !== 'roll';

  const secretPassageBtn = $('#secretPassageBtn');

  if (secretPassageBtn) {
    const me = state.players.find(p => +p.user_id === +CURRENT_USER_ID);
    const currentRoom = me ? roomAt(+me.pos_x, +me.pos_y) : null;
    const hasSecretPassage =
      currentRoom &&
      state.board.rooms[currentRoom] &&
      state.board.rooms[currentRoom].secret;

    secretPassageBtn.disabled = !(
      g.phase === 'roll' &&
      +g.current_turn_player_id === +CURRENT_USER_ID &&
      hasSecretPassage
    );

    secretPassageBtn.title = hasSecretPassage
      ? `Перейти: ${currentRoom} → ${state.board.rooms[currentRoom].secret}`
      : 'Секретный проход доступен только из угловой комнаты';
  }

  $('#suggestBtn').disabled = g.phase !== 'suggest';
  $('#accuseBtn').disabled = !['accuse', 'suggest', 'move'].includes(g.phase);
  $('#endBtn').disabled = g.phase === 'disprove';

  renderPlayersAndSeats();
  renderCanvas();
  renderCards();
  renderLog();
  renderNotes();
  renderShownHistory();
  renderDisproveFlow();
  renderEndGameFlow();
}

function renderDisproveFlow() {
  const g = state.game;

  if (g.phase !== 'disprove') {
    lastDisprovePromptKey = null;
  }

  if (g.phase === 'disprove' && state.pending) {
    const p = state.pending;

    if (+p.disprover_id === +CURRENT_USER_ID) {
      const key = [
        p.suggester_id,
        p.disprover_id,
        p.suspect,
        p.weapon,
        p.room
      ].join('|');

      if (lastDisprovePromptKey !== key) {
        lastDisprovePromptKey = key;
        openShowCardModal(p);
      }
    }
  }

  if (state.shownNotice) {
    const key = state.shownNotice.by + '|' + state.shownNotice.card;

    if (lastShownNoticeKey !== key) {
      lastShownNoticeKey = key;
      addShownHistory(state.shownNotice.card, state.shownNotice.by);
      openModal(
        'Карта показана',
        `
          <div class="result-box">
            <p>Игрок <b>${state.shownNotice.by}</b> опроверг ваше предположение.</p>
            <div class="big-card">${state.shownNotice.card}</div>
            <button id="shownOk">Понятно</button>
          </div>
        `
      );

      $('#shownOk').onclick = closeModal;
    }
  }
}

function renderEndGameFlow() {
  const g = state.game;

  if (g.status !== 'finished' && g.phase !== 'ended') {
    return;
  }

  if (endGameShown) {
    return;
  }

  endGameShown = true;

  const winner = state.players.find(p => +p.user_id === +g.winner_user_id);
  const meWon = +g.winner_user_id === +CURRENT_USER_ID;

  const title = meWon
    ? 'Победа!'
    : 'Игра завершена';

  const message = winner
    ? `Победитель: <b>${winner.username}</b> / ${winner.character_name}`
    : 'Игра завершена без победителя.';

  openModal(
    title,
    `
      <div class="result-box">
        <div class="big-card">
          ${meWon ? 'Вы выиграли расследование!' : 'Расследование завершено'}
        </div>

        <p>${message}</p>

        ${meWon
      ? '<p>Ваш винрейт и статистика побед обновлены.</p>'
      : '<p>Ваш результат засчитан в статистику партии.</p>'
    }

        <button id="finishOk">Понятно</button>
      </div>
    `
  );

  $('#finishOk').onclick = () => {
    window.location.href = 'lobby.php';
  };
}

function openShowCardModal(p) {
  const cards = p.myMatchingCards || [];

  if (!cards.length) {
    return;
  }

  openModal(
    'Покажите карту',
    `
      <div class="result-box">
        <p>
          Игрок <b>${p.suggester_name}</b> сделал предположение:
        </p>

        <div class="suggestion-line">
          <span>${p.suspect}</span>
          <span>${p.weapon}</span>
          <span>${p.room}</span>
        </div>

        <p>У вас есть подходящие карты. Выберите одну, которую хотите показать.</p>

        <div class="show-card-list">
          ${cards.map(c => `
            <button
              class="show-card-choice"
              data-card="${escapeHtml(c.card_name)}"
              data-card-id="${escapeHtml(c.card_id || '')}"
            >
              <b>${escapeHtml(c.title || c.card_name)}</b>
              <small>${escapeHtml(c.card_type)}</small>
            </button>
          `).join('')}
        </div>
      </div>
    `
  );

  document.querySelectorAll('.show-card-choice').forEach(btn => {
    btn.onclick = async () => {
      const r = await api('showCard', {
        card: btn.dataset.card,
        card_id: btn.dataset.cardId || ''
      });

      if (r.error) {
        showErrorNotification(r.error);
        return;
      }

      closeModal();
      refresh();
    };
  });
}

function renderPlayersAndSeats() {
  const playersBox = $('#players');

  if (playersBox) {
    playersBox.innerHTML = state.players.map(p => `
      <div class="player ${+p.user_id === +state.game.current_turn_player_id ? 'current-player' : ''}">
        <b>${p.character_name}</b>
        <span>${p.username}${+p.is_eliminated ? ' · выбыл' : ''}</span>
      </div>
    `).join('');
  }

  const seatsBox = $('#seats');
  const seatsTitle = $('#seatsTitle');

  if (!seatsBox) {
    return;
  }

  if (state.game.status !== 'waiting') {
    seatsBox.innerHTML = '';

    if (seatsTitle) {
      seatsTitle.style.display = 'none';
    }

    return;
  }

  if (seatsTitle) {
    seatsTitle.style.display = '';
  }

  const taken = new Map();

  state.players.forEach(p => {
    taken.set(+p.seat_no, p);
  });

  const meInGame = state.players.some(p => +p.user_id === +CURRENT_USER_ID);

  seatsBox.innerHTML = state.characters.map((c, i) => {
    const p = taken.get(i);

    if (p) {
      const mine = +p.user_id === +CURRENT_USER_ID;

      return `
        <div class="seat busy ${mine ? 'mine' : ''}">
          <b>${c.name}</b>
          <span>${mine ? 'вы выбрали' : 'занял ' + p.username}</span>
        </div>
      `;
    }

    const href = meInGame
      ? `change_seat.php?game_id=${gid}&seat=${i}`
      : `join_game.php?game_id=${gid}&seat=${i}`;

    return `
      <a class="seat" href="${href}">
        <b>${c.name}</b>
        <span>${meInGame ? 'сменить' : 'занять'}</span>
      </a>
    `;
  }).join('');
}

function fitCanvas() {
  const box = canvas.parentElement.getBoundingClientRect();

  const cssW = Math.max(840, Math.floor(box.width));
  const cssH = Math.max(760, Math.floor(Math.min(window.innerHeight - 165, cssW * 0.82)));

  const ratio = window.devicePixelRatio || 1;

  canvas.style.width = cssW + 'px';
  canvas.style.height = cssH + 'px';

  canvas.width = Math.floor(cssW * ratio);
  canvas.height = Math.floor(cssH * ratio);

  ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
}
function cellToPx(x, y) { return { x: meta.ox + x * meta.cell, y: meta.oy + y * meta.cell }; }
function roundRect(x, y, w, h, r) { ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r); ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath(); }
function roomAt(x, y) { for (const [name, r] of Object.entries(state.board.rooms)) { if (x >= r.x1 && x <= r.x2 && y >= r.y1 && y <= r.y2) return name; } return null; }
function reachableCell(x, y) { return (state.reachable || []).find(r => +r.x === +x && +r.y === +y && r.type !== 'room'); }
function reachableRoom(name) { return (state.reachable || []).find(r => r.type === 'room' && r.room === name && r.reachable); }
function colorForChar(name) { const c = (state.characters || []).find(x => x.name === name); return c ? c.color : '#f5c542'; }

function renderCanvas() {
  if (!canvas || !ctx || !state) {
    return;
  }

  fitCanvas();
  meta.clickable = [];

  const b = state.board;
  const w = canvas.clientWidth;
  const h = canvas.clientHeight;

  /**
   * Так как поле стало 12x13, клетки станут крупнее.
   */
  meta.cell = Math.min((w - 54) / b.width, (h - 54) / b.height);
  meta.ox = (w - meta.cell * b.width) / 2;
  meta.oy = (h - meta.cell * b.height) / 2;

  const bg = ctx.createLinearGradient(0, 0, w, h);
  bg.addColorStop(0, '#23140d');
  bg.addColorStop(.42, '#725239');
  bg.addColorStop(1, '#120c09');

  ctx.fillStyle = bg;
  ctx.fillRect(0, 0, w, h);

  drawWood(w, h);
  drawCorridors();
  drawRooms();
  drawDoors();
  // drawStarts();
  drawPlayers();
}
function drawWood(w, h) { ctx.save(); ctx.globalAlpha = .13; for (let y = 0; y < h; y += 34) { ctx.fillStyle = y % 68 === 0 ? '#fff0bd' : '#0e0704'; ctx.fillRect(0, y, w, 2); } ctx.restore(); }
function drawCorridors() {
  const c = meta.cell;
  const paths = state.board.paths || [];

  for (const path of paths) {
    const x = +path[0];
    const y = +path[1];

    const p = cellToPx(x, y);
    const reach = reachableCell(x, y);

    ctx.fillStyle = reach
      ? 'rgba(92,255,161,.42)'
      : 'rgba(255,231,185,.18)';

    ctx.strokeStyle = reach
      ? 'rgba(92,255,161,.95)'
      : 'rgba(255,231,185,.16)';

    roundRect(
      p.x + 4,
      p.y + 4,
      c - 8,
      c - 8,
      Math.max(10, c * 0.20)
    );

    ctx.fill();
    ctx.stroke();

    if (reach) {
      ctx.save();
      ctx.fillStyle = '#fff4cd';
      ctx.beginPath();
      ctx.arc(
        p.x + c / 2,
        p.y + c / 2,
        Math.max(4, c * 0.075),
        0,
        Math.PI * 2
      );
      ctx.fill();
      ctx.restore();

      meta.clickable.push({
        x,
        y,
        px: p.x,
        py: p.y,
        w: c,
        h: c
      });
    }
  }
}
function drawRooms() {
  const themes = { kitchen: ['#5c4734', '#c6a06f'], ballroom: ['#432654', '#d4b3ff'], greenhouse: ['#244832', '#a8e6a3'], dining: ['#56291f', '#d79772'], billiard: ['#1d4c43', '#83d8c3'], library: ['#3b2217', '#d0a071'], lounge: ['#4f2930', '#e59ca7'], hall: ['#484034', '#d8c7a1'], study: ['#26344c', '#a7b9e8'] };
  const c = meta.cell;
  for (const [name, r] of Object.entries(state.board.rooms)) {
    const p = cellToPx(r.x1, r.y1), rw = (r.x2 - r.x1 + 1) * c, rh = (r.y2 - r.y1 + 1) * c;
    const cols = themes[r.theme] || ['#333', '#aaa'];
    const g = ctx.createLinearGradient(p.x, p.y, p.x + rw, p.y + rh); g.addColorStop(0, cols[0]); g.addColorStop(1, cols[1]);
    ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.48)'; ctx.shadowBlur = 18; ctx.shadowOffsetY = 10; roundRect(p.x + 4, p.y + 4, rw - 8, rh - 8, 22); ctx.fillStyle = g; ctx.fill(); ctx.lineWidth = 4; ctx.strokeStyle = 'rgba(255,238,198,.62)'; ctx.stroke(); ctx.restore();
    ctx.save(); ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.font = '800 ' + Math.max(15, Math.min(22, c * .46)) + 'px Inter, Arial'; ctx.fillStyle = 'rgba(0,0,0,.34)'; ctx.fillText(name, p.x + rw / 2 + 2, p.y + rh / 2 + 2); ctx.fillStyle = '#fff7d9'; ctx.fillText(name, p.x + rw / 2, p.y + rh / 2); ctx.restore();
    if (reachableRoom(name)) { ctx.save(); ctx.lineWidth = 6; ctx.strokeStyle = 'rgba(92,255,161,.96)'; roundRect(p.x + 10, p.y + 10, rw - 20, rh - 20, 18); ctx.stroke(); ctx.restore(); meta.clickable.push({ x: Math.floor((r.x1 + r.x2) / 2), y: Math.floor((r.y1 + r.y2) / 2), px: p.x, py: p.y, w: rw, h: rh }); }
  }
}
function drawDoors() {
  const c = meta.cell;

  ctx.save();

  for (const [, r] of Object.entries(state.board.rooms)) {
    const d = r.door || r.doors[0];
    const p = cellToPx(d[0], d[1]);

    ctx.fillStyle = '#f5c542';
    ctx.strokeStyle = '#2b1600';
    ctx.lineWidth = Math.max(2, c * 0.06);

    /**
     * Дверь рисуем не как отдельную клетку поля,
     * а как метку на краю комнаты.
     */
    roundRect(
      p.x + c * 0.26,
      p.y + c * 0.26,
      c * 0.48,
      c * 0.48,
      Math.max(6, c * 0.14)
    );

    ctx.fill();
    ctx.stroke();

    ctx.fillStyle = '#2b1600';
    ctx.font = '900 ' + Math.max(13, c * 0.34) + 'px Inter, Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('В', p.x + c / 2, p.y + c / 2);
  }

  ctx.restore();
} function drawStarts() { const c = meta.cell; for (const s of state.board.starts || []) { const p = cellToPx(s.x, s.y); ctx.fillStyle = s.color || '#fff'; ctx.beginPath(); ctx.arc(p.x + c / 2, p.y + c / 2, c * .23, 0, Math.PI * 2); ctx.fill(); ctx.strokeStyle = 'rgba(255,255,255,.85)'; ctx.lineWidth = 3; ctx.stroke(); } }
function drawPlayers() {
  const c = meta.cell;
  const positions = state.characterPositions || [];

  const grouped = {};

  positions.forEach(p => {
    const key = p.pos_x + ':' + p.pos_y;

    if (!grouped[key]) {
      grouped[key] = [];
    }

    grouped[key].push(p);
  });

  Object.values(grouped).forEach(list => {
    list.forEach((p, i) => {
      const base = cellToPx(+p.pos_x, +p.pos_y);

      const angle = (Math.PI * 2 / Math.max(1, list.length)) * i;
      const offset = list.length > 1 ? c * 0.20 : 0;

      const x = base.x + c / 2 + Math.cos(angle) * offset;
      const y = base.y + c / 2 + Math.sin(angle) * offset;

      const isOwned = !!p.owner_user_id;
      const isCurrent =
        isOwned &&
        +p.owner_user_id === +state.game.current_turn_player_id;

      ctx.save();

      if (!isOwned) {
        ctx.globalAlpha = 0.55;
      }

      ctx.shadowColor = 'rgba(0,0,0,.5)';
      ctx.shadowBlur = 12;
      ctx.shadowOffsetY = 4;

      ctx.fillStyle = p.color || colorForChar(p.character_name);

      ctx.beginPath();
      ctx.arc(x, y, c * 0.30, 0, Math.PI * 2);
      ctx.fill();

      ctx.lineWidth = isCurrent ? 5 : 2;
      ctx.strokeStyle = isCurrent ? '#fff' : '#211207';
      ctx.stroke();

      ctx.fillStyle = '#111';
      ctx.font = '900 ' + Math.max(13, c * 0.34) + 'px Inter, Arial';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';

      ctx.fillText(
        p.character_name ? p.character_name[0] : '?',
        x,
        y
      );

      ctx.restore();
    });
  });
}
if (canvas) { canvas.addEventListener('click', async e => { if (!state || +state.game.current_turn_player_id !== +CURRENT_USER_ID || state.game.phase !== 'move') return; const r = canvas.getBoundingClientRect(); const x = e.clientX - r.left, y = e.clientY - r.top; const t = meta.clickable.find(a => x >= a.px && x <= a.px + a.w && y >= a.py && y <= a.py + a.h); if (!t) return; const res = await api('move', { x: t.x, y: t.y }); if (res.error) return showErrorNotification(res.error); refresh(); }); }
function renderCards() {
  const showImages = shouldShowCardImages();

  const cardsKey = state.myCards
    .map(c => c.card_type + ':' + (c.card_id || c.card_name) + ':' + (showImages ? 'img' : 'text'))
    .join('|');
  /**
   * Если набор карт не изменился — вообще не перерисовываем.
   * Это убирает постоянное повторение flip-анимации при refresh().
   */
  if (cardsRenderedOnce && cardsKey === lastCardsKey) {
    return;
  }

  lastCardsKey = cardsKey;

  const shouldAnimate = !cardsRenderedOnce && state.myCards.length > 0;

  $('#myCards').innerHTML =
    `<h3>Мои карты</h3>
    <label class="card-image-toggle">
      <input type="checkbox" id="toggleCardImages" ${showImages ? 'checked' : ''}>
      <span>Картинки карточек</span>
    </label>` +
    state.myCards.map(c => {
      const label = escapeHtml(c.title || c.card_name);
      const image = c.image || null;
      const imageHtml = showImages && image
        ? `<img class="card-img" src="${escapeHtml(image)}" alt="${label}" loading="lazy">`
        : '';

      return `
      <div class="card ${shouldAnimate ? 'flip' : ''}">
        ${imageHtml}
        <b>${label}</b>
        <small>${escapeHtml(c.card_type)}</small>
      </div>
    `;
    }).join('');

  const toggle = $('#toggleCardImages');

  if (toggle) {
    toggle.onchange = () => {
      setShowCardImages(toggle.checked);
      lastCardsKey = '';
      renderCards();
    };
  }

  cardsRenderedOnce = true;
}
function renderLog() { $('#log').innerHTML = state.logs.map(l => `<p><b>${l.username || 'Система'}:</b> ${l.message}</p>`).join(''); $('#log').scrollTop = 99999; }
function renderNotes() {
  const all = [
    ['Подозреваемые', state.suspects],
    ['Оружие', state.weapons],
    ['Комнаты', state.roomNames]
  ];

  const checkKey = 'mansion-notes-' + gid;
  const textKey = 'mansion-note-texts-' + gid;

  const saved = JSON.parse(localStorage.getItem(checkKey) || '{}');
  const textSaved = JSON.parse(localStorage.getItem(textKey) || '{}');

  $('#notes').innerHTML = all.map(([title, list]) => `
    <section class="note-section">
      <h3>${title}</h3>

      ${list.map(n => `
        <div class="note-row">
          <label class="note-check">
            <input 
              type="checkbox" 
              data-note="${n}" 
              ${saved[n] ? 'checked' : ''}
            >
            <span>${n}</span>
          </label>

          <span
            class="note-mark"
            contenteditable="true"
            data-note-text="${n}"
            data-placeholder="пометка"
          >${escapeHtml(textSaved[n] || '')}</span>
        </div>
      `).join('')}
    </section>
  `).join('');

  $('#notes').querySelectorAll('input[type="checkbox"]').forEach(input => {
    input.onchange = () => {
      saved[input.dataset.note] = input.checked;
      localStorage.setItem(checkKey, JSON.stringify(saved));
    };
  });

  $('#notes').querySelectorAll('.note-mark').forEach(el => {
    el.oninput = () => {
      textSaved[el.dataset.noteText] = el.textContent.trim();
      localStorage.setItem(textKey, JSON.stringify(textSaved));
    };
  });
}
function shownHistoryKey() {
  return 'mansion-shown-history-' + gid + '-' + CURRENT_USER_ID;
}
function escapeAttr(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('"', '&quot;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');
}
function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}
function loadShownHistory() {
  try {
    return JSON.parse(localStorage.getItem(shownHistoryKey()) || '[]');
  } catch (e) {
    return [];
  }
}

function saveShownHistory(items) {
  localStorage.setItem(shownHistoryKey(), JSON.stringify(items));
}

function addShownHistory(card, by) {
  const items = loadShownHistory();

  const exists = items.some(x => x.card === card && x.by === by);

  if (!exists) {
    items.unshift({
      card,
      by,
      time: new Date().toLocaleTimeString()
    });
  }

  saveShownHistory(items.slice(0, 20));
}

function renderShownHistory() {
  const box = $('#shownHistory');

  if (!box) {
    return;
  }

  const items = loadShownHistory();

  if (!items.length) {
    box.innerHTML = '<p class="muted">Пока никто не показывал вам карты.</p>';
    return;
  }

  box.innerHTML = items.map(item => `
    <div class="shown-history-item">
      <b>${item.card}</b>
      <span>${item.by} · ${item.time}</span>
    </div>
  `).join('');
}
$('#startBtn').onclick = async () => { const r = await api('start'); if (r.error) showErrorNotification(r.error); refresh(); };
$('#rollBtn').onclick = async () => { const dice = $('#dice'); dice.classList.add('shake'); const r = await api('roll'); setTimeout(() => dice.classList.remove('shake'), 700); if (r.error) return showErrorNotification(r.error); dice.innerHTML = `<span>${r.d1}</span><span>${r.d2}</span>`; refresh(); };
const secretPassageBtn = $('#secretPassageBtn');

if (secretPassageBtn) {
  secretPassageBtn.onclick = async () => {
    const r = await api('secretPassage');

    if (r.error) {
      showErrorNotification(r.error);
      return;
    }

    const dice = $('#dice');

    if (dice) {
      dice.innerHTML = '<span>↘</span><span>↖</span>';
    }

    refresh();
  };
}
$('#endBtn').onclick = async () => { const r = await api('endTurn'); if (r.error) showErrorNotification(r.error); refresh(); };
const surrenderBtn = $('#surrenderBtn');

if (surrenderBtn) {
  surrenderBtn.onclick = async () => {
    if (!confirm('Вы точно хотите сдаться? После сдачи вы больше не сможете ходить.')) {
      return;
    }

    const r = await api('surrender');

    if (r.error) {
      showErrorNotification(r.error);
      return;
    }

    if (r.redirect) {
      window.location.href = r.redirect;
      return;
    }

    if (r.finished) {
      refresh();
      return;
    }

    showModalNotification('Вы сдались.');
    refresh();
  };
}
$('#suggestBtn').onclick = () => selectTriple('Сделать предложение', false); $('#accuseBtn').onclick = () => selectTriple('Финальное обвинение', true);
function selectTriple(title, accuse) {
  const me = state.players.find(p => +p.user_id === +CURRENT_USER_ID);
  const myCharacter = me ? me.character_name : null;

  const suspectOptions = cardsOfType('suspect')
    .filter(c => accuse || cardLegacyName(c) !== myCharacter)
    .map(cardOption)
    .join('');

  const weaponOptions = cardsOfType('weapon')
    .map(cardOption)
    .join('');

  const roomSelect = accuse
    ? `<select id="mRoom">${cardsOfType('room').map(cardOption).join('')}</select>`
    : '<p>Комната берётся автоматически по текущей комнате фишки.</p>';

  openModal(
    title,
    `
      <div class="formgrid">
        <select id="mSus">
          ${suspectOptions}
        </select>

        <select id="mWeap">
          ${weaponOptions}
        </select>

        ${roomSelect}

        <button id="mSend">Подтвердить</button>
      </div>
    `
  );

  $('#mSend').onclick = async () => {
    const susPayload = selectedCardPayload($('#mSus'), 'suspect', 'suspect_id');
    const weapPayload = selectedCardPayload($('#mWeap'), 'weapon', 'weapon_id');

    const data = {
      ...susPayload,
      ...weapPayload
    };

    if (accuse) {
      Object.assign(data, selectedCardPayload($('#mRoom'), 'room', 'room_id'));
    }

    if (accuse) {
      openModal(
        'Подтвердите обвинение',
        `
          <div class="result-box">
            <p>Вы собираетесь сделать финальное обвинение:</p>

            <div class="suggestion-line">
              <span>${data.suspect}</span>
              <span>${data.weapon}</span>
              <span>${data.room}</span>
            </div>

            <p><b>Важно:</b> если обвинение неверное, вы выбываете из расследования.</p>

            <div class="modal-actions">
              <button id="confirmAccuseBtn" class="danger-btn">Да, обвинить</button>
              <button id="cancelAccuseBtn">Отмена</button>
            </div>
          </div>
        `
      );

      $('#cancelAccuseBtn').onclick = () => {
        closeModal();
        selectTriple('Финальное обвинение', true);
      };

      $('#confirmAccuseBtn').onclick = async () => {
        const r = await api('accuse', data);
        handleTripleResult(r, true);
      };

      return;
    }

    const r = await api('suggest', data);
    handleTripleResult(r, false);
  };
}
function handleTripleResult(r, accuse) {
  if (r.error) {
    showErrorNotification(r.error);
    return;
  }

  if (!accuse && r.autoShown && r.shown) {
    addShownHistory(r.shown.card, r.shown.by);
    openModal(
      'Карта показана автоматически',
      `
        <div class="result-box">
          <p>Предположение было опровергнуто картой выбывшего игрока.</p>
          <p>Игрок: <b>${r.shown.by}</b></p>
          <div class="big-card">${r.shown.card}</div>

          <div class="modal-actions">
            <button id="autoShownAccuse">Сделать обвинение</button>
            <button id="autoShownEnd">Завершить ход</button>
          </div>
        </div>
      `
    );

    $('#autoShownAccuse').onclick = () => {
      closeModal();
      selectTriple('Финальное обвинение', true);
    };

    $('#autoShownEnd').onclick = async () => {
      const end = await api('endTurn');

      if (end.error) {
        showErrorNotification(end.error);
        return;
      }

      closeModal();
      refresh();
    };

    refresh();
    return;
  }

  if (!accuse && r.needsDisprove) {
    openModal(
      'Ожидание опровержения',
      `
        <div class="result-box">
          <p>Персонаж <b>${r.movedSuspect}</b> перемещён в комнату.</p>
          <p>Игрок <b>${r.disprover}</b> должен выбрать карту для показа.</p>
        </div>
      `
    );

    refresh();
    return;
  }

  if (!accuse) {
    openModal(
      'Никто не опроверг',
      `
        <div class="result-box">
          <p>Персонаж <b>${r.movedSuspect}</b> перемещён в комнату.</p>
          <p>Никто из игроков не смог показать карту.</p>
          <p>Теперь вы можете сделать финальное обвинение или завершить ход.</p>

          <div class="modal-actions">
            <button id="afterSuggestAccuse">Сделать обвинение</button>
            <button id="afterSuggestEnd">Завершить ход</button>
          </div>
        </div>
      `
    );

    $('#afterSuggestAccuse').onclick = () => {
      closeModal();
      selectTriple('Финальное обвинение', true);
    };

    $('#afterSuggestEnd').onclick = async () => {
      const end = await api('endTurn');

      if (end.error) {
        showErrorNotification(end.error);
        return;
      }

      closeModal();
      refresh();
    };

    refresh();
    return;
  }

  if (accuse) {
    openModal(
      r.win ? 'Победа!' : 'Обвинение неверное',
      `
        <div class="result-box">
          <div class="big-card">
            ${r.win ? 'Вы раскрыли дело!' : 'Вы ошиблись и выбыли из расследования.'}
          </div>
          <button id="accuseOk">Понятно</button>
        </div>
      `
    );

    $('#accuseOk').onclick = () => {
      closeModal();
      refresh();
    };

    refresh();
    return;
  }
}
// модалки для уведомлений и ошибках
function showModalNotification(title, message, callback) {
  openModal(
    title,
    `
      <div class="result-box">
        <p>${message}</p>
        <button id="modalOk">Понятно</button>
      </div>
    `
  );

  $('#modalOk').onclick = () => {
    closeModal();
    if (callback) callback();
  };
}
function showErrorNotification(message) {
  openModal(
    'Ошибка',
    `
      <div class="result-box">
        <p>${message}</p>
        <button id="modalErrorOk">Закрыть</button>
      </div>
    `
  );

  $('#modalErrorOk').onclick = closeModal;
}
const notebookTab = $('#notebookTab'), notebookDrawer = $('#notebookDrawer'), closeNotebook = $('#closeNotebook'); if (notebookTab) notebookTab.onclick = () => notebookDrawer.classList.add('open'); if (closeNotebook) closeNotebook.onclick = () => notebookDrawer.classList.remove('open');
window.addEventListener('resize', () => { if (state) renderCanvas(); });
refresh(); setInterval(refresh, 2500);
