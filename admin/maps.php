<?php

require '../includes/config.php';
require '../includes/maps.php';
require '../includes/reports.php';
require '../includes/map_settings.php';

require_moderator_or_admin();
csrf_check();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';

unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = update_map_setting(
        (string) ($_POST['map_id'] ?? ''),
        '',
        (int) ($_POST['enabled'] ?? 0),
        (string) ($_POST['category'] ?? 'official'),
        (string) ($_POST['visibility'] ?? 'public'),
        (int) ($_POST['sort_order'] ?? 100),
        (string) ($_POST['notes'] ?? '')
    );

    if (!empty($result['error'])) {
        $_SESSION['flash_error'] = $result['error'];
    } else {
        $_SESSION['flash_success'] = 'Настройки карты сохранены';
    }

    header('Location: maps.php');
    exit;
}

$maps = get_admin_maps();

?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Карты · Админка</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>
    <header class="top">
        <b>🗺️ Карты</b>
        <nav>
            <a href="../lobby.php">Лобби</a>
            <a href="index.php">Админка</a>
            <a href="reports.php">Репорты</a>
            <a href="maps.php">Карты</a>
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
                <p class="muted-label">Админка</p>
                <h1>Карты</h1>
                <p>Управляй доступностью карт, их типом и видимостью для игроков.</p>
            </div>
        </section>

        <section class="panel">
            <div class="section-head">
                <h2>Список карт</h2>
                <small><?= count($maps) ?> карт</small>
            </div>

            <div class="admin-map-list">
                <?php foreach ($maps as $map): ?>
                    <article class="admin-map-card <?= !$map['exists_in_json'] ? 'map-missing' : '' ?>">
                        <div class="admin-map-main">
                            <div class="admin-map-title">
                                <strong><?= h($map['title']) ?></strong>

                                <span class="status-pill <?= h(map_category_class($map['category'])) ?>">
                                    <?= h(map_category_label($map['category'])) ?>
                                </span>

                                <?php if ($map['enabled']): ?>
                                    <span class="status-pill status-confirmed">Включена</span>
                                <?php else: ?>
                                    <span class="status-pill status-rejected">Выключена</span>
                                <?php endif; ?>
                            </div>

                            <small>
                                ID: <?= h($map['id']) ?> ·
                                JSON: <?= h($map['json_title']) ?> ·
                                Видимость: <?= h(map_visibility_label($map['visibility'])) ?>
                            </small>

                            <small>
                                <?= (int) $map['meta']['rooms_count'] ?> комнат ·
                                до <?= (int) $map['meta']['players_count'] ?> игроков ·
                                поле <?= (int) $map['meta']['board_w'] ?>×<?= (int) $map['meta']['board_h'] ?>
                            </small>

                            <?php if (!$map['exists_in_json']): ?>
                                <p class="muted-text">JSON-файл карты отсутствует. Новые игры с ней невозможны.</p>
                            <?php elseif (!empty($map['description'])): ?>
                                <p class="muted-text"><?= h($map['description']) ?></p>
                            <?php endif; ?>
                        </div>

                        <form method="post" class="admin-map-form">
                            <input type="hidden" name="map_id" value="<?= h($map['id']) ?>">

                            <label class="admin-map-toggle">
                                <input type="checkbox" name="enabled" value="1" <?= $map['enabled'] ? 'checked' : '' ?>>
                                <span>Включена</span>
                            </label>



                            <label>
                                Тип
                                <select name="category">
                                    <?php foreach (map_category_labels() as $key => $label): ?>
                                        <option value="<?= h($key) ?>" <?= $map['category'] === $key ? 'selected' : '' ?>>
                                            <?= h($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                Видимость
                                <select name="visibility">
                                    <?php foreach (map_visibility_labels() as $key => $label): ?>
                                        <option value="<?= h($key) ?>" <?= $map['visibility'] === $key ? 'selected' : '' ?>>
                                            <?= h($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                Позиция
                                <input name="sort_order" type="number" value="<?= (int) $map['sort_order'] ?>">
                            </label>

                            <label class="admin-map-notes">
                                Заметка
                                <textarea name="notes" rows="3" maxlength="2000"><?= h($map['notes']) ?></textarea>
                            </label>

                            <button class="btn small" type="submit">Сохранить</button>
                            <?= csrf_field() ?>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="panel">
            <h2>Как пользоваться</h2>

            <div class="admin-map-help">
                <article>
                    <b>Включена</b>
                    <p>Карту можно использовать для новых игр, если она видима пользователю.</p>
                </article>

                <article>
                    <b>Тип</b>
                    <p>Официальная, пользовательская, ивентовая, тестовая или архивная. Это бейдж и группировка.</p>
                </article>

                <article>
                    <b>Видимость</b>
                    <p>“Всем” — обычным игрокам, “Только staff” — admin/moderator, “Скрыта” — никому в создании игр.</p>
                </article>
            </div>
        </section>
    </main>
</body>

</html>