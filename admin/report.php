<?php

require '../includes/config.php';
require '../includes/reports.php';

require_moderator_or_admin();

$reportId = (int) ($_GET['id'] ?? 0);
$report = get_report_by_id($reportId);

if (!$report) {
    http_response_code(404);
    echo 'Репорт не найден';
    exit;
}

$snapshot = decode_report_snapshot($report['snapshot_json'] ?? null);
$snapshotGame = $snapshot['game'] ?? [];
$snapshotPlayers = $snapshot['players'] ?? [];
$snapshotLogs = $snapshot['recent_logs'] ?? [];

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';

unset($_SESSION['flash_success'], $_SESSION['flash_error']);

?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Репорт #<?= (int) $report['id'] ?> · Админка</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>
    <header class="top">
        <b>🛡️ Репорт #<?= (int) $report['id'] ?></b>
        <nav>
            <a href="../lobby.php">Лобби</a>
            <a href="reports.php">Репорты</a>
            <a href="index.php">Админка</a>
            <a href="../logout.php">Выход</a>
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
            <h1>Проверка репорта #<?= (int) $report['id'] ?></h1>
            <p>
                <a href="../profile.php?id=<?= (int) $report['reporter_user_id'] ?>">
                    <?= h($report['reporter_username']) ?>
                </a>
                пожаловался на
                <a href="../profile.php?id=<?= (int) $report['reported_user_id'] ?>">
                    <?= h($report['reported_username']) ?>
                </a>
                в матче
                <b><?= h($report['game_title']) ?></b>.
            </p>

            <p>
                <span class="status-pill <?= h(report_status_class($report['status'])) ?>">
                    <?= h(report_status_label($report['status'])) ?>
                </span>
            </p>
        </section>

        <section class="grid2">
            <div class="panel">
                <h2>Данные репорта</h2>

                <dl class="admin-dl">
                    <dt>ID</dt>
                    <dd>#<?= (int) $report['id'] ?></dd>

                    <dt>Матч</dt>
                    <dd>
                        <?= h($report['game_title']) ?>
                        <br>
                        <small>Матч #<?= (int) $report['game_id'] ?></small>
                    </dd>

                    <dt>Отправитель</dt>
                    <dd>
                        <a href="../profile.php?id=<?= (int) $report['reporter_user_id'] ?>">
                            <?= h($report['reporter_username']) ?>
                        </a>
                    </dd>

                    <dt>Нарушитель</dt>
                    <dd>
                        <a href="../profile.php?id=<?= (int) $report['reported_user_id'] ?>">
                            <?= h($report['reported_username']) ?>
                        </a>
                    </dd>

                    <dt>Причина</dt>
                    <dd><?= h(report_reason_label($report['reason'])) ?></dd>

                    <dt>Комментарий игрока</dt>
                    <dd><?= nl2br(h($report['comment'] ?: '—')) ?></dd>

                    <dt>Создан</dt>
                    <dd><?= h($report['created_at']) ?></dd>

                    <dt>Обновлён</dt>
                    <dd><?= h($report['updated_at']) ?></dd>

                    <dt>Модератор</dt>
                    <dd><?= h($report['reviewer_username'] ?: '—') ?></dd>

                    <dt>Комментарий проверки</dt>
                    <dd><?= nl2br(h($report['review_comment'] ?: '—')) ?></dd>
                </dl>
            </div>

            <div class="panel">
                <h2>Снимок матча</h2>

                <?php if (!$snapshotGame): ?>
                    <p>Snapshot отсутствует или не читается.</p>
                <?php else: ?>
                    <dl class="admin-dl">
                        <dt>Захвачен</dt>
                        <dd><?= h($snapshot['captured_at'] ?? '—') ?></dd>

                        <dt>Статус</dt>
                        <dd><?= h($snapshotGame['status'] ?? '—') ?></dd>

                        <dt>Фаза</dt>
                        <dd><?= h($snapshotGame['phase'] ?? '—') ?></dd>

                        <dt>Текущий игрок ID</dt>
                        <dd><?= h($snapshotGame['current_turn_player_id'] ?? '—') ?></dd>

                        <dt>Карта</dt>
                        <dd><?= h($snapshotGame['map_id'] ?? '—') ?></dd>

                        <dt>Бросок кубиков</dt>
                        <dd><?= h($snapshotGame['dice_total'] ?? '—') ?></dd>

                        <dt>Начало фазы</dt>
                        <dd><?= h($snapshotGame['phase_started_at'] ?? '—') ?></dd>
                    </dl>

                    <p>
                        <a class="btn" href="../game.php?id=<?= (int) $report['game_id'] ?>">Открыть матч</a>
                    </p>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <h2>Игроки на момент репорта</h2>

            <?php if (!$snapshotPlayers): ?>
                <p>Нет данных по игрокам.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Игрок</th>
                                <th>Персонаж</th>
                                <th>Место</th>
                                <th>Порядок</th>
                                <th>Позиция</th>
                                <th>Выбыл</th>
                                <th>AFK</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($snapshotPlayers as $p): ?>
                                <?php
                                $isReported = (int) ($p['user_id'] ?? 0) === (int) $report['reported_user_id'];
                                $isReporter = (int) ($p['user_id'] ?? 0) === (int) $report['reporter_user_id'];
                                ?>
                                <tr class="<?= $isReported ? 'row-danger' : ($isReporter ? 'row-info' : '') ?>">
                                    <td><?= (int) ($p['user_id'] ?? 0) ?></td>
                                    <td>
                                        <?= h($p['username'] ?? '—') ?>
                                        <?php if ($isReporter): ?>
                                            <br><small>отправитель</small>
                                        <?php endif; ?>
                                        <?php if ($isReported): ?>
                                            <br><small>обвиняемый</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h($p['character_name'] ?? '—') ?></td>
                                    <td><?= h($p['seat_no'] ?? '—') ?></td>
                                    <td><?= h($p['turn_order'] ?? '—') ?></td>
                                    <td>
                                        <?= h($p['pos_x'] ?? '—') ?>,
                                        <?= h($p['pos_y'] ?? '—') ?>
                                    </td>
                                    <td><?= !empty($p['is_eliminated']) ? 'Да' : 'Нет' ?></td>
                                    <td><?= (int) ($p['afk_misses'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="grid2">
            <div class="panel">
                <h2>Последние логи матча</h2>

                <?php if (!$snapshotLogs): ?>
                    <p>Логов в snapshot нет.</p>
                <?php else: ?>
                    <div class="admin-log-list">
                        <?php foreach ($snapshotLogs as $log): ?>
                            <article class="admin-log-item">
                                <small>
                                    #<?= (int) ($log['id'] ?? 0) ?>
                                    ·
                                    <?= h($log['created_at'] ?? '') ?>
                                    ·
                                    <?= h($log['username'] ?? 'Система') ?>
                                </small>
                                <p><?= h($log['message'] ?? '') ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="panel">
                <h2>Решение модератора</h2>

                <div class="moderation-current-state">
                    <span class="status-pill <?= h(report_status_class($report['status'])) ?>">
                        <?= h(report_status_label($report['status'])) ?>
                    </span>

                    <?php if (!empty($report['reviewer_username'])): ?>
                        <small>Последнее решение: <?= h($report['reviewer_username']) ?></small>
                    <?php endif; ?>
                </div>

                <?php if ($report['status'] === 'open'): ?>
                    <form action="report_action.php" method="post" class="moderation-decision-card">
                        <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                        <input type="hidden" name="decision" value="reviewing">
                        <input type="hidden" name="action_type" value="none">
                        <input type="hidden" name="review_comment" value="<?= h($report['review_comment'] ?? '') ?>">

                        <h3>Первый шаг</h3>
                        <p>Пометь репорт как взятый в проверку. Это покажет другим модераторам, что репорт уже
                            обрабатывается.</p>

                        <button type="submit" class="btn">Взять в проверку</button>
                    </form>
                <?php endif; ?>

                <form action="report_action.php" method="post" class="moderation-decision-card danger-zone">
                    <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                    <input type="hidden" name="decision" value="confirmed">

                    <h3>Подтвердить нарушение</h3>
                    <p>Используй этот вариант только если по snapshot, логам и контексту матча нарушение действительно
                        подтверждается.</p>

                    <label>
                        Последствие для игрока
                        <select name="action_type">
                            <option value="warning">Предупреждение</option>
                            <option value="block_create_games_24h">Запрет создавать игры на 24 часа</option>
                            <option value="block_games_24h">Запрет участвовать в играх на 24 часа</option>
                            <option value="none">Подтвердить без санкции</option>
                        </select>
                    </label>

                    <label>
                        Комментарий модератора
                        <textarea name="review_comment" rows="6" maxlength="2000" required
                            placeholder="Что именно подтверждено и почему выбрано это последствие"><?= h($report['review_comment'] ?? '') ?></textarea>
                    </label>

                    <button type="submit" class="danger-btn">Подтвердить нарушение</button>
                </form>

                <form action="report_action.php" method="post" class="moderation-decision-card">
                    <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                    <input type="hidden" name="decision" value="rejected">
                    <input type="hidden" name="action_type" value="none">

                    <h3>Отклонить репорт</h3>
                    <p>Если нарушение не подтверждается, закрой репорт как отклонённый. Санкции к игроку применены не
                        будут.</p>

                    <label>
                        Комментарий модератора
                        <textarea name="review_comment" rows="5" maxlength="2000"
                            placeholder="Почему нарушение не подтверждено"><?= h($report['review_comment'] ?? '') ?></textarea>
                    </label>

                    <button type="submit" class="btn">Отклонить репорт</button>
                </form>

                <form action="report_action.php" method="post" class="moderation-decision-card">
                    <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">
                    <input type="hidden" name="decision" value="closed">
                    <input type="hidden" name="action_type" value="none">

                    <h3>Закрыть без решения</h3>
                    <p>Для дублей, ошибочных жалоб, устаревших репортов или случаев, где решение не требуется.</p>

                    <label>
                        Комментарий модератора
                        <textarea name="review_comment" rows="4" maxlength="2000"
                            placeholder="Причина закрытия"><?= h($report['review_comment'] ?? '') ?></textarea>
                    </label>

                    <button type="submit" class="btn">Закрыть репорт</button>
                </form>
            </div>
        </section>
    </main>
</body>

</html>