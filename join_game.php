<?php

require 'includes/config.php';
require_auth();
require 'includes/data.php';
require 'includes/maps.php';

$uid = current_user_id();
$gid = (int) ($_GET['game_id'] ?? 0);
$seat = (int) ($_GET['seat'] ?? -1);

$chars = characters();

if ($gid <= 0 || !isset($chars[$seat])) {
    header('Location: lobby.php');
    exit;
}

$db = db();
$db->beginTransaction();

try {
    $g = $db->prepare(
        'SELECT * FROM games WHERE id=? FOR UPDATE'
    );
    $g->execute([$gid]);
    $game = $g->fetch();

    if (!$game || $game['status'] !== 'waiting') {
        $db->rollBack();
        header('Location: lobby.php');
        exit;
    }

    $already = $db->prepare(
        'SELECT id FROM game_players WHERE game_id=? AND user_id=? FOR UPDATE'
    );
    $already->execute([$gid, $uid]);
    $alreadyId = $already->fetchColumn();

    if ($alreadyId) {
        $db->rollBack();
        header('Location: game.php?id=' . $gid);
        exit;
    }

    $count = $db->prepare(
        'SELECT COUNT(*) FROM game_players WHERE game_id=?'
    );
    $count->execute([$gid]);
    $count = (int) $count->fetchColumn();

    if ($count >= (int) $game['max_players']) {
        $db->rollBack();
        header('Location: game.php?id=' . $gid . '&full=1');
        exit;
    }

    $taken = $db->prepare(
        'SELECT id FROM game_players WHERE game_id=? AND seat_no=? FOR UPDATE'
    );
    $taken->execute([$gid, $seat]);
    $takenId = $taken->fetchColumn();

    if ($takenId) {
        $db->rollBack();
        header('Location: game.php?id=' . $gid . '&seat_busy=1');
        exit;
    }

    $c = $chars[$seat];
    $starts = map_character_starts($gid);
    [$startX, $startY] = $starts[$c['name']] ?? [(int) $c['x'], (int) $c['y']];
    $order = $count + 1;

    $st = $db->prepare(
        'INSERT INTO game_players
            (game_id, user_id, character_name, seat_no, turn_order, pos_x, pos_y)
         VALUES
            (?,?,?,?,?,?,?)'
    );

    $st->execute([
        $gid,
        $uid,
        $c['name'],
        $seat,
        $order,
        $startX,
        $startY
    ]);

    $db->commit();

    header('Location: game.php?id=' . $gid);
    exit;
} catch (Throwable $e) {
    $db->rollBack();

    die('Ошибка входа в игру: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}