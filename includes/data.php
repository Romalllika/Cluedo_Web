<?php

function suspects(): array
{
    return [
        'Алекс Громов',
        'Мария Скарлет',
        'Профессор Фиолетов',
        'Виктор Олив',
        'Елена Белая',
        'София Синяя'
    ];
}

function weapons(): array
{
    return [
        'Подсвечник',
        'Кинжал',
        'Верёвка',
        'Гаечный ключ',
        'Револьвер',
        'Свинцовая труба'
    ];
}

function rooms(): array
{
    return [
        'Кухня',
        'Бальный зал',
        'Оранжерея',
        'Столовая',
        'Бильярдная',
        'Библиотека',
        'Гостиная',
        'Холл',
        'Кабинет'
    ];
}

function characters(): array
{
    return [
        ['name' => 'Алекс Громов', 'x' => 8, 'y' => 9, 'color' => '#e53935'],
        ['name' => 'Мария Скарлет', 'x' => 7, 'y' => 9, 'color' => '#d81b60'],
        ['name' => 'Профессор Фиолетов', 'x' => 9, 'y' => 9, 'color' => '#7e57c2'],
        ['name' => 'Виктор Олив', 'x' => 8, 'y' => 8, 'color' => '#689f38'],
        ['name' => 'Елена Белая', 'x' => 7, 'y' => 8, 'color' => '#eceff1'],
        ['name' => 'София Синяя', 'x' => 9, 'y' => 8, 'color' => '#42a5f5'],
    ];
}

function available_maps(): array
{
    return [

        'classic_mansion' => [

            'id' => 'classic_mansion',

            'title' => 'Классический особняк',

            'description' => 'Стабильная рабочая карта особняка.'

        ],

        'mansion_shifted_doors' => [

            'id' => 'mansion_shifted_doors',

            'title' => 'Особняк: другие двери',

            'description' => 'Та же карта особняка, но с немного изменёнными дверями комнат.'

        ],
        'mansion_evening' => [

            'id' => 'mansion_evening',

            'title' => 'Особняк: вечерняя схема',

            'description' => 'Альтернативная схема особняка с другими входами в часть комнат.'

        ],

    ];
}

function normalize_map_id(?string $mapId): string
{
    $mapId = trim((string) $mapId);
    $maps = available_maps();

    return isset($maps[$mapId]) ? $mapId : 'classic_mansion';
}

function map_id_for_game(int $gid = 0): string
{
    if ($gid <= 0) {
        return 'classic_mansion';
    }

    static $cache = [];

    if (isset($cache[$gid])) {
        return $cache[$gid];
    }

    try {
        $s = db()->prepare('SELECT map_id FROM games WHERE id=?');
        $s->execute([$gid]);

        $cache[$gid] = normalize_map_id($s->fetchColumn() ?: 'classic_mansion');
    } catch (Throwable $e) {
        /**
         * Защита на случай, если код уже обновили,
         * а ALTER TABLE games ADD map_id ещё не выполнен.
         */
        $cache[$gid] = 'classic_mansion';
    }

    return $cache[$gid];
}

function load_map_config(int $gid = 0): array
{
    $mapId = map_id_for_game($gid);

    static $cache = [];

    if (isset($cache[$mapId])) {
        return $cache[$mapId];
    }

    $path = __DIR__ . '/../maps/' . $mapId . '.json';

    if (!is_file($path)) {
        $path = __DIR__ . '/../maps/classic_mansion.json';
    }

    $json = file_get_contents($path);
    $data = json_decode($json, true);

    if (!is_array($data)) {
        throw new RuntimeException('Не удалось прочитать карту: ' . $mapId);
    }

    $cache[$mapId] = $data;

    return $cache[$mapId];
}

function board_variant(int $gid = 0): int
{
    $map = load_map_config($gid);

    return (int) ($map['variant'] ?? 0);
}

function board_size(int $gid = 0): array
{
    $map = load_map_config($gid);
    $board = $map['board'] ?? [];

    return [
        'w' => (int) ($board['w'] ?? 17),
        'h' => (int) ($board['h'] ?? 17)
    ];
}

