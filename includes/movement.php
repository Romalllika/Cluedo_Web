<?php
/**
 * Старая стабильная дорожная сетка.
 * Нужна как fallback для карт, где paths ещё не вынесены в JSON.
 */
function default_board_paths(): array
{
    $paths = [];

    $add = function (int $x, int $y) use (&$paths) {
        $paths["$x:$y"] = [$x, $y];
    };

    /**
     * Центральная зона.
     */
    for ($y = 4; $y <= 13; $y++) {
        for ($x = 5; $x <= 11; $x++) {
            $add($x, $y);
        }
    }

    /**
     * Стартовые позиции.
     */
    foreach (default_character_starts() as [$x, $y]) {
        $add((int) $x, (int) $y);
    }

    return array_values($paths);
}

/**
 * Только эти клетки являются коридорами.
 * Всё остальное, что не комната, считается стеной/пустотой и недоступно.
 *
 * Если в JSON карты есть paths — используем их.
 * Если нет — используем старую стабильную сетку.
 */

function board_paths(int $gid = 0): array
{
    $map = load_map_config($gid);
    $paths = [];

    $add = function (int $x, int $y) use (&$paths) {
        $paths["$x:$y"] = [$x, $y];
    };

    if (isset($map['paths']) && is_array($map['paths'])) {
        foreach ($map['paths'] as $point) {
            if (!is_array($point) || count($point) < 2) {
                continue;
            }

            $add((int) $point[0], (int) $point[1]);
        }
    } else {
        foreach (default_board_paths() as $point) {
            $add((int) $point[0], (int) $point[1]);
        }
    }

    /**
     * Стартовые позиции всегда считаем доступными.
     * Это защита от карты, где автор забыл добавить стартовые клетки в paths.
     */
    foreach (map_character_starts($gid) as [$x, $y]) {
        $add((int) $x, (int) $y);
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
        'starts' => characters_for_game($gid)
    ];
}