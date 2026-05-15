<?php
require_once __DIR__ . '/cards.php';
function suspects(): array
{
    return legacy_card_titles_by_type('suspect');
}

function weapons(): array
{
    return legacy_card_titles_by_type('weapon');
}

function rooms(): array
{
    return legacy_card_titles_by_type('room');
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
