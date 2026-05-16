<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (PHP_SAPI !== 'cli') {
    require_auth();
}

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

$defaultCharacterStarts = [
    'Алекс Громов' => [8, 9],
    'Мария Скарлет' => [7, 9],
    'Профессор Фиолетов' => [9, 9],
    'Виктор Олив' => [8, 8],
    'Елена Белая' => [7, 8],
    'София Синяя' => [9, 8],
];

$cardLimits = [
    'suspects' => 6,
    'weapons' => 6,
    'rooms' => 16,
];

function add_error(array &$errors, string $message): void
{
    $errors[] = $message;
}

function cell_key(int $x, int $y): string
{
    return $x . ':' . $y;
}
function validate_card_group(
    array $cardsBlock,
    string $jsonKey,
    string $expectedType,
    int $maxCount,
    array &$errors,
    array &$warnings
): array {
    $cards = $cardsBlock[$jsonKey] ?? null;
    $ids = [];

    if (!is_array($cards)) {
        add_error($errors, "cards.$jsonKey должен быть массивом");
        return $ids;
    }

    if (count($cards) === 0) {
        add_error($errors, "cards.$jsonKey не должен быть пустым");
        return $ids;
    }

    if (count($cards) > $maxCount) {
        add_error($errors, "cards.$jsonKey содержит слишком много карточек: " . count($cards) . " из $maxCount");
    }

    foreach ($cards as $index => $card) {
        if (!is_array($card)) {
            add_error($errors, "cards.$jsonKey[$index] должен быть объектом");
            continue;
        }

        $id = trim((string) ($card['id'] ?? ''));
        $type = trim((string) ($card['type'] ?? $expectedType));
        $title = trim((string) ($card['title'] ?? ''));
        $legacyName = trim((string) ($card['legacy_name'] ?? $title));

        if ($id === '') {
            add_error($errors, "cards.$jsonKey[$index].id не указан");
            continue;
        }

        if (!preg_match('/^[a-z0-9_]+$/', $id)) {
            add_error($errors, "cards.$jsonKey[$index].id `$id` должен содержать только латинские буквы, цифры и подчёркивания");
        }

        if (isset($ids[$id])) {
            add_error($errors, "Дублирующийся card id `$id` в cards.$jsonKey");
            continue;
        }

        $ids[$id] = [
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'legacy_name' => $legacyName,
            'index' => $index,
        ];

        if ($type !== $expectedType) {
            add_error($errors, "cards.$jsonKey[$index].type должен быть `$expectedType`, сейчас `$type`");
        }

        if ($title === '') {
            add_error($errors, "cards.$jsonKey[$index].title не указан");
        }

        if ($legacyName === '') {
            add_error($errors, "cards.$jsonKey[$index].legacy_name не указан");
        }

        if (array_key_exists('image', $card) && $card['image'] !== null && !is_string($card['image'])) {
            add_error($errors, "cards.$jsonKey[$index].image должен быть строкой или null");
        }

        if ($expectedType === 'suspect') {
            $color = $card['color'] ?? null;

            if ($color === null || trim((string) $color) === '') {
                $warnings[] = "cards.$jsonKey[$index] `$id`: не указан color";
            } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color)) {
                add_error($errors, "cards.$jsonKey[$index].color должен быть HEX-цветом вида #d62828");
            }
        }

        if ($expectedType === 'room') {
            $roomKey = trim((string) ($card['room_key'] ?? $legacyName));

            if ($roomKey === '') {
                add_error($errors, "cards.$jsonKey[$index].room_key не указан");
            }

            $ids[$id]['room_key'] = $roomKey;
        }
    }

    return $ids;
}

