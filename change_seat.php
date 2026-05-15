<?php

require 'includes/config.php';
require_auth();
require 'includes/data.php';
require 'includes/maps.php';

$uid = current_user_id();
$gid = (int) ($_POST['game_id'] ?? $_GET['game_id'] ?? 0);
$seat = (int) ($_POST['seat'] ?? $_GET['seat'] ?? -1);

$chars = characters();

if ($gid <= 0 || $seat < 0 || $seat >= count($chars)) {
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
        header('Location: game.php?id=' . $gid);
        exit;
    }

    $me = $db->prepare(
        'SELECT * FROM game_players WHERE game_id=? AND user_id=? FOR UPDATE'
    );
    $me->execute([$gid, $uid]);
    $me = $me->fetch();

    if (!$me) {
        $db->rollBack();
        header('Location: game.php?id=' . $gid);
        exit;
    }

    $taken = $db->prepare(
        'SELECT user_id FROM game_players WHERE game_id=? AND seat_no=? FOR UPDATE'
    );
    $taken->execute([$gid, $seat]);
    $takenBy = $taken->fetchColumn();

    if ($takenBy && (int) $takenBy !== $uid) {
        $db->rollBack();
        header('Location: game.php?id=' . $gid . '&seat_busy=1');
        exit;
    }

    $char = $chars[$seat];

    $starts = map_character_starts($gid);
    [$startX, $startY] = $starts[$char['name']] ?? [(int) $char['x'], (int) $char['y']];

    $db->prepare(
        'UPDATE game_players
        SET seat_no=?, character_name=?, pos_x=?, pos_y=?
        WHERE game_id=? AND user_id=?'
    )->execute([
        $seat,
        $char['name'],
        $startX,
        $startY,
        $gid,
        $uid
    ]);

    $db->commit();

    header('Location: game.php?id=' . $gid);
    exit;
} catch (Throwable $e) {
    $db->rollBack();
    die('Ошибка смены персонажа: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}