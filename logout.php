<?php

require 'includes/config.php';
require 'includes/data.php';

session_start();

$uid = (int) ($_SESSION['user_id'] ?? 0);

function game_by_id_for_logout(int $gid) {
    $s = db()->prepare('SELECT * FROM games WHERE id=?');
    $s->execute([$gid]);
    return $s->fetch();
}

function players_for_logout(int $gid): array {
    $s = db()->prepare(
        'SELECT gp.*, u.username
         FROM game_players gp
         JOIN users u ON u.id = gp.user_id
         WHERE gp.game_id=?
         ORDER BY gp.turn_order'
    );
    $s->execute([$gid]);
    return $s->fetchAll();
}

function log_logout_msg(int $gid, ?int $uid, string $msg): void {
    $s = db()->prepare(
        'INSERT INTO game_logs(game_id, user_id, message)
         VALUES(?,?,?)'
    );
    $s->execute([$gid, $uid, $msg]);
}

function next_turn_for_logout(int $gid): void {
    $ps = players_for_logout($gid);
    $g = game_by_id_for_logout($gid);

    if (!$g) {
        return;
    }

    $ids = array_values(array_filter(array_map(
        fn($p) => (int) $p['is_eliminated'] === 1 ? null : (int) $p['user_id'],
        $ps
    )));

    if (count($ids) === 0) {
        return;
    }

    $idx = array_search((int) $g['current_turn_player_id'], $ids, true);
    $next = $ids[($idx === false ? 0 : $idx + 1) % count($ids)];

    db()->prepare(
        "UPDATE games
         SET current_turn_player_id=?,
             phase='roll',
             phase_started_at=NOW(),
             dice_total=0,
             pending_suggester_id=NULL,
             pending_disprover_id=NULL,
             pending_suspect=NULL,
             pending_weapon=NULL,
             pending_room=NULL,
             shown_card_name=NULL,
             shown_by_user_id=NULL
         WHERE id=?"
    )->execute([$next, $gid]);
}

function active_players_count_for_logout(int $gid): int {
    $q = db()->prepare(
        'SELECT COUNT(*)
         FROM game_players
         WHERE game_id=? AND is_eliminated=0'
    );
    $q->execute([$gid]);

    return (int) $q->fetchColumn();
}

function apply_game_stats_once_for_logout(int $gid, ?int $winnerId): void {
    $db = db();

    $g = $db->prepare('SELECT stats_applied FROM games WHERE id=?');
    $g->execute([$gid]);
    $applied = (int) $g->fetchColumn();

    if ($applied === 1) {
        return;
    }

    $players = $db->prepare('SELECT user_id FROM game_players WHERE game_id=?');
    $players->execute([$gid]);

    foreach ($players->fetchAll() as $p) {
        $pid = (int) $p['user_id'];

        if ($winnerId && $pid === (int) $winnerId) {
            $db->prepare(
                'UPDATE users
                 SET games_played = games_played + 1,
                     wins = wins + 1
                 WHERE id=?'
            )->execute([$pid]);
        } else {
            $db->prepare(
                'UPDATE users
                 SET games_played = games_played + 1,
                     losses = losses + 1
                 WHERE id=?'
            )->execute([$pid]);
        }
    }

    $db->prepare('UPDATE games SET stats_applied=1 WHERE id=?')->execute([$gid]);
}

function finish_game_if_needed_for_logout(int $gid): void {
    $count = active_players_count_for_logout($gid);

    if ($count >= 2) {
        return;
    }

    $winner = db()->prepare(
        'SELECT user_id
         FROM game_players
         WHERE game_id=? AND is_eliminated=0
         LIMIT 1'
    );
    $winner->execute([$gid]);

    $winnerId = $winner->fetchColumn();
    $winnerId = $winnerId ? (int) $winnerId : null;

    db()->prepare(
        "UPDATE games
         SET status='finished',
             phase='ended',
             phase_started_at=NOW(),
             winner_user_id=?,
             current_turn_player_id=NULL,
             pending_suggester_id=NULL,
             pending_disprover_id=NULL,
             pending_suspect=NULL,
             pending_weapon=NULL,
             pending_room=NULL,
             shown_card_name=NULL,
             shown_by_user_id=NULL
         WHERE id=?"
    )->execute([$winnerId, $gid]);

    apply_game_stats_once_for_logout($gid, $winnerId);

    if ($winnerId) {
        log_logout_msg($gid, $winnerId, 'Игра завершена. Остался последний активный игрок.');
    } else {
        log_logout_msg($gid, null, 'Игра завершена. Активных игроков не осталось.');
    }
}

function leave_waiting_game_for_logout(int $gid, int $uid): void {
    $game = game_by_id_for_logout($gid);

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

function surrender_active_game_for_logout(int $gid, int $uid): void {
    $game = game_by_id_for_logout($gid);

    if (!$game || $game['status'] !== 'active') {
        return;
    }

    $p = db()->prepare(
        'SELECT * FROM game_players WHERE game_id=? AND user_id=?'
    );
    $p->execute([$gid, $uid]);
    $player = $p->fetch();

    if (!$player || (int) $player['is_eliminated'] === 1) {
        return;
    }

    db()->prepare(
        'UPDATE game_players
         SET is_eliminated=1
         WHERE game_id=? AND user_id=?'
    )->execute([$gid, $uid]);

    log_logout_msg($gid, $uid, 'Игрок вышел из аккаунта и автоматически сдался.');

    finish_game_if_needed_for_logout($gid);

    $freshGame = game_by_id_for_logout($gid);

    if (
        $freshGame &&
        $freshGame['status'] === 'active' &&
        (int) $freshGame['current_turn_player_id'] === $uid
    ) {
        next_turn_for_logout($gid);
        log_logout_msg($gid, null, 'Ход передан следующему игроку, потому что текущий игрок вышел.');
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
            surrender_active_game_for_logout($gid, $uid);
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