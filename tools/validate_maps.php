<?php

declare(strict_types=1);

/**
 * Валидатор JSON-карт.
 *
 * Запуск:
 *   php tools/validate_maps.php
 *
 * Или через браузер:
 *   /tools/validate_maps.php
 */

$root = dirname(__DIR__);
$mapsDir = $root . '/maps';

$requiredRooms = [
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

$characterStarts = [
    [8, 9],
    [7, 9],
    [9, 9],
    [8, 8],
    [7, 8],
    [9, 8],
];

function add_error(array &$errors, string $message): void
{
    $errors[] = $message;
}

function cell_key(int $x, int $y): string
{
    return $x . ':' . $y;
}

function default_path_keys(array $characterStarts): array
{
    $paths = [];

    /**
     * Это должно соответствовать board_paths() из includes/data.php.
     * Сейчас дорожная сетка у всех карт одна.
     */
    for ($y = 4; $y <= 13; $y++) {
        for ($x = 5; $x <= 11; $x++) {
            $paths[cell_key($x, $y)] = true;
        }
    }

    for ($x = 5; $x <= 11; $x++) {
        $paths[cell_key($x, 13)] = true;
    }

    foreach ($characterStarts as [$x, $y]) {
        $paths[cell_key((int) $x, (int) $y)] = true;
    }

    return $paths;
}

function point_in_room(int $x, int $y, array $room): bool
{
    return
        $x >= (int) $room['x1'] &&
        $x <= (int) $room['x2'] &&
        $y >= (int) $room['y1'] &&
        $y <= (int) $room['y2'];
}

function point_in_any_room(int $x, int $y, array $rooms): bool
{
    foreach ($rooms as $room) {
        if (point_in_room($x, $y, $room)) {
            return true;
        }
    }

    return false;
}

function rooms_overlap(array $a, array $b): bool
{
    return !(
        (int) $a['x2'] < (int) $b['x1'] ||
        (int) $b['x2'] < (int) $a['x1'] ||
        (int) $a['y2'] < (int) $b['y1'] ||
        (int) $b['y2'] < (int) $a['y1']
    );
}

function validate_map_file(
    string $file,
    string $mapsDir,
    array $requiredRooms,
    array $characterStarts
): array {
    $errors = [];
    $warnings = [];

    $baseName = basename($file, '.json');
    $raw = file_get_contents($file);

    if ($raw === false) {
        return [
            'title' => $baseName,
            'errors' => ['Файл не читается'],
            'warnings' => []
        ];
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        return [
            'title' => $baseName,
            'errors' => ['JSON невалидный: ' . json_last_error_msg()],
            'warnings' => []
        ];
    }

    $id = trim((string) ($data['id'] ?? ''));
    $title = (string) ($data['title'] ?? $id ?: $baseName);

    if ($id === '') {
        add_error($errors, 'Не указан id карты');
    } elseif ($id !== $baseName) {
        add_error($errors, "id карты `$id` не совпадает с именем файла `$baseName.json`");
    }

    if (trim((string) ($data['title'] ?? '')) === '') {
        add_error($errors, 'Не указан title карты');
    }

    $board = $data['board'] ?? null;

    if (!is_array($board)) {
        add_error($errors, 'Не указан объект board');
        $w = 17;
        $h = 17;
    } else {
        $w = (int) ($board['w'] ?? 0);
        $h = (int) ($board['h'] ?? 0);

        if ($w <= 0 || $h <= 0) {
            add_error($errors, 'board.w и board.h должны быть положительными числами');
            $w = 17;
            $h = 17;
        }
    }

    $rooms = $data['rooms'] ?? null;

    if (!is_array($rooms)) {
        add_error($errors, 'Не указан объект rooms');
        $rooms = [];
    }

    foreach ($requiredRooms as $roomName) {
        if (!isset($rooms[$roomName])) {
            add_error($errors, "Отсутствует обязательная комната: `$roomName`");
        }
    }

    foreach ($rooms as $roomName => $room) {
        if (!is_array($room)) {
            add_error($errors, "Комната `$roomName`: описание должно быть объектом");
            continue;
        }

        foreach (['x1', 'y1', 'x2', 'y2'] as $key) {
            if (!array_key_exists($key, $room)) {
                add_error($errors, "Комната `$roomName`: отсутствует `$key`");
            }
        }

        if (!isset($room['x1'], $room['y1'], $room['x2'], $room['y2'])) {
            continue;
        }

        $x1 = (int) $room['x1'];
        $y1 = (int) $room['y1'];
        $x2 = (int) $room['x2'];
        $y2 = (int) $room['y2'];

        if ($x1 > $x2 || $y1 > $y2) {
            add_error($errors, "Комната `$roomName`: некорректный прямоугольник");
            continue;
        }

        if ($x1 < 0 || $y1 < 0 || $x2 >= $w || $y2 >= $h) {
            add_error($errors, "Комната `$roomName`: выходит за границы поля");
        }

        if (!isset($room['door']) || !is_array($room['door']) || count($room['door']) < 2) {
            add_error($errors, "Комната `$roomName`: отсутствует корректная door");
            continue;
        }

        $dx = (int) $room['door'][0];
        $dy = (int) $room['door'][1];

        $doorInsideRoom =
            $dx >= $x1 &&
            $dx <= $x2 &&
            $dy >= $y1 &&
            $dy <= $y2;

        $doorOnBorder =
            $doorInsideRoom &&
            ($dx === $x1 || $dx === $x2 || $dy === $y1 || $dy === $y2);

        if (!$doorOnBorder) {
            add_error($errors, "Комната `$roomName`: дверь [$dx,$dy] должна быть на границе комнаты");
        }
    }

    $roomNames = array_keys($rooms);

    for ($i = 0; $i < count($roomNames); $i++) {
        for ($j = $i + 1; $j < count($roomNames); $j++) {
            $aName = $roomNames[$i];
            $bName = $roomNames[$j];

            if (
                is_array($rooms[$aName]) &&
                is_array($rooms[$bName]) &&
                isset(
                    $rooms[$aName]['x1'],
                    $rooms[$aName]['y1'],
                    $rooms[$aName]['x2'],
                    $rooms[$aName]['y2'],
                    $rooms[$bName]['x1'],
                    $rooms[$bName]['y1'],
                    $rooms[$bName]['x2'],
                    $rooms[$bName]['y2']
                ) &&
                rooms_overlap($rooms[$aName], $rooms[$bName])
            ) {
                add_error($errors, "Комнаты `$aName` и `$bName` пересекаются");
            }
        }
    }

    $pathKeys = default_path_keys($characterStarts);

    foreach ($rooms as $roomName => $room) {
        if (
            !is_array($room) ||
            !isset($room['door']) ||
            !is_array($room['door']) ||
            count($room['door']) < 2
        ) {
            continue;
        }

        $dx = (int) $room['door'][0];
        $dy = (int) $room['door'][1];

        $neighbors = [
            [$dx + 1, $dy],
            [$dx - 1, $dy],
            [$dx, $dy + 1],
            [$dx, $dy - 1],
        ];

        $hasEntry = false;

        foreach ($neighbors as [$nx, $ny]) {
            if (
                isset($pathKeys[cell_key($nx, $ny)]) &&
                !point_in_any_room($nx, $ny, $rooms)
            ) {
                $hasEntry = true;
                break;
            }
        }

        if (!$hasEntry) {
            add_error($errors, "Комната `$roomName`: у двери [$dx,$dy] нет соседней клетки коридора");
        }
    }

    foreach ($rooms as $roomName => $room) {
        if (!is_array($room)) {
            continue;
        }

        $secret = $room['secret'] ?? null;

        if ($secret === null || $secret === '') {
            continue;
        }

        if (!isset($rooms[$secret])) {
            add_error($errors, "Комната `$roomName`: secret ведёт в несуществующую комнату `$secret`");
            continue;
        }

        $back = $rooms[$secret]['secret'] ?? null;

        if ($back !== $roomName) {
            $warnings[] = "Комната `$roomName`: secret ведёт в `$secret`, но обратный secret = `" . (string) $back . "`";
        }
    }

    return [
        'title' => $title,
        'errors' => $errors,
        'warnings' => $warnings,
    ];
}

$files = glob($mapsDir . '/*.json') ?: [];
sort($files);

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Проверка карт</title>';
    echo '<style>
        body{font-family:Arial,sans-serif;background:#101322;color:#eef2ff;padding:24px}
        .ok{color:#70e38c}.err{color:#ff7b7b}.warn{color:#ffd166}
        section{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);border-radius:16px;padding:16px;margin:12px 0}
        code{background:rgba(0,0,0,.25);padding:2px 6px;border-radius:6px}
    </style>';
    echo '<h1>Проверка JSON-карт</h1>';
}

$hasErrors = false;

foreach ($files as $file) {
    $result = validate_map_file($file, $mapsDir, $requiredRooms, $characterStarts);
    $name = basename($file);

    if ($result['errors']) {
        $hasErrors = true;
    }

    if ($isCli) {
        echo $name . ': ' . ($result['errors'] ? 'ERROR' : 'OK') . PHP_EOL;

        foreach ($result['errors'] as $error) {
            echo '  [ERROR] ' . $error . PHP_EOL;
        }

        foreach ($result['warnings'] as $warning) {
            echo '  [WARN] ' . $warning . PHP_EOL;
        }

        continue;
    }

    echo '<section>';
    echo '<h2><code>' . htmlspecialchars($name) . '</code> — ' . htmlspecialchars($result['title']) . '</h2>';

    if (!$result['errors'] && !$result['warnings']) {
        echo '<p class="ok">OK: ошибок не найдено.</p>';
    }

    if ($result['errors']) {
        echo '<h3 class="err">Ошибки</h3><ul>';

        foreach ($result['errors'] as $error) {
            echo '<li class="err">' . htmlspecialchars($error) . '</li>';
        }

        echo '</ul>';
    }

    if ($result['warnings']) {
        echo '<h3 class="warn">Предупреждения</h3><ul>';

        foreach ($result['warnings'] as $warning) {
            echo '<li class="warn">' . htmlspecialchars($warning) . '</li>';
        }

        echo '</ul>';
    }

    echo '</section>';
}

if ($isCli) {
    exit($hasErrors ? 1 : 0);
}

echo $hasErrors
    ? '<h2 class="err">Есть ошибки. Эти карты лучше не использовать в партиях.</h2>'
    : '<h2 class="ok">Все карты прошли проверку.</h2>';