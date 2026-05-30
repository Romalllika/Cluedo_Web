<?php
require 'includes/config.php';
require_auth();
require 'includes/maps.php';
require 'includes/movement.php';
require 'includes/profile.php';
require 'includes/friends.php';
require 'includes/invites.php';
require 'includes/notifications.php';
require 'includes/reports.php';

update_current_user_presence();

$gid = (int) ($_GET['id'] ?? 0);
$st = db()->prepare('SELECT g.*, u.username owner FROM games g JOIN users u ON u.id=g.owner_id WHERE g.id=?');
$st->execute([$gid]);
$game = $st->fetch();
if (!$game)
    die('Игра не найдена');
$uid = current_user_id();
$joined = db()->prepare('SELECT * FROM game_players WHERE game_id=? AND user_id=?');
$joined->execute([$gid, $uid]);
$mePlayer = $joined->fetch();
if ($game['status'] === 'active' && !$mePlayer && !user_is_moderator_or_admin((int) $uid)) {
    http_response_code(403);
    echo 'Вы не участвуете в этом активном матче.';
    exit;
}
$players = db()->prepare('SELECT gp.*,u.username FROM game_players gp JOIN users u ON u.id=gp.user_id WHERE gp.game_id=? ORDER BY gp.turn_order');
$players->execute([$gid]);
$players = $players->fetchAll();
$taken = array_column($players, 'seat_no');

$onlineFriendsForInvite = [];

if ($game['status'] === 'waiting' && $mePlayer) {
    $onlineFriendsForInvite = get_online_friends_for_game_invite((int) $uid, (int) $gid);
}
?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h($game['title']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body data-game="<?= $gid ?>">
    <header class="top"><b>🎲 <?= h($game['title']) ?></b>
        <nav>
            <?php if ($game['status'] === 'waiting' && $mePlayer): ?>
                <form action="leave_lobby.php" method="post" class="inline-form">
                    <input type="hidden" name="game_id" value="<?= $gid ?>">
                    <button class="link-button" type="submit">Покинуть лобби</button>
                </form>
            <?php endif; ?>

            <a href="lobby.php">← Лобби</a>
            <a href="players.php">Игроки</a>
            <a href="profile.php">Мой профиль</a>
            <a href="logout.php">Выход</a>
        </nav>
    </header>
    <main class="game-layout">
        <aside class="panel side">
            <h2>Игроки</h2>
            <div id="players"></div>

            <?php if ($game['status'] === 'waiting'): ?>
                <h3 id="seatsTitle">Места / персонажи</h3>
                <div class="seats" id="seats"></div>
            <?php endif; ?>

            <div id="myCards" class="mycards"></div>
        </aside>
        <section class="panel board-wrap">
            <div class="toolbar">
                <div><b id="turnLabel">Загрузка...</b>
                    <p id="phaseLabel"></p>
                    <div id="afkTimer" class="afk-timer"></div>
                </div>
                <div class="actions">
                    <button id="startBtn">Старт</button>
                    <?php if ($game['status'] === 'waiting' && $mePlayer): ?>
                        <button id="inviteFriendsBtn" type="button">Пригласить</button>
                    <?php endif; ?>
                    <button id="rollBtn">Бросить кубики</button>
                    <button id="secretPassageBtn">Секретный проход</button>
                    <button id="suggestBtn">Предложение</button>
                    <button id="accuseBtn">Обвинение</button>
                    <button id="endBtn">Конец хода</button>
                    <button id="surrenderBtn" class="danger-btn">Сдаться</button>
                </div>
            </div>
            <div class="dice" id="dice"><span>?</span><span>?</span></div>
            <div class="mansion-shell"><canvas id="mansionCanvas"></canvas>
                <!-- <div class="canvas-hint">Клик по подсвеченному коридору или комнате — ход. В комнату можно войти только
                    если хватает очков кубика.</div> -->
            </div>
        </section>
        <aside class="panel side">
            <h2>Журнал</h2>
            <div id="log" class="log"></div>
        </aside>
        <button class="notebook-tab" id="notebookTab">Блокнот</button>
        <aside class="notebook-drawer" id="notebookDrawer">
            <div class="notebook-head">
                <h2>Детективный блокнот</h2><button id="closeNotebook" type="button">×</button>
            </div>
            <div class="notebook-paper">
                <div id="notes" class="notes"></div>
                <div class="shown-history">
                    <h3>Мне показывали</h3>
                    <div id="shownHistory"></div>
                </div>
            </div>
        </aside>
    </main>
    <?php if ($game['status'] === 'waiting' && $mePlayer): ?>
        <template id="inviteFriendsTemplate">
            <div class="invite-friends-modal">
                <?php if (!$onlineFriendsForInvite): ?>
                    <p>Сейчас нет друзей онлайн, которых можно пригласить в этот матч.</p>
                <?php else: ?>
                    <div class="friend-list">
                        <?php foreach ($onlineFriendsForInvite as $friend): ?>
                            <article class="friend-card">
                                <div>
                                    <strong><?= h($friend['username']) ?></strong>
                                    <small><?= h(profile_online_label($friend['last_seen_at'] ?? null)) ?></small>
                                </div>

                                <?php if (!empty($friend['pending_invite_id'])): ?>
                                    <span class="status-pill status-reviewing">Уже приглашён</span>
                                <?php else: ?>
                                    <form action="invite_action.php" method="post">
                                        <input type="hidden" name="action" value="send">
                                        <input type="hidden" name="game_id" value="<?= (int) $gid ?>">
                                        <input type="hidden" name="receiver_user_id" value="<?= (int) $friend['id'] ?>">
                                        <input type="hidden" name="profile_user_id" value="<?= (int) $friend['id'] ?>">
                                        <button class="btn small" type="submit">Пригласить</button>
                                    </form>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </template>
    <?php endif; ?>
    <div id="modal" class="modal">
        <div class="modal-box"><button class="x" onclick="closeModal()">×</button>
            <h2 id="modalTitle"></h2>
            <div id="modalBody"></div>
        </div>
    </div>
    <?php render_notification_mount(); ?>
    <script>window.CURRENT_USER_ID = <?= $uid ?>;</script>
    <script src="assets/game.js"></script>
</body>

</html>