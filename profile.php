<?php

require 'includes/config.php';
require 'includes/profile.php';
require 'includes/reports.php';
require 'includes/friends.php';
require 'includes/invites.php';

$notificationsFile = __DIR__ . '/includes/notifications.php';
if (is_file($notificationsFile)) {
    require $notificationsFile;
}

require_auth();
update_current_user_presence();

$viewerId = (int) current_user_id();
$userId = (int) ($_GET['id'] ?? $viewerId);

if ($userId <= 0) {
    $userId = $viewerId;
}

$user = get_profile_user($userId);

if (!$user) {
    http_response_code(404);
    echo 'Пользователь не найден';
    exit;
}

$isMe = $viewerId === (int) $user['id'];
$isModerator = user_is_moderator_or_admin($viewerId);

$friendRelation = get_friend_relation($viewerId, (int) $user['id']);
$friends = get_user_friends((int) $user['id']);
$friendsCount = count_user_friends((int) $user['id']);

$incomingFriendRequests = $isMe ? get_incoming_friend_requests($viewerId) : [];
$incomingGameInvites = $isMe ? get_incoming_game_invites($viewerId) : [];

$stats = get_profile_stats($user);
$recentMatches = get_profile_recent_matches((int) $user['id']);
$commonMatches = get_profile_common_matches_count($viewerId, (int) $user['id']);
$commonMatchesForReport = !$isMe ? get_profile_common_matches_for_report($viewerId, (int) $user['id']) : [];

