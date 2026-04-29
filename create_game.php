<?php

require 'includes/config.php';
require_auth();
require 'includes/data.php';

$uid = current_user_id();

$title = trim($_POST['title'] ?? 'Новая игра');

if ($title === '') {
    $title = 'Новая игра';
}

$max = max(3, min(6, (int) ($_POST['max'] ?? 6)));

$db = db();
$db->beginTransaction();

try {
    $st = $db->prepare(
        'INSERT INTO games(title, owner_id, max_players) VALUES(?,?,?)'
    );
    $st->execute([$title, $uid, $max]);

    $gid = (int) $db->lastInsertId();

    $chars = characters();
    $seat = 0;
    $c = $chars[$seat];

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
        $c['x'],
        $c['y']
    ]);

    $db->commit();

    header('Location: game.php?id=' . $gid);
    exit;
} catch (Throwable $e) {
    $db->rollBack();

    die('Ошибка создания игры: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}