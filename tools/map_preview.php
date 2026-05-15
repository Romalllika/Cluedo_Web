<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/maps.php';
require_once __DIR__ . '/../includes/movement.php';
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

function preview_starts(array $startsByName): array
{
    $starts = [];
    $colors = [];

    foreach (characters() as $character) {
        $colors[$character['name']] = (string) $character['color'];
    }

    foreach ($startsByName as $name => [$x, $y]) {
        $x = (int) $x;
        $y = (int) $y;

        $starts[preview_cell_key($x, $y)][] = [
            'name' => (string) $name,
            'color' => $colors[$name] ?? '#f5c542',
        ];
    }

    return $starts;
}

function preview_character_start_list(array $startsByName): array
{
    $starts = [];
    $colors = [];

    foreach (characters() as $character) {
        $colors[$character['name']] = (string) $character['color'];
    }

    foreach ($startsByName as $name => [$x, $y]) {
        $starts[] = [
            'name' => (string) $name,
            'x' => (int) $x,
            'y' => (int) $y,
            'color' => $colors[$name] ?? '#f5c542',
        ];
    }

    return $starts;
}

function preview_distances_from(int $sx, int $sy, array $pathKeys): array
{
    $startKey = preview_cell_key($sx, $sy);

    if (!isset($pathKeys[$startKey])) {
        return [];
    }

    $dist = [$startKey => 0];
    $queue = [[$sx, $sy]];

    for ($i = 0; $i < count($queue); $i++) {
        [$x, $y] = $queue[$i];
        $base = $dist[preview_cell_key($x, $y)];

        foreach (neighbor_cells($x, $y) as [$nx, $ny]) {
            $key = preview_cell_key($nx, $ny);

            if (!isset($pathKeys[$key]) || isset($dist[$key])) {
                continue;
            }

            $dist[$key] = $base + 1;
            $queue[] = [$nx, $ny];
        }
    }

    return $dist;
}

function preview_room_distance_from_start(array $room, array $rooms, array $pathKeys, array $dist): ?int
{
    $entries = door_entry_cells($room, $pathKeys, $rooms);

    if (!$entries) {
        return null;
    }

    $best = null;

    foreach ($entries as $entryKey => $entryPoint) {
        if (!isset($dist[$entryKey])) {
            continue;
        }

        /**
         * +1 — вход из клетки коридора в комнату.
         * Это соответствует текущей игровой логике distance_to_room().
         */
        $value = $dist[$entryKey] + 1;

        if ($best === null || $value < $best) {
            $best = $value;
        }
    }

    return $best;
}

function preview_balance_analysis(array $rooms, array $pathKeys, array $starts): array
{
    $roomRows = [];
    $allAverages = [];

    foreach ($rooms as $roomName => $room) {
        if (!is_array($room)) {
            continue;
        }

        $distances = [];

        foreach ($starts as $start) {
            $dist = preview_distances_from((int) $start['x'], (int) $start['y'], $pathKeys);
            $distanceToRoom = preview_room_distance_from_start($room, $rooms, $pathKeys, $dist);

            if ($distanceToRoom !== null) {
                $distances[] = $distanceToRoom;
            }
        }

        if (!$distances) {
            $roomRows[] = [
                'room' => (string) $roomName,
                'min' => null,
                'avg' => null,
                'max' => null,
                'spread' => null,
                'status' => 'unreachable',
                'note' => 'Комната недостижима от стартовых позиций',
            ];

            continue;
        }

        $min = min($distances);
        $max = max($distances);
        $avg = array_sum($distances) / count($distances);
        $spread = $max - $min;

        $status = 'ok';
        $note = 'OK';

        /**
         * Пороги пока мягкие и технические.
         * Позже их можно вынести в конфиг допуска карт.
         */
        if ($min <= 2) {
            $status = 'warn';
            $note = 'Слишком близко к старту';
        }

        if ($avg >= 12) {
            $status = 'warn';
            $note = 'Средняя дистанция высокая';
        }

        if ($spread >= 6) {
            $status = 'warn';
            $note = 'Большой разброс между стартами';
        }

        $roomRows[] = [
            'room' => (string) $roomName,
            'min' => $min,
            'avg' => round($avg, 1),
            'max' => $max,
            'spread' => $spread,
            'status' => $status,
            'note' => $note,
        ];

        $allAverages[] = $avg;
    }

    $summary = [
        'rooms' => count($roomRows),
        'avg_min' => null,
        'avg_max' => null,
        'avg_spread' => null,
        'status' => 'ok',
        'note' => 'OK',
    ];

    if ($allAverages) {
        $summary['avg_min'] = round(min($allAverages), 1);
        $summary['avg_max'] = round(max($allAverages), 1);
        $summary['avg_spread'] = round(max($allAverages) - min($allAverages), 1);

        if ($summary['avg_spread'] >= 5) {
            $summary['status'] = 'warn';
            $summary['note'] = 'Есть заметный разброс средней удалённости комнат';
        }
    }

    return [
        'summary' => $summary,
        'rooms' => $roomRows,
    ];
}

