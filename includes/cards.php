<?php

function cards(): array
{
    return [
        [
            'id' => 'suspect_alex_gromov',
            'type' => 'suspect',
            'title' => 'Алекс Громов',
            'legacy_name' => 'Алекс Громов',
            'image' => null,
            'color' => '#d62828',
        ],
        [
            'id' => 'suspect_maria_scarlet',
            'type' => 'suspect',
            'title' => 'Мария Скарлет',
            'legacy_name' => 'Мария Скарлет',
            'image' => null,
            'color' => '#c1121f',
        ],
        [
            'id' => 'suspect_professor_violet',
            'type' => 'suspect',
            'title' => 'Профессор Фиолетов',
            'legacy_name' => 'Профессор Фиолетов',
            'image' => null,
            'color' => '#7209b7',
        ],
        [
            'id' => 'suspect_viktor_olive',
            'type' => 'suspect',
            'title' => 'Виктор Олив',
            'legacy_name' => 'Виктор Олив',
            'image' => null,
            'color' => '#6a994e',
        ],
        [
            'id' => 'suspect_elena_white',
            'type' => 'suspect',
            'title' => 'Елена Белая',
            'legacy_name' => 'Елена Белая',
            'image' => null,
            'color' => '#f8f9fa',
        ],
        [
            'id' => 'suspect_sofia_blue',
            'type' => 'suspect',
            'title' => 'София Синяя',
            'legacy_name' => 'София Синяя',
            'image' => null,
            'color' => '#277da1',
        ],

        [
            'id' => 'weapon_knife',
            'type' => 'weapon',
            'title' => 'Кинжал',
            'legacy_name' => 'Кинжал',
            'image' => null,
        ],
        [
            'id' => 'weapon_revolver',
            'type' => 'weapon',
            'title' => 'Револьвер',
            'legacy_name' => 'Револьвер',
            'image' => null,
        ],
        [
            'id' => 'weapon_rope',
            'type' => 'weapon',
            'title' => 'Верёвка',
            'legacy_name' => 'Верёвка',
            'image' => null,
        ],
        [
            'id' => 'weapon_candlestick',
            'type' => 'weapon',
            'title' => 'Подсвечник',
            'legacy_name' => 'Подсвечник',
            'image' => null,
        ],
        [
            'id' => 'weapon_wrench',
            'type' => 'weapon',
            'title' => 'Гаечный ключ',
            'legacy_name' => 'Гаечный ключ',
            'image' => null,
        ],
        [
            'id' => 'weapon_pipe',
            'type' => 'weapon',
            'title' => 'Труба',
            'legacy_name' => 'Труба',
            'image' => null,
        ],

        [
            'id' => 'room_kitchen',
            'type' => 'room',
            'title' => 'Кухня',
            'legacy_name' => 'Кухня',
            'image' => null,
        ],
        [
            'id' => 'room_ballroom',
            'type' => 'room',
            'title' => 'Бальный зал',
            'legacy_name' => 'Бальный зал',
            'image' => null,
        ],
        [
            'id' => 'room_greenhouse',
            'type' => 'room',
            'title' => 'Оранжерея',
            'legacy_name' => 'Оранжерея',
            'image' => null,
        ],
        [
            'id' => 'room_dining',
            'type' => 'room',
            'title' => 'Столовая',
            'legacy_name' => 'Столовая',
            'image' => null,
        ],
        [
            'id' => 'room_billiard',
            'type' => 'room',
            'title' => 'Бильярдная',
            'legacy_name' => 'Бильярдная',
            'image' => null,
        ],
        [
            'id' => 'room_library',
            'type' => 'room',
            'title' => 'Библиотека',
            'legacy_name' => 'Библиотека',
            'image' => null,
        ],
        [
            'id' => 'room_lounge',
            'type' => 'room',
            'title' => 'Гостиная',
            'legacy_name' => 'Гостиная',
            'image' => null,
        ],
        [
            'id' => 'room_hall',
            'type' => 'room',
            'title' => 'Холл',
            'legacy_name' => 'Холл',
            'image' => null,
        ],
        [
            'id' => 'room_study',
            'type' => 'room',
            'title' => 'Кабинет',
            'legacy_name' => 'Кабинет',
            'image' => null,
        ],
    ];
}

function cards_by_type(string $type): array
{
    return array_values(array_filter(
        cards(),
        fn(array $card) => $card['type'] === $type
    ));
}

function suspect_cards(): array
{
    return cards_by_type('suspect');
}

function weapon_cards(): array
{
    return cards_by_type('weapon');
}

function room_cards(): array
{
    return cards_by_type('room');
}

function card_by_id(string $id): ?array
{
    foreach (cards() as $card) {
        if ($card['id'] === $id) {
            return $card;
        }
    }

    return null;
}

function card_title(string $id): string
{
    $card = card_by_id($id);

    return $card ? (string) $card['title'] : $id;
}

function legacy_card_name_to_id(string $type, string $name): ?string
{
    foreach (cards_by_type($type) as $card) {
        if ($card['legacy_name'] === $name || $card['title'] === $name) {
            return (string) $card['id'];
        }
    }

    return null;
}

function card_id_to_legacy_name(string $id): ?string
{
    $card = card_by_id($id);

    return $card ? (string) $card['legacy_name'] : null;
}

function legacy_card_titles_by_type(string $type): array
{
    return array_map(
        fn(array $card) => (string) $card['legacy_name'],
        cards_by_type($type)
    );
}