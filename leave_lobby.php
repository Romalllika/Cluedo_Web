<?php
require 'includes/config.php';
require_auth();
$uid = current_user_id();
$gid = (int) ($_POST['game_id'] ?? $_GET['game_id'] ?? 0);
$g = db()->prepare('SELECT * FROM games WHERE id=?');
$g->execute([$gid]);
$game = $g->fetch();
if (!$game) {
    header('Location: lobby.php');
    exit;
}
if ($game['status'] !== 'waiting') {
    header('Location: game.php?id=' . $gid);
    exit;
}
db()->prepare('DELETE FROM game_players WHERE game_id=? AND user_id=?')->execute([$gid, $uid]);
$count = db()->prepare('SELECT COUNT(*) FROM game_players WHERE game_id=?');
$count->execute([$gid]);
$count = (int) $count->fetchColumn();
if ($count === 0) {
    db()->prepare('DELETE FROM games WHERE id=?')->execute([$gid]);
    header('Location: lobby.php');
    exit;
}
if ((int) $game['owner_id'] === $uid) {
    $next = db()->prepare('SELECT user_id FROM game_players WHERE game_id=? ORDER BY joined_at ASC LIMIT 1');
    $next->execute([$gid]);
    $next = (int) $next->fetchColumn();
    if ($next > 0)
        db()->prepare('UPDATE games SET owner_id=? WHERE id=?')->execute([$next, $gid]);
}
header('Location: lobby.php');
exit;
