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

?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Как играть · Mystery Mansion</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
<header class="top">
    <b>📘 Как играть</b>
    <nav>
        <a href="lobby.php">Лобби</a>
        <a href="players.php">Игроки</a>
        <a href="map_tutorial.php">Создать карту</a>
        <a href="map_submit.php">Отправить карту</a>
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
            <p class="muted-label">Краткое обучение</p>
            <h1>Как играть в Mystery Mansion</h1>
            <p>
                Твоя цель — первым раскрыть тайну: кто совершил преступление, каким предметом и в какой комнате.
                Для этого нужно перемещаться по особняку, делать предположения, собирать информацию и в нужный момент
                выдвинуть финальное обвинение.
            </p>
        </div>
    </section>

    <section class="grid2">
        <div class="panel">
            <h2>Цель игры</h2>

            <div class="how-steps">
                <article>
                    <b>1. В конверте спрятано решение</b>
                    <p>В начале игры случайно выбираются преступник, оружие и комната. Эти карты никто не получает.</p>
                </article>

                <article>
                    <b>2. Остальные карты раздаются игрокам</b>
                    <p>Если карта у тебя или её показал другой игрок — значит её нет в конверте.</p>
                </article>

                <article>
                    <b>3. Нужно исключать варианты</b>
                    <p>Через предположения и показанные карты ты постепенно понимаешь, какие варианты невозможны.</p>
                </article>

                <article>
                    <b>4. Побеждает точное обвинение</b>
                    <p>Когда уверен, сделай финальное обвинение. Если оно верное — ты выигрываешь.</p>
                </article>
            </div>
        </div>

        <div class="panel">
            <h2>Важно не путать</h2>

            <div class="how-warning">
                <h3>Предположение ≠ обвинение</h3>
                <p>
                    <b>Предположение</b> — обычное действие в комнате. Оно помогает получить информацию.
                </p>
                <p>
                    <b>Обвинение</b> — финальная попытка победить. Если ошибёшься, выбываешь из расследования.
                </p>
            </div>

            <ul class="clean-list">
                <li>Предположение можно делать только в комнате.</li>
                <li>В предположении комната обычно равна той, где ты находишься.</li>
                <li>После предположения другие игроки могут показать одну карту.</li>
                <li>После предположения можно завершить ход или рискнуть обвинением.</li>
                <li>Неверное обвинение выбивает игрока из расследования.</li>
            </ul>
        </div>
    </section>

    <section class="panel">
        <h2>Как проходит ход</h2>

        <div class="how-flow">
            <article>
                <span>🎲</span>
                <div>
                    <b>Бросок кубиков</b>
                    <p>В начале своего хода брось кубики. Количество очков определяет, насколько далеко можно пройти.</p>
                </div>
            </article>

            <article>
                <span>🚶</span>
                <div>
                    <b>Перемещение</b>
                    <p>Выбери доступную клетку на поле. Если попадёшь в комнату, сможешь сделать предположение.</p>
                </div>
            </article>

            <article>
                <span>🕵️</span>
                <div>
                    <b>Предположение</b>
                    <p>Назови подозреваемого, оружие и комнату. Подозреваемый переместится в эту комнату.</p>
                </div>
            </article>

            <article>
                <span>🃏</span>
                <div>
                    <b>Опровержение</b>
                    <p>Другие игроки по очереди пытаются показать тебе одну карту из твоего предположения.</p>
                </div>
            </article>

            <article>
                <span>⚖️</span>
                <div>
                    <b>Решение после предположения</b>
                    <p>После проверки ты можешь завершить ход или сделать финальное обвинение.</p>
                </div>
            </article>

            <article>
                <span>🏆</span>
                <div>
                    <b>Победа или выбывание</b>
                    <p>Верное обвинение заканчивает игру победой. Неверное — выбивает тебя из расследования.</p>
                </div>
            </article>
        </div>
    </section>

    <section class="grid2">
        <div class="panel">
            <h2>Карты в руке</h2>

            <p>
                Карты в твоей руке точно не лежат в конверте. Используй их как доказательства для исключения вариантов.
            </p>

            <div class="how-note">
                Если у тебя есть карта “Кухня”, значит преступление не произошло на кухне.
            </div>
        </div>

        <div class="panel">
            <h2>Журнал и блокнот</h2>

            <p>
                Журнал показывает события матча. Блокнот нужен для личных заметок и исключения вариантов.
                Не полагайся только на память — отмечай, кто что мог показать.
            </p>

            <div class="how-note">
                Хорошая стратегия — отмечать не только показанные карты, но и ситуации, когда игрок не смог ничего показать.
            </div>
        </div>
    </section>

    <section class="panel">
        <h2>Частые ошибки новичков</h2>

        <div class="how-mistakes">
            <article>
                <b>Слишком раннее обвинение</b>
                <p>Не обвиняй, если просто “кажется”. Ошибка выбьет тебя из игры.</p>
            </article>

            <article>
                <b>Игнорирование чужих ответов</b>
                <p>Даже если карту показали не тебе, сам факт показа важен: у игрока была хотя бы одна карта из предположения.</p>
            </article>

            <article>
                <b>Путаница с комнатой</b>
                <p>Предположение обычно связано с комнатой, где сейчас стоит твой персонаж.</p>
            </article>

            <article>
                <b>Неиспользование тайных проходов</b>
                <p>Если комната имеет тайный проход, он может быстро перенести тебя в другую часть карты.</p>
            </article>
        </div>
    </section>

    <section class="panel">
        <div class="section-head">
            <h2>Готов играть</h2>
            <a class="btn primary-action" href="lobby.php">Вернуться в лобби</a>
        </div>
    </section>
</main>

<?php
if (function_exists('render_notification_mount')) {
    render_notification_mount();
}
?>
</body>
</html>