<?php

function character_starts_from_config(array $map): array
{
    $starts = [];
    $suspectCards = map_suspect_cards_from_config($map);

    foreach ($suspectCards as $card) {
        $cardId = (string) ($card['id'] ?? '');
        $name   = (string) ($card['legacy_name'] ?? $card['title'] ?? '');

        if ($name === '') {
            continue;
        }

        // Новый формат: starts по card_id.
        $point = $cardId !== '' ? ($map['starts'][$cardId] ?? null) : null;

        // Старый формат: starts по legacy-имени персонажа.
        if (!is_array($point)) {
            $point = $map['starts'][$name] ?? null;
        }

        if (!is_array($point) || count($point) < 2) {
            // Позиция не указана в JSON — кладём [0,0] и валидатор это поймает.
            $point = [0, 0];
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
    $map = load_map_config($gid);
    $starts = character_starts_from_config($map);
    $suspectCards = map_suspect_cards($gid);

    $characters = [];

    foreach ($suspectCards as $card) {
        $name = (string) ($card['legacy_name'] ?? $card['title'] ?? '');

        if ($name === '') {
            continue;
        }

        $point = $starts[$name] ?? [0, 0];

        $characters[] = [
            'id'      => (string) ($card['id'] ?? ''),
            'card_id' => (string) ($card['id'] ?? ''),
            'name'    => $name,
            'title'   => (string) ($card['title'] ?? $name),
            'color'   => (string) ($card['color'] ?? '#f5c542'),
            'image'   => $card['image'] ?? null,
            'x'       => (int) $point[0],
            'y'       => (int) $point[1],
        ];
    }

    return $characters;
}

function available_maps(): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $mapsDir = __DIR__ . '/../maps';
    $cache = [];

    foreach (glob($mapsDir . '/*.json') ?: [] as $path) {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            continue;
        }
        $data = @json_decode($raw, true);
        if (!is_array($data)) {
            continue;
        }
        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            $id = basename($path, '.json');
        }
        $cache[$id] = [
            'id'          => $id,
            'title'       => (string) ($data['title'] ?? $id),
            'description' => (string) ($data['description'] ?? ''),
        ];
    }

    // Гарантируем что classic_mansion идёт первым, если он есть
    if (isset($cache['classic_mansion'])) {
        $cache = ['classic_mansion' => $cache['classic_mansion']] + $cache;
    }

    return $cache;
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

function load_map_config_by_id(string $mapId): array
{
    $mapId = normalize_map_id($mapId);

    static $cache = [];

    if (isset($cache[$mapId])) {
        return $cache[$mapId];
    }

    $path = __DIR__ . '/../maps/' . $mapId . '.json';

    if (!is_file($path)) {
        throw new RuntimeException('Карта не найдена: ' . $mapId);
    }

    $json = file_get_contents($path);
    $data = json_decode($json, true);

    if (!is_array($data)) {
        throw new RuntimeException('Не удалось прочитать карту: ' . $mapId);
    }

    $cache[$mapId] = $data;

    return $cache[$mapId];
}

function load_map_config(int $gid = 0): array
{
    return load_map_config_by_id(map_id_for_game($gid));
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
            'card_id' => $room['card_id'] ?? null,
        ];

        $out[$name]['doors'] = [$out[$name]['door']];
    }

    return $out;
}


function normalize_map_card(array $card, string $type): array
{
    $id = trim((string) ($card['id'] ?? ''));
    $title = trim((string) ($card['title'] ?? ''));
    $legacyName = trim((string) ($card['legacy_name'] ?? $title));

    return [
        'id' => $id,
        'type' => $type,
        'title' => $title !== '' ? $title : $legacyName,
        'legacy_name' => $legacyName !== '' ? $legacyName : $title,
        'image' => $card['image'] ?? null,
        'color' => $card['color'] ?? null,
        'room_key' => $card['room_key'] ?? null,
    ];
}

function map_cards_from_config(array $map): array
{
    $cardsBlock = $map['cards'] ?? null;

    if (!is_array($cardsBlock)) {
        // Блок cards не определён в JSON-карте.
        return [];
    }

    $out = [];

    $groups = [
        'suspects' => 'suspect',
        'weapons' => 'weapon',
        'rooms' => 'room',
    ];

    foreach ($groups as $jsonKey => $type) {
        $items = $cardsBlock[$jsonKey] ?? [];

        if (!is_array($items)) {
            continue;
        }

        foreach ($items as $card) {
            if (!is_array($card)) {
                continue;
            }

            $normalized = normalize_map_card($card, $type);

            if ($normalized['id'] === '' || $normalized['title'] === '') {
                continue;
            }

            $out[] = $normalized;
        }
    }

    return $out;
}

function map_suspect_cards_from_config(array $map): array
{
    return array_values(array_filter(
        map_cards_from_config($map),
        fn(array $card) => $card['type'] === 'suspect'
    ));
}

function map_cards(int $gid = 0): array
{
    return map_cards_from_config(load_map_config($gid));
}
function map_cards_by_type(int $gid, string $type): array
{
    return array_values(array_filter(
        map_cards($gid),
        fn(array $card) => $card['type'] === $type
    ));
}

function map_suspect_cards(int $gid = 0): array
{
    return map_cards_by_type($gid, 'suspect');
}

function map_weapon_cards(int $gid = 0): array
{
    return map_cards_by_type($gid, 'weapon');
}

function map_room_cards(int $gid = 0): array
{
    return map_cards_by_type($gid, 'room');
}

function map_card_by_id(int $gid, string $id): ?array
{
    foreach (map_cards($gid) as $card) {
        if ($card['id'] === $id) {
            return $card;
        }
    }

    return null;
}

function map_legacy_card_name_to_id(int $gid, string $type, string $name): ?string
{
    foreach (map_cards_by_type($gid, $type) as $card) {
        if ($card['legacy_name'] === $name || $card['title'] === $name) {
            return (string) $card['id'];
        }
    }

    return null;
}

function map_legacy_card_titles_by_type(int $gid, string $type): array
{
    return array_map(
        fn(array $card) => (string) $card['legacy_name'],
        map_cards_by_type($gid, $type)
    );
}