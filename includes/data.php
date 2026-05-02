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
        ['name' => 'Алекс Громов',        'x' => 8, 'y' => 9, 'color' => '#e53935'],
        ['name' => 'Мария Скарлет',      'x' => 7, 'y' => 9, 'color' => '#d81b60'],
        ['name' => 'Профессор Фиолетов', 'x' => 9, 'y' => 9, 'color' => '#7e57c2'],
        ['name' => 'Виктор Олив',        'x' => 8, 'y' => 8, 'color' => '#689f38'],
        ['name' => 'Елена Белая',        'x' => 7, 'y' => 8, 'color' => '#eceff1'],
        ['name' => 'София Синяя',        'x' => 9, 'y' => 8, 'color' => '#42a5f5'],
    ];
}

function board_variant(int $gid = 0): int {
    return $gid > 0 ? $gid % 3 : 0;
}

function board_size(int $gid = 0): array {
    return ['w' => 17, 'h' => 17];
}

/**
 * Комнаты стоят плотнее и занимают почти весь периметр поля.
 * Дверь находится НА КРАЮ комнаты.
 */
function mansion_rooms(int $gid = 0): array {
    $v = board_variant($gid);

    $r = [
        'Кухня' => [
            'x1' => 0, 'y1' => 0, 'x2' => 4, 'y2' => 4,
            'door' => [4, 5],
            'secret' => 'Кабинет',
            'theme' => 'kitchen'
        ],

        'Бальный зал' => [
            'x1' => 5, 'y1' => 0, 'x2' => 11, 'y2' => 3,
            'door' => [8, 3],
            'secret' => null,
            'theme' => 'ballroom'
        ],

        'Оранжерея' => [
            'x1' => 12, 'y1' => 0, 'x2' => 16, 'y2' => 4,
            'door' => [12, 5],
            'secret' => 'Гостиная',
            'theme' => 'greenhouse'
        ],

        'Столовая' => [
            'x1' => 0, 'y1' => 5, 'x2' => 4, 'y2' => 9,
            'door' => [4, 7],
            'secret' => null,
            'theme' => 'dining'
        ],

        'Бильярдная' => [
            'x1' => 12, 'y1' => 5, 'x2' => 16, 'y2' => 9,
            'door' => [12, 7],
            'secret' => null,
            'theme' => 'billiard'
        ],

        'Библиотека' => [
            'x1' => 0, 'y1' => 10, 'x2' => 4, 'y2' => 12,
            'door' => [4, 11],
            'secret' => null,
            'theme' => 'library'
        ],

        'Гостиная' => [
            'x1' => 0, 'y1' => 13, 'x2' => 4, 'y2' => 16,
            'door' => [4, 13],
            'secret' => 'Оранжерея',
            'theme' => 'lounge'
        ],

        'Холл' => [
            'x1' => 5, 'y1' => 14, 'x2' => 11, 'y2' => 16,
            'door' => [8, 14],
            'secret' => null,
            'theme' => 'hall'
        ],

        'Кабинет' => [
            'x1' => 12, 'y1' => 10, 'x2' => 16, 'y2' => 16,
            'door' => [12, 12],
            'secret' => 'Кухня',
            'theme' => 'study'
        ],
    ];

    /**
     * Вариант 1 — немного другие двери.
     * Комнаты остаются на тех же местах, чтобы поле не ломалось.
     */
    if ($v === 1) {
        $r['Кухня']['door'] = [4, 3];
        $r['Бальный зал']['door'] = [6, 3];
        $r['Оранжерея']['door'] = [12, 3];
        $r['Столовая']['door'] = [4, 6];
        $r['Бильярдная']['door'] = [12, 8];
        $r['Библиотека']['door'] = [4, 10];
        $r['Гостиная']['door'] = [4, 14];
        $r['Холл']['door'] = [6, 14];
        $r['Кабинет']['door'] = [12, 13];
    }

    /**
     * Вариант 2 — ещё одно смещение дверей.
     */
    if ($v === 2) {
        $r['Кухня']['door'] = [3, 4];
        $r['Бальный зал']['door'] = [10, 3];
        $r['Оранжерея']['door'] = [13, 4];
        $r['Столовая']['door'] = [4, 8];
        $r['Бильярдная']['door'] = [12, 6];
        $r['Библиотека']['door'] = [4, 12];
        $r['Гостиная']['door'] = [3, 13];
        $r['Холл']['door'] = [10, 14];
        $r['Кабинет']['door'] = [12, 11];
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