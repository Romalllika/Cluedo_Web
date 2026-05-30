<?php

require '../includes/config.php';
require '../includes/reports.php';
require '../includes/map_submissions.php';

require_admin();

$status = (string) ($_GET['status'] ?? 'all');

if ($status !== 'all' && !array_key_exists($status, map_submission_statuses())) {
    $status = 'all';
}

$submissions = list_map_submissions($status);

?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Заявки карт · Админка</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>
<header class="top">
    <b>🗺️ Заявки карт</b>
    <nav>
        <a href="../lobby.php">Лобби</a>
        <a href="index.php">Админка</a>
        <a href="reports.php">Репорты</a>
        <a href="maps.php">Карты</a>
        <a href="tools.php">Инструменты</a>
        <a href="../logout.php">Выход</a>
    </nav>
</header>

<main class="layout admin-layout">
    <section class="panel hero admin-hero">
        <div>
            <p class="muted-label">Admin only</p>
            <h1>Заявки пользовательских карт</h1>
            <p>Проверяй JSON-карты игроков, оставляй комментарии и принимай решение.</p>
        </div>
    </section>

    <section class="panel admin-filter-panel">
        <div class="admin-filters">
            <a class="btn <?= $status === 'all' ? 'active' : '' ?>" href="map_submissions.php">Все</a>

            <?php foreach (map_submission_statuses() as $key => $label): ?>
                <a class="btn <?= $status === $key ? 'active' : '' ?>" href="map_submissions.php?status=<?= h($key) ?>">
                    <?= h($label) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <?php if (!$submissions): ?>
            <p>Заявок по этому фильтру нет.</p>
        <?php else: ?>
            <div class="admin-report-list">
                <?php foreach ($submissions as $submission): ?>
                    <article class="admin-report-card">
                        <div class="admin-report-card-main">
                            <div class="admin-report-card-title">
                                <strong><?= h($submission['title'] ?: 'Без названия') ?></strong>
                                <span class="status-pill <?= h(map_submission_status_class($submission['status'])) ?>">
                                    <?= h(map_submission_status_label($submission['status'])) ?>
                                </span>
                            </div>

                            <p>
                                Автор:
                                <a href="../profile.php?id=<?= (int) $submission['user_id'] ?>">
                                    <?= h($submission['username']) ?>
                                </a>
                            </p>

                            <small>
                                ID карты: <?= h($submission['map_id'] ?: '—') ?> ·
                                <?= h($submission['created_at']) ?>
                            </small>
                        </div>

                        <div class="admin-report-card-meta">
                            <small>Проверил</small>
                            <span><?= h($submission['reviewer_username'] ?: '—') ?></span>
                        </div>

                        <a class="btn small" href="map_submission.php?id=<?= (int) $submission['id'] ?>">Открыть</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>