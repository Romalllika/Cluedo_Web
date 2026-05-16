<?php

require_once __DIR__ . '/../includes/config.php';

require_auth();

require_once __DIR__ . '/../includes/cards.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/maps.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$maps = available_maps();
$selectedMapId = normalize_map_id($_GET['map'] ?? 'classic_mansion');
$map = load_map_config_by_id($selectedMapId);

$hasOwnCards = isset($map['cards']) && is_array($map['cards']);

$cards = map_cards_from_config($map);

$groups = [
    'suspect' => [
        'title' => 'Персонажи',
        'cards' => array_values(array_filter($cards, fn(array $card) => $card['type'] === 'suspect')),
    ],
    'weapon' => [
        'title' => 'Оружие',
        'cards' => array_values(array_filter($cards, fn(array $card) => $card['type'] === 'weapon')),
    ],
    'room' => [
        'title' => 'Комнаты',
        'cards' => array_values(array_filter($cards, fn(array $card) => $card['type'] === 'room')),
    ],
];

$layoutRooms = is_array($map['rooms'] ?? null) ? $map['rooms'] : [];
$roomGeometryByCardId = [];

foreach ($layoutRooms as $roomName => $room) {
    if (!is_array($room)) {
        continue;
    }

    $cardId = trim((string) ($room['card_id'] ?? ''));

    if ($cardId !== '') {
        $roomGeometryByCardId[$cardId][] = (string) $roomName;
    }
}