function validate_cards_block(
    array $map,
    array $cardLimits,
    array &$errors,
    array &$warnings
): array {
    $cardsBlock = $map['cards'] ?? null;

    if ($cardsBlock === null) {
        return [
            'has_cards' => false,
            'suspects' => [],
            'weapons' => [],
            'rooms' => [],
            'all_ids' => [],
        ];
    }

    if (!is_array($cardsBlock)) {
        add_error($errors, 'cards должен быть объектом с группами suspects, weapons, rooms');

        return [
            'has_cards' => true,
            'suspects' => [],
            'weapons' => [],
            'rooms' => [],
            'all_ids' => [],
        ];
    }

    $suspects = validate_card_group(
        $cardsBlock,
        'suspects',
        'suspect',
        (int) $cardLimits['suspects'],
        $errors,
        $warnings
    );

    $weapons = validate_card_group(
        $cardsBlock,
        'weapons',
        'weapon',
        (int) $cardLimits['weapons'],
        $errors,
        $warnings
    );

    $rooms = validate_card_group(
        $cardsBlock,
        'rooms',
        'room',
        (int) $cardLimits['rooms'],
        $errors,
        $warnings
    );

    $allIds = [];

    foreach ([
        'suspects' => $suspects,
        'weapons' => $weapons,
        'rooms' => $rooms,
    ] as $group => $items) {
        foreach ($items as $id => $card) {
            if (isset($allIds[$id])) {
                add_error($errors, "card id `$id` повторяется в разных группах cards");
            }

            $allIds[$id] = $group;
        }
    }

    return [
        'has_cards' => true,
        'suspects' => $suspects,
        'weapons' => $weapons,
        'rooms' => $rooms,
        'all_ids' => $allIds,
    ];
}
function map_character_starts_for_validation(
    array $map,
    array $defaultCharacterStarts,
    array &$errors,
    array &$warnings
): array {
    $starts = $defaultCharacterStarts;

    if (!isset($map['starts'])) {
        $warnings[] = 'В карте нет блока starts. Используются стартовые позиции по умолчанию.';
        return array_values($starts);
    }

    if (!is_array($map['starts'])) {
        add_error($errors, 'starts должен быть объектом вида "card_id или имя персонажа": [x,y]');
        return array_values($starts);
    }

    $suspectCards = [];

    if (isset($map['cards']) && is_array($map['cards']) && isset($map['cards']['suspects']) && is_array($map['cards']['suspects'])) {
        foreach ($map['cards']['suspects'] as $card) {
            if (!is_array($card)) {
                continue;
            }

            $id = trim((string) ($card['id'] ?? ''));
            $name = trim((string) ($card['legacy_name'] ?? $card['title'] ?? ''));

            if ($id !== '' && $name !== '') {
                $suspectCards[$id] = $name;
            }
        }
    }

    /**
     * Если в карте нет cards.suspects, используем старые имена.
     * Это fallback для карт, которые ещё не переехали на cards в JSON.
     */
    if (!$suspectCards) {
        foreach ($defaultCharacterStarts as $name => $point) {
            $suspectCards[$name] = $name;
        }
    }

    $knownStartKeys = [];

    foreach ($suspectCards as $startKey => $name) {
        $knownStartKeys[$startKey] = true;

        if (!isset($defaultCharacterStarts[$name])) {
            $warnings[] = "cards.suspects содержит персонажа `$name`, которого нет в стандартных персонажах";
            continue;
        }

        $point = $map['starts'][$startKey] ?? null;

        /**
         * Fallback на старый формат:
         * starts по имени персонажа.
         */
        if (!is_array($point)) {
            $point = $map['starts'][$name] ?? null;

            if (is_array($point)) {
                $knownStartKeys[$name] = true;
            }
        }

        if (!is_array($point) || count($point) < 2) {
            $warnings[] = "В starts не указан персонаж `$name`. Используется fallback.";
            continue;
        }

        $starts[$name] = [(int) $point[0], (int) $point[1]];
    }

    foreach ($map['starts'] as $key => $point) {
        if (!isset($knownStartKeys[$key])) {
            $warnings[] = "starts содержит неизвестный ключ `$key`";
        }

        if (!is_array($point) || count($point) < 2) {
            add_error($errors, "starts.`$key` должен быть массивом [x,y]");
        }
    }

    return array_values($starts);
}

function default_path_keys(array $characterStarts): array
{
    $paths = [];

    /**
     * Это старый fallback, соответствующий default_board_paths()
     * из includes/data.php.
     */
    for ($y = 4; $y <= 13; $y++) {
        for ($x = 5; $x <= 11; $x++) {
            $paths[cell_key($x, $y)] = true;
        }
    }

    foreach ($characterStarts as [$x, $y]) {
        $paths[cell_key((int) $x, (int) $y)] = true;
    }

    return $paths;
}

function parse_cell_key(string $key): array
{
    [$x, $y] = array_map('intval', explode(':', $key, 2));

    return [$x, $y];
}

function neighbor_cells(int $x, int $y): array
{
    return [
        [$x + 1, $y],
        [$x - 1, $y],
        [$x, $y + 1],
        [$x, $y - 1],
    ];
}

