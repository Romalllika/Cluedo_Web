<?php

require 'includes/config.php';
require 'includes/profile.php';
require 'includes/reports.php';
require 'includes/friends.php';
require 'includes/players.php';
require 'includes/notifications.php';

require_auth();
update_current_user_presence();

$viewerId = (int) current_user_id();
$isModerator = user_is_moderator_or_admin($viewerId);

$q = trim((string) ($_GET['q'] ?? ''));
$players = search_players($q, $viewerId);

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';

unset($_SESSION['flash_success'], $_SESSION['flash_error']);

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Игроки · Mystery Mansion</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
<header class="top">
    <b>👥 Игроки</b>
    <nav>
        <a href="lobby.php">Лобби</a>
        <a href="players.php">Игроки</a>
        <a href="profile.php">Мой профиль</a>

        <?php if ($isModerator): ?>
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

    <section class="panel hero">
        <h1>Поиск игроков</h1>
        <p>Найди игрока по нику, открой профиль, добавь в друзья и позже пригласи в матч.</p>

        <form class="player-search-form" action="players.php" method="get">
            <input
                name="q"
                value="<?= h($q) ?>"
                placeholder="Введите ник игрока"
                autocomplete="off"
            >
            <button type="submit">Найти</button>

            <?php if ($q !== ''): ?>
                <a class="btn" href="players.php">Сбросить</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="panel">
        <div class="section-head">
            <h2><?= $q === '' ? 'Недавно активные игроки' : 'Результаты поиска' ?></h2>
            <small><?= count($players) ?> найдено</small>
        </div>

        <?php if (!$players): ?>
            <p>Игроки не найдены.</p>
        <?php else: ?>
            <div class="players-directory">
                <?php foreach ($players as $player): ?>
                    <?php
                        $relation = get_friend_relation($viewerId, (int) $player['id']);
                        $winRate = player_win_rate($player);
                    ?>

                    <article class="player-directory-card">
                        <div class="player-directory-main">
                            <a href="profile.php?id=<?= (int) $player['id'] ?>">
                                <h3><?= h($player['username']) ?></h3>
                            </a>

                            <div class="profile-badges">
                                <span class="status-pill <?= h(profile_online_class($player['last_seen_at'] ?? null)) ?>">
                                    <?= h(profile_online_label($player['last_seen_at'] ?? null)) ?>
                                </span>

                                <?php if ($relation['type'] === 'friends'): ?>
                                    <span class="status-pill status-confirmed">В друзьях</span>
                                <?php elseif ($relation['type'] === 'outgoing_pending'): ?>
                                    <span class="status-pill status-reviewing">Заявка отправлена</span>
                                <?php elseif ($relation['type'] === 'incoming_pending'): ?>
                                    <span class="status-pill status-reviewing">Есть входящая заявка</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="player-directory-stats">
                            <span>Матчей: <?= (int) $player['games_played'] ?></span>
                            <span>Побед: <?= (int) $player['wins'] ?></span>
                            <span>Винрейт: <?= (int) $winRate ?>%</span>
                            <span>Друзей: <?= (int) $player['friends_count'] ?></span>
                            <span>Общих матчей: <?= (int) $player['common_matches'] ?></span>
                        </div>

                        <div class="player-directory-actions">
                            <a class="btn small" href="profile.php?id=<?= (int) $player['id'] ?>">Профиль</a>

                            <?php if ($relation['type'] === 'none'): ?>
                                <form action="friend_action.php" method="post">
                                    <input type="hidden" name="action" value="send">
                                    <input type="hidden" name="profile_user_id" value="<?= (int) $player['id'] ?>">
                                    <button class="btn small" type="submit">Добавить</button>
                                </form>
                            <?php elseif ($relation['type'] === 'incoming_pending'): ?>
                                <form action="friend_action.php" method="post">
                                    <input type="hidden" name="action" value="accept">
                                    <input type="hidden" name="request_id" value="<?= (int) $relation['request']['id'] ?>">
                                    <input type="hidden" name="profile_user_id" value="<?= (int) $player['id'] ?>">
                                    <button class="btn small" type="submit">Принять</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php render_notification_mount(); ?>
</body>
</html>