/**
 * Комнаты стоят плотнее и занимают почти весь периметр поля.
 * Дверь находится НА КРАЮ комнаты.
 */
/**
 * Комнаты, двери, секретные проходы и визуальные темы текущей карты.
 */
function mansion_rooms(int $gid = 0): array
{
    $map = load_map_config($gid);
    $rooms = $map['rooms'] ?? [];

    $out = [];

    foreach ($rooms as $name => $room) {
        $door = $room['door'] ?? [0, 0];

        $out[$name] = [
            'x1' => (int) $room['x1'],
            'y1' => (int) $room['y1'],
            'x2' => (int) $room['x2'],
            'y2' => (int) $room['y2'],
            'door' => [(int) $door[0], (int) $door[1]],
            'secret' => $room['secret'] ?? null,
            'theme' => $room['theme'] ?? 'default',
        ];

        $out[$name]['doors'] = [$out[$name]['door']];
    }

    return $out;
}
/**
 * Только эти клетки являются коридорами.
 * Всё остальное, что не комната, считается стеной/пустотой и недоступно.
 */
function board_paths(int $gid = 0): array
{
    $paths = [];

    $add = function (int $x, int $y) use (&$paths) {
        $paths["$x:$y"] = [$x, $y];
    };

    /**
     * Центральная зона.
     * Это не комната, а внутренний двор/холл движения.
     * Персонажи стартуют здесь.
     */
    for ($y = 4; $y <= 13; $y++) {
        for ($x = 5; $x <= 11; $x++) {
            $add($x, $y);
        }
    }

    /**
     * Соединение к Холлу снизу.
     * Холл начинается с y=14, поэтому клетка y=13 перед ним остаётся коридором.
     */
    for ($x = 5; $x <= 11; $x++) {
        $add($x, 13);
    }

    /**
     * Стартовые позиции.
     */
    foreach (characters() as $c) {
        $add((int) $c['x'], (int) $c['y']);
    }

    return array_values($paths);
}
function board_path_keys(int $gid = 0): array
{
    $keys = [];

    foreach (board_paths($gid) as $p) {
        $keys[$p[0] . ':' . $p[1]] = true;
    }

    return $keys;
}

function room_positions(int $gid = 0): array
{
    $out = [];

    foreach (mansion_rooms($gid) as $name => $r) {
        $out[$name] = [
            (int) floor(($r['x1'] + $r['x2']) / 2),
            (int) floor(($r['y1'] + $r['y2']) / 2)
        ];
    }

    return $out;
}

function room_at(int $x, int $y, int $gid = 0): ?string
{
    foreach (mansion_rooms($gid) as $name => $r) {
        if (
            $x >= $r['x1'] &&
            $x <= $r['x2'] &&
            $y >= $r['y1'] &&
            $y <= $r['y2']
        ) {
            return $name;
        }
    }

    return null;
}

function is_inside_board(int $x, int $y, int $gid = 0): bool
{
    $s = board_size($gid);

    return $x >= 0 && $y >= 0 && $x < $s['w'] && $y < $s['h'];
}

function is_room_cell(int $x, int $y, int $gid = 0): bool
{
    return room_at($x, $y, $gid) !== null;
}

function room_by_door(int $x, int $y, int $gid = 0): ?string
{
    foreach (mansion_rooms($gid) as $room => $r) {
        $d = $r['door'];

        if ((int) $d[0] === $x && (int) $d[1] === $y) {
            return $room;
        }
    }

    return null;
}

function is_door_cell(int $x, int $y, int $gid = 0): bool
{
    return room_by_door($x, $y, $gid) !== null;
}

function is_walk_cell(int $x, int $y, int $gid = 0): bool
{
    if (!is_inside_board($x, $y, $gid)) {
        return false;
    }

    if (is_room_cell($x, $y, $gid)) {
        return false;
    }

    $keys = board_path_keys($gid);

    return isset($keys["$x:$y"]);
}

