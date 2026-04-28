<?php require 'includes/config.php';
require_auth();
$title = trim($_POST['title'] ?? 'Новая игра');
$max = max(3, min(6, (int) ($_POST['max'] ?? 6)));
$st = db()->prepare('INSERT INTO games(title,owner_id,max_players) VALUES(?,?,?)');
$st->execute([$title, current_user_id(), $max]);
header('Location: game.php?id=' . db()->lastInsertId());
