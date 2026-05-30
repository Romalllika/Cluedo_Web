<?php

require '../includes/config.php';
require '../includes/reports.php';

require_admin();

?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Инструменты · Админка</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>
<header class="top">
    <b>🧰 Инструменты</b>
    <nav>
        <a href="../lobby.php">Лобби</a>
        <a href="index.php">Админка</a>
        <a href="reports.php">Репорты</a>
        <a href="maps.php">Карты</a>
        <a href="../logout.php">Выход</a>
    </nav>
</header>

<main class="layout admin-layout">
    <section class="panel hero admin-hero">
        <div>
            <p class="muted-label">Admin only</p>
            <h1>Инструменты разработчика</h1>
            <p>
                Эти инструменты нужны для проверки JSON-карт, предпросмотра поля и карточек.
                Обычные игроки и модераторы сюда не допускаются.
            </p>
        </div>
    </section>

    <section class="admin-tools-grid">
        <article class="panel admin-tool-card">
            <h2>Проверка карт</h2>
            <p>Запускает валидатор JSON-карт и показывает ошибки структуры.</p>
            <a class="btn" href="../tools/validate_maps.php">Открыть валидатор</a>
        </article>

        <article class="panel admin-tool-card">
            <h2>Предпросмотр карты</h2>
            <p>Показывает игровое поле, комнаты, пути, двери и стартовые позиции.</p>
            <a class="btn" href="../tools/map_preview.php">Открыть preview</a>
        </article>

        <article class="panel admin-tool-card">
            <h2>Предпросмотр карточек</h2>
            <p>Показывает карточки персонажей, оружия и комнат выбранной карты.</p>
            <a class="btn" href="../tools/cards_preview.php">Открыть карточки</a>
        </article>
    </section>

    <section class="panel">
        <h2>Как использовать при проверке пользовательской карты</h2>

        <div class="map-guide-steps">
            <article>
                <b>1. Получить JSON</b>
                <p>Игрок присылает файл карты администратору.</p>
            </article>

            <article>
                <b>2. Положить файл в maps/</b>
                <p>На beta-этапе добавление файла делается вручную.</p>
            </article>

            <article>
                <b>3. Проверить валидатором</b>
                <p>Если есть ошибки — вернуть игроку комментарий.</p>
            </article>

            <article>
                <b>4. Посмотреть preview</b>
                <p>Проверить, что карта визуально и логически работает.</p>
            </article>

            <article>
                <b>5. Включить в админке карт</b>
                <p>Если всё хорошо — поставить тип “Пользовательская”, включить карту и выбрать видимость.</p>
            </article>
        </div>
    </section>
</main>
</body>
</html>