/**
 * Находит клетку коридора рядом с дверью комнаты.
 */
function door_entry_cell(string $room, int $gid = 0): ?array
{
    $rooms = mansion_rooms($gid);

    if (!isset($rooms[$room])) {
        return null;
    }

    $d = $rooms[$room]['door'];

    foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as $v) {
        $nx = $d[0] + $v[0];
        $ny = $d[1] + $v[1];

        if (is_walk_cell($nx, $ny, $gid)) {
            return [$nx, $ny];
        }
    }

    return null;
}

function room_exit_cells(string $room, int $gid = 0): array
{
    $e = door_entry_cell($room, $gid);

    return $e ? [$e] : [];
}

function bfs_distances_from(int $sx, int $sy, int $gid = 0): array
{
    $dist = [];
    $queue = [];

    $startRoom = room_at($sx, $sy, $gid);

    if ($startRoom) {
        foreach (room_exit_cells($startRoom, $gid) as $e) {
            $k = $e[0] . ':' . $e[1];
            $dist[$k] = 1;
            $queue[] = $e;
        }
    } else {
        if (!is_walk_cell($sx, $sy, $gid)) {
            return [];
        }

        $dist["$sx:$sy"] = 0;
        $queue = [[$sx, $sy]];
    }

    for ($i = 0; $i < count($queue); $i++) {
        [$x, $y] = $queue[$i];
        $base = $dist["$x:$y"];

        foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as $d) {
            $nx = $x + $d[0];
            $ny = $y + $d[1];

            if (!is_walk_cell($nx, $ny, $gid)) {
                continue;
            }

            $k = "$nx:$ny";

            if (!isset($dist[$k])) {
                $dist[$k] = $base + 1;
                $queue[] = [$nx, $ny];
            }
        }
    }

    return $dist;
}

function distance_to_room(string $room, array $dist, int $gid = 0): ?int
{
    $entry = door_entry_cell($room, $gid);

    if (!$entry) {
        return null;
    }

    $k = $entry[0] . ':' . $entry[1];

    return isset($dist[$k]) ? $dist[$k] + 1 : null;
}

function distance_to_target(
    int $sx,
    int $sy,
    int $tx,
    int $ty,
    int $gid = 0
): ?int {
    $dist = bfs_distances_from($sx, $sy, $gid);
    $targetRoom = room_at($tx, $ty, $gid);

    if ($targetRoom) {
        return distance_to_room($targetRoom, $dist, $gid);
    }

    if (!is_walk_cell($tx, $ty, $gid)) {
        return null;
    }

    $k = "$tx:$ty";

    return $dist[$k] ?? null;
}

function reachable_targets(
    int $sx,
    int $sy,
    int $points,
    int $gid = 0
): array {
    $dist = bfs_distances_from($sx, $sy, $gid);
    $out = [];

    foreach ($dist as $key => $d) {
        if ($d <= $points) {
            [$x, $y] = array_map('intval', explode(':', $key));

            $out[] = [
                'x' => $x,
                'y' => $y,
                'distance' => $d,
                'type' => 'path',
                'room' => null,
                'reachable' => true
            ];
        }
    }

    foreach (mansion_rooms($gid) as $name => $r) {
        $center = room_positions($gid)[$name];
        $need = distance_to_room($name, $dist, $gid);

        $out[] = [
            'x' => $center[0],
            'y' => $center[1],
            'door_x' => $r['door'][0],
            'door_y' => $r['door'][1],
            'distance' => $need,
            'type' => 'room',
            'room' => $name,
            'reachable' => $need !== null && $need <= $points
        ];
    }

    return $out;
}

function board_cells(int $gid = 0): array
{
    $s = board_size($gid);
    $map = load_map_config($gid);

    return [
        'width' => $s['w'],
        'height' => $s['h'],
        'variant' => board_variant($gid),
        'mapId' => map_id_for_game($gid),
        'mapTitle' => $map['title'] ?? 'Карта',
        'rooms' => mansion_rooms($gid),
        'paths' => board_paths($gid),
        'starts' => characters()
    ];
}