function map_path_keys(
    array $map,
    int $width,
    int $height,
    array $rooms,
    array $characterStarts,
    array &$errors
): array {
    if (!isset($map['paths'])) {
        return default_path_keys($characterStarts);
    }

    if (!is_array($map['paths'])) {
        add_error($errors, 'paths должен быть массивом координат');
        return default_path_keys($characterStarts);
    }

    $paths = [];

    foreach ($map['paths'] as $index => $point) {
        if (!is_array($point) || count($point) < 2) {
            add_error($errors, "paths[$index]: точка должна быть массивом [x,y]");
            continue;
        }

        $x = (int) $point[0];
        $y = (int) $point[1];

        if ($x < 0 || $y < 0 || $x >= $width || $y >= $height) {
            add_error($errors, "paths[$index]: точка [$x,$y] выходит за границы поля");
            continue;
        }

        if (point_in_any_room($x, $y, $rooms)) {
            add_error($errors, "paths[$index]: точка [$x,$y] находится внутри комнаты");
            continue;
        }

        $paths[cell_key($x, $y)] = true;
    }

    foreach ($characterStarts as [$x, $y]) {
        $x = (int) $x;
        $y = (int) $y;

        if ($x < 0 || $y < 0 || $x >= $width || $y >= $height) {
            add_error($errors, "Стартовая позиция [$x,$y] выходит за границы поля");
            continue;
        }

        if (point_in_any_room($x, $y, $rooms)) {
            add_error($errors, "Стартовая позиция [$x,$y] находится внутри комнаты");
            continue;
        }

        $paths[cell_key($x, $y)] = true;
    }

    if (!$paths) {
        add_error($errors, 'paths не содержит ни одной валидной клетки');
        return default_path_keys($characterStarts);
    }

    return $paths;
}

function reachable_path_keys(array $pathKeys, array $characterStarts): array
{
    if (!$pathKeys) {
        return [];
    }

    $startKey = null;

    foreach ($characterStarts as [$x, $y]) {
        $key = cell_key((int) $x, (int) $y);

        if (isset($pathKeys[$key])) {
            $startKey = $key;
            break;
        }
    }

    if ($startKey === null) {
        $startKey = array_key_first($pathKeys);
    }

    $queue = [$startKey];
    $visited = [$startKey => true];

    while ($queue) {
        $current = array_shift($queue);
        [$x, $y] = parse_cell_key($current);

        foreach (neighbor_cells($x, $y) as [$nx, $ny]) {
            $nextKey = cell_key($nx, $ny);

            if (!isset($pathKeys[$nextKey]) || isset($visited[$nextKey])) {
                continue;
            }

            $visited[$nextKey] = true;
            $queue[] = $nextKey;
        }
    }

    return $visited;
}

function door_entry_cells(array $room, array $pathKeys, array $rooms): array
{
    if (!isset($room['door']) || !is_array($room['door']) || count($room['door']) < 2) {
        return [];
    }

    $dx = (int) $room['door'][0];
    $dy = (int) $room['door'][1];

    $entries = [];

    foreach (neighbor_cells($dx, $dy) as [$nx, $ny]) {
        $key = cell_key($nx, $ny);

        if (
            isset($pathKeys[$key]) &&
            !point_in_any_room($nx, $ny, $rooms)
        ) {
            $entries[$key] = [$nx, $ny];
        }
    }

    return $entries;
}

function validate_path_connectivity(
    array $rooms,
    array $pathKeys,
    array $characterStarts,
    array &$errors,
    array &$warnings
): void {
    if (!$pathKeys) {
        add_error($errors, 'Нет валидных клеток paths для проверки связности');
        return;
    }

    $reachable = reachable_path_keys($pathKeys, $characterStarts);

    if (!$reachable) {
        add_error($errors, 'Не удалось построить достижимую сеть paths от стартовой зоны');
        return;
    }

    $isolated = array_diff_key($pathKeys, $reachable);

    if ($isolated) {
        $examples = array_slice(array_keys($isolated), 0, 10);

        add_error(
            $errors,
            'Найдены изолированные клетки paths, недостижимые от стартовой зоны: ' .
            implode(', ', $examples) .
            (count($isolated) > 10 ? ' ...' : '')
        );
    }

    foreach ($rooms as $roomName => $room) {
        if (!is_array($room)) {
            continue;
        }

        $entries = door_entry_cells($room, $pathKeys, $rooms);

        if (!$entries) {
            continue;
        }

        $hasReachableEntry = false;

        foreach ($entries as $key => $point) {
            if (isset($reachable[$key])) {
                $hasReachableEntry = true;
                break;
            }
        }

        if (!$hasReachableEntry) {
            $door = $room['door'];
            add_error(
                $errors,
                "Комната `$roomName`: дверь [" . (int) $door[0] . "," . (int) $door[1] . "] имеет соседний path, но он недостижим от стартовой зоны"
            );
        }
    }

    foreach ($characterStarts as [$x, $y]) {
        $key = cell_key((int) $x, (int) $y);

        if (isset($pathKeys[$key]) && !isset($reachable[$key])) {
            $warnings[] = "Стартовая позиция [$x,$y] находится вне основной сети paths";
        }
    }
}
function explicit_json_path_keys(array $map): array
{
    $keys = [];

    if (!isset($map['paths']) || !is_array($map['paths'])) {
        return $keys;
    }

    foreach ($map['paths'] as $point) {
        if (!is_array($point) || count($point) < 2) {
            continue;
        }

        $keys[cell_key((int) $point[0], (int) $point[1])] = true;
    }

    return $keys;
}

