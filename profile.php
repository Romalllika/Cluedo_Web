<?php

require 'includes/config.php';
require 'includes/profile.php';
require 'includes/reports.php';

require_auth();

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
    <section class="profile-hero panel">
        <div>
            <p class="muted-label"><?= $isMe ? 'Мой профиль' : 'Профиль игрока' ?></p>
            <h1><?= h($user['username']) ?></h1>

            <div class="profile-badges">
                <span class="status-pill"><?= h(profile_role_label($user['role'])) ?></span>
                <span class="status-pill status-closed">Оффлайн / онлайн позже</span>
            </div>
        </div>

        <div class="profile-actions">
            <?php if ($isMe): ?>
                <a class="btn" href="lobby.php">Вернуться в лобби</a>
            <?php else: ?>
                <button class="btn" type="button" disabled>Добавить в друзья</button>
                <button class="danger-btn" type="button" disabled>Пожаловаться</button>
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

                <dt>Роль</dt>
                <dd><?= h(profile_role_label($user['role'])) ?></dd>

                <dt>Дата регистрации</dt>
                <dd><?= h($user['created_at']) ?></dd>

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
            <h2>Социальный блок</h2>

            <div class="empty-feature-card">
                <h3>Друзья</h3>
                <p>Здесь появятся список друзей, входящие и исходящие заявки.</p>
            </div>

            <div class="empty-feature-card">
                <h3>Приглашения в игру</h3>
                <p>Позже отсюда можно будет пригласить друга в выбранное лобби.</p>
            </div>
        </div>

        <div class="panel">
            <h2>Будущие действия</h2>

            <div class="profile-action-list">
                <button type="button" class="btn" disabled>Добавить в друзья</button>
                <button type="button" class="btn" disabled>Пригласить в игру</button>
                <button type="button" class="danger-btn" disabled>Пожаловаться</button>
            </div>

            <p class="muted-text">
                Кнопки подготовлены под следующие этапы: друзья, заявки и приглашения в лобби.
            </p>
        </div>
    </section>

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