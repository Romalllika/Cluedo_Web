<?php

require 'includes/config.php';
require_auth();
require 'includes/maps.php';

$uid = current_user_id();
$gid = (int) ($_GET['game_id'] ?? 0);
$seat = isset($_GET['seat']) ? (int) $_GET['seat'] : -1;

$chars = characters_for_game($gid);

if ($gid <= 0) {
    header('Location: lobby.php');
    exit;
}

if ($seat < 0) {
    $takenStmt = db()->prepare(
        'SELECT seat_no
         FROM game_players
         WHERE game_id=?'
    );
    $takenStmt->execute([$gid]);

    $takenSeats = array_map('intval', array_column($takenStmt->fetchAll(), 'seat_no'));

    foreach ($chars as $i => $char) {
        if (!in_array((int) $i, $takenSeats, true)) {
            $seat = (int) $i;
            break;
        }
    }
}

if (!isset($chars[$seat])) {
    header('Location: game.php?id=' . $gid . '&full=1');
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
    $startX = (int) $c['x'];
    $startY = (int) $c['y'];
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