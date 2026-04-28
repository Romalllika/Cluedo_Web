<?php require 'includes/config.php';
require_auth();
require 'includes/data.php';
$gid = (int) ($_GET['game_id'] ?? 0);
$seat = (int) ($_GET['seat'] ?? -1);
$chars = characters();
if (!isset($chars[$seat]))
    die('bad seat');
$g = db()->prepare('SELECT * FROM games WHERE id=? AND status="waiting"');
$g->execute([$gid]);
if (!$g->fetch())
    die('game not found');
try {
    $c = $chars[$seat];
    $st = db()->prepare('INSERT INTO game_players(game_id,user_id,character_name,seat_no,turn_order,pos_x,pos_y) VALUES(?,?,?,?,?,?,?)');
    $st->execute([$gid, current_user_id(), $c['name'], $seat, $seat + 1, $c['x'], $c['y']]);
} catch (Exception $e) {
}
header('Location: game.php?id=' . $gid);
