<?php
require 'includes/config.php';
require 'includes/data.php';

require_auth();

$uid = current_user_id();
$a = $_POST['action'] ?? $_GET['action'] ?? '';
$gid = (int) ($_POST['game_id'] ?? $_GET['game_id'] ?? 0);

const AFK_TURN_SECONDS = 180;
const AFK_DISPROVE_SECONDS = 120;
const AFK_MAX_MISSES = 2;

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
    $s = db()->prepare('INSERT INTO game_logs(game_id,user_id,message) VALUES(?,?,?)');
    $s->execute([$gid, $uid, $msg]);
}

function is_turn($g, int $uid): bool
{
    return $g && (int) $g['current_turn_player_id'] === $uid && $g['status'] === 'active';
}

function username_by_id(int $uid): string
{
    $s = db()->prepare('SELECT username FROM users WHERE id=?');
    $s->execute([$uid]);
    return (string) ($s->fetchColumn() ?: 'Игрок');
}

function clear_pending_disprove(int $gid): void
{
    db()->prepare(
        "UPDATE games
         SET pending_suggester_id=NULL,
             pending_disprover_id=NULL,
             pending_suspect=NULL,
             pending_weapon=NULL,
             pending_room=NULL,
             shown_card_name=NULL,
             shown_by_user_id=NULL
         WHERE id=?"
    )->execute([$gid]);
}

function ensure_character_positions(int $gid): void
{
    $insert = db()->prepare(
        'INSERT IGNORE INTO game_character_positions
            (game_id, character_name, pos_x, pos_y)
         VALUES
            (?,?,?,?)'
    );

    foreach (characters() as $c) {
        $insert->execute([
            $gid,
            $c['name'],
            (int) $c['x'],
            (int) $c['y']
        ]);
    }
}

function reset_character_positions(int $gid): void
{
    db()->prepare('DELETE FROM game_character_positions WHERE game_id=?')->execute([$gid]);
    ensure_character_positions($gid);

    $startByName = [];

    foreach (characters() as $c) {
        $startByName[$c['name']] = [(int) $c['x'], (int) $c['y']];
    }

    $players = players($gid);

    foreach ($players as $p) {
        $name = $p['character_name'];

        if (!isset($startByName[$name])) {
            continue;
        }

        [$x, $y] = $startByName[$name];

        db()->prepare(
            'UPDATE game_players
             SET pos_x=?, pos_y=?, is_eliminated=0, afk_misses=0
             WHERE game_id=? AND user_id=?'
        )->execute([$x, $y, $gid, $p['user_id']]);
    }
}

function set_character_position(int $gid, string $characterName, int $x, int $y): void
{
    ensure_character_positions($gid);

    db()->prepare(
        'UPDATE game_character_positions
         SET pos_x=?, pos_y=?
         WHERE game_id=? AND character_name=?'
    )->execute([$x, $y, $gid, $characterName]);
}

function character_positions(int $gid): array
{
    ensure_character_positions($gid);

    $q = db()->prepare(
        'SELECT
            gcp.character_name,
            gcp.pos_x,
            gcp.pos_y,
            gp.user_id AS owner_user_id,
            u.username AS owner_username,
            gp.is_eliminated
         FROM game_character_positions gcp
         LEFT JOIN game_players gp
            ON gp.game_id = gcp.game_id
           AND gp.character_name = gcp.character_name
         LEFT JOIN users u
            ON u.id = gp.user_id
         WHERE gcp.game_id=?
         ORDER BY gcp.id ASC'
    );

    $q->execute([$gid]);
    $positions = $q->fetchAll();

    $colors = [];

    foreach (characters() as $c) {
        $colors[$c['name']] = $c['color'];
    }

    foreach ($positions as &$p) {
        $p['color'] = $colors[$p['character_name']] ?? '#f5c542';
    }

    unset($p);

    return $positions;
}

function matching_cards_for_user(
    int $gid,
    int $uid,
    string $suspect,
    string $weapon,
    string $room
): array {
    $q = db()->prepare(
        'SELECT card_type, card_name
         FROM player_cards
         WHERE game_id=?
           AND user_id=?
           AND card_name IN (?,?,?)
         ORDER BY card_type, card_name'
    );

    $q->execute([$gid, $uid, $suspect, $weapon, $room]);

    return $q->fetchAll();
}