$totalCards = count($groups['suspect']['cards']) + count($groups['weapon']['cards']) + count($groups['room']['cards']);
?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <title>Карточки карты</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            background: #111827;
            color: #eef2ff;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .page {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
            padding: 24px 0 40px;
        }

        .top {
            display: flex;
            gap: 14px;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        h2 {
            margin: 0 0 14px;
            font-size: 20px;
        }

        .muted {
            color: rgba(238, 242, 255, .68);
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn,
        select {
            border: 1px solid rgba(255, 255, 255, .16);
            background: rgba(255, 255, 255, .08);
            color: #fff;
            border-radius: 12px;
            padding: 9px 12px;
            font: inherit;
            text-decoration: none;
        }

        select {
            min-width: 260px;
        }

        option {
            color: #111827;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .summary-card,
        .panel {
            background: rgba(255, 255, 255, .07);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 18px;
            padding: 16px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, .18);
        }

        .summary-card b {
            display: block;
            font-size: 22px;
            margin-top: 5px;
        }

        .status-ok {
            color: #70e38c;
            font-weight: 800;
        }

        .status-warn {
            color: #ffd166;
            font-weight: 800;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            align-items: start;
        }

        .card-list {
            display: grid;
            gap: 10px;
        }

        .game-card {
            border: 1px solid rgba(255, 255, 255, .1);
            background: rgba(0, 0, 0, .16);
            border-radius: 14px;
            padding: 12px;
        }

        .game-card h3 {
            margin: 0 0 8px;
            font-size: 16px;
        }

        .row {
            display: grid;
            grid-template-columns: 88px minmax(0, 1fr);
            gap: 8px;
            padding: 4px 0;
            font-size: 13px;
        }

        code {
            color: #bfdbfe;
            overflow-wrap: anywhere;
        }

        .color-dot {
            display: inline-block;
            width: 13px;
            height: 13px;
            border-radius: 999px;
            vertical-align: -2px;
            border: 1px solid rgba(255, 255, 255, .45);
            margin-right: 6px;
        }

        .fallback-warning {
            margin: -4px 0 18px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(255, 209, 102, .35);
            background: rgba(255, 209, 102, .12);
            color: #ffd166;
            font-weight: 700;
        }

        .fallback-warning code {
            color: #fff3bf;
        }

        @media (max-width: 900px) {

            .summary,
            .grid {
                grid-template-columns: 1fr;
            }

            select {
                min-width: 0;
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="top">
            <div>
                <h1>Карточки карты</h1>
                <div class="muted">
                    <?= e((string) ($map['title'] ?? $selectedMapId)) ?>
                    · <code><?= e($selectedMapId) ?></code>
                </div>
            </div>

            <div class="actions">
                <a class="btn" href="map_preview.php?map=<?= e($selectedMapId) ?>">Карта</a>
                <a class="btn" href="validate_maps.php">Валидатор</a>
                <a class="btn" href="../lobby.php">Лобби</a>
            </div>
        </div>

        <form method="get" style="margin-bottom: 18px;">
            <select name="map" onchange="this.form.submit()">
                <?php foreach ($maps as $mapId => $mapInfo): ?>
                    <option value="<?= e((string) $mapId) ?>" <?= $mapId === $selectedMapId ? 'selected' : '' ?>>
                        <?= e((string) $mapInfo['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <div class="summary">
            <div class="summary-card">
                <span class="muted">Источник</span>
                <b class="<?= $hasOwnCards ? 'status-ok' : 'status-warn' ?>">
                    <?= $hasOwnCards ? 'JSON' : 'Fallback' ?>
                </b>
            </div>

            <div class="summary-card">
                <span class="muted">Всего карточек</span>
                <b><?= $totalCards ?></b>
            </div>

            <div class="summary-card">
                <span class="muted">Персонажи</span>
                <b><?= count($groups['suspect']['cards']) ?> / 6</b>
            </div>

            <div class="summary-card">
                <span class="muted">Оружие</span>
                <b><?= count($groups['weapon']['cards']) ?> / 6</b>
            </div>

            <div class="summary-card">
                <span class="muted">Комнаты</span>
                <b><?= count($groups['room']['cards']) ?> / 16</b>
            </div>
        </div>
        <?php if (!$hasOwnCards): ?>
            <div class="fallback-warning">
                Карточки взяты из fallback-набора. В JSON этой карты пока нет блока <code>cards</code>.
            </div>
        <?php endif; ?>
        <div class="grid">
            <?php foreach ($groups as $type => $group): ?>
                <section class="panel">
                    <h2><?= e($group['title']) ?></h2>

                    <?php if (!$group['cards']): ?>
                        <p class="muted">Нет карточек.</p>
                    <?php endif; ?>

                    <div class="card-list">
                        <?php foreach ($group['cards'] as $card): ?>
                            <?php
                            $id = (string) ($card['id'] ?? '');
                            $title = (string) ($card['title'] ?? '');
                            $legacy = (string) ($card['legacy_name'] ?? $title);
                            $image = $card['image'] ?? null;
                            $color = $card['color'] ?? null;
                            $roomKey = $card['room_key'] ?? null;
                            $linkedRooms = $type === 'room' ? ($roomGeometryByCardId[$id] ?? []) : [];
                            ?>
                            <article class="game-card">
                                <h3>
                                    <?php if ($color): ?>
                                        <span class="color-dot" style="background: <?= e((string) $color) ?>"></span>
                                    <?php endif; ?>
                                    <?= e($title) ?>
                                </h3>

                                <div class="row">
                                    <span class="muted">id</span>
                                    <code><?= e($id) ?></code>
                                </div>

                                <div class="row">
                                    <span class="muted">type</span>
                                    <span><?= e((string) ($card['type'] ?? $type)) ?></span>
                                </div>

                                <div class="row">
                                    <span class="muted">legacy</span>
                                    <span><?= e($legacy) ?></span>
                                </div>

                                <?php if ($image !== null): ?>
                                    <div class="row">
                                        <span class="muted">image</span>
                                        <code><?= e((string) $image) ?></code>
                                    </div>
                                <?php endif; ?>

                                <?php if ($type === 'room'): ?>
                                    <div class="row">
                                        <span class="muted">room_key</span>
                                        <span><?= e((string) ($roomKey ?? '—')) ?></span>
                                    </div>

                                    <div class="row">
                                        <span class="muted">комната поля</span>
                                        <span>
                                            <?= $linkedRooms
                                                ? e(implode(', ', $linkedRooms))
                                                : '<span class="status-warn">не связана</span>' ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</body>

</html>