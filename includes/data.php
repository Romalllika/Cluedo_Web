<?php

function suspects(): array {
    return [
        'Алекс Громов',
        'Мария Скарлет',
        'Профессор Фиолетов',
        'Виктор Олив',
        'Елена Белая',
        'София Синяя'
    ];
}

function weapons(): array {
    return [
        'Подсвечник',
        'Кинжал',
        'Верёвка',
        'Гаечный ключ',
        'Револьвер',
        'Свинцовая труба'
    ];
}

function rooms(): array {
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

function characters(): array {
    return [
        ['name' => 'Алекс Громов',        'x' => 8,  'y' => 17, 'color' => '#e53935'],
        ['name' => 'Мария Скарлет',      'x' => 7,  'y' => 17, 'color' => '#d81b60'],
        ['name' => 'Профессор Фиолетов', 'x' => 9,  'y' => 17, 'color' => '#7e57c2'],
        ['name' => 'Виктор Олив',        'x' => 6,  'y' => 16, 'color' => '#689f38'],
        ['name' => 'Елена Белая',        'x' => 10, 'y' => 16, 'color' => '#eceff1'],
        ['name' => 'София Синяя',        'x' => 8,  'y' => 16, 'color' => '#42a5f5'],
    ];
}

function board_variant(int $gid = 0): int {
    return $gid > 0 ? $gid % 3 : 0;
}

function board_size(int $gid = 0): array {
    return ['w' => 17, 'h' => 18];
}

/**
 * Комнаты стоят плотнее и занимают почти весь периметр поля.
 * Дверь находится НА КРАЮ комнаты.
 */
function mansion_rooms(int $gid = 0): array {
    $v = board_variant($gid);

    $r = [
        'Кухня' => [
            'x1' => 0, 'y1' => 0, 'x2' => 3, 'y2' => 3,
            'door' => [2, 3],
            'secret' => 'Кабинет',
            'theme' => 'kitchen'
        ],
        'Бальный зал' => [
            'x1' => 4, 'y1' => 0, 'x2' => 10, 'y2' => 3,
            'door' => [7, 3],
            'secret' => null,
            'theme' => 'ballroom'
        ],
        'Оранжерея' => [
            'x1' => 11, 'y1' => 0, 'x2' => 14, 'y2' => 3,
            'door' => [12, 3],
            'secret' => 'Гостиная',
            'theme' => 'greenhouse'
        ],
        'Столовая' => [
            'x1' => 0, 'y1' => 5, 'x2' => 3, 'y2' => 8,
            'door' => [3, 6],
            'secret' => null,
            'theme' => 'dining'
        ],
        'Бильярдная' => [
            'x1' => 11, 'y1' => 5, 'x2' => 14, 'y2' => 8,
            'door' => [11, 6],
            'secret' => null,
            'theme' => 'billiard'
        ],
        'Библиотека' => [
            'x1' => 0, 'y1' => 9, 'x2' => 3, 'y2' => 11,
            'door' => [3, 10],
            'secret' => null,
            'theme' => 'library'
        ],
        'Гостиная' => [
            'x1' => 0, 'y1' => 12, 'x2' => 3, 'y2' => 14,
            'door' => [3, 13],
            'secret' => 'Оранжерея',
            'theme' => 'lounge'
        ],
        'Холл' => [
            'x1' => 5, 'y1' => 11, 'x2' => 9, 'y2' => 14,
            'door' => [7, 11],
            'secret' => null,
            'theme' => 'hall'
        ],
        'Кабинет' => [
            'x1' => 11, 'y1' => 10, 'x2' => 14, 'y2' => 14,
            'door' => [11, 12],
            'secret' => 'Кухня',
            'theme' => 'study'
        ],
    ];

    /**
     * Вариант 1: те же комнаты, но двери немного смещены.
     */
    if ($v === 1) {
        $r['Кухня']['door'] = [1, 3];
        $r['Бальный зал']['door'] = [5, 3];
        $r['Оранжерея']['door'] = [13, 3];
        $r['Столовая']['door'] = [3, 7];
        $r['Бильярдная']['door'] = [11, 7];
        $r['Библиотека']['door'] = [3, 9];
        $r['Гостиная']['door'] = [3, 12];
        $r['Холл']['door'] = [5, 11];
        $r['Кабинет']['door'] = [11, 13];
    }

    /**
     * Вариант 2: ещё одно расположение дверей.
     */
    if ($v === 2) {
        $r['Кухня']['door'] = [2, 3];
        $r['Бальный зал']['door'] = [9, 3];
        $r['Оранжерея']['door'] = [11, 3];
        $r['Столовая']['door'] = [3, 5];
        $r['Бильярдная']['door'] = [11, 5];
        $r['Библиотека']['door'] = [3, 11];
        $r['Гостиная']['door'] = [3, 14];
        $r['Холл']['door'] = [9, 11];
        $r['Кабинет']['door'] = [11, 10];
    }

    foreach ($r as $name => $room) {
        $r[$name]['doors'] = [$room['door']];
    }

    return $r;
}

/**
 * Только эти клетки являются коридорами.
 * Всё остальное, что не комната, считается стеной/пустотой и недоступно.
 */
function board_paths(int $gid = 0): array {
    $paths = [];

    $add = function (int $x, int $y) use (&$paths) {
        $paths["$x:$y"] = [$x, $y];
    };

    /*
     * Основная идея:
     * комнаты стоят сверху/слева/справа/снизу,
     * а движение идёт по центральной системе коридоров.
     */

    // Верхний коридор под верхними комнатами
    for ($x = 1; $x <= 15; $x++) {
        $add($x, 4);
    }

    // Левый вертикальный коридор
    for ($y = 4; $y <= 15; $y++) {
        $add(4, $y);
    }

    // Центральный вертикальный коридор
    for ($y = 4; $y <= 17; $y++) {
        $add(8, $y);
    }

    // Правый вертикальный коридор
    for ($y = 4; $y <= 15; $y++) {
        $add(12, $y);
    }

    // Средний коридор между Столовой и Бильярдной
    for ($x = 4; $x <= 12; $x++) {
        $add($x, 6);
    }

    // Нижний центральный коридор
    for ($x = 4; $x <= 12; $x++) {
        $add($x, 10);
    }

    // Коридор перед нижними комнатами
    for ($x = 4; $x <= 12; $x++) {
        $add($x, 13);
    }

    // Нижний стартовый коридор
    for ($x = 6; $x <= 10; $x++) {
        $add($x, 16);
        $add($x, 17);
    }

    // Дополнительные соединители в центре
    for ($x = 4; $x <= 12; $x++) {
        $add($x, 5);
        $add($x, 7);
        $add($x, 8);
        $add($x, 9);
        $add($x, 11);
        $add($x, 12);
    }

    // Переходы от центрального коридора к нижним стартам
    $add(7, 15);
    $add(8, 15);
    $add(9, 15);
    $add(7, 16);
    $add(8, 16);
    $add(9, 16);
    $add(7, 17);
    $add(8, 17);
    $add(9, 17);

    // Стартовые клетки персонажей обязательно доступны
    foreach (characters() as $c) {
        $add((int) $c['x'], (int) $c['y']);
    }

    return array_values($paths);
}

function board_path_keys(int $gid = 0): array {
    $keys = [];

    foreach (board_paths($gid) as $p) {
        $keys[$p[0] . ':' . $p[1]] = true;
    }

    return $keys;
}

function room_positions(int $gid = 0): array {
    $out = [];

    foreach (mansion_rooms($gid) as $name => $r) {
        $out[$name] = [
            (int) floor(($r['x1'] + $r['x2']) / 2),
            (int) floor(($r['y1'] + $r['y2']) / 2)
        ];
    }

    return $out;
}

function room_at(int $x, int $y, int $gid = 0): ?string {
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

function is_inside_board(int $x, int $y, int $gid = 0): bool {
    $s = board_size($gid);

    return $x >= 0 && $y >= 0 && $x < $s['w'] && $y < $s['h'];
}

function is_room_cell(int $x, int $y, int $gid = 0): bool {
    return room_at($x, $y, $gid) !== null;
}

function room_by_door(int $x, int $y, int $gid = 0): ?string {
    foreach (mansion_rooms($gid) as $room => $r) {
        $d = $r['door'];

        if ((int) $d[0] === $x && (int) $d[1] === $y) {
            return $room;
        }
    }

    return null;
}

function is_door_cell(int $x, int $y, int $gid = 0): bool {
    return room_by_door($x, $y, $gid) !== null;
}

function is_walk_cell(int $x, int $y, int $gid = 0): bool {
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
function door_entry_cell(string $room, int $gid = 0): ?array {
    $rooms = mansion_rooms($gid);

    if (!isset($rooms[$room])) {
        return null;
    }

    $d = $rooms[$room]['door'];

    foreach ([[1,0], [-1,0], [0,1], [0,-1]] as $v) {
        $nx = $d[0] + $v[0];
        $ny = $d[1] + $v[1];

        if (is_walk_cell($nx, $ny, $gid)) {
            return [$nx, $ny];
        }
    }

    return null;
}

function room_exit_cells(string $room, int $gid = 0): array {
    $e = door_entry_cell($room, $gid);

    return $e ? [$e] : [];
}

function bfs_distances_from(int $sx, int $sy, int $gid = 0): array {
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

        foreach ([[1,0], [-1,0], [0,1], [0,-1]] as $d) {
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

function distance_to_room(string $room, array $dist, int $gid = 0): ?int {
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

function board_cells(int $gid = 0): array {
    $s = board_size($gid);

    return [
        'width' => $s['w'],
        'height' => $s['h'],
        'variant' => board_variant($gid),
        'rooms' => mansion_rooms($gid),
        'paths' => board_paths($gid),
        'starts' => characters()
    ];
}