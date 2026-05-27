<?php

require 'includes/config.php';
require_auth();
require 'includes/maps.php';
require 'includes/reports.php';

$uid = current_user_id();

$restriction = get_user_create_restriction_message((int) $uid);

if ($restriction !== null) {
    $_SESSION['flash_error'] = $restriction;
    header('Location: lobby.php');
    exit;
}


$title = trim($_POST['title'] ?? 'Новая игра');

if ($title === '') {
    $title = 'Новая игра';
}

$max = max(3, min(6, (int) ($_POST['max'] ?? 6)));
$mapId = normalize_map_id($_POST['map_id'] ?? 'classic_mansion');

$db = db();
$db->beginTransaction();

try {
    $st = $db->prepare(
        'INSERT INTO games(title, owner_id, max_players, map_id) VALUES(?,?,?,?)'
    );
    $st->execute([$title, $uid, $max, $mapId]);

    $gid = (int) $db->lastInsertId();

    $chars = characters_for_game($gid);
    $seat = 0;
    $c = $chars[$seat];

    // Позиции берутся из JSON через characters_for_game() — $c['x']/$c['y'] уже содержат их
    $startX = (int) $c['x'];
    $startY = (int) $c['y'];

    $join = $db->prepare(
        'INSERT INTO game_players
            (game_id, user_id, character_name, seat_no, turn_order, pos_x, pos_y)
         VALUES
            (?,?,?,?,?,?,?)'
    );

    $join->execute([
        $gid,
        $uid,
        $c['name'],
        $seat,
        1,
        $startX,
        $startY
    ]);

    $db->commit();

    header('Location: game.php?id=' . $gid);
    exit;
} catch (Throwable $e) {
    $db->rollBack();

    die('Ошибка создания игры: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}