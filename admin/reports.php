<?php

require '../includes/config.php';
require '../includes/reports.php';

require_moderator_or_admin();

$status = (string) ($_GET['status'] ?? 'all');

$filterStatuses = [
    'open' => 'Новые',
    'confirmed' => 'Подтверждённые',
    'rejected' => 'Отклонённые',
    'closed' => 'Закрытые',
];

if ($status !== 'all' && !array_key_exists($status, $filterStatuses)) {
    $status = 'all';
}

$reports = list_reports($status);

$statusCounts = [
    'all' => 0,
    'open' => 0,
    'confirmed' => 0,
    'rejected' => 0,
    'closed' => 0,
];

$countStmt = db()->query(
    "SELECT status, COUNT(*) AS total
     FROM game_reports
     GROUP BY status"
);

foreach ($countStmt->fetchAll() as $row) {
    $key = (string) $row['status'];
    $total = (int) $row['total'];

    $statusCounts['all'] += $total;

    if (array_key_exists($key, $statusCounts)) {
        $statusCounts[$key] = $total;
    }
}

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

<main class="layout admin-layout">
    <section class="panel hero admin-hero">
        <div>
            <p class="muted-label">Модерация</p>
            <h1>Репорты игроков</h1>
            <p>Все жалобы сохраняются в истории. Решённые репорты не удаляются и доступны через фильтр.</p>
        </div>

        <div class="admin-report-summary">
            <article>
                <small>Всего</small>
                <strong><?= (int) $statusCounts['all'] ?></strong>
            </article>
            <article>
                <small>Новые</small>
                <strong><?= (int) $statusCounts['open'] ?></strong>
            </article>
            <article>
                <small>Подтверждены</small>
                <strong><?= (int) $statusCounts['confirmed'] ?></strong>
            </article>
            <article>
                <small>Отклонены</small>
                <strong><?= (int) $statusCounts['rejected'] ?></strong>
            </article>
        </div>
    </section>

    <section class="panel admin-filter-panel">
        <div class="admin-filters">
            <a class="btn <?= $status === 'all' ? 'active' : '' ?>" href="reports.php">
                Все
                <span><?= (int) $statusCounts['all'] ?></span>
            </a>

            <?php foreach ($filterStatuses as $key => $label): ?>
                <a class="btn <?= $status === $key ? 'active' : '' ?>" href="reports.php?status=<?= h($key) ?>">
                    <?= h($label) ?>
                    <span><?= (int) $statusCounts[$key] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <div class="section-head">
            <h2>Список репортов</h2>
            <small><?= count($reports) ?> по текущему фильтру</small>
        </div>

        <?php if (!$reports): ?>
            <div class="empty-feature-card">
                <h3>Репортов нет</h3>
                <p>По выбранному фильтру ничего не найдено.</p>
            </div>
        <?php else: ?>
            <div class="admin-report-list">
                <?php foreach ($reports as $r): ?>
                    <article class="admin-report-card">
                        <div class="admin-report-card-main">
                            <div class="admin-report-card-title">
                                <strong>Репорт #<?= (int) $r['id'] ?></strong>
                                <span class="status-pill <?= h(report_status_class($r['status'])) ?>">
                                    <?= h(report_status_label($r['status'])) ?>
                                </span>
                            </div>

                            <p>
                                <a href="../profile.php?id=<?= (int) $r['reporter_user_id'] ?>">
                                    <?= h($r['reporter_username']) ?>
                                </a>
                                пожаловался на
                                <a href="../profile.php?id=<?= (int) $r['reported_user_id'] ?>">
                                    <?= h($r['reported_username']) ?>
                                </a>
                            </p>

                            <small>
                                Причина: <?= h(report_reason_label($r['reason'])) ?> ·
                                Матч #<?= (int) $r['game_id'] ?> ·
                                <?= h($r['created_at']) ?>
                            </small>
                        </div>

                        <div class="admin-report-card-meta">
                            <small>Модератор</small>
                            <span><?= h($r['reviewer_username'] ?: '—') ?></span>
                        </div>

                        <a class="btn small" href="report.php?id=<?= (int) $r['id'] ?>">Открыть</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>