$moderationStats = $isModerator ? get_profile_moderation_stats((int) $user['id']) : null;
$recentReports = $isModerator ? get_profile_recent_reports((int) $user['id']) : [];
$moderationActions = ($isModerator && function_exists('get_user_moderation_actions'))
    ? get_user_moderation_actions((int) $user['id'])
    : [];

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($isMe) {
    $profileHeaderCounts = [
        ['label' => 'Друзей', 'value' => $friendsCount],
        ['label' => 'Заявок', 'value' => count($incomingFriendRequests)],
        ['label' => 'Приглашений', 'value' => count($incomingGameInvites)],
        ['label' => 'Матчей', 'value' => (int) $stats['games_played']],
    ];
} else {
    $profileHeaderCounts = [
        ['label' => 'Матчей', 'value' => (int) $stats['games_played']],
        ['label' => 'Побед', 'value' => (int) $stats['wins']],
        ['label' => 'Винрейт', 'value' => (int) $stats['win_rate'] . '%'],
        ['label' => 'Друзей', 'value' => $friendsCount],
        ['label' => 'Общих матчей', 'value' => (int) $commonMatches],
    ];
}

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Профиль · <?= h($user['username']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="top">
    <b>👤 Профиль</b>

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

<main class="layout profile-page">
    <?php if ($flashSuccess): ?>
        <section class="panel flash flash-success"><?= h($flashSuccess) ?></section>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <section class="panel flash flash-error"><?= h($flashError) ?></section>
    <?php endif; ?>

    <section class="panel profile-hero">
        <div class="profile-hero-main">
            <p class="muted-label"><?= $isMe ? 'Мой профиль' : 'Профиль игрока' ?></p>
            <h1><?= h($user['username']) ?></h1>

            <div class="profile-hero-counters">
                <?php foreach ($profileHeaderCounts as $item): ?>
                    <span>
                        <small><?= h($item['label']) ?></small>
                        <b><?= h($item['value']) ?></b>
                    </span>
                <?php endforeach; ?>
            </div>

            <div class="profile-badges">
                <span class="status-pill <?= h(profile_online_class($user['last_seen_at'] ?? null)) ?>">
                    <?= h(profile_online_label($user['last_seen_at'] ?? null)) ?>
                </span>

                <?php if ($isMe): ?>
                    <span class="status-pill status-reviewing">Это вы</span>
                <?php endif; ?>

                <?php if (!$isMe && $friendRelation['type'] === 'friends'): ?>
                    <span class="status-pill status-confirmed">В друзьях</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-actions">
            <?php if ($isMe): ?>
                <a class="btn" href="lobby.php">Вернуться в лобби</a>
            <?php else: ?>
                <?php if ($friendRelation['type'] === 'none'): ?>
                    <form action="friend_action.php" method="post">
                        <input type="hidden" name="action" value="send">
                        <input type="hidden" name="profile_user_id" value="<?= (int) $user['id'] ?>">
                        <button class="btn" type="submit">Добавить в друзья</button>
                    </form>
                <?php elseif ($friendRelation['type'] === 'outgoing_pending'): ?>
                    <form action="friend_action.php" method="post">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="request_id" value="<?= (int) $friendRelation['request']['id'] ?>">
                        <input type="hidden" name="profile_user_id" value="<?= (int) $user['id'] ?>">
                        <button class="btn" type="submit">Отменить заявку</button>
                    </form>
                <?php elseif ($friendRelation['type'] === 'incoming_pending'): ?>
                    <form action="friend_action.php" method="post">
                        <input type="hidden" name="action" value="accept">
                        <input type="hidden" name="request_id" value="<?= (int) $friendRelation['request']['id'] ?>">
                        <input type="hidden" name="profile_user_id" value="<?= (int) $user['id'] ?>">
                        <button class="btn" type="submit">Принять заявку</button>
                    </form>

                    <form action="friend_action.php" method="post">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="request_id" value="<?= (int) $friendRelation['request']['id'] ?>">
                        <input type="hidden" name="profile_user_id" value="<?= (int) $user['id'] ?>">
                        <button class="danger-btn" type="submit">Отклонить</button>
                    </form>
                <?php elseif ($friendRelation['type'] === 'friends'): ?>
                    <form action="friend_action.php" method="post">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="profile_user_id" value="<?= (int) $user['id'] ?>">
                        <button class="danger-btn" type="submit">Удалить из друзей</button>
                    </form>
                <?php endif; ?>

                <a class="danger-btn" href="#profile-report">Пожаловаться</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="section-head">
            <h2>Игровая статистика</h2>
            <small>Основные показатели игрока</small>
        </div>

        <div class="profile-stats-grid">
            <article class="profile-stat-card"><small>Сыграно матчей</small><strong><?= (int) $stats['games_played'] ?></strong></article>
            <article class="profile-stat-card"><small>Побед</small><strong><?= (int) $stats['wins'] ?></strong></article>
            <article class="profile-stat-card"><small>Поражений</small><strong><?= (int) $stats['losses'] ?></strong></article>
            <article class="profile-stat-card"><small>Сдач</small><strong><?= (int) $stats['surrenders'] ?></strong></article>
            <article class="profile-stat-card"><small>Неверных обвинений</small><strong><?= (int) $stats['wrong_accusations'] ?></strong></article>
            <article class="profile-stat-card"><small>Процент побед</small><strong><?= (int) $stats['win_rate'] ?>%</strong></article>
        </div>
    </section>

    <section class="panel">
        <div class="section-head">
            <h2>Последние матчи</h2>
            <small>Последние игры, где игрок занимал место в матче</small>
        </div>

        <?php if (!$recentMatches): ?>
            <p class="muted-text">Матчей пока нет.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>Матч</th>
                        <th>Карта</th>
                        <th>Персонаж</th>
                        <th>Дата</th>
                        <th>Статус</th>
                        <th>Результат</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentMatches as $m): ?>
                        <tr>
                            <td><?= h($m['title']) ?><br><small>#<?= (int) $m['game_id'] ?></small></td>
                            <td><?= h($m['map_id']) ?></td>
                            <td><?= h($m['character_name']) ?></td>
                            <td><?= h($m['created_at']) ?></td>
                            <td><?= h(profile_match_status_label($m['status'])) ?></td>
                            <td><?= h(profile_match_result($m, (int) $user['id'])) ?></td>
                            <td><a class="btn small" href="game.php?id=<?= (int) $m['game_id'] ?>">Открыть</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="section-head">
            <h2>Друзья</h2>
            <small><?= (int) $friendsCount ?> всего</small>
        </div>

        <?php if (!$friends): ?>
            <p class="muted-text">Список друзей пока пуст.</p>
        <?php else: ?>
            <div class="friend-list compact-list">
                <?php foreach ($friends as $friend): ?>
                    <article class="friend-card">
                        <div>
                            <a href="profile.php?id=<?= (int) $friend['id'] ?>"><strong><?= h($friend['username']) ?></strong></a>
                            <small><?= h(profile_online_label($friend['last_seen_at'] ?? null)) ?></small>
                        </div>
                        <a class="btn small" href="profile.php?id=<?= (int) $friend['id'] ?>">Профиль</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!$isMe): ?>
        <section class="panel" id="profile-report">
            <div class="section-head">
                <h2>Жалоба на игрока</h2>
                <small>Жалоба привязывается к общему матчу для проверки логов</small>
            </div>

            <?php if (!$commonMatchesForReport): ?>
                <p class="muted-text">
                    У вас пока нет общих матчей с этим игроком. Во время матча жалоба доступна рядом с игроком в списке участников.
                </p>
            <?php else: ?>
                <form action="profile_report_action.php" method="post" class="profile-report-form">
                    <input type="hidden" name="reported_user_id" value="<?= (int) $user['id'] ?>">

                    <label>
                        Общий матч
                        <select name="game_id" required>
                            <?php foreach ($commonMatchesForReport as $match): ?>
                                <option value="<?= (int) $match['id'] ?>">
                                    #<?= (int) $match['id'] ?> · <?= h($match['title']) ?> · <?= h(profile_match_status_label($match['status'])) ?> · <?= h($match['map_id']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Причина
                        <select name="reason">
                            <?php foreach (report_reasons() as $key => $label): ?>
                                <option value="<?= h($key) ?>"><?= h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Комментарий
                        <textarea name="comment" rows="5" maxlength="1000" placeholder="Кратко опиши, что произошло"></textarea>
                    </label>

                    <div class="form-actions">
                        <button class="danger-btn" type="submit">Отправить жалобу</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($isModerator): ?>
        <section class="panel">
            <div class="section-head">
                <h2>Модераторский блок</h2>
                <small>Виден только администраторам и модераторам</small>
            </div>

            <div class="profile-stats-grid compact-stats">
                <article class="profile-stat-card"><small>Репортов на игрока</small><strong><?= (int) $moderationStats['reports_on_user'] ?></strong></article>
                <article class="profile-stat-card"><small>Репортов от игрока</small><strong><?= (int) $moderationStats['reports_from_user'] ?></strong></article>
                <article class="profile-stat-card"><small>Подтверждены</small><strong><?= (int) $moderationStats['confirmed_on_user'] ?></strong></article>
                <article class="profile-stat-card"><small>Отклонены</small><strong><?= (int) $moderationStats['rejected_on_user'] ?></strong></article>
                <article class="profile-stat-card"><small>Открытые</small><strong><?= (int) $moderationStats['open_on_user'] ?></strong></article>
            </div>

            <div class="grid2 profile-moderation-grid">
                <div>
                    <h3>Последние репорты</h3>
                    <?php if (!$recentReports): ?>
                        <p class="muted-text">Репортов по этому игроку пока нет.</p>
                    <?php else: ?>
                        <div class="admin-log-list">
                            <?php foreach ($recentReports as $r): ?>
                                <article class="admin-log-item">
                                    <small>Репорт #<?= (int) $r['id'] ?> · Матч #<?= (int) $r['game_id'] ?> · <?= h($r['created_at']) ?></small>
                                    <p><?= h($r['reporter_username']) ?> → <?= h($r['reported_username']) ?><br>Статус: <?= h(report_status_label($r['status'])) ?></p>
                                    <a class="btn small" href="admin/report.php?id=<?= (int) $r['id'] ?>">Открыть</a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <h3>Последние санкции</h3>
                    <?php if (!$moderationActions): ?>
                        <p class="muted-text">Санкций пока нет.</p>
                    <?php else: ?>
                        <div class="admin-log-list">
                            <?php foreach ($moderationActions as $a): ?>
                                <article class="admin-log-item">
                                    <small><?= h($a['created_at']) ?> · <?= h($a['moderator_username']) ?></small>
                                    <p>
                                        <?= h(function_exists('report_action_label') ? report_action_label($a['action_type']) : $a['action_type']) ?>
                                        <?php if (!empty($a['expires_at'])): ?>
                                            <br>До: <?= h($a['expires_at']) ?>
                                        <?php endif; ?>
                                    </p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php if (function_exists('render_notification_mount')): ?>
    <?php render_notification_mount(); ?>
<?php endif; ?>
</body>
</html>
