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

$moderationActions = get_user_moderation_actions((int) $report['reported_user_id'], 8);

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

<main class="layout admin-layout">
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

    <section class="panel hero admin-report-hero">
        <div>
            <p class="muted-label">Проверка жалобы</p>
            <h1>Репорт #<?= (int) $report['id'] ?></h1>

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
        </div>

        <div class="admin-report-hero-side">
            <span class="status-pill <?= h(report_status_class($report['status'])) ?>">
                <?= h(report_status_label($report['status'])) ?>
            </span>

            <small>Создан: <?= h($report['created_at']) ?></small>

            <?php if (!empty($report['reviewer_username'])): ?>
                <small>Решение: <?= h($report['reviewer_username']) ?></small>
            <?php endif; ?>
        </div>
    </section>

    <section class="grid2 admin-report-main-grid">
        <div class="panel">
            <h2>Жалоба игрока</h2>

            <div class="admin-report-facts">
                <article>
                    <small>Причина</small>
                    <strong><?= h(report_reason_label($report['reason'])) ?></strong>
                </article>

                <article>
                    <small>Отправитель</small>
                    <strong>
                        <a href="../profile.php?id=<?= (int) $report['reporter_user_id'] ?>">
                            <?= h($report['reporter_username']) ?>
                        </a>
                    </strong>
                </article>

                <article>
                    <small>Обвиняемый</small>
                    <strong>
                        <a href="../profile.php?id=<?= (int) $report['reported_user_id'] ?>">
                            <?= h($report['reported_username']) ?>
                        </a>
                    </strong>
                </article>

                <article>
                    <small>Матч</small>
                    <strong>#<?= (int) $report['game_id'] ?></strong>
                </article>
            </div>

            <div class="admin-comment-box">
                <small>Комментарий игрока</small>
                <p><?= nl2br(h($report['comment'] ?: 'Комментарий не указан.')) ?></p>
            </div>

            <?php if (!empty($report['review_comment'])): ?>
                <div class="admin-comment-box">
                    <small>Последний комментарий модератора</small>
                    <p><?= nl2br(h($report['review_comment'])) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2>Решение модератора</h2>

            <form action="report_action.php" method="post" class="moderation-final-form" id="moderationDecisionForm">
                <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">

                <label>
                    Итоговое решение
                    <select name="decision" id="moderationDecisionSelect" required>
                        <option value="confirmed" <?= $report['status'] === 'confirmed' ? 'selected' : '' ?>>
                            Подтвердить нарушение
                        </option>
                        <option value="rejected" <?= $report['status'] === 'rejected' ? 'selected' : '' ?>>
                            Отклонить жалобу
                        </option>
                        <option value="closed" <?= $report['status'] === 'closed' ? 'selected' : '' ?>>
                            Закрыть без решения
                        </option>
                    </select>
                </label>

                <div class="moderation-sanction-box" id="moderationSanctionBox">
                    <label>
                        Санкция
                        <select name="action_type" id="moderationActionSelect">
                            <option value="none">Без санкции</option>
                            <option value="warning">Предупреждение</option>
                            <option value="create_ban">Запрет создавать игры</option>
                            <option value="game_ban">Запрет участвовать в играх</option>
                        </select>
                    </label>

                    <label id="moderationDurationLabel">
                        Срок ограничения
                        <select name="duration" id="moderationDurationSelect">
                            <option value="none">Без срока</option>
                            <option value="1h">1 час</option>
                            <option value="24h">24 часа</option>
                            <option value="7d">7 дней</option>
                            <option value="30d">30 дней</option>
                            <option value="permanent">Навсегда</option>
                        </select>
                    </label>
                </div>

                <label>
                    Комментарий модератора
                    <textarea
                        name="review_comment"
                        rows="7"
                        maxlength="2000"
                        placeholder="Кратко объясни решение: что подтвердилось, что видно по логам, почему выбрана санкция."
                    ><?= h($report['review_comment'] ?? '') ?></textarea>
                </label>

                <div class="moderation-help" id="moderationHelpText">
                    При подтверждении нарушения можно выбрать санкцию. При отклонении или закрытии санкция не применяется.
                </div>

                <button type="submit" class="btn primary-action">Сохранить решение</button>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="section-head">
            <h2>Снимок матча</h2>
            <a class="btn small" href="../game.php?id=<?= (int) $report['game_id'] ?>">Открыть матч</a>
        </div>

        <?php if (!$snapshotGame): ?>
            <p>Snapshot отсутствует или не читается.</p>
        <?php else: ?>
            <div class="admin-snapshot-grid">
                <article>
                    <small>Захвачен</small>
                    <strong><?= h($snapshot['captured_at'] ?? '—') ?></strong>
                </article>

                <article>
                    <small>Статус</small>
                    <strong><?= h($snapshotGame['status'] ?? '—') ?></strong>
                </article>

                <article>
                    <small>Фаза</small>
                    <strong><?= h($snapshotGame['phase'] ?? '—') ?></strong>
                </article>

                <article>
                    <small>Текущий игрок ID</small>
                    <strong><?= h($snapshotGame['current_turn_player_id'] ?? '—') ?></strong>
                </article>

                <article>
                    <small>Карта</small>
                    <strong><?= h($snapshotGame['map_id'] ?? '—') ?></strong>
                </article>

                <article>
                    <small>Кубики</small>
                    <strong><?= h($snapshotGame['dice_total'] ?? '—') ?></strong>
                </article>
            </div>
        <?php endif; ?>
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
                                <a href="../profile.php?id=<?= (int) ($p['user_id'] ?? 0) ?>">
                                    <?= h($p['username'] ?? '—') ?>
                                </a>

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
                                · <?= h($log['created_at'] ?? '') ?>
                                · <?= h($log['username'] ?? 'Система') ?>
                            </small>
                            <p><?= h($log['message'] ?? '') ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2>История санкций игрока</h2>

            <?php if (!$moderationActions): ?>
                <p>По этому игроку пока нет санкций.</p>
            <?php else: ?>
                <div class="admin-log-list">
                    <?php foreach ($moderationActions as $action): ?>
                        <article class="admin-log-item">
                            <small>
                                <?= h($action['created_at']) ?>
                                · <?= h($action['moderator_username']) ?>
                                <?php if (!empty($action['report_id'])): ?>
                                    · Репорт #<?= (int) $action['report_id'] ?>
                                <?php endif; ?>
                            </small>

                            <p>
                                <b><?= h(report_action_label($action['action_type'])) ?></b>
                                <br>
                                Срок:
                                <?= h(moderation_duration_label(
                                    isset($action['duration_value']) ? (int) $action['duration_value'] : null,
                                    $action['duration_unit'] ?? null
                                )) ?>

                                <?php if (!empty($action['expires_at'])): ?>
                                    · До <?= h($action['expires_at']) ?>
                                <?php endif; ?>
                            </p>

                            <?php if (!empty($action['reason'])): ?>
                                <p><?= nl2br(h($action['reason'])) ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