function validate_character_starts(
    array $map,
    int $width,
    int $height,
    array $rooms,
    array $pathKeys,
    array $reachable,
    array $characterStarts,
    array &$errors,
    array &$warnings
): void {
    if (!$characterStarts) {
        add_error($errors, 'Не заданы стартовые позиции персонажей');
        return;
    }

    $seen = [];
    $explicitPaths = explicit_json_path_keys($map);
    $hasExplicitPaths = isset($map['paths']) && is_array($map['paths']);

    foreach ($characterStarts as $index => [$x, $y]) {
        $x = (int) $x;
        $y = (int) $y;
        $key = cell_key($x, $y);
        $label = 'Стартовая позиция #' . ($index + 1) . " [$x,$y]";

        if (isset($seen[$key])) {
            add_error($errors, "$label дублирует другую стартовую позицию");
        }

        $seen[$key] = true;

        if ($x < 0 || $y < 0 || $x >= $width || $y >= $height) {
            add_error($errors, "$label выходит за границы поля");
            continue;
        }

        if (point_in_any_room($x, $y, $rooms)) {
            add_error($errors, "$label находится внутри комнаты");
            continue;
        }

        if (!isset($pathKeys[$key])) {
            add_error($errors, "$label не находится в paths");
            continue;
        }

        if (!isset($reachable[$key])) {
            add_error($errors, "$label недостижима из основной сети paths");
        }

        if ($hasExplicitPaths && !isset($explicitPaths[$key])) {
            $warnings[] = "$label не указана явно в JSON paths. Сейчас она добавляется автоматически, но для самодостаточной карты лучше добавить её в paths.";
        }
    }
}

function map_statistics(
    array $map,
    int $width,
    int $height,
    array $rooms,
    array $pathKeys,
    array $characterStarts
): array {
    $doors = 0;
    $secrets = 0;

    foreach ($rooms as $room) {
        if (!is_array($room)) {
            continue;
        }

        if (isset($room['door']) && is_array($room['door']) && count($room['door']) >= 2) {
            $doors++;
        }

        if (!empty($room['secret'])) {
            $secrets++;
        }
    }

    return [
        'board' => $width . 'x' . $height,
        'rooms' => count($rooms),
        'paths' => count($pathKeys),
        'doors' => $doors,
        'secrets' => $secrets,
        'starts' => count($characterStarts),
        'has_explicit_paths' => isset($map['paths']) && is_array($map['paths']),
        'cards_suspects' => count($cardsValidation['suspects'] ?? []),
        'cards_weapons' => count($cardsValidation['weapons'] ?? []),
        'cards_rooms' => count($cardsValidation['rooms'] ?? []),
    ];
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
    array $defaultCharacterStarts,
    array $cardLimits
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
    $cardsValidation = validate_cards_block($data, $cardLimits, $errors, $warnings);
    $characterStarts = map_character_starts_for_validation(
        $data,
        $defaultCharacterStarts,
        $errors,
        $warnings
    );

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

    $hasCardsBlock = (bool) ($cardsValidation['has_cards'] ?? false);
    $roomCardIds = $cardsValidation['rooms'] ?? [];

    // if ($hasCardsBlock) {
    //     $cardRooms = $data['cards']['rooms'] ?? [];

    //     if (!is_array($cardRooms) || !$cardRooms) {
    //         add_error($errors, 'cards.rooms должен содержать список карточек комнат');
    //     } else {
    //         foreach ($cardRooms as $cardIndex => $card) {
    //             if (!is_array($card)) {
    //                 add_error($errors, "cards.rooms[$cardIndex] должен быть объектом");
    //                 continue;
    //             }

    //             $cardId = trim((string) ($card['id'] ?? ''));

    //             if ($cardId === '') {
    //                 add_error($errors, "cards.rooms[$cardIndex].id не указан");
    //                 continue;
    //             }

    //             if (isset($roomCardIds[$cardId])) {
    //                 add_error($errors, "Дублирующийся id карточки комнаты `$cardId`");
    //                 continue;
    //             }

    //             $roomCardIds[$cardId] = true;
    //         }
    //     }
    // }

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
        if ($hasCardsBlock) {
            $roomCardId = trim((string) ($room['card_id'] ?? ''));

            if ($roomCardId === '') {
                add_error($errors, "Комната `$roomName`: не указан card_id");
            } elseif (!isset($roomCardIds[$roomCardId])) {
                add_error($errors, "Комната `$roomName`: card_id `$roomCardId` не найден в cards.rooms");
            }
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

    $pathKeys = map_path_keys($data, $w, $h, $rooms, $characterStarts, $errors);
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

        $entries = door_entry_cells($room, $pathKeys, $rooms);

        if (!$entries) {
            add_error($errors, "Комната `$roomName`: у двери [$dx,$dy] нет соседней клетки коридора");
        }
    }

    validate_path_connectivity(
        $rooms,
        $pathKeys,
        $characterStarts,
        $errors,
        $warnings
    );
    $reachable = reachable_path_keys($pathKeys, $characterStarts);

    validate_character_starts(
        $data,
        $w,
        $h,
        $rooms,
        $pathKeys,
        $reachable,
        $characterStarts,
        $errors,
        $warnings
    );

    $stats = map_statistics(
        $data,
        $w,
        $h,
        $rooms,
        $pathKeys,
        $characterStarts
    );

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
        'stats' => $stats ?? [
            'board' => 'unknown',
            'rooms' => 0,
            'paths' => 0,
            'doors' => 0,
            'secrets' => 0,
            'starts' => 0,
            'has_explicit_paths' => false,
        ],
    ];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== realpath(__FILE__)) {
    return;
}

