<?php

require '../includes/config.php';
require '../includes/reports.php';

require_moderator_or_admin();

$status = (string) ($_GET['status'] ?? 'all');

$allowedStatuses = array_keys(report_statuses());

if ($status !== 'all' && !in_array($status, $allowedStatuses, true)) {
    $status = 'all';
}

$reports = list_reports($status);

?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Репорты · Админка</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>
    <header class="top">
        <b>🛡️ Репорты</b>
        <nav>
            <a href="../lobby.php">Лобби</a>
            <a href="index.php">Админка</a>
            <a href="../logout.php">Выход</a>
        </nav>
    </header>

    <main class="layout">
        <section class="panel hero">
            <h1>Репорты игроков</h1>
            <p>Список жалоб, отправленных во время матчей.</p>
        </section>

        <section class="panel">
            <div class="admin-filters">
                <a class="btn <?= $status === 'all' ? 'active' : '' ?>" href="reports.php">Все</a>

                <?php foreach (report_statuses() as $key => $label): ?>
                    <a class="btn <?= $status === $key ? 'active' : '' ?>" href="reports.php?status=<?= h($key) ?>">
                        <?= h($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="panel">
            <?php if (!$reports): ?>
                <p>По этому фильтру репортов нет.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Матч</th>
                                <th>Отправитель</th>
                                <th>Нарушитель</th>
                                <th>Причина</th>
                                <th>Статус</th>
                                <th>Модератор</th>
                                <th>Создан</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $r): ?>
                                <tr>
                                    <td>#<?= (int) $r['id'] ?></td>
                                    <td>
                                        <?= h($r['game_title']) ?>
                                        <br>
                                        <small>Матч #<?= (int) $r['game_id'] ?></small>
                                    </td>
                                    <td>
                                        <a href="../profile.php?id=<?= (int) $r['reporter_user_id'] ?>">
                                            <?= h($r['reporter_username']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="../profile.php?id=<?= (int) $r['reported_user_id'] ?>">
                                            <?= h($r['reported_username']) ?>
                                        </a>
                                    </td>
                                    <td><?= h(report_reason_label($r['reason'])) ?></td>
                                    <td>
                                        <span class="status-pill <?= h(report_status_class($r['status'])) ?>">
                                            <?= h(report_status_label($r['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= h($r['reviewer_username'] ?: '—') ?></td>
                                    <td><?= h($r['created_at']) ?></td>
                                    <td>
                                        <a class="btn small" href="report.php?id=<?= (int) $r['id'] ?>">Открыть</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>

</html>