$maps = available_maps();
$selectedMapId = normalize_map_id($_GET['map'] ?? 'classic_mansion');
$map = preview_load_map($selectedMapId);

$mapFile = __DIR__ . '/../maps/' . $selectedMapId . '.json';
$relativeMapFile = 'maps/' . $selectedMapId . '.json';
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

$startsByNameForPreview = character_starts_from_config($map);
$characterStartsForPreview = array_values($startsByNameForPreview);

$validationResult = validate_map_file(
    $mapFile,
    __DIR__ . '/../maps',
    $requiredRoomsForPreview,
    default_character_starts()
);

$validationErrors = $validationResult['errors'] ?? [];
$validationWarnings = $validationResult['warnings'] ?? [];
$validationStats = $validationResult['stats'] ?? [];
$validationOk = !$validationErrors;

$errorCells = [];
$errorRooms = [];

foreach ($validationErrors as $error) {
    $error = (string) $error;

    $roomName = null;

    /**
     * Достаём название комнаты из текста:
     * Комната `Кабинет`: ...
     */
    $roomMarker = 'Комната `';
    $roomStart = strpos($error, $roomMarker);

    if ($roomStart !== false) {
        $roomStart += strlen($roomMarker);
        $roomEnd = strpos($error, '`', $roomStart);

        if ($roomEnd !== false) {
            $roomName = substr($error, $roomStart, $roomEnd - $roomStart);
        }
    }

    /**
     * Достаём последние координаты из текста ошибки.
     *
     * Работает для:
     * - Комната `Кабинет`: у двери [12,14] нет соседней клетки коридора
     * - paths[12]: точка [16,9] находится внутри комнаты
     * - Стартовая позиция #1 [0,0] находится внутри комнаты
     */
    $left = strrpos($error, '[');
    $right = strrpos($error, ']');

    if ($left === false || $right === false || $right <= $left) {
        if ($roomName !== null) {
            $errorRooms[$roomName][] = $error;
        }

        continue;
    }

    $inside = substr($error, $left + 1, $right - $left - 1);
    $parts = array_map('trim', explode(',', $inside));

    if (count($parts) < 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
        if ($roomName !== null) {
            $errorRooms[$roomName][] = $error;
        }

        continue;
    }

    $x = (int) $parts[0];
    $y = (int) $parts[1];
    $key = preview_cell_key($x, $y);

    $errorCells[$key][] = $error;

    if ($roomName !== null) {
        $errorRooms[$roomName][] = $error;
    }
}

$board = $map['board'] ?? [];
$width = (int) ($board['w'] ?? 17);
$height = (int) ($board['h'] ?? 17);
$rooms = $map['rooms'] ?? [];

if (!is_array($rooms)) {
    $rooms = [];
}

$pathKeys = preview_path_keys($map);
$starts = preview_starts($startsByNameForPreview);
$startList = preview_character_start_list($startsByNameForPreview);

$balanceAnalysis = preview_balance_analysis($rooms, $pathKeys, $startList);

$reachablePathKeys = reachable_path_keys($pathKeys, $characterStartsForPreview);
$isolatedPathKeys = array_diff_key($pathKeys, $reachablePathKeys);
$unreachableDoorCells = [];

$doorByCell = [];
$roomLabelByCell = [];
$secretLines = [];
$secretOverlayLines = [];