(function () {
    const decisionSelect = document.querySelector('#moderationDecisionSelect');
    const actionSelect = document.querySelector('#moderationActionSelect');
    const sanctionBox = document.querySelector('#moderationSanctionBox');
    const durationLabel = document.querySelector('#moderationDurationLabel');
    const durationSelect = document.querySelector('#moderationDurationSelect');
    const helpText = document.querySelector('#moderationHelpText');

    if (!decisionSelect || !actionSelect || !sanctionBox || !durationLabel || !durationSelect || !helpText) {
        return;
    }

    function syncModerationForm() {
        const decision = decisionSelect.value;
        const action = actionSelect.value;
        const isConfirmed = decision === 'confirmed';
        const isTimedBan = action === 'create_ban' || action === 'game_ban';

        sanctionBox.style.display = isConfirmed ? 'grid' : 'none';
        durationLabel.style.display = isConfirmed && isTimedBan ? 'grid' : 'none';

        if (!isConfirmed) {
            actionSelect.value = 'none';
            durationSelect.value = 'none';
            helpText.textContent = 'Санкция не применяется, потому что нарушение не подтверждается.';
            return;
        }

        if (action === 'none') {
            durationSelect.value = 'none';
            helpText.textContent = 'Репорт будет подтверждён, но без наказания игрока.';
            return;
        }

        if (action === 'warning') {
            durationSelect.value = 'none';
            helpText.textContent = 'Игрок получит предупреждение. Ограничений на игру не будет.';
            return;
        }

        helpText.textContent = 'Выбери срок ограничения. На это время игрок не сможет выполнять выбранное действие.';
    }

    decisionSelect.addEventListener('change', syncModerationForm);
    actionSelect.addEventListener('change', syncModerationForm);

    syncModerationForm();
})();
</script>
</body>
</html>