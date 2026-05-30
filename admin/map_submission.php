<?php

require '../includes/config.php';
require '../includes/reports.php';
require '../includes/map_submissions.php';

require_admin();

$submissionId = (int) ($_GET['id'] ?? 0);
$submission = get_map_submission_by_id($submissionId);

if (!$submission) {
    http_response_code(404);
    echo 'Заявка не найдена';
    exit;
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';

unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$parsed = parse_submitted_map_json((string) $submission['json_text']);
$filename = map_submission_download_filename($submission);

?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Заявка карты #<?= (int) $submission['id'] ?> · Админка</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>
<header class="top">
    <b>🗺️ Заявка #<?= (int) $submission['id'] ?></b>
    <nav>
        <a href="../lobby.php">Лобби</a>
        <a href="map_submissions.php">Заявки карт</a>
        <a href="maps.php">Карты</a>
        <a href="tools.php">Инструменты</a>
        <a href="../logout.php">Выход</a>
    </nav>
</header>

<main class="layout admin-layout">
    <?php if ($flashSuccess): ?>
        <section class="panel flash flash-success"><?= h($flashSuccess) ?></section>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <section class="panel flash flash-error"><?= h($flashError) ?></section>
    <?php endif; ?>

    <section class="panel hero admin-hero">
        <div>
            <p class="muted-label">Проверка пользовательской карты</p>
            <h1><?= h($submission['title'] ?: 'Без названия') ?></h1>
            <p>
                Автор:
                <a href="../profile.php?id=<?= (int) $submission['user_id'] ?>">
                    <?= h($submission['username']) ?>
                </a>
                · ID карты: <?= h($submission['map_id'] ?: '—') ?>
            </p>
        </div>

        <div class="admin-report-hero-side">
            <span class="status-pill <?= h(map_submission_status_class($submission['status'])) ?>">
                <?= h(map_submission_status_label($submission['status'])) ?>
            </span>
            <small>Создана: <?= h($submission['created_at']) ?></small>
        </div>
    </section>

    <section class="grid2">
        <div class="panel">
            <h2>Проверка JSON</h2>

            <?php if (!empty($parsed['error'])): ?>
                <section class="flash flash-error">
                    <?= h($parsed['error']) ?>
                </section>
            <?php else: ?>
                <section class="flash flash-success">
                    JSON читается. Базовая структура найдена.
                </section>

                <div class="admin-report-facts">
                    <article>
                        <small>ID</small>
                        <strong><?= h($parsed['map_id']) ?></strong>
                    </article>

                    <article>
                        <small>Название</small>
                        <strong><?= h($parsed['title']) ?></strong>
                    </article>
                </div>
            <?php endif; ?>

            <?php if (!empty($submission['user_comment'])): ?>
                <div class="admin-comment-box">
                    <small>Комментарий автора</small>
                    <p><?= nl2br(h($submission['user_comment'])) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($submission['admin_comment'])): ?>
                <div class="admin-comment-box">
                    <small>Комментарий администратора</small>
                    <p><?= nl2br(h($submission['admin_comment'])) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2>Решение администратора</h2>

            <form action="map_submission_action.php" method="post" class="moderation-final-form">
                <input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>">

                <label>
                    Статус
                    <select name="status">
                        <?php foreach (map_submission_statuses() as $key => $label): ?>
                            <option value="<?= h($key) ?>" <?= $submission['status'] === $key ? 'selected' : '' ?>>
                                <?= h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Комментарий администратора
                    <textarea name="admin_comment" rows="7" maxlength="3000" placeholder="Что исправить или почему карта одобрена/отклонена"><?= h($submission['admin_comment'] ?? '') ?></textarea>
                </label>

                <button class="btn primary-action" type="submit">Сохранить решение</button>
            </form>
        </div>
    </section>

    <section class="grid2">
        <div class="panel">
            <div class="section-head">
                <h2>JSON карты</h2>
                <a
                    class="btn small"
                    href="map_submission_download.php?id=<?= (int) $submission['id'] ?>"
                >
                    Скачать <?= h($filename) ?>
                </a>
            </div>

            <pre class="tutorial-pre"><?= h($submission['json_text']) ?></pre>
        </div>

        <div class="panel">
            <h2>Как проверить карту</h2>

            <div class="map-guide-steps">
                <article>
                    <b>1. Скачай JSON</b>
                    <p>Сохрани файл как <?= h($filename) ?>.</p>
                </article>

                <article>
                    <b>2. Положи в maps/</b>
                    <p>Временно добавь файл в папку карт локально или на тестовом сервере.</p>
                </article>

                <article>
                    <b>3. Запусти tools</b>
                    <p>Проверь валидатор, preview карты и карточки.</p>
                </article>

                <article>
                    <b>4. Дай решение</b>
                    <p>Одобри, отклони или отправь на доработку.</p>
                </article>
            </div>

            <p>
                <a class="btn" href="tools.php">Открыть инструменты</a>
            </p>
        </div>
    </section>
</main>
</body>
</html>