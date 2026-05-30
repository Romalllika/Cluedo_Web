<?php

require 'includes/config.php';
require 'includes/profile.php';
require 'includes/reports.php';

if (is_file(__DIR__ . '/includes/notifications.php')) {
    require 'includes/notifications.php';
}

require_auth();
update_current_user_presence();

$uid = (int) current_user_id();
$isModerator = user_is_moderator_or_admin($uid);

$tutorialPath = __DIR__ . '/map_tutorial.txt';
$tutorialText = is_file($tutorialPath)
    ? file_get_contents($tutorialPath)
    : 'Инструкция по созданию карт пока не найдена.';

?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Создание карт · Mystery Mansion</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
    <header class="top">
        <b>🗺️ Создание карт</b>
        <nav>
            <a href="lobby.php">Лобби</a>
            <a href="players.php">Игроки</a>
            <a href="profile.php">Мой профиль</a>

            <?php if ($isModerator): ?>
                <a href="admin/index.php">Админка</a>
            <?php endif; ?>

            <a href="logout.php">Выход</a>
        </nav>
    </header>

    <main class="layout">
        <section class="panel hero">
            <div>
                <p class="muted-label">Пользовательские карты</p>
                <h1>Как создать свою карту</h1>
                <p>
                    Карта создаётся как JSON-файл. Перед отправкой на проверку её нужно внимательно проверить:
                    ID, карточки, комнаты, двери, коридоры, стартовые позиции и секретные проходы.
                </p>
                <p>
                    <a class="btn" href="map_submit.php">Отправить карту на проверку</a>
                </p>
            </div>
        </section>

        <section class="grid2">
            <div class="panel">
                <h2>Как будет проходить проверка</h2>

                <div class="map-guide-steps">
                    <article>
                        <b>1. Создай JSON</b>
                        <p>Собери карту по инструкции ниже.</p>
                    </article>

                    <article>
                        <b>2. Проверь структуру</b>
                        <p>Убедись, что ID карты совпадает с названием файла, а все обязательные блоки заполнены.</p>
                    </article>

                    <article>
                        <b>3. Отправь администратору</b>
                        <p>На текущем этапе карту нужно передать администратору вручную.</p>
                    </article>

                    <article>
                        <b>4. Карта попадёт на проверку</b>
                        <p>Администратор проверит карту через инструменты и включит её, если она готова.</p>
                    </article>
                </div>
            </div>

            <div class="panel">
                <h2>Что важно</h2>

                <ul class="clean-list">
                    <li>JSON должен быть валидным.</li>
                    <li>ID внутри карты должен совпадать с названием файла.</li>
                    <li>У каждой комнаты должна быть карточка.</li>
                    <li>Стартовые позиции должны быть на игровом поле.</li>
                    <li>Коридоры и двери должны быть связаны логично.</li>
                    <li>Карта не должна ломать игровой баланс.</li>
                </ul>
            </div>
        </section>

        <section class="panel">
            <div class="section-head">
                <h2>Полная инструкция</h2>
                <!-- <small>Файл map_tutorial.txt</small> -->
            </div>

            <pre class="tutorial-pre"><?= h($tutorialText) ?></pre>
        </section>
    </main>

    <?php
    if (function_exists('render_notification_mount')) {
        render_notification_mount();
    }
    ?>
</body>

</html>