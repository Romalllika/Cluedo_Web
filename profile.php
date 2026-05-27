<?php

require 'includes/config.php';
require 'includes/profile.php';
require 'includes/reports.php';
require 'includes/friends.php';

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
$outgoingFriendRequests = $isMe ? get_outgoing_friend_requests($viewerId) : [];

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';

unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$stats = get_profile_stats($user);
$recentMatches = get_profile_recent_matches((int) $user['id']);
$commonMatches = get_profile_common_matches_count($viewerId, (int) $user['id']);
$moderationStats = $isModerator ? get_profile_moderation_stats((int) $user['id']) : null;
$recentReports = $isModerator ? get_profile_recent_reports((int) $user['id']) : [];

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
        <section class="profile-hero panel">
            <div>
                <p class="muted-label"><?= $isMe ? 'Мой профиль' : 'Профиль игрока' ?></p>
                <h1><?= h($user['username']) ?></h1>

                <div class="profile-badges">
                    <span class="status-pill status-closed">Оффлайн / онлайн позже</span>
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
                        <span class="status-pill status-confirmed">В друзьях</span>

                        <form action="friend_action.php" method="post">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="profile_user_id" value="<?= (int) $user['id'] ?>">
                            <button class="danger-btn" type="submit">Удалить из друзей</button>
                        </form>
                    <?php endif; ?>

                    <button class="danger-btn" type="button" disabled>Пожаловаться из профиля позже</button>
                <?php endif; ?>
            </div>
        </section>

        <section class="grid2">
            <div class="panel">
                <h2>Основная информация</h2>

                <dl class="admin-dl">
                    <dt>ID</dt>
                    <dd>#<?= (int) $user['id'] ?></dd>

                    <dt>Ник</dt>
                    <dd><?= h($user['username']) ?></dd>

                    <dt>Дата регистрации</dt>
                    <dd><?= h($user['created_at']) ?></dd>

                    <dt>Статус</dt>
                    <dd><?= h(profile_online_label($user['last_seen_at'] ?? null)) ?></dd>

                    <dt>Друзей</dt>
                    <dd><?= (int) $friendsCount ?></dd>

                    <dt>Общие матчи</dt>
                    <dd>
                        <?= $isMe ? '—' : (int) $commonMatches ?>
                    </dd>
                </dl>
            </div>

            <div class="panel">
                <h2>Игровая статистика</h2>

                <div class="profile-stats-grid">
                    <article class="profile-stat-card">
                        <small>Сыграно матчей</small>
                        <strong><?= (int) $stats['games_played'] ?></strong>
                    </article>

                    <article class="profile-stat-card">
                        <small>Побед</small>
                        <strong><?= (int) $stats['wins'] ?></strong>
                    </article>

                    <article class="profile-stat-card">
                        <small>Поражений</small>
                        <strong><?= (int) $stats['losses'] ?></strong>
                    </article>

                    <article class="profile-stat-card">
                        <small>Сдач</small>
                        <strong><?= (int) $stats['surrenders'] ?></strong>
                    </article>

                    <article class="profile-stat-card">
                        <small>Неверных обвинений</small>
                        <strong><?= (int) $stats['wrong_accusations'] ?></strong>
                    </article>

                    <article class="profile-stat-card">
                        <small>Процент побед</small>
                        <strong><?= (int) $stats['win_rate'] ?>%</strong>
                    </article>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="section-head">
                <h2>Последние матчи</h2>
                <small>Показываются последние игры, где игрок занимал место в матче.</small>
            </div>

            <?php if (!$recentMatches): ?>
                <p>Матчей пока нет.</p>
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
                                    <td>
                                        <?= h($m['title']) ?>
                                        <br>
                                        <small>#<?= (int) $m['game_id'] ?></small>
                                    </td>
                                    <td><?= h($m['map_id']) ?></td>
                                    <td><?= h($m['character_name']) ?></td>
                                    <td><?= h($m['created_at']) ?></td>
                                    <td><?= h(profile_match_status_label($m['status'])) ?></td>
                                    <td><?= h(profile_match_result($m, (int) $user['id'])) ?></td>
                                    <td>
                                        <a class="btn small" href="game.php?id=<?= (int) $m['game_id'] ?>">Открыть</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="grid2">
            <div class="panel">
                <div class="section-head">
                    <h2>Друзья</h2>
                    <small><?= (int) $friendsCount ?> всего</small>
                </div>

                <?php if (!$friends): ?>
                    <p>Список друзей пока пуст.</p>
                <?php else: ?>
                    <div class="friend-list">
                        <?php foreach ($friends as $friend): ?>
                            <article class="friend-card">
                                <div>
                                    <a href="profile.php?id=<?= (int) $friend['id'] ?>">
                                        <strong><?= h($friend['username']) ?></strong>
                                    </a>
                                    <small><?= h(profile_online_label($friend['last_seen_at'] ?? null)) ?></small>
                                </div>
                                <a class="btn small" href="profile.php?id=<?= (int) $friend['id'] ?>">Профиль</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="panel">
                <h2>Приглашения в игру</h2>

                <div class="empty-feature-card">
                    <h3>Следующий этап</h3>
                    <p>
                        После друзей подключим приглашения в лобби: можно будет выбрать друга
                        и отправить ему приглашение в конкретный матч.
                    </p>
                </div>

                <?php if (!$isMe && $friendRelation['type'] === 'friends'): ?>
                    <button type="button" class="btn" disabled>Пригласить в игру — скоро</button>
                <?php else: ?>
                    <p class="muted-text">Приглашать в игру можно будет друзей.</p>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($isMe): ?>
            <section class="grid2">
                <div class="panel">
                    <h2>Входящие заявки</h2>

                    <?php if (!$incomingFriendRequests): ?>
                        <p>Входящих заявок нет.</p>
                    <?php else: ?>
                        <div class="friend-list">
                            <?php foreach ($incomingFriendRequests as $request): ?>
                                <article class="friend-card">
                                    <div>
                                        <a href="profile.php?id=<?= (int) $request['sender_user_id'] ?>">
                                            <strong><?= h($request['username']) ?></strong>
                                        </a>
                                        <small><?= h($request['created_at']) ?></small>
                                    </div>

                                    <div class="friend-actions">
                                        <form action="friend_action.php" method="post">
                                            <input type="hidden" name="action" value="accept">
                                            <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                            <input type="hidden" name="profile_user_id"
                                                value="<?= (int) $request['sender_user_id'] ?>">
                                            <button class="btn small" type="submit">Принять</button>
                                        </form>

                                        <form action="friend_action.php" method="post">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                            <input type="hidden" name="profile_user_id"
                                                value="<?= (int) $request['sender_user_id'] ?>">
                                            <button class="danger-btn small" type="submit">Отклонить</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <h2>Исходящие заявки</h2>

                    <?php if (!$outgoingFriendRequests): ?>
                        <p>Исходящих заявок нет.</p>
                    <?php else: ?>
                        <div class="friend-list">
                            <?php foreach ($outgoingFriendRequests as $request): ?>
                                <article class="friend-card">
                                    <div>
                                        <a href="profile.php?id=<?= (int) $request['receiver_user_id'] ?>">
                                            <strong><?= h($request['username']) ?></strong>
                                        </a>
                                        <small><?= h($request['created_at']) ?></small>
                                    </div>

                                    <form action="friend_action.php" method="post">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                        <input type="hidden" name="profile_user_id"
                                            value="<?= (int) $request['receiver_user_id'] ?>">
                                        <button class="btn small" type="submit">Отменить</button>
                                    </form>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($isModerator): ?>
            <section class="panel">
                <h2>Модераторский блок</h2>

                <div class="profile-stats-grid">
                    <article class="profile-stat-card">
                        <small>Репортов на игрока</small>
                        <strong><?= (int) $moderationStats['reports_on_user'] ?></strong>
                    </article>

                    <article class="profile-stat-card">
                        <small>Репортов от игрока</small>
                        <strong><?= (int) $moderationStats['reports_from_user'] ?></strong>
                    </article>

                    <article class="profile-stat-card">
                        <small>Подтверждены</small>
                        <strong><?= (int) $moderationStats['confirmed_on_user'] ?></strong>
                    </article>

                    <article class="profile-stat-card">
                        <small>Отклонены</small>
                        <strong><?= (int) $moderationStats['rejected_on_user'] ?></strong>
                    </article>

                    <article class="profile-stat-card">
                        <small>Открытые</small>
                        <strong><?= (int) $moderationStats['open_on_user'] ?></strong>
                    </article>
                </div>

                <div class="grid2 profile-moderation-grid">
                    <div>
                        <h3>Последние репорты</h3>

                        <?php if (!$recentReports): ?>
                            <p>Репортов по этому игроку пока нет.</p>
                        <?php else: ?>
                            <div class="admin-log-list">
                                <?php foreach ($recentReports as $r): ?>
                                    <article class="admin-log-item">
                                        <small>
                                            Репорт #<?= (int) $r['id'] ?>
                                            · Матч #<?= (int) $r['game_id'] ?>
                                            · <?= h($r['created_at']) ?>
                                        </small>
                                        <p>
                                            <?= h($r['reporter_username']) ?>
                                            →
                                            <?= h($r['reported_username']) ?>
                                            <br>
                                            Статус: <?= h(report_status_label($r['status'])) ?>
                                        </p>
                                        <a class="btn small" href="admin/report.php?id=<?= (int) $r['id'] ?>">Открыть</a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <h3>Будущая модерация</h3>

                        <div class="empty-feature-card">
                            <h4>Предупреждения</h4>
                            <p>Позже здесь будет история предупреждений игрока.</p>
                        </div>

                        <div class="empty-feature-card">
                            <h4>Блокировки</h4>
                            <p>Позже здесь будут временные блокировки и ограничения.</p>
                        </div>

                        <div class="empty-feature-card">
                            <h4>Заметки модератора</h4>
                            <p>Позже здесь будут внутренние заметки по игроку.</p>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>
</body>

</html>