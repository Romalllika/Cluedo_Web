<?php

function default_character_starts(): array
{
    $starts = [];

    foreach (characters() as $c) {
        $starts[$c['name']] = [(int) $c['x'], (int) $c['y']];
    }

    return $starts;
}

function character_starts_from_config(array $map): array
{
    $starts = default_character_starts();

    if (!isset($map['starts']) || !is_array($map['starts'])) {
        return $starts;
    }

    foreach ($starts as $name => $fallback) {
        $point = $map['starts'][$name] ?? null;

        if (!is_array($point) || count($point) < 2) {
            continue;
        }

        $starts[$name] = [(int) $point[0], (int) $point[1]];
    }

    return $starts;
}

function map_character_starts(int $gid = 0): array
{
    $map = load_map_config($gid);

    return character_starts_from_config($map);
}

function characters_for_game(int $gid = 0): array
{
    $starts = map_character_starts($gid);
    $characters = characters();

    foreach ($characters as &$character) {
        $name = $character['name'];

        if (!isset($starts[$name])) {
            continue;
        }

        [$x, $y] = $starts[$name];

        $character['x'] = (int) $x;
        $character['y'] = (int) $y;
    }

    unset($character);

    return $characters;
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

        'mansion_crossroads' => [
            'id' => 'mansion_crossroads',
            'title' => 'Особняк: перекрёстки',
            'description' => 'Карта с более узкой сетью коридоров и несколькими центральными перекрёстками.'
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