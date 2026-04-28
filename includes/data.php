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
        ['name' => 'Алекс Громов',       'x' => 3,  'y' => 12, 'color' => '#e53935'],
        ['name' => 'Мария Скарлет',     'x' => 0,  'y' => 8,  'color' => '#d81b60'],
        ['name' => 'Профессор Фиолетов','x' => 11, 'y' => 10, 'color' => '#7e57c2'],
        ['name' => 'Виктор Олив',       'x' => 3,  'y' => 0,  'color' => '#689f38'],
        ['name' => 'Елена Белая',       'x' => 8,  'y' => 0,  'color' => '#eceff1'],
        ['name' => 'София Синяя',       'x' => 11, 'y' => 3,  'color' => '#42a5f5'],
    ];
}

function board_variant(int $gid = 0): int {
    return $gid > 0 ? $gid % 3 : 0;
}

/**
 * Было 24x25.
 * Теперь поле примерно в 2 раза меньше:
 * каждая логическая клетка ощущается как 2x2 старых.
 */
function board_size(int $gid = 0): array {
    return ['w' => 12, 'h' => 13];
}

/**
 * ВАЖНО:
 * door теперь находится НА КРАЮ комнаты, а не отдельной клеткой коридора.
 * У каждой комнаты только один вход.
 */
function mansion_rooms(int $gid = 0): array {
    $v = board_variant($gid);

    $r = [
        'Кухня' => [
            'x1' => 0, 'y1' => 0, 'x2' => 2, 'y2' => 2,
            'door' => [3, 2],
            'secret' => 'Кабинет',
            'theme' => 'kitchen'
        ],
        'Бальный зал' => [
            'x1' => 4, 'y1' => 0, 'x2' => 7, 'y2' => 2,
            'door' => [5, 3],
            'secret' => null,
            'theme' => 'ballroom'
        ],
        'Оранжерея' => [
            'x1' => 9, 'y1' => 0, 'x2' => 11, 'y2' => 2,
            'door' => [8, 2],
            'secret' => 'Гостиная',
            'theme' => 'greenhouse'
        ],
        'Столовая' => [
            'x1' => 0, 'y1' => 4, 'x2' => 2, 'y2' => 6,
            'door' => [3, 5],
            'secret' => null,
            'theme' => 'dining'
        ],
        'Бильярдная' => [
            'x1' => 9, 'y1' => 4, 'x2' => 11, 'y2' => 6,
            'door' => [8, 5],
            'secret' => null,
            'theme' => 'billiard'
        ],
        'Библиотека' => [
            'x1' => 0, 'y1' => 8, 'x2' => 2, 'y2' => 9,
            'door' => [3, 8],
            'secret' => null,
            'theme' => 'library'
        ],
        'Гостиная' => [
            'x1' => 0, 'y1' => 11, 'x2' => 2, 'y2' => 12,
            'door' => [3, 11],
            'secret' => 'Оранжерея',
            'theme' => 'lounge'
        ],
        'Холл' => [
            'x1' => 4, 'y1' => 10, 'x2' => 7, 'y2' => 12,
            'door' => [6, 9],
            'secret' => null,
            'theme' => 'hall'
        ],
        'Кабинет' => [
            'x1' => 9, 'y1' => 10, 'x2' => 11, 'y2' => 12,
            'door' => [8, 10],
            'secret' => 'Кухня',
            'theme' => 'study'
        ],
    ];

    /**
     * Вариативность поля:
     * комнаты остаются узнаваемыми, но входы немного меняются.
     */
    if ($v === 1) {
        $r['Кухня']['door'] = [2, 3];
        $r['Бальный зал']['door'] = [6, 3];
        $r['Оранжерея']['door'] = [9, 3];
        $r['Столовая']['door'] = [3, 4];
        $r['Бильярдная']['door'] = [8, 6];
        $r['Библиотека']['door'] = [2, 10];
        $r['Гостиная']['door'] = [3, 12];
        $r['Холл']['door'] = [4, 9];
        $r['Кабинет']['door'] = [9, 9];
    }

    if ($v === 2) {
        $r['Кухня']['door'] = [3, 1];
        $r['Бальный зал']['door'] = [4, 3];
        $r['Оранжерея']['door'] = [8, 1];
        $r['Столовая']['door'] = [2, 7];
        $r['Бильярдная']['door'] = [9, 7];
        $r['Библиотека']['door'] = [3, 9];
        $r['Гостиная']['door'] = [2, 10];
        $r['Холл']['door'] = [7, 9];
        $r['Кабинет']['door'] = [8, 12];
    }

    /**
     * Для совместимости со старым JS оставляем doors,
     * но там всегда один элемент.
     */
    foreach ($r as $name => $room) {
        $r[$name]['doors'] = [$room['door']];
    }

    return $r;
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
    return is_inside_board($x, $y, $gid) && !is_room_cell($x, $y, $gid);
}

/**
 * Клетка коридора рядом с дверью комнаты.
 * Игрок стоит на коридоре и тратит +1 очко, чтобы войти в комнату.
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

/**
 * Выход из комнаты.
 * Чтобы выйти из комнаты на соседний коридор — тоже 1 очко.
 */
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
        'starts' => characters()
    ];
}