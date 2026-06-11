<?php

require 'includes/config.php';
require 'includes/profile.php';
require 'includes/reports.php';
require 'includes/map_submissions.php';

if (is_file(__DIR__ . '/includes/notifications.php')) {
    require 'includes/notifications.php';
}

require_auth();
csrf_check();
update_current_user_presence();

$uid = (int) current_user_id();
$isModerator = user_is_moderator_or_admin($uid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jsonText = trim((string) ($_POST['json_text'] ?? ''));
    $userComment = trim((string) ($_POST['user_comment'] ?? ''));

    if (!empty($_FILES['json_file']['tmp_name']) && is_uploaded_file($_FILES['json_file']['tmp_name'])) {
        $size = (int) ($_FILES['json_file']['size'] ?? 0);

        if ($size > 300000) {
            $_SESSION['flash_error'] = 'Файл слишком большой. Максимум около 300 KB.';
            header('Location: map_submit.php');
            exit;
        }

        $jsonText = (string) file_get_contents($_FILES['json_file']['tmp_name']);
    }

    $result = create_map_submission($uid, $jsonText, $userComment);

    if (!empty($result['error'])) {
        $_SESSION['flash_error'] = $result['error'];
    } else {
        $_SESSION['flash_success'] = 'Карта отправлена на проверку';
    }

    header('Location: map_submit.php');
    exit;
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';

unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$submissions = get_user_map_submissions($uid);

?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Отправить карту · Mystery Mansion</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
<header class="top">
    <b>🗺️ Отправить карту</b>
    <nav>
        <a href="lobby.php">Лобби</a>
        <a href="map_tutorial.php">Инструкция</a>
        <a href="players.php">Игроки</a>
        <a href="profile.php">Мой профиль</a>

        <?php if ($isModerator): ?>
            <a href="admin/index.php">Админка</a>
        <?php endif; ?>

        <a href="logout.php">Выход</a>
    </nav>
</header>

<main class="layout">
    <?php if ($flashSuccess): ?>
        <section class="panel flash flash-success"><?= h($flashSuccess) ?></section>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <section class="panel flash flash-error"><?= h($flashError) ?></section>
    <?php endif; ?>

    <section class="panel hero">
        <div>
            <p class="muted-label">Пользовательские карты</p>
            <h1>Отправить карту на проверку</h1>
            <p>
                Загрузи JSON-файл или вставь JSON вручную. Администратор проверит карту и даст ответ.
                Пока карта не одобрена, она не попадает в игру.
            </p>
        </div>
    </section>

    <section class="grid2">
        <div class="panel">
            <h2>Новая заявка</h2>

            <form method="post" enctype="multipart/form-data" class="map-submit-form">
                <label>
                    JSON-файл карты
                    <input type="file" name="json_file" accept=".json,application/json">
                </label>

                <label>
                    Или вставь JSON вручную
                    <textarea name="json_text" rows="14" placeholder='{"id":"my_map","title":"Моя карта",...}'></textarea>
                </label>

                <label>
                    Комментарий авторa
                    <textarea name="user_comment" rows="4" maxlength="2000" placeholder="Что важно знать администратору: идея карты, особенности, что нужно проверить"></textarea>
                </label>

                <div class="modal-actions">
                    <button type="submit" class="btn">Отправить на проверку</button>
                    <a class="btn" href="map_tutorial.php">Открыть инструкцию</a>
                </div>
                <?= csrf_field() ?>
            </form>
        </div>

        <div class="panel">
            <h2>Как проходит проверка</h2>

            <div class="map-guide-steps">
                <article>
                    <b>1. Базовая проверка</b>
                    <p>Система проверит, что JSON читается и содержит обязательные поля.</p>
                </article>

                <article>
                    <b>2. Проверка администратором</b>
                    <p>Админ проверит карту через инструменты и посмотрит, не ломает ли она игру.</p>
                </article>

                <article>
                    <b>3. Ответ по заявке</b>
                    <p>Карта может быть одобрена, отклонена или отправлена на доработку.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="section-head">
            <h2>Мои заявки</h2>
            <small><?= count($submissions) ?> последних</small>
        </div>

        <?php if (!$submissions): ?>
            <p>Ты ещё не отправлял карты на проверку.</p>
        <?php else: ?>
            <div class="map-submission-list">
                <?php foreach ($submissions as $submission): ?>
                    <article class="map-submission-card">
                        <div>
                            <strong><?= h($submission['title'] ?: 'Без названия') ?></strong>
                            <small>
                                ID: <?= h($submission['map_id'] ?: '—') ?> ·
                                <?= h($submission['created_at']) ?>
                            </small>

                            <?php if (!empty($submission['admin_comment'])): ?>
                                <p><?= nl2br(h($submission['admin_comment'])) ?></p>
                            <?php endif; ?>
                        </div>

                        <span class="status-pill <?= h(map_submission_status_class($submission['status'])) ?>">
                            <?= h(map_submission_status_label($submission['status'])) ?>
                        </span>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php
if (function_exists('render_notification_mount')) {
    render_notification_mount();
}
?>
</body>
</html>