function auto_show_card_from_eliminated_player(
    int $gid,
    int $suggesterId,
    int $disproverId,
    string $suspect,
    string $weapon,
    string $room
): ?array {
    $cards = matching_cards_for_user($gid, $disproverId, $suspect, $weapon, $room);

    if (!$cards) {
        return null;
    }

    $card = $cards[0];

    db()->prepare(
        "UPDATE games
         SET phase='accuse',
             phase_started_at=NOW(),
             pending_suggester_id=?,
             pending_disprover_id=?,
             pending_suspect=?,
             pending_weapon=?,
             pending_room=?,
             shown_card_name=?,
             shown_by_user_id=?
         WHERE id=?"
    )->execute([
                $suggesterId,
                $disproverId,
                $suspect,
                $weapon,
                $room,
                $card['card_name'],
                $disproverId,
                $gid
            ]);

    log_msg($gid, null, 'Предположение автоматически опровергнуто картой выбывшего игрока.');

    return [
        'card' => $card['card_name'],
        'by_user_id' => $disproverId
    ];
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

    $p = db()->prepare('SELECT * FROM game_players WHERE game_id=? AND user_id=?');
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

function phase_age_seconds($g): int {
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
             shown_by_user_id=?
         WHERE id=?"
    )->execute([
                $card['card_name'],
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

if ($a === 'state') {
    $g = game($gid);

    if (!$g) {
        json_out(['error' => 'Игра не найдена']);
    }

    check_afk_timeout($gid);
    $g = game($gid);

    $ps = players($gid);

    $cards = db()->prepare(
        'SELECT card_type,card_name
         FROM player_cards
         WHERE game_id=? AND user_id=?
         ORDER BY card_type,card_name'
    );
    $cards->execute([$gid, $uid]);

    $logs = db()->prepare(
        'SELECT gl.*, u.username
         FROM game_logs gl
         LEFT JOIN users u ON u.id=gl.user_id
         WHERE gl.game_id=?
         ORDER BY gl.id DESC
         LIMIT 40'
    );
    $logs->execute([$gid]);

    $me = null;

    foreach ($ps as $pp) {
        if ((int) $pp['user_id'] === $uid) {
            $me = $pp;
            break;
        }
    }

    $reachable = [];

    if (
        $me &&
        $g['phase'] === 'move' &&
        (int) $g['current_turn_player_id'] === $uid
    ) {
        $reachable = reachable_targets(
            (int) $me['pos_x'],
            (int) $me['pos_y'],
            (int) $g['dice_total'],
            $gid
        );
    }

    $pending = null;

    if ($g['phase'] === 'disprove') {
        $pending = [
            'suggester_id' => (int) $g['pending_suggester_id'],
            'suggester_name' => username_by_id((int) $g['pending_suggester_id']),
            'disprover_id' => (int) $g['pending_disprover_id'],
            'disprover_name' => username_by_id((int) $g['pending_disprover_id']),
            'suspect' => $g['pending_suspect'],
            'weapon' => $g['pending_weapon'],
            'room' => $g['pending_room'],
            'myMatchingCards' => []
        ];

        if ((int) $g['pending_disprover_id'] === $uid) {
            $pending['myMatchingCards'] = matching_cards_for_user(
                $gid,
                $uid,
                (string) $g['pending_suspect'],
                (string) $g['pending_weapon'],
                (string) $g['pending_room']
            );
        }
    }

    $shownNotice = null;

    if (
        $g['phase'] === 'accuse' &&
        (int) $g['pending_suggester_id'] === $uid &&
        !empty($g['shown_card_name'])
    ) {
        $shownNotice = [
            'card' => $g['shown_card_name'],
            'by' => username_by_id((int) $g['shown_by_user_id'])
        ];
    }

    $solution = null;

    if ($g['status'] === 'finished') {
        $solution = [
            'suspect' => $g['solution_suspect'],
            'weapon' => $g['solution_weapon'],
            'room' => $g['solution_room']
        ];
    }

    json_out([
        'game' => $g,
        'players' => $ps,
        'myCards' => $cards->fetchAll(),
        'logs' => array_reverse($logs->fetchAll()),
        'board' => board_cells($gid),
        'reachable' => $reachable,
        'pending' => $pending,
        'shownNotice' => $shownNotice,
        'characterPositions' => character_positions($gid),
        'phaseAge' => phase_age_seconds($g),
        'afkTurnSeconds' => AFK_TURN_SECONDS,
        'afkDisproveSeconds' => AFK_DISPROVE_SECONDS,
        'solution' => $solution,
        'suspects' => suspects(),
        'weapons' => weapons(),
        'roomNames' => rooms(),
        'characters' => characters()
    ]);
}

if ($a === 'start') {
    $g = game($gid);

    if (!$g || (int) $g['owner_id'] !== $uid) {
        json_out(['error' => 'Только создатель может начать']);
    }

    if ($g['status'] !== 'waiting') {
        json_out(['error' => 'Игра уже началась']);
    }

    $ps = players($gid);

    if (count($ps) < 3) {
        json_out(['error' => 'Нужно минимум 3 игрока']);
    }

    db()->prepare('DELETE FROM player_cards WHERE game_id=?')->execute([$gid]);
    reset_character_positions($gid);
    $ps = players($gid);

    $suspects = suspects();
    $weapons = weapons();
    $rooms = rooms();

    $sol = [
        $suspects[array_rand($suspects)],
        $weapons[array_rand($weapons)],
        $rooms[array_rand($rooms)]
    ];

    $deck = [];

    foreach ($suspects as $c) {
        if ($c !== $sol[0]) {
            $deck[] = ['suspect', $c];
        }
    }

    foreach ($weapons as $c) {
        if ($c !== $sol[1]) {
            $deck[] = ['weapon', $c];
        }
    }

    foreach ($rooms as $c) {
        if ($c !== $sol[2]) {
            $deck[] = ['room', $c];
        }
    }

    shuffle($deck);

    $i = 0;

    foreach ($deck as $card) {
        $p = $ps[$i % count($ps)];

        db()->prepare(
            'INSERT INTO player_cards(game_id,user_id,card_type,card_name)
             VALUES(?,?,?,?)'
        )->execute([$gid, $p['user_id'], $card[0], $card[1]]);

        $i++;
    }

    db()->prepare(
        "UPDATE games
         SET status='active',
             phase='roll',
             phase_started_at=NOW(),
             current_turn_player_id=?,
             dice_total=0,
             solution_suspect=?,
             solution_weapon=?,
             solution_room=?,
             winner_user_id=NULL,
             stats_applied=0,
             pending_suggester_id=NULL,
             pending_disprover_id=NULL,
             pending_suspect=NULL,
             pending_weapon=NULL,
             pending_room=NULL,
             shown_card_name=NULL,
             shown_by_user_id=NULL
         WHERE id=?"
    )->execute([
                $ps[0]['user_id'],
                $sol[0],
                $sol[1],
                $sol[2],
                $gid
            ]);

    log_msg($gid, $uid, 'Игра началась. Тайное дело спрятано в конверте.');

    json_out(['ok' => 1]);
}

if ($a === 'roll') {
    $g = game($gid);

    if (!is_turn($g, $uid) || $g['phase'] !== 'roll') {
        json_out(['error' => 'Сейчас нельзя бросать кубики']);
    }

    $d1 = random_int(1, 6);
    $d2 = random_int(1, 6);

    db()->prepare(
        "UPDATE games
         SET dice_total=?,
             phase='move',
             phase_started_at=NOW()
         WHERE id=?"
    )->execute([$d1 + $d2, $gid]);

    reset_player_afk($gid, $uid);
    log_msg($gid, $uid, "Бросок кубиков: $d1 + $d2 = " . ($d1 + $d2));

    json_out(['ok' => 1, 'd1' => $d1, 'd2' => $d2]);
}

if ($a === 'move') {
    $g = game($gid);

    if (!is_turn($g, $uid) || $g['phase'] !== 'move') {
        json_out(['error' => 'Сейчас нельзя двигаться']);
    }

    $size = board_size($gid);
    $x = max(0, min($size['w'] - 1, (int) $_POST['x']));
    $y = max(0, min($size['h'] - 1, (int) $_POST['y']));

    $p = db()->prepare('SELECT * FROM game_players WHERE game_id=? AND user_id=?');
    $p->execute([$gid, $uid]);
    $p = $p->fetch();

    if (!$p) {
        json_out(['error' => 'Вы не состоите в этой игре']);
    }

    $dist = distance_to_target((int) $p['pos_x'], (int) $p['pos_y'], $x, $y, $gid);

    if ($dist === null) {
        json_out(['error' => 'Сюда нельзя перейти. Двигайтесь по коридорам или входите в комнату через дверь.']);
    }

    if ($dist > (int) $g['dice_total']) {
        json_out(['error' => 'Не хватает очков кубика. Нужно: ' . $dist . ', выпало: ' . $g['dice_total']]);
    }

    $room = room_at($x, $y, $gid);

    if ($room) {
        $center = room_positions($gid)[$room];
        $x = $center[0];
        $y = $center[1];
    }

    db()->prepare(
        'UPDATE game_players
         SET pos_x=?, pos_y=?
         WHERE game_id=? AND user_id=?'
    )->execute([$x, $y, $gid, $uid]);

    set_character_position($gid, $p['character_name'], $x, $y);

    db()->prepare(
        "UPDATE games
         SET phase=?,
             phase_started_at=NOW()
         WHERE id=?"
    )->execute([$room ? 'suggest' : 'accuse', $gid]);

    reset_player_afk($gid, $uid);

    log_msg(
        $gid,
        $uid,
        'Фишка перемещена' .
        ($room ? ' в комнату «' . $room . '»' : '') .
        '. Потрачено очков: ' . $dist . '.'
    );

    json_out(['ok' => 1, 'room' => $room, 'distance' => $dist]);
}

if ($a === 'suggest') {
    $g = game($gid);

    if (!is_turn($g, $uid) || $g['phase'] !== 'suggest') {
        json_out(['error' => 'Предложение можно сделать только после входа в комнату']);
    }

    $p = db()->prepare('SELECT * FROM game_players WHERE game_id=? AND user_id=?');
    $p->execute([$gid, $uid]);
    $p = $p->fetch();

    if (!$p) {
        json_out(['error' => 'Вы не состоите в этой игре']);
    }

    $room = room_at((int) $p['pos_x'], (int) $p['pos_y'], $gid);

    if (!$room) {
        json_out(['error' => 'Вы не в комнате']);
    }

    $sus = $_POST['suspect'] ?? '';
    $weap = $_POST['weapon'] ?? '';

    if (!in_array($sus, suspects(), true) || !in_array($weap, weapons(), true)) {
        json_out(['error' => 'Неверные карты']);
    }
    if ($sus === $p['character_name']) {
        json_out(['error' => 'Нельзя делать предположение на самого себя']);
    }

    $center = room_positions($gid)[$room];

    db()->prepare(
        'UPDATE game_players
         SET pos_x=?, pos_y=?
         WHERE game_id=? AND character_name=?'
    )->execute([
                $center[0],
                $center[1],
                $gid,
                $sus
            ]);

    set_character_position($gid, $sus, $center[0], $center[1]);

    $ps = players($gid);
    $ids = array_map(fn($pl) => (int) $pl['user_id'], $ps);
    $start = array_search($uid, $ids, true);

    if ($start === false) {
        json_out(['error' => 'Игрок не найден в порядке хода']);
    }

    log_msg($gid, $uid, "Предложение: $sus, $weap, $room. Персонаж «$sus» перемещён в комнату.");

    for ($i = 1; $i < count($ps); $i++) {
        $other = $ps[($start + $i) % count($ps)];
        $otherId = (int) $other['user_id'];

        $cards = matching_cards_for_user($gid, $otherId, $sus, $weap, $room);

        if (count($cards) === 0) {
            continue;
        }

        if ((int) $other['is_eliminated'] === 1) {
            $shown = auto_show_card_from_eliminated_player($gid, $uid, $otherId, $sus, $weap, $room);

            reset_player_afk($gid, $uid);

            json_out([
                'ok' => 1,
                'room' => $room,
                'movedSuspect' => $sus,
                'needsDisprove' => false,
                'autoShown' => true,
                'shown' => [
                    'card' => $shown['card'],
                    'by' => $other['username']
                ]
            ]);
        }

        db()->prepare(
            "UPDATE games
             SET phase='disprove',
                 phase_started_at=NOW(),
                 pending_suggester_id=?,
                 pending_disprover_id=?,
                 pending_suspect=?,
                 pending_weapon=?,
                 pending_room=?,
                 shown_card_name=NULL,
                 shown_by_user_id=NULL
             WHERE id=?"
        )->execute([
                    $uid,
                    $otherId,
                    $sus,
                    $weap,
                    $room,
                    $gid
                ]);

        reset_player_afk($gid, $uid);
        log_msg($gid, null, 'Игрок ' . $other['username'] . ' должен показать одну карту.');

        json_out([
            'ok' => 1,
            'room' => $room,
            'movedSuspect' => $sus,
            'needsDisprove' => true,
            'disprover' => $other['username'],
            'matchingCount' => count($cards)
        ]);
    }

    db()->prepare(
        "UPDATE games
         SET phase='accuse',
             phase_started_at=NOW(),
             pending_suggester_id=?,
             pending_disprover_id=NULL,
             pending_suspect=?,
             pending_weapon=?,
             pending_room=?,
             shown_card_name=NULL,
             shown_by_user_id=NULL
         WHERE id=?"
    )->execute([$uid, $sus, $weap, $room, $gid]);

    reset_player_afk($gid, $uid);
    log_msg($gid, null, 'Никто не смог опровергнуть предположение.');

    json_out([
        'ok' => 1,
        'room' => $room,
        'movedSuspect' => $sus,
        'needsDisprove' => false,
        'noDisprove' => true
    ]);
}

if ($a === 'showCard') {
    $g = game($gid);

    if (!$g || $g['status'] !== 'active' || $g['phase'] !== 'disprove') {
        json_out(['error' => 'Сейчас не нужно показывать карту']);
    }

    if ((int) $g['pending_disprover_id'] !== $uid) {
        json_out(['error' => 'Карту должен показать другой игрок']);
    }

    $card = $_POST['card'] ?? '';

    $allowed = matching_cards_for_user(
        $gid,
        $uid,
        (string) $g['pending_suspect'],
        (string) $g['pending_weapon'],
        (string) $g['pending_room']
    );

    $names = array_map(fn($c) => $c['card_name'], $allowed);

    if (!in_array($card, $names, true)) {
        json_out(['error' => 'Эту карту нельзя показать']);
    }

    db()->prepare(
        "UPDATE games
         SET phase='accuse',
             phase_started_at=NOW(),
             shown_card_name=?,
             shown_by_user_id=?
         WHERE id=?"
    )->execute([$card, $uid, $gid]);

    reset_player_afk($gid, $uid);
    log_msg($gid, $uid, 'Игрок показал карту для опровержения предположения.');

    json_out(['ok' => 1]);
}

if ($a === 'accuse') {
    $g = game($gid);

    if (!is_turn($g, $uid)) {
        json_out(['error' => 'Сейчас не ваш ход']);
    }

    $sus = $_POST['suspect'] ?? '';
    $weap = $_POST['weapon'] ?? '';
    $room = $_POST['room'] ?? '';

    $ok = (
        $sus === $g['solution_suspect'] &&
        $weap === $g['solution_weapon'] &&
        $room === $g['solution_room']
    );

    if ($ok) {
        log_msg($gid, $uid, "Финальное обвинение верное: $sus, $weap, $room. Игра окончена!");
        finish_game($gid, $uid);
        json_out(['ok' => 1, 'win' => 1]);
    }

    db()->prepare(
        'UPDATE game_players
         SET is_eliminated=1
         WHERE game_id=? AND user_id=?'
    )->execute([$gid, $uid]);

    reset_player_afk($gid, $uid);
    log_msg($gid, $uid, 'Обвинение неверное. Игрок выбывает из расследования.');

    if (finish_game_if_needed($gid)) {
        json_out(['ok' => 1, 'win' => 0, 'finished' => true]);
    }

    next_turn($gid);

    json_out(['ok' => 1, 'win' => 0]);
}

if ($a === 'surrender') {
    $result = surrender_player($gid, $uid, 'Игрок сдался.');
    json_out($result);
}

if ($a === 'endTurn') {
    $g = game($gid);

    if (!is_turn($g, $uid)) {
        json_out(['error' => 'Сейчас не ваш ход']);
    }

    reset_player_afk($gid, $uid);
    next_turn($gid);
    log_msg($gid, $uid, 'Ход завершён.');

    json_out(['ok' => 1]);
}

json_out(['error' => 'Неизвестное действие']);