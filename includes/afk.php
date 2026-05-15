<?php

const AFK_TURN_SECONDS = 180;
const AFK_DISPROVE_SECONDS = 120;
const AFK_MAX_MISSES = 2;

function set_phase_timer(int $gid): void
{
    db()->prepare('UPDATE games SET phase_started_at=NOW() WHERE id=?')->execute([$gid]);
}

function reset_player_afk(int $gid, int $uid): void
{
    db()->prepare(
        'UPDATE game_players
         SET afk_misses=0
         WHERE game_id=? AND user_id=?'
    )->execute([$gid, $uid]);
}

function add_player_afk_miss(int $gid, int $uid): int
{
    db()->prepare(
        'UPDATE game_players
         SET afk_misses=afk_misses+1
         WHERE game_id=? AND user_id=?'
    )->execute([$gid, $uid]);

    $q = db()->prepare(
        'SELECT afk_misses
         FROM game_players
         WHERE game_id=? AND user_id=?'
    );

    $q->execute([$gid, $uid]);

    return (int) $q->fetchColumn();
}

function phase_age_seconds($g): int
{
    if (!$g || empty($g['id']) || empty($g['phase_started_at'])) {
        return 0;
    }

    $q = db()->prepare(
        'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, phase_started_at, NOW()))
         FROM games
         WHERE id=?'
    );

    $q->execute([(int) $g['id']]);

    return (int) $q->fetchColumn();
}

function auto_show_pending_disprove_card(array $g): bool
{
    $gid = (int) $g['id'];
    $disproverId = (int) $g['pending_disprover_id'];

    if ($disproverId <= 0) {
        return false;
    }

    $cards = matching_cards_for_user(
        $gid,
        $disproverId,
        (string) $g['pending_suspect'],
        (string) $g['pending_weapon'],
        (string) $g['pending_room']
    );

    if (!$cards) {
        db()->prepare(
            "UPDATE games
            SET phase='accuse',
                phase_started_at=NOW(),
                shown_card_name=NULL,
                shown_card_id=NULL,
                shown_by_user_id=NULL
            WHERE id=?"
        )->execute([$gid]);

        log_msg($gid, null, 'Игрок не показал карту вовремя. Подходящих карт не найдено.');

        return true;
    }

    $card = $cards[0];

    db()->prepare(
    "UPDATE games
        SET phase='accuse',
            phase_started_at=NOW(),
            shown_card_name=?,
            shown_card_id=?,
            shown_by_user_id=?
        WHERE id=?"
    )->execute([
        $card['card_name'],
        $card['card_id'] ?? null,
        $disproverId,
        $gid
    ]);

    add_player_afk_miss($gid, $disproverId);

    log_msg($gid, $disproverId, 'Игрок не выбрал карту вовремя. Карта была показана автоматически.');

    return true;
}

function check_afk_timeout(int $gid): void
{
    $g = game($gid);

    if (!$g || $g['status'] !== 'active') {
        return;
    }

    if (empty($g['phase_started_at'])) {
        set_phase_timer($gid);
        return;
    }

    $age = phase_age_seconds($g);

    if ($g['phase'] === 'disprove') {
        if ($age < AFK_DISPROVE_SECONDS) {
            return;
        }

        auto_show_pending_disprove_card($g);
        return;
    }

    if (!in_array($g['phase'], ['roll', 'move', 'suggest', 'accuse'], true)) {
        return;
    }

    if ($age < AFK_TURN_SECONDS) {
        return;
    }

    $turnUid = (int) $g['current_turn_player_id'];

    if ($turnUid <= 0) {
        return;
    }

    $misses = add_player_afk_miss($gid, $turnUid);

    if ($misses >= AFK_MAX_MISSES) {
        surrender_player($gid, $turnUid, 'Игрок автоматически сдался из-за AFK.');
        return;
    }

    log_msg($gid, $turnUid, 'Ход автоматически пропущен из-за AFK.');
    next_turn($gid);
}