foreach ($rooms as $roomName => $room) {
    if (!is_array($room)) {
        continue;
    }

    $door = $room['door'] ?? null;

    if (is_array($door) && count($door) >= 2) {
        $doorByCell[preview_cell_key((int) $door[0], (int) $door[1])] = (string) $roomName;

        $entries = door_entry_cells($room, $pathKeys, $rooms);
        $hasReachableEntry = false;

        foreach ($entries as $entryKey => $entryPoint) {
            if (isset($reachablePathKeys[$entryKey])) {
                $hasReachableEntry = true;
                break;
            }
        }

        if (!$hasReachableEntry) {
            $unreachableDoorCells[preview_cell_key((int) $door[0], (int) $door[1])] = true;
        }
    }

    [$cx, $cy] = preview_room_center($room);
    $roomLabelByCell[preview_cell_key($cx, $cy)] = (string) $roomName;

    $secret = $room['secret'] ?? null;

    if (is_string($secret) && $secret !== '' && isset($rooms[$secret])) {
        $secretLines[] = [
            'from' => (string) $roomName,
            'to' => $secret,
        ];

        [$fromX, $fromY] = preview_room_center($room);
        [$toX, $toY] = preview_room_center($rooms[$secret]);

        $pairKeyParts = [(string) $roomName, $secret];
        sort($pairKeyParts);
        $pairKey = implode('|', $pairKeyParts);

        /**
         * Рисуем каждую пару один раз.
         * В JSON secret обычно взаимный: Кухня -> Кабинет и Кабинет -> Кухня.
         * Для preview одна линия между комнатами выглядит чище.
         */
        if (!isset($secretOverlayLines[$pairKey])) {
            $secretOverlayLines[$pairKey] = [
                'from' => (string) $roomName,
                'to' => $secret,
                'x1' => $fromX,
                'y1' => $fromY,
                'x2' => $toX,
                'y2' => $toY,
            ];
        }
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

        .secret-overlay {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 5;
        }

        .secret-overlay line {
            stroke: rgba(255, 209, 102, .55);
            stroke-width: 2;
            stroke-linecap: round;
            stroke-dasharray: 8 10;
            filter: drop-shadow(0 0 3px rgba(255, 209, 102, .35));
            opacity: .8;
        }

        .board.reachability-mode .cell.path.reachable-path {
            background: rgba(112, 227, 140, .2);
            border-color: rgba(112, 227, 140, .45);
            box-shadow: inset 0 0 0 1px rgba(112, 227, 140, .35);
        }

        .board.reachability-mode .cell.path.isolated-path {
            background: rgba(255, 123, 123, .22);
            border-color: rgba(255, 123, 123, .7);
            box-shadow:
                inset 0 0 0 2px rgba(255, 123, 123, .65),
                0 0 12px rgba(255, 123, 123, .35);
        }

        .board.reachability-mode .cell.unreachable-door-cell {
            background: rgba(255, 123, 123, .24);
            outline: 3px solid #ff7b7b;
            outline-offset: -3px;
            box-shadow:
                inset 0 0 0 2px rgba(255, 123, 123, .75),
                0 0 18px rgba(255, 123, 123, .42);
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
        .cell.room.selectable-room {
            cursor: pointer;
        }

        .cell.room.selected-room {
            background: rgba(112, 227, 140, .24);
            border-color: rgba(112, 227, 140, .75);
            box-shadow:
                inset 0 0 0 2px rgba(112, 227, 140, .9),
                0 0 16px rgba(112, 227, 140, .35);
        }

        .room-item {
            cursor: pointer;
            transition: background .15s ease, border-color .15s ease, transform .15s ease;
        }

        .room-item:hover {
            background: rgba(255, 255, 255, .11);
        }

        .room-item.selected-room-item {
            background: rgba(112, 227, 140, .18);
            border-color: rgba(112, 227, 140, .65);
            transform: translateY(-1px);
        }

        .cell.door {
            outline: 2px solid #ffd166;
            outline-offset: -3px;
        }

        .cell.error-cell {
            outline: 2px solid #ff7b7b;
            outline-offset: -3px;
            box-shadow:
                inset 0 0 0 2px rgba(255, 123, 123, .75),
                0 0 18px rgba(255, 123, 123, .42);
        }

        .cell.door.error-cell {
            outline: 3px solid #ff7b7b;
            background: rgba(255, 123, 123, .18);
        }

        .room-item.error-room-item {
            background: rgba(255, 123, 123, .14);
            border-color: rgba(255, 123, 123, .6);
        }

        .room-item.error-room-item::after {
            content: "Есть ошибка";
            display: inline-flex;
            margin-top: 8px;
            padding: 4px 8px;
            border-radius: 999px;
            background: rgba(255, 123, 123, .16);
            color: #ffb3b3;
            font-size: 12px;
            font-weight: 700;
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
        .board.hide-coords .coord {
            display: none;
        }

        .preview-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .preview-toggle {
            border: 1px solid rgba(255, 255, 255, .18);
            background: rgba(255, 255, 255, .1);
            color: #fff;
            border-radius: 14px;
            padding: 9px 12px;
            cursor: pointer;
            font: inherit;
        }

        .preview-toggle:hover {
            background: rgba(255, 255, 255, .16);
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

        .tools-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 20px;
            margin-top: 20px;
            align-items: start;
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

        .balance-box {
            padding: 0;
        }

        .balance-box h2 {
            margin: 0 0 12px;
        }

        .balance-summary {
            display: grid;
            gap: 6px;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .balance-summary div {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 6px 0;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
        }

        .balance-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .balance-table th,
        .balance-table td {
            padding: 7px 6px;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
            text-align: left;
            vertical-align: top;
        }

        .balance-table th {
            color: rgba(238, 242, 255, .68);
            font-weight: 700;
        }

        .balance-table .num {
            text-align: right;
            white-space: nowrap;
        }

        .balance-status-ok {
            color: #70e38c;
            font-weight: 700;
        }

        .balance-status-warn {
            color: #ffd166;
            font-weight: 700;
        }

        .balance-status-bad {
            color: #ff7b7b;
            font-weight: 700;
        }

        .dev-box {
            padding: 0;
        }

        .dev-box h2 {
            margin: 0 0 12px;
        }

        .dev-row {
            display: grid;
            grid-template-columns: 110px minmax(0, 1fr);
            gap: 10px;
            padding: 7px 0;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
            font-size: 14px;
        }

        .dev-row:last-child {
            border-bottom: 0;
        }

        .dev-value {
            overflow-wrap: anywhere;
        }

        .copy-btn {
            margin-top: 10px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, .18);
            background: rgba(255, 255, 255, .1);
            color: #fff;
            border-radius: 14px;
            padding: 9px 12px;
            cursor: pointer;
            font: inherit;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, .16);
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
            .details-grid,
            .tools-grid {
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
                <div class="preview-controls">
                    <button class="preview-toggle" type="button" id="toggleCoordsBtn">
                        Скрыть координаты
                    </button>

                    <button class="preview-toggle" type="button" id="toggleReachabilityBtn">
                        Показать достижимость
                    </button>
                </div>

                <div
                    class="board"
                    id="mapBoard"
                    style="grid-template-columns: repeat(<?= $width ?>, 42px);"
                >
                <?php
                $cellSize = 42;
                $gapSize = 2;
                $step = $cellSize + $gapSize;
                $overlayWidth = $width * $cellSize + max(0, $width - 1) * $gapSize;
                $overlayHeight = $height * $cellSize + max(0, $height - 1) * $gapSize;

                $cellCenter = function (int $x, int $y) use ($cellSize, $step): array {
                    return [
                        $x * $step + (int) floor($cellSize / 2),
                        $y * $step + (int) floor($cellSize / 2),
                    ];
                };
                ?>

                <?php if ($secretOverlayLines): ?>
                    <svg
                        class="secret-overlay"
                        width="<?= $overlayWidth ?>"
                        height="<?= $overlayHeight ?>"
                        viewBox="0 0 <?= $overlayWidth ?> <?= $overlayHeight ?>"
                        aria-hidden="true"
                    >
                        <?php foreach ($secretOverlayLines as $line): ?>
                            <?php
                            [$x1, $y1] = $cellCenter((int) $line['x1'], (int) $line['y1']);
                            [$x2, $y2] = $cellCenter((int) $line['x2'], (int) $line['y2']);
                            $labelX = (int) floor(($x1 + $x2) / 2);
                            $labelY = (int) floor(($y1 + $y2) / 2);
                            ?>
                            <line
                                x1="<?= $x1 ?>"
                                y1="<?= $y1 ?>"
                                x2="<?= $x2 ?>"
                                y2="<?= $y2 ?>"
                            />
                        <?php endforeach; ?>
                    </svg>
                <?php endif; ?>
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

                                if (isset($reachablePathKeys[$key])) {
                                    $classes[] = 'reachable-path';
                                }

                                if (isset($isolatedPathKeys[$key])) {
                                    $classes[] = 'isolated-path';
                                }
                            }

                            if ($isDoor) {
                                $classes[] = 'door';
                            }

                            if (isset($unreachableDoorCells[$key])) {
                                $classes[] = 'unreachable-door-cell';
                            }

                            if ($isStart) {
                                $classes[] = 'start';
                            }
                            if (isset($errorCells[$key])) {
                                $classes[] = 'error-cell';
                            }
                            $cellTitle = $x . ',' . $y . ($roomName ? ' · ' . $roomName : '');

                            if (isset($errorCells[$key])) {
                                $cellTitle .= ' · Ошибка: ' . implode(' | ', $errorCells[$key]);
                            }

                            if (isset($isolatedPathKeys[$key])) {
                                $cellTitle .= ' · Недостижимый коридор';
                            }

                            if (isset($unreachableDoorCells[$key])) {
                                $cellTitle .= ' · Дверь без достижимого входа';
                            }
                            ?>

                            <div
                                class="<?= e(implode(' ', $classes)) ?><?= $roomName !== null ? ' selectable-room' : '' ?>"
                                title="<?= e($cellTitle) ?>"
                                <?= $roomName !== null ? 'data-room="' . e($roomName) . '"' : '' ?>
                            >
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
                    <div class="legend-item">Пунктирная жёлтая линия — секретный проход</div>
                    <div class="legend-item">В режиме достижимости: зелёный — доступный коридор, красный — недостижимый участок</div>
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
                        <div
                            class="room-item <?= isset($errorRooms[(string) $roomName]) ? 'error-room-item' : '' ?>"
                            data-room-item="<?= e((string) $roomName) ?>"
                            title="<?= isset($errorRooms[(string) $roomName]) ? e(implode(' | ', $errorRooms[(string) $roomName])) : '' ?>"
                        >
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
        <div class="tools-grid">
            <section class="panel details-panel">
                <div class="balance-box">
                    <h2>Анализ расстояний</h2>

                    <?php
                    $balanceSummary = $balanceAnalysis['summary'] ?? [];
                    $balanceRows = $balanceAnalysis['rooms'] ?? [];
                    $summaryStatus = (string) ($balanceSummary['status'] ?? 'ok');
                    ?>

                    <div class="balance-summary">
                        <div>
                            <span class="muted">Комнат</span>
                            <b><?= (int) ($balanceSummary['rooms'] ?? 0) ?></b>
                        </div>
                        <div>
                            <span class="muted">Средняя min/max</span>
                            <b>
                                <?= e((string) ($balanceSummary['avg_min'] ?? '—')) ?>
                                /
                                <?= e((string) ($balanceSummary['avg_max'] ?? '—')) ?>
                            </b>
                        </div>
                        <div>
                            <span class="muted">Разброс средних</span>
                            <b class="<?= $summaryStatus === 'warn' ? 'balance-status-warn' : 'balance-status-ok' ?>">
                                <?= e((string) ($balanceSummary['avg_spread'] ?? '—')) ?>
                            </b>
                        </div>
                        <div>
                            <span class="muted">Статус</span>
                            <b class="<?= $summaryStatus === 'warn' ? 'balance-status-warn' : 'balance-status-ok' ?>">
                                <?= e((string) ($balanceSummary['note'] ?? 'OK')) ?>
                            </b>
                        </div>
                    </div>

                    <table class="balance-table">
                        <thead>
                            <tr>
                                <th>Комната</th>
                                <th class="num">min</th>
                                <th class="num">avg</th>
                                <th class="num">max</th>
                                <th>Оценка</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($balanceRows as $row): ?>
                                <?php
                                $status = (string) ($row['status'] ?? 'ok');
                                $statusClass = $status === 'unreachable'
                                    ? 'balance-status-bad'
                                    : ($status === 'warn' ? 'balance-status-warn' : 'balance-status-ok');
                                ?>
                                <tr>
                                    <td><?= e((string) $row['room']) ?></td>
                                    <td class="num"><?= $row['min'] === null ? '—' : e((string) $row['min']) ?></td>
                                    <td class="num"><?= $row['avg'] === null ? '—' : e((string) $row['avg']) ?></td>
                                    <td class="num"><?= $row['max'] === null ? '—' : e((string) $row['max']) ?></td>
                                    <td class="<?= $statusClass ?>"><?= e((string) ($row['note'] ?? 'OK')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel details-panel">
                <div class="dev-box">
                    <h2>Действия разработчика</h2>

                    <div class="dev-row">
                        <span class="muted">map_id</span>
                        <b class="dev-value" id="devMapId"><?= e($selectedMapId) ?></b>
                    </div>

                    <div class="dev-row">
                        <span class="muted">JSON</span>
                        <span class="dev-value"><?= e($relativeMapFile) ?></span>
                    </div>

                    <div class="dev-row">
                        <span class="muted">Статус</span>
                        <b class="<?= $validationOk ? 'ok' : 'err' ?>">
                            <?= $validationOk ? 'можно использовать' : 'есть ошибки' ?>
                        </b>
                    </div>

                    <div class="dev-row">
                        <span class="muted">Размер</span>
                        <span class="dev-value"><?= $width ?> × <?= $height ?></span>
                    </div>

                    <button class="copy-btn" type="button" id="copyMapIdBtn">
                        Скопировать map_id
                    </button>
                </div>
            </section>
        </div>
    </div>
    <script>
    (function () {
        const board = document.getElementById('mapBoard');
        const btn = document.getElementById('toggleCoordsBtn');

        if (!board || !btn) {
            return;
        }

        const storageKey = 'mapPreview.showCoords';

        function applyState(showCoords) {
            board.classList.toggle('hide-coords', !showCoords);
            btn.textContent = showCoords ? 'Скрыть координаты' : 'Показать координаты';
            localStorage.setItem(storageKey, showCoords ? '1' : '0');
        }

        const saved = localStorage.getItem(storageKey);

        /**
         * По умолчанию координаты включены, потому что это tools-страница.
         */
        let showCoords = saved === null ? true : saved === '1';

        applyState(showCoords);

        btn.addEventListener('click', function () {
            showCoords = !showCoords;
            applyState(showCoords);
        });
    })();
    </script>
    <script>
    (function () {
        const roomCells = Array.from(document.querySelectorAll('[data-room]'));
        const roomItems = Array.from(document.querySelectorAll('[data-room-item]'));

        if (!roomCells.length && !roomItems.length) {
            return;
        }

        let selectedRoom = null;

        function clearSelection() {
            roomCells.forEach(cell => cell.classList.remove('selected-room'));
            roomItems.forEach(item => item.classList.remove('selected-room-item'));
        }

        function selectRoom(roomName) {
            selectedRoom = roomName;
            clearSelection();

            roomCells.forEach(cell => {
                if (cell.dataset.room === roomName) {
                    cell.classList.add('selected-room');
                }
            });

            roomItems.forEach(item => {
                if (item.dataset.roomItem === roomName) {
                    item.classList.add('selected-room-item');
                }
            });
        }

        roomCells.forEach(cell => {
            cell.addEventListener('click', function () {
                const roomName = this.dataset.room;

                if (!roomName) {
                    return;
                }

                selectRoom(roomName);
            });
        });

        roomItems.forEach(item => {
            item.addEventListener('click', function () {
                const roomName = this.dataset.roomItem;

                if (!roomName) {
                    return;
                }

                selectRoom(roomName);

                const firstCell = document.querySelector(`[data-room="${CSS.escape(roomName)}"]`);

                if (firstCell) {
                    firstCell.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center',
                        inline: 'center'
                    });
                }
            });
        });
    })();
    </script>
    <script>
    (function () {
        const board = document.getElementById('mapBoard');
        const btn = document.getElementById('toggleReachabilityBtn');

        if (!board || !btn) {
            return;
        }

        const storageKey = 'mapPreview.showReachability';

        function applyState(showReachability) {
            board.classList.toggle('reachability-mode', showReachability);
            btn.textContent = showReachability ? 'Скрыть достижимость' : 'Показать достижимость';
            localStorage.setItem(storageKey, showReachability ? '1' : '0');
        }

        const saved = localStorage.getItem(storageKey);
        let showReachability = saved === null ? false : saved === '1';

        applyState(showReachability);

        btn.addEventListener('click', function () {
            showReachability = !showReachability;
            applyState(showReachability);
        });
    })();
    </script>
    <script>
    (function () {
        const btn = document.getElementById('copyMapIdBtn');
        const value = document.getElementById('devMapId');

        if (!btn || !value) {
            return;
        }

        btn.addEventListener('click', async function () {
            const text = value.textContent.trim();

            try {
                await navigator.clipboard.writeText(text);
                btn.textContent = 'map_id скопирован';

                setTimeout(function () {
                    btn.textContent = 'Скопировать map_id';
                }, 1400);
            } catch (e) {
                btn.textContent = 'Не удалось скопировать';

                setTimeout(function () {
                    btn.textContent = 'Скопировать map_id';
                }, 1400);
            }
        });
    })();
</script>
</body>

</html>