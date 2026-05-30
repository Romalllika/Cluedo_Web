<?php

function map_category_labels(): array
{
    return [
        'official' => 'Официальная',
        'community' => 'Пользовательская',
        'event' => 'Ивентовая',
        'testing' => 'Тестовая',
        'archived' => 'Архивная',
    ];
}

function map_visibility_labels(): array
{
    return [
        'public' => 'Всем',
        'staff' => 'Только staff',
        'private' => 'Скрыта',
    ];
}

function map_category_label(string $category): string
{
    $labels = map_category_labels();

    return $labels[$category] ?? $category;
}

function map_visibility_label(string $visibility): string
{
    $labels = map_visibility_labels();

    return $labels[$visibility] ?? $visibility;
}

function map_category_class(string $category): string
{
    return match ($category) {
        'official' => 'status-confirmed',
        'community' => 'status-reviewing',
        'event' => 'status-open',
        'testing' => 'status-reviewing',
        'archived' => 'status-closed',
        default => 'status-unknown',
    };
}

function ensure_map_settings_rows(): void
{
    $maps = available_maps();

    $insert = db()->prepare(
        'INSERT IGNORE INTO map_settings
            (map_id, title, enabled, category, visibility, sort_order)
         VALUES
            (?, ?, 1, "official", "public", ?)'
    );

    $order = 100;

    foreach ($maps as $map) {
        $insert->execute([
            $map['id'],
            $map['title'],
            $order,
        ]);

        $order += 10;
    }
}

function get_map_settings_index(): array
{
    ensure_map_settings_rows();

    $rows = db()->query(
        'SELECT *
         FROM map_settings
         ORDER BY sort_order ASC, title ASC, map_id ASC'
    )->fetchAll();

    $index = [];

    foreach ($rows as $row) {
        $index[$row['map_id']] = $row;
    }

    return $index;
}

function map_meta_from_json(string $mapId): array
{
    try {
        $map = load_map_config_by_id($mapId);

        $rooms = is_array($map['rooms'] ?? null) ? count($map['rooms']) : 0;
        $starts = is_array($map['starts'] ?? null) ? count($map['starts']) : 0;
        $board = $map['board'] ?? [];

        return [
            'rooms_count' => $rooms,
            'players_count' => $starts,
            'board_w' => (int) ($board['w'] ?? 0),
            'board_h' => (int) ($board['h'] ?? 0),
        ];
    } catch (Throwable $e) {
        return [
            'rooms_count' => 0,
            'players_count' => 0,
            'board_w' => 0,
            'board_h' => 0,
        ];
    }
}

function get_admin_maps(): array
{
    $jsonMaps = available_maps();
    $settings = get_map_settings_index();

    $out = [];

    foreach ($jsonMaps as $mapId => $map) {
        $setting = $settings[$mapId] ?? null;
        $meta = map_meta_from_json($mapId);

        $out[] = [
            'id' => $mapId,
            'title' => $map['title'],
            'json_title' => $map['title'],
            'description' => $map['description'] ?? '',
            'enabled' => (int) ($setting['enabled'] ?? 1),
            'category' => $setting['category'] ?? 'official',
            'visibility' => $setting['visibility'] ?? 'public',
            'sort_order' => (int) ($setting['sort_order'] ?? 100),
            'notes' => $setting['notes'] ?? '',
            'exists_in_json' => true,
            'meta' => $meta,
        ];
    }

    foreach ($settings as $mapId => $setting) {
        if (isset($jsonMaps[$mapId])) {
            continue;
        }

        $out[] = [
            'id' => $mapId,
            'title' => $setting['title'] ?: $mapId,
            'json_title' => 'JSON-файл отсутствует',
            'description' => '',
            'enabled' => (int) $setting['enabled'],
            'category' => $setting['category'],
            'visibility' => $setting['visibility'],
            'sort_order' => (int) $setting['sort_order'],
            'notes' => $setting['notes'] ?? '',
            'exists_in_json' => false,
            'meta' => [
                'rooms_count' => 0,
                'players_count' => 0,
                'board_w' => 0,
                'board_h' => 0,
            ],
        ];
    }

    usort($out, static function (array $a, array $b): int {
        return [$a['sort_order'], $a['title'], $a['id']]
            <=> [$b['sort_order'], $b['title'], $b['id']];
    });

    return $out;
}

function selectable_maps_for_user(?int $userId = null): array
{
    $userId = $userId ?? current_user_id();
    $isStaff = function_exists('user_is_moderator_or_admin') && user_is_moderator_or_admin($userId);

    $maps = available_maps();
    $settings = get_map_settings_index();

    $out = [];

    foreach ($maps as $mapId => $map) {
        $setting = $settings[$mapId] ?? null;

        $enabled = (int) ($setting['enabled'] ?? 1) === 1;
        $visibility = $setting['visibility'] ?? 'public';
        $category = $setting['category'] ?? 'official';

        if (!$enabled) {
            continue;
        }

        if ($visibility === 'private') {
            continue;
        }

        if ($visibility === 'staff' && !$isStaff) {
            continue;
        }

        $meta = map_meta_from_json($mapId);

        $out[$mapId] = [
            'id' => $mapId,
            'title' => $map['title'],
            'description' => $map['description'] ?? '',
            'category' => $category,
            'category_label' => map_category_label($category),
            'visibility' => $visibility,
            'meta' => $meta,
        ];
    }

    return $out;
}

function map_can_be_created(string $mapId, ?int $userId = null): bool
{
    $mapId = normalize_map_id($mapId);
    $maps = selectable_maps_for_user($userId);

    return isset($maps[$mapId]);
}

function update_map_setting(
    string $mapId,
    string $title,
    int $enabled,
    string $category,
    string $visibility,
    int $sortOrder,
    string $notes
): array {
    $mapId = trim($mapId);
    $title = trim($title);
    $notes = trim($notes);

    if ($mapId === '') {
        return ['error' => 'ID карты не указан'];
    }

    if (!array_key_exists($category, map_category_labels())) {
        return ['error' => 'Некорректный тип карты'];
    }

    if (!array_key_exists($visibility, map_visibility_labels())) {
        return ['error' => 'Некорректная видимость карты'];
    }

    $maps = available_maps();
    $title = $maps[$mapId]['title'] ?? $mapId;

    if (mb_strlen($notes) > 2000) {
        $notes = mb_substr($notes, 0, 2000);
    }

    db()->prepare(
        'INSERT INTO map_settings
            (map_id, title, enabled, category, visibility, sort_order, notes)
         VALUES
            (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            enabled = VALUES(enabled),
            category = VALUES(category),
            visibility = VALUES(visibility),
            sort_order = VALUES(sort_order),
            notes = VALUES(notes),
            updated_at = NOW()'
    )->execute([
                $mapId,
                $title,
                $enabled ? 1 : 0,
                $category,
                $visibility,
                $sortOrder,
                $notes,
            ]);

    return ['ok' => true];
}