<?php

function game(int $gid)
{
    $s = db()->prepare('SELECT * FROM games WHERE id=?');
    $s->execute([$gid]);

    return $s->fetch();
}

function players(int $gid): array
{
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

function log_msg(int $gid, ?int $uid, string $msg): void
{
    $s = db()->prepare(
        'INSERT INTO game_logs(game_id,user_id,message)
         VALUES(?,?,?)'
    );
    $s->execute([$gid, $uid, $msg]);
}

function is_turn($g, int $uid): bool
{
    return $g && (int) $g['current_turn_player_id'] === $uid && $g['status'] === 'active';
}

function next_turn(int $gid): void
{
    $ps = players($gid);
    $g = game($gid);

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
             pending_suspect_card_id=NULL,
             pending_weapon_card_id=NULL,
             pending_room_card_id=NULL,
             shown_card_id=NULL,
             shown_card_name=NULL,
             shown_by_user_id=NULL
         WHERE id=?"
    )->execute([$next, $gid]);
}

function active_players_count(int $gid): int
{
    $q = db()->prepare(
        'SELECT COUNT(*)
         FROM game_players
         WHERE game_id=? AND is_eliminated=0'
    );

    $q->execute([$gid]);

    return (int) $q->fetchColumn();
}

function apply_game_stats_once(int $gid, ?int $winnerId): void
{
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

function finish_game(int $gid, ?int $winnerId): void
{
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
             pending_suspect_card_id=NULL,
             pending_weapon_card_id=NULL,
             pending_room_card_id=NULL,
             shown_card_id=NULL,
             shown_card_name=NULL,
             shown_by_user_id=NULL
         WHERE id=?"
    )->execute([$winnerId, $gid]);

    apply_game_stats_once($gid, $winnerId);
}

function finish_game_if_needed(int $gid): bool
{
    $count = active_players_count($gid);

    if ($count >= 2) {
        return false;
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

    finish_game($gid, $winnerId);

    if ($winnerId) {
        log_msg($gid, $winnerId, 'Игра завершена. Остался последний активный игрок.');
    } else {
        log_msg($gid, null, 'Игра завершена. Активных игроков не осталось.');
    }

    return true;
}

function surrender_player(int $gid, int $uid, string $reason = 'Игрок сдался.'): array
{
    $g = game($gid);

    if (!$g) {
        return ['error' => 'Игра не найдена'];
    }

    $p = db()->prepare(
        'SELECT *
         FROM game_players
         WHERE game_id=? AND user_id=?'
    );
    $p->execute([$gid, $uid]);
    $player = $p->fetch();

    if (!$player) {
        return ['error' => 'Вы не состоите в этой игре'];
    }

    if ($g['status'] === 'waiting') {
        return ['redirect' => 'leave_lobby.php?game_id=' . $gid];
    }

    if ($g['status'] !== 'active') {
        return ['ok' => 1];
    }

    if ((int) $player['is_eliminated'] === 1) {
        return ['ok' => 1];
    }

    db()->prepare(
        'UPDATE game_players
         SET is_eliminated=1
         WHERE game_id=? AND user_id=?'
    )->execute([$gid, $uid]);

    log_msg($gid, $uid, $reason);

    if (finish_game_if_needed($gid)) {
        return ['ok' => 1, 'finished' => true];
    }

    $freshGame = game($gid);

    if ($freshGame && (int) $freshGame['current_turn_player_id'] === $uid) {
        next_turn($gid);
        log_msg($gid, null, 'Ход передан следующему игроку, потому что текущий игрок выбыл.');
    }

    return ['ok' => 1];
}