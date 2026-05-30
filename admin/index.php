<?php

require '../includes/config.php';
require '../includes/reports.php';

require_moderator_or_admin();

$countsStmt = db()->query(
    "SELECT status, COUNT(*) AS total
     FROM game_reports
     GROUP BY status"
);

$counts = [
    'open' => 0,
    'reviewing' => 0,
    'confirmed' => 0,
    'rejected' => 0,
    'closed' => 0,
];

foreach ($countsStmt->fetchAll() as $row) {
    $counts[$row['status']] = (int) $row['total'];
}

$recentReports = list_reports('all');
$recentReports = array_slice($recentReports, 0, 8);

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Админка · Mystery Mansion</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<header class="top">
    <b>🛡️ Админка</b>
    <nav>
        <a href="../lobby.php">Лобби</a>
        <a href="reports.php">Репорты</a>
        <a href="maps.php">Карты</a>
        <a href="../logout.php">Выход</a>
    </nav>
</header>

<main class="layout">
    <section class="panel hero">
        <h1>Панель модерации</h1>
        <p>Здесь будут инструменты админа и модератора. Первый главный модуль — проверка игровых репортов.</p>
    </section>

    <section class="admin-stats">
        <article class="admin-stat-card">
            <small>Новые</small>
            <strong><?= (int) $counts['open'] ?></strong>
        </article>

        <article class="admin-stat-card">
            <small>На проверке</small>
            <strong><?= (int) $counts['reviewing'] ?></strong>
        </article>

        <article class="admin-stat-card">
            <small>Подтверждены</small>
            <strong><?= (int) $counts['confirmed'] ?></strong>
        </article>

        <article class="admin-stat-card">
            <small>Отклонены</small>
            <strong><?= (int) $counts['rejected'] ?></strong>
        </article>

        <article class="admin-stat-card">
            <small>Закрыты</small>
            <strong><?= (int) $counts['closed'] ?></strong>
        </article>
    </section>

    <section class="panel">
        <div class="section-head">
            <h2>Последние репорты</h2>
            <a class="btn" href="reports.php">Открыть все</a>
        </div>

        <?php if (!$recentReports): ?>
            <p>Репортов пока нет.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Матч</th>
                        <th>Жалоба</th>
                        <th>Статус</th>
                        <th>Создан</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentReports as $r): ?>
                        <tr>
                            <td>#<?= (int) $r['id'] ?></td>
                            <td><?= h($r['game_title']) ?></td>
                            <td>
                                <?= h($r['reporter_username']) ?>
                                →
                                <?= h($r['reported_username']) ?>
                                <br>
                                <small><?= h(report_reason_label($r['reason'])) ?></small>
                            </td>
                            <td>
                                <span class="status-pill <?= h(report_status_class($r['status'])) ?>">
                                    <?= h(report_status_label($r['status'])) ?>
                                </span>
                            </td>
                            <td><?= h($r['created_at']) ?></td>
                            <td>
                                <a class="btn small" href="report.php?id=<?= (int) $r['id'] ?>">Проверить</a>
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