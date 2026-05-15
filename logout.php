<?php

require 'includes/config.php';
require 'includes/data.php';
require 'includes/game_lifecycle.php';


$uid = (int) ($_SESSION['user_id'] ?? 0);


function leave_waiting_game_for_logout(int $gid, int $uid): void {
    $game = game($gid);

    if (!$game || $game['status'] !== 'waiting') {
        return;
    }

    db()->prepare(
        'DELETE FROM game_players WHERE game_id=? AND user_id=?'
    )->execute([$gid, $uid]);

    $count = db()->prepare(
        'SELECT COUNT(*) FROM game_players WHERE game_id=?'
    );
    $count->execute([$gid]);
    $count = (int) $count->fetchColumn();

    if ($count === 0) {
        db()->prepare('DELETE FROM games WHERE id=?')->execute([$gid]);
        return;
    }

    if ((int) $game['owner_id'] === $uid) {
        $nextOwner = db()->prepare(
            'SELECT user_id
             FROM game_players
             WHERE game_id=?
             ORDER BY joined_at ASC
             LIMIT 1'
        );
        $nextOwner->execute([$gid]);
        $nextOwner = (int) $nextOwner->fetchColumn();

        if ($nextOwner > 0) {
            db()->prepare(
                'UPDATE games SET owner_id=? WHERE id=?'
            )->execute([$nextOwner, $gid]);
        }
    }
}

if ($uid > 0) {
    $q = db()->prepare(
        "SELECT g.id, g.status
         FROM games g
         JOIN game_players gp ON gp.game_id = g.id
         WHERE gp.user_id=?
           AND g.status IN ('waiting', 'active')"
    );

    $q->execute([$uid]);
    $games = $q->fetchAll();

    foreach ($games as $g) {
        $gid = (int) $g['id'];

        if ($g['status'] === 'waiting') {
            leave_waiting_game_for_logout($gid, $uid);
        }

        if ($g['status'] === 'active') {
            surrender_player($gid, $uid, 'Игрок вышел из аккаунта и автоматически сдался.');
        }
    }
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: index.php');
exit;