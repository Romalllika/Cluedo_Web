<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/validate_maps.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function preview_cell_key(int $x, int $y): string
{
    return $x . ':' . $y;
}

function preview_load_map(string $mapId): array
{
    $mapId = normalize_map_id($mapId);
    $path = __DIR__ . '/../maps/' . $mapId . '.json';

    if (!is_file($path)) {
        $path = __DIR__ . '/../maps/classic_mansion.json';
    }

    $json = file_get_contents($path);
    $data = json_decode((string) $json, true);

    if (!is_array($data)) {
        throw new RuntimeException('Не удалось прочитать карту: ' . $mapId);
    }

    return $data;
}

function preview_room_at(int $x, int $y, array $rooms): ?string
{
    foreach ($rooms as $name => $room) {
        if (
            $x >= (int) $room['x1'] &&
            $x <= (int) $room['x2'] &&
            $y >= (int) $room['y1'] &&
            $y <= (int) $room['y2']
        ) {
            return (string) $name;
        }
    }

    return null;
}

function preview_room_center(array $room): array
{
    return [
        (int) floor(((int) $room['x1'] + (int) $room['x2']) / 2),
        (int) floor(((int) $room['y1'] + (int) $room['y2']) / 2),
    ];
}

function preview_path_keys(array $map): array
{
    /**
     * Если в будущем мы добавим paths в JSON карты,
     * просмотрщик уже будет готов их использовать.
     */
    if (isset($map['paths']) && is_array($map['paths'])) {
        $keys = [];

        foreach ($map['paths'] as $point) {
            if (!is_array($point) || count($point) < 2) {
                continue;
            }

            $keys[preview_cell_key((int) $point[0], (int) $point[1])] = true;
        }

        return $keys;
    }

    /**
     * Пока дорожная сетка берётся из текущей игровой логики.
     */
    $keys = [];

    foreach (default_board_paths() as $point) {
        $keys[preview_cell_key((int) $point[0], (int) $point[1])] = true;
    }

    return $keys;
}

function preview_starts(): array
{
    $starts = [];

    foreach (characters() as $character) {
        $x = (int) $character['x'];
        $y = (int) $character['y'];

        $starts[preview_cell_key($x, $y)][] = [
            'name' => (string) $character['name'],
            'color' => (string) $character['color'],
        ];
    }

    return $starts;
}

$maps = available_maps();
$selectedMapId = normalize_map_id($_GET['map'] ?? 'classic_mansion');
$map = preview_load_map($selectedMapId);

$mapFile = __DIR__ . '/../maps/' . $selectedMapId . '.json';

$requiredRoomsForPreview = [
    'Кухня',
    'Бальный зал',
    'Оранжерея',
    'Столовая',
    'Бильярдная',
    'Библиотека',
    'Гостиная',
    'Холл',
    'Кабинет',
];

$characterStartsForPreview = [];

foreach (characters() as $character) {
    $characterStartsForPreview[] = [
        (int) $character['x'],
        (int) $character['y']
    ];
}

$validationResult = validate_map_file(
    $mapFile,
    __DIR__ . '/../maps',
    $requiredRoomsForPreview,
    $characterStartsForPreview
);

$board = $map['board'] ?? [];
$width = (int) ($board['w'] ?? 17);
$height = (int) ($board['h'] ?? 17);
$rooms = $map['rooms'] ?? [];

if (!is_array($rooms)) {
    $rooms = [];
}

$pathKeys = preview_path_keys($map);
$starts = preview_starts();

$doorByCell = [];
$roomLabelByCell = [];
$secretLines = [];

foreach ($rooms as $roomName => $room) {
    if (!is_array($room)) {
        continue;
    }

    $door = $room['door'] ?? null;

    if (is_array($door) && count($door) >= 2) {
        $doorByCell[preview_cell_key((int) $door[0], (int) $door[1])] = (string) $roomName;
    }

    [$cx, $cy] = preview_room_center($room);
    $roomLabelByCell[preview_cell_key($cx, $cy)] = (string) $roomName;

    $secret = $room['secret'] ?? null;

    if (is_string($secret) && $secret !== '' && isset($rooms[$secret])) {
        $secretLines[] = [
            'from' => (string) $roomName,
            'to' => $secret,
        ];
    }
}