$files = glob($mapsDir . '/*.json') ?: [];
sort($files);

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Проверка карт</title>';
    echo '<style>
        body{font-family:Arial,sans-serif;background:#101322;color:#eef2ff;padding:24px}
        .ok{color:#70e38c}.err{color:#ff7b7b}.warn{color:#ffd166}.muted{color:rgba(238,242,255,.68)}
        section{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);border-radius:16px;padding:16px;margin:12px 0}
        code{background:rgba(0,0,0,.25);padding:2px 6px;border-radius:6px}
    </style>';
    echo '<h1>Проверка JSON-карт</h1>';
}

$hasErrors = false;

foreach ($files as $file) {
    $result = validate_map_file($file, $mapsDir, $requiredRooms, $defaultCharacterStarts, $cardLimits);
    $name = basename($file);

    if ($result['errors']) {
        $hasErrors = true;
    }

    if ($isCli) {
        echo $name . ': ' . ($result['errors'] ? 'ERROR' : 'OK') . PHP_EOL;

        $stats = $result['stats'] ?? [];

        echo '  [INFO] board=' . ($stats['board'] ?? 'unknown') .
            ', rooms=' . ($stats['rooms'] ?? 0) .
            ', paths=' . ($stats['paths'] ?? 0) .
            ', doors=' . ($stats['doors'] ?? 0) .
            ', secrets=' . ($stats['secrets'] ?? 0) .
            ', starts=' . ($stats['starts'] ?? 0) .
            ', explicit_paths=' . (!empty($stats['has_explicit_paths']) ? 'yes' : 'no') .
            PHP_EOL;

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

    $stats = $result['stats'] ?? [];

    echo '<p class="muted">';
    echo 'Поле: <b>' . htmlspecialchars((string) ($stats['board'] ?? 'unknown')) . '</b> · ';
    echo 'Комнат: <b>' . (int) ($stats['rooms'] ?? 0) . '</b> · ';
    echo 'Коридоров: <b>' . (int) ($stats['paths'] ?? 0) . '</b> · ';
    echo 'Дверей: <b>' . (int) ($stats['doors'] ?? 0) . '</b> · ';
    echo 'Secret: <b>' . (int) ($stats['secrets'] ?? 0) . '</b> · ';
    echo 'Стартов: <b>' . (int) ($stats['starts'] ?? 0) . '</b> · ';
    echo 'Paths в JSON: <b>' . (!empty($stats['has_explicit_paths']) ? 'да' : 'нет') . '</b>';
    echo '</p>';

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