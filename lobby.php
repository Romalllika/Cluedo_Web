<?php require 'includes/config.php';
require_auth();
require 'includes/maps.php';
require 'includes/map_settings.php';
require 'includes/reports.php';
require 'includes/profile.php';
require 'includes/invites.php';
require 'includes/notifications.php';
require 'includes/progression.php';
require 'includes/reconnect.php';

update_current_user_presence();
$uid = current_user_id();

$accountXp = get_user_account_xp((int) $uid);
$levelProgress = account_level_progress($accountXp);
$dailyTasks = get_daily_tasks((int) $uid);

$activeGames = get_user_active_games((int) $uid);

$incomingGameInvites = get_incoming_game_invites((int) $uid);

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';

unset($_SESSION['flash_success'], $_SESSION['flash_error']);
// Автоудаление пустых ожидающих лобби
// db()->query("DELETE g FROM games g LEFT JOIN game_players gp ON gp.game_id=g.id WHERE g.status='waiting' AND gp.id IS NULL");
$u = db()->prepare('SELECT *, ROUND(IF(games_played=0,0,wins/games_played*100),1) AS wr FROM users WHERE id=?');
$u->execute([$uid]);
$me = $u->fetch();
$maps = selectable_maps_for_user((int) $uid);
$selectedMap = reset($maps) ?: null;
$games = db()->query("SELECT g.*, u.username owner, COUNT(gp.id) players FROM games g JOIN users u ON u.id=g.owner_id LEFT JOIN game_players gp ON gp.game_id=g.id WHERE g.status<>'finished' GROUP BY g.id ORDER BY g.created_at DESC")->fetchAll();
$leaders = db()->query("SELECT username,wins,losses,games_played,ROUND(IF(games_played=0,0,wins/games_played*100),1) wr FROM users ORDER BY wr DESC,wins DESC LIMIT 10")->fetchAll();
?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Лобби</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
    <header class="top"><b>🕵️ Mystery Mansion</b>
        <nav>
            <a href="lobby.php">Лобби</a>
            <a href="players.php">Игроки</a>
            <a href="map_tutorial.php">Создать карту</a>
            <a href="map_submit.php">Отправить карту</a>
            <a href="profile.php">Мой профиль</a>
            <?php if (user_is_moderator_or_admin()): ?>
                <a href="admin/index.php">Админка</a>
            <?php endif; ?>

            <a href="logout.php">Выход</a>
        </nav>
    </header>
    <main class="layout">
        <?php if ($flashSuccess): ?>
            <section class="panel flash flash-success">
                <?= h($flashSuccess) ?>
            </section>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <section class="panel flash flash-error">
                <?= h($flashError) ?>
            </section>
        <?php endif; ?>
        <section class="panel hero tasks-hero">
            <div class="tasks-hero-main">
                <p class="muted-label">Прогресс аккаунта</p>
                <h1>Уровень <?= (int) $levelProgress['level'] ?></h1>
                <p>Выполняй ежедневные задания, чтобы повышать уровень аккаунта и открывать будущие режимы.</p>

                <div class="level-progress">
                    <div class="level-progress-top">
                        <span><?= (int) $levelProgress['into_level'] ?> / <?= (int) $levelProgress['needed_for_next'] ?>
                            XP</span>
                        <span><?= (int) $levelProgress['percent'] ?>%</span>
                    </div>
                    <div class="level-progress-bar">
                        <div style="width: <?= (int) $levelProgress['percent'] ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="daily-tasks">
                <div class="section-head">
                    <h2>Ежедневные задания</h2>
                    <small><?= date('d.m.Y') ?></small>
                </div>

                <?php foreach ($dailyTasks as $task): ?>
                    <?php
                    $progress = (int) $task['progress'];
                    $target = (int) $task['target'];
                    $done = $progress >= $target;
                    $claimed = (int) $task['is_claimed'] === 1;
                    ?>

                    <article class="daily-task-card <?= $done ? 'task-complete' : '' ?>">
                        <div class="daily-task-info">
                            <strong><?= h($task['title']) ?></strong>
                            <small><?= h($task['description']) ?></small>

                            <div class="task-mini-progress">
                                <span><?= $progress ?>/<?= $target ?></span>
                                <div>
                                    <i
                                        style="width: <?= min(100, (int) round(($progress / max(1, $target)) * 100)) ?>%"></i>
                                </div>
                            </div>
                        </div>

                        <div class="daily-task-reward">
                            <span>+<?= (int) $task['xp_reward'] ?> XP</span>

                            <?php if ($claimed): ?>
                                <b>Получено</b>
                            <?php elseif ($done): ?>
                                <form action="task_action.php" method="post">
                                    <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                                    <button class="btn small" type="submit">Забрать</button>
                                </form>
                            <?php else: ?>
                                <b>В процессе</b>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php if ($activeGames): ?>
            <section class="panel">
                <div class="section-head">
                    <h2>Ваши активные матчи</h2>
                    <small><?= count($activeGames) ?> активных</small>
                </div>

                <div class="reconnect-list">
                    <?php foreach ($activeGames as $activeGame): ?>
                        <?php
                        $isMyTurn = (int) ($activeGame['current_turn_player_id'] ?? 0) === (int) $uid;
                        ?>
                        <article class="reconnect-card <?= $isMyTurn ? 'my-turn' : '' ?>">
                            <div class="reconnect-main">
                                <div class="reconnect-title">
                                    <strong><?= h($activeGame['title']) ?></strong>

                                    <span
                                        class="status-pill <?= $activeGame['status'] === 'active' ? 'status-confirmed' : 'status-reviewing' ?>">
                                        <?= h(game_status_human($activeGame['status'])) ?>
                                    </span>

                                    <?php if ($isMyTurn): ?>
                                        <span class="status-pill status-open">Ваш ход</span>
                                    <?php endif; ?>
                                </div>

                                <small>
                                    Карта: <?= h($activeGame['map_id']) ?> ·
                                    Персонаж: <?= h($activeGame['character_name']) ?> ·
                                    Игроков: <?= (int) $activeGame['players_count'] ?> ·
                                    Фаза: <?= h(game_phase_human($activeGame['phase'])) ?>
                                </small>
                            </div>

                            <a class="btn" href="game.php?id=<?= (int) $activeGame['id'] ?>">
                                Вернуться
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
        <section class="panel" id="incomingInvitesPanel" style="<?= $incomingGameInvites ? '' : 'display:none' ?>">
            <div class="section-head">
                <h2>Входящие приглашения</h2>
                <small id="incomingInvitesCount"><?= count($incomingGameInvites) ?> активных</small>
            </div>

            <div class="invite-list" id="incomingInvitesList">
                <?php foreach ($incomingGameInvites as $invite): ?>
                    <article class="invite-card">
                        <div>
                            <h3><?= h($invite['game_title']) ?></h3>
                            <p>
                                <?= h($invite['sender_username']) ?>
                                приглашает вас в матч.
                            </p>
                            <small>
                                Карта: <?= h($invite['map_id']) ?> ·
                                Игроков: <?= (int) $invite['players_count'] ?>/<?= (int) $invite['max_players'] ?> ·
                                Истекает: <?= h($invite['expires_at'] ?: '—') ?>
                            </small>

                            <?php if (!empty($invite['message'])): ?>
                                <p class="muted-text"><?= h($invite['message']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="invite-actions">
                            <form action="invite_action.php" method="post">
                                <input type="hidden" name="action" value="accept">
                                <input type="hidden" name="invite_id" value="<?= (int) $invite['id'] ?>">
                                <button class="btn" type="submit">Принять</button>
                            </form>

                            <form action="invite_action.php" method="post">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="invite_id" value="<?= (int) $invite['id'] ?>">
                                <button class="danger-btn" type="submit">Отклонить</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="grid2">
            <div class="panel">
                <h2>Создать игру</h2>
                <form action="create_game.php" method="post" class="create">
                    <input name="title" placeholder="Название матча" value="Матч <?= date('H:i') ?>">

                    <select name="max">
                        <option>3</option>
                        <option>4</option>
                        <option>5</option>
                        <option selected>6</option>
                    </select>

                    <input type="hidden" name="map_id" id="selectedMapInput" value="<?= h($selectedMap['id'] ?? '') ?>">

                    <div class="map-picker-field">
                        <div class="selected-map-card" id="selectedMapCard">
                            <?php if ($selectedMap): ?>
                                <strong id="selectedMapTitle"><?= h($selectedMap['title']) ?></strong>
                                <span id="selectedMapMeta">
                                    <?= h($selectedMap['category_label']) ?> ·
                                    <?= (int) $selectedMap['meta']['rooms_count'] ?> комнат ·
                                    до <?= (int) $selectedMap['meta']['players_count'] ?> игроков
                                </span>
                            <?php else: ?>
                                <strong id="selectedMapTitle">Нет доступных карт</strong>
                                <span id="selectedMapMeta">Администратор должен включить хотя бы одну карту.</span>
                            <?php endif; ?>
                        </div>

                        <button type="button" class="btn" id="openMapPickerBtn" <?= !$maps ? 'disabled' : '' ?>>
                            Выбрать карту
                        </button>
                    </div>

                    <button <?= !$maps ? 'disabled' : '' ?>>Создать</button>
                </form>
            </div>
            <div class="panel">
                <h2>Топ игроков</h2>
                <table><?php foreach ($leaders as $l): ?>
                        <tr>
                            <td><?= h($l['username']) ?></td>
                            <td><?= $l['wr'] ?>%</td>
                            <td><?= $l['wins'] ?>W / <?= $l['losses'] ?>L</td>
                        </tr><?php endforeach; ?>
                </table>
            </div>
            <div class="modal" id="mapPickerModal">
                <div class="modal-box map-picker-modal">
                    <div class="modal-head">
                        <h2>Выбор карты</h2>
                        <button type="button" id="closeMapPickerBtn">×</button>
                    </div>

                    <div class="map-picker-grid">
                        <?php foreach ($maps as $map): ?>
                            <article class="map-choice-card" data-map-id="<?= h($map['id']) ?>"
                                data-map-title="<?= h($map['title']) ?>"
                                data-map-meta="<?= h($map['category_label'] . ' · ' . (int) $map['meta']['rooms_count'] . ' комнат · до ' . (int) $map['meta']['players_count'] . ' игроков') ?>">
                                <div class="map-choice-top">
                                    <strong><?= h($map['title']) ?></strong>
                                    <span class="status-pill <?= h(map_category_class($map['category'])) ?>">
                                        <?= h($map['category_label']) ?>
                                    </span>
                                </div>

                                <?php if (!empty($map['description'])): ?>
                                    <p><?= h($map['description']) ?></p>
                                <?php else: ?>
                                    <p>Карта для партии Mystery Mansion.</p>
                                <?php endif; ?>

                                <small>
                                    <?= (int) $map['meta']['rooms_count'] ?> комнат ·
                                    до <?= (int) $map['meta']['players_count'] ?> игроков ·
                                    <?= (int) $map['meta']['board_w'] ?>×<?= (int) $map['meta']['board_h'] ?>
                                </small>

                                <button type="button" class="btn small">Выбрать</button>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
        <section class="panel">
            <h2>Активные и ожидающие матчи</h2>
            <div class="cards" id="lobbyGames">
                <?php foreach ($games as $g): ?>
                    <article class="game-card">
                        <h3><?= h($g['title']) ?></h3>
                        <p>Создатель: <?= h($g['owner']) ?></p>
                        <p class="badge <?= h($g['status']) ?>">
                            <?= $g['status'] === 'waiting' ? 'Ожидает игроков' : 'Идёт игра' ?> ·
                            <?= (int) $g['players'] ?>/<?= (int) $g['max_players'] ?>
                        </p>
                        <a class="btn" href="game.php?id=<?= (int) $g['id'] ?>">Открыть</a>
                    </article>
                <?php endforeach; ?>

                <?php if (!$games): ?>
                    <p>Пока матчей нет. Создай первый.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <script>
        async function loadLobbyGames() {
            const box = document.querySelector('#lobbyGames');

            if (!box) {
                return;
            }

            try {
                const res = await fetch('lobby_state.php', {
                    cache: 'no-store'
                });

                const data = await res.json();

                if (!data.games) {
                    return;
                }
                renderIncomingInvites(data.incoming_game_invites || []);
                if (data.games.length === 0) {
                    box.innerHTML = `
                    <div class="empty-state">
                        <h3>Пока нет активных лобби</h3>
                        <p>Создай новую игру, чтобы другие игроки могли присоединиться.</p>
                    </div>
                `;
                    return;
                }

                box.innerHTML = data.games.map(g => {
                    const isWaiting = g.status === 'waiting';

                    return `
                        <article class="game-card">
                            <h3>${escapeHtml(g.title || 'Матч')}</h3>

                            <p>Создатель: ${escapeHtml(g.owner_name || g.owner || '—')}</p>

                            <p>Карта: <b>${escapeHtml(g.map_title || 'Классический особняк')}</b></p>

                            <p class="badge ${escapeHtml(g.status || '')}">
                            ${isWaiting ? 'Ожидает игроков' : 'Идёт игра'} ·
                            ${Number(g.players_count || g.players || 0)} / ${Number(g.max_players || 0)}
                            </p>

                            <a class="btn" href="game.php?id=${Number(g.id)}">Открыть</a>
                        </article>
                    `;
                }).join('');
            } catch (e) {
                console.error('Lobby refresh failed', e);
            }
        }

        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        loadLobbyGames();
        setInterval(loadLobbyGames, 2000);
        function renderIncomingInvites(invites) {
            const panel = document.querySelector('#incomingInvitesPanel');
            const list = document.querySelector('#incomingInvitesList');
            const count = document.querySelector('#incomingInvitesCount');

            if (!panel || !list || !count) {
                return;
            }

            if (!Array.isArray(invites) || invites.length === 0) {
                panel.style.display = 'none';
                list.innerHTML = '';
                count.textContent = '0 активных';
                return;
            }

            panel.style.display = '';
            count.textContent = `${invites.length} активных`;

            list.innerHTML = invites.map(invite => `
        <article class="invite-card">
            <div>
                <h3>${escapeHtml(invite.game_title || 'Матч')}</h3>
                <p>${escapeHtml(invite.sender_username || 'Игрок')} приглашает вас в матч.</p>
                <small>
                    Карта: ${escapeHtml(invite.map_id || '—')} ·
                    Игроков: ${Number(invite.players_count || 0)}/${Number(invite.max_players || 0)} ·
                    Истекает: ${escapeHtml(invite.expires_at || '—')}
                </small>

                ${invite.message ? `<p class="muted-text">${escapeHtml(invite.message)}</p>` : ''}
            </div>

            <div class="invite-actions">
                <form action="invite_action.php" method="post">
                    <input type="hidden" name="action" value="accept">
                    <input type="hidden" name="invite_id" value="${Number(invite.id)}">
                    <button class="btn" type="submit">Принять</button>
                </form>

                <form action="invite_action.php" method="post">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="invite_id" value="${Number(invite.id)}">
                    <button class="danger-btn" type="submit">Отклонить</button>
                </form>
            </div>
        </article>
    `).join('');
        }

        const mapPickerModal = document.querySelector('#mapPickerModal');
        const openMapPickerBtn = document.querySelector('#openMapPickerBtn');
        const closeMapPickerBtn = document.querySelector('#closeMapPickerBtn');
        const selectedMapInput = document.querySelector('#selectedMapInput');
        const selectedMapTitle = document.querySelector('#selectedMapTitle');
        const selectedMapMeta = document.querySelector('#selectedMapMeta');

        if (openMapPickerBtn && mapPickerModal) {
            openMapPickerBtn.addEventListener('click', () => {
                mapPickerModal.classList.add('show');
            });
        }

        if (closeMapPickerBtn && mapPickerModal) {
            closeMapPickerBtn.addEventListener('click', () => {
                mapPickerModal.classList.remove('show');
            });
        }

        if (mapPickerModal) {
            mapPickerModal.addEventListener('click', (e) => {
                if (e.target === mapPickerModal) {
                    mapPickerModal.classList.remove('show');
                }
            });

            mapPickerModal.querySelectorAll('.map-choice-card').forEach(card => {
                card.addEventListener('click', () => {
                    const mapId = card.dataset.mapId;
                    const mapTitle = card.dataset.mapTitle;
                    const mapMeta = card.dataset.mapMeta;

                    if (selectedMapInput) selectedMapInput.value = mapId || '';
                    if (selectedMapTitle) selectedMapTitle.textContent = mapTitle || 'Карта';
                    if (selectedMapMeta) selectedMapMeta.textContent = mapMeta || '';

                    mapPickerModal.classList.remove('show');
                });
            });
        }
    </script>
    <?php render_notification_mount(); ?>
</body>

</html>