?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <title>Просмотр карт</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, Arial, sans-serif;
            background: radial-gradient(circle at top, #29324a, #0d1020 55%, #080910);
            color: #eef2ff;
            padding: 24px;
        }

        a {
            color: inherit;
        }

        .page {
            max-width: 1280px;
            margin: 0 auto;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 20px;
        }

        .panel {
            background: rgba(255, 255, 255, .09);
            border: 1px solid rgba(255, 255, 255, .14);
            border-radius: 22px;
            box-shadow: 0 24px 80px rgba(0, 0, 0, .28);
            backdrop-filter: blur(18px);
        }

        .head {
            padding: 20px;
        }

        .head h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .head p {
            margin: 0;
            color: rgba(238, 242, 255, .72);
        }

        .select-box {
            padding: 20px;
            min-width: 300px;
        }

        select {
            width: 100%;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, .22);
            background: rgba(0, 0, 0, .25);
            color: #fff;
            font-size: 15px;
        }

        .layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 20px;
            align-items: start;
        }

        .board-wrap {
            padding: 20px;
            overflow: auto;
        }

        .board {
            display: grid;
            gap: 2px;
            width: max-content;
            margin: 0 auto;
            position: relative;
        }

        .cell {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, .08);
            background: rgba(255, 255, 255, .035);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: rgba(238, 242, 255, .62);
            overflow: hidden;
        }

        .cell.path {
            background: rgba(170, 190, 255, .18);
            border-color: rgba(170, 190, 255, .28);
        }

        .cell.room {
            background: rgba(255, 255, 255, .16);
            border-color: rgba(255, 255, 255, .24);
        }

        .cell.door {
            outline: 2px solid #ffd166;
            outline-offset: -3px;
        }

        .cell.start {
            box-shadow: inset 0 0 0 2px rgba(112, 227, 140, .8);
        }

        .coord {
            position: absolute;
            left: 4px;
            top: 3px;
            font-size: 8px;
            opacity: .35;
        }

        .room-label {
            position: absolute;
            inset: 3px;
            display: grid;
            place-items: center;
            text-align: center;
            font-weight: 800;
            line-height: 1.05;
            color: #fff;
            text-shadow: 0 2px 8px rgba(0, 0, 0, .9);
            z-index: 2;
        }

        .door-mark {
            position: absolute;
            right: 3px;
            bottom: 2px;
            font-size: 17px;
            z-index: 3;
        }

        .start-dot {
            width: 14px;
            height: 14px;
            border-radius: 999px;
            border: 2px solid rgba(255, 255, 255, .9);
            z-index: 4;
        }

        .side {
            padding: 18px;
        }

        .side h2 {
            margin: 0 0 12px;
        }

        .meta {
            display: grid;
            gap: 10px;
            margin-bottom: 22px;
        }

        .meta div {
            padding: 10px 12px;
            border-radius: 14px;
            background: rgba(0, 0, 0, .18);
            border: 1px solid rgba(255, 255, 255, .1);
        }

        .room-list,
        .secret-list,
        .legend {
            display: grid;
            gap: 8px;
            margin-bottom: 22px;
        }

        .room-list,
        .secret-list,
        .legend {
            display: grid;
            gap: 8px;
            margin-bottom: 22px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 20px;
            margin-top: 20px;
        }

        .details-panel {
            padding: 18px;
        }

        .details-panel h2 {
            margin: 0 0 12px;
        }

        .room-list.compact {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-bottom: 0;
        }

        .secret-list.compact {
            margin-bottom: 0;
        }

        .muted {
            color: rgba(238, 242, 255, .62);
        }

        .warn {
            color: #ffd166;
        }

        .ok {
            color: #70e38c;
        }

        .err {
            color: #ff7b7b;
        }

        .validation-box {
            margin-bottom: 22px;
            padding: 14px;
            border-radius: 16px;
            background: rgba(0, 0, 0, .18);
            border: 1px solid rgba(255, 255, 255, .12);
        }

        .validation-box.ok-box {
            border-color: rgba(112, 227, 140, .45);
        }

        .validation-box.error-box {
            border-color: rgba(255, 123, 123, .55);
        }

        .validation-box ul {
            margin: 8px 0 0;
            padding-left: 18px;
        }

        .validation-stats {
            display: grid;
            gap: 6px;
            margin-top: 10px;
            font-size: 14px;
        }

        .validation-stats div {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 6px 0;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 14px;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .18);
            text-decoration: none;
            color: #fff;
        }

        @media (max-width: 1000px) {
            .top,
            .layout,
            .details-grid {
                grid-template-columns: 1fr;
                display: grid;
            }

            .room-list.compact {
                grid-template-columns: 1fr;
            }

            .select-box {
                min-width: 0;
            }
        }

        @media (max-width: 1250px) {
            .room-list.compact {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .room-list.compact {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="top">
            <section class="panel head">
                <h1>Просмотр карт</h1>
                <p>
                    Визуальная проверка JSON-карт: комнаты, двери, коридоры, стартовые позиции и секретные проходы.
                </p>
            </section>

            <section class="panel select-box">
                <form method="get">
                    <label for="map"><b>Карта</b></label>
                    <select name="map" id="map" onchange="this.form.submit()">
                        <?php foreach ($maps as $mapInfo): ?>
                            <option
                                value="<?= e((string) $mapInfo['id']) ?>"
                                <?= $selectedMapId === $mapInfo['id'] ? 'selected' : '' ?>
                            >
                                <?= e((string) $mapInfo['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </section>
        </div>

        <div class="layout">
            <section class="panel board-wrap">
                <div
                    class="board"
                    style="grid-template-columns: repeat(<?= $width ?>, 42px);"
                >
                    <?php for ($y = 0; $y < $height; $y++): ?>
                        <?php for ($x = 0; $x < $width; $x++): ?>
                            <?php
                            $key = preview_cell_key($x, $y);
                            $roomName = preview_room_at($x, $y, $rooms);
                            $isPath = isset($pathKeys[$key]);
                            $isDoor = isset($doorByCell[$key]);
                            $isStart = isset($starts[$key]);

                            $classes = ['cell'];

                            if ($roomName !== null) {
                                $classes[] = 'room';
                            }

                            if ($isPath) {
                                $classes[] = 'path';
                            }

                            if ($isDoor) {
                                $classes[] = 'door';
                            }

                            if ($isStart) {
                                $classes[] = 'start';
                            }
                            ?>

                            <div class="<?= e(implode(' ', $classes)) ?>" title="<?= e($x . ',' . $y . ($roomName ? ' · ' . $roomName : '')) ?>">
                                <span class="coord"><?= $x ?>,<?= $y ?></span>

                                <?php if (isset($roomLabelByCell[$key])): ?>
                                    <span class="room-label"><?= e($roomLabelByCell[$key]) ?></span>
                                <?php endif; ?>

                                <?php if ($isDoor): ?>
                                    <span class="door-mark">🚪</span>
                                <?php endif; ?>

                                <?php if ($isStart): ?>
                                    <?php foreach ($starts[$key] as $start): ?>
                                        <span
                                            class="start-dot"
                                            title="<?= e($start['name']) ?>"
                                            style="background: <?= e($start['color']) ?>;"
                                        ></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    <?php endfor; ?>
                </div>
            </section>

            <aside class="panel side">
                <h2><?= e((string) ($map['title'] ?? $selectedMapId)) ?></h2>
                
                <?php
                $validationErrors = $validationResult['errors'] ?? [];
                $validationWarnings = $validationResult['warnings'] ?? [];
                $validationStats = $validationResult['stats'] ?? [];
                $validationOk = !$validationErrors;
                ?>

                <div class="validation-box <?= $validationOk ? 'ok-box' : 'error-box' ?>">
                    <b class="<?= $validationOk ? 'ok' : 'err' ?>">
                        <?= $validationOk ? 'Проверка карты: OK' : 'Проверка карты: ERROR' ?>
                    </b>

                    <?php if ($validationStats): ?>
                        <div class="validation-stats">
                            <div>
                                <span class="muted">Поле</span>
                                <b><?= e((string) ($validationStats['board'] ?? 'unknown')) ?></b>
                            </div>
                            <div>
                                <span class="muted">Комнаты</span>
                                <b><?= (int) ($validationStats['rooms'] ?? 0) ?></b>
                            </div>
                            <div>
                                <span class="muted">Коридоры</span>
                                <b><?= (int) ($validationStats['paths'] ?? 0) ?></b>
                            </div>
                            <div>
                                <span class="muted">Двери</span>
                                <b><?= (int) ($validationStats['doors'] ?? 0) ?></b>
                            </div>
                            <div>
                                <span class="muted">Secret</span>
                                <b><?= (int) ($validationStats['secrets'] ?? 0) ?></b>
                            </div>
                            <div>
                                <span class="muted">Старты</span>
                                <b><?= (int) ($validationStats['starts'] ?? 0) ?></b>
                            </div>
                            <div>
                                <span class="muted">Paths в JSON</span>
                                <b><?= !empty($validationStats['has_explicit_paths']) ? 'да' : 'нет' ?></b>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($validationErrors): ?>
                        <h3 class="err">Ошибки</h3>
                        <ul>
                            <?php foreach ($validationErrors as $error): ?>
                                <li class="err"><?= e((string) $error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($validationWarnings): ?>
                        <h3 class="warn">Предупреждения</h3>
                        <ul>
                            <?php foreach ($validationWarnings as $warning): ?>
                                <li class="warn"><?= e((string) $warning) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="meta">
                    <div>
                        <span class="muted">ID:</span>
                        <b><?= e((string) ($map['id'] ?? $selectedMapId)) ?></b>
                    </div>
                    <div>
                        <span class="muted">Размер:</span>
                        <b><?= $width ?> × <?= $height ?></b>
                    </div>
                    <div>
                        <span class="muted">Описание:</span><br>
                        <?= e((string) ($map['description'] ?? '—')) ?>
                    </div>
                </div>

                <h2>Легенда</h2>
                <div class="legend">
                    <div class="legend-item">🚪 — дверь комнаты</div>
                    <div class="legend-item"><span class="ok">●</span> — стартовая позиция персонажа</div>
                    <div class="legend-item">Светлая клетка — комната</div>
                    <div class="legend-item">Синеватая клетка — коридор/доступный путь</div>
                </div>

                
                <div class="actions">
                    <a class="btn" href="../lobby.php">← В лобби</a>
                    <a class="btn" href="validate_maps.php">Проверить карты</a>
                </div>
            </aside>
        </div>
            <div class="details-grid">
            <section class="panel details-panel">
                <h2>Комнаты</h2>

                <div class="room-list compact">
                    <?php foreach ($rooms as $roomName => $room): ?>
                        <?php
                        $door = $room['door'] ?? ['?', '?'];
                        $theme = (string) ($room['theme'] ?? 'default');
                        ?>
                        <div class="room-item">
                            <b><?= e((string) $roomName) ?></b><br>
                            <span class="muted">
                                x<?= (int) $room['x1'] ?>-<?= (int) $room['x2'] ?>,
                                y<?= (int) $room['y1'] ?>-<?= (int) $room['y2'] ?> ·
                                дверь [<?= e((string) $door[0]) ?>,<?= e((string) $door[1]) ?>] ·
                                theme: <?= e($theme) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="panel details-panel">
                <h2>Секретные проходы</h2>

                <div class="secret-list compact">
                    <?php if (!$secretLines): ?>
                        <div class="secret-item muted">Нет секретных проходов</div>
                    <?php endif; ?>

                    <?php foreach ($secretLines as $line): ?>
                        <div class="secret-item">
                            <b><?= e($line['from']) ?></b>
                            →
                            <b><?= e($line['to']) ?></b>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</body>

</html>