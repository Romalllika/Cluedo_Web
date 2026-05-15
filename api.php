<?php
require 'includes/config.php';
require 'includes/data.php';
require 'includes/maps.php';
require 'includes/movement.php';
require 'includes/game_lifecycle.php';
require 'includes/afk.php';

require_auth();

$uid = current_user_id();
$a = $_POST['action'] ?? $_GET['action'] ?? '';
$gid = (int) ($_POST['game_id'] ?? $_GET['game_id'] ?? 0);


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
             pending_suspect_card_id=NULL,
             pending_weapon_card_id=NULL,
             pending_room_card_id=NULL,
             shown_card_name=NULL,
             shown_card_id=NULL,
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

    foreach (characters_for_game($gid) as $c) {
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

    $startByName = map_character_starts($gid);

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
        'SELECT card_type, card_id, card_name
         FROM player_cards
         WHERE game_id=?
           AND user_id=?
           AND card_name IN (?,?,?)
         ORDER BY card_type, card_name'
    );

    $q->execute([$gid, $uid, $suspect, $weapon, $room]);

    return $q->fetchAll();
}

function suggestion_card_ids(string $suspect, string $weapon, string $room): array
{
    return [
        'suspect' => legacy_card_name_to_id('suspect', $suspect),
        'weapon' => legacy_card_name_to_id('weapon', $weapon),
        'room' => legacy_card_name_to_id('room', $room),
    ];
}

function resolve_card_input(string $type, ?string $id, ?string $legacyName): array
{
    $id = trim((string) $id);
    $legacyName = trim((string) $legacyName);

    if ($id !== '') {
        $card = card_by_id($id);

        if ($card && $card['type'] === $type) {
            return [
                'id' => (string) $card['id'],
                'name' => (string) $card['legacy_name'],
                'title' => (string) $card['title'],
            ];
        }
    }

    if ($legacyName !== '') {
        $resolvedId = legacy_card_name_to_id($type, $legacyName);

        if ($resolvedId) {
            $card = card_by_id($resolvedId);

            if ($card) {
                return [
                    'id' => (string) $card['id'],
                    'name' => (string) $card['legacy_name'],
                    'title' => (string) $card['title'],
                ];
            }
        }
    }

    return [
        'id' => null,
        'name' => $legacyName,
        'title' => $legacyName,
    ];
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

    $ids = suggestion_card_ids($suspect, $weapon, $room);

    db()->prepare(
        "UPDATE games
     SET phase='accuse',
         phase_started_at=NOW(),
         pending_suggester_id=?,
         pending_disprover_id=?,
         pending_suspect=?,
         pending_weapon=?,
         pending_room=?,
         pending_suspect_card_id=?,
         pending_weapon_card_id=?,
         pending_room_card_id=?,
         shown_card_name=?,
         shown_card_id=?,
         shown_by_user_id=?
     WHERE id=?"
    )->execute([
                $suggesterId,
                $disproverId,
                $suspect,
                $weapon,
                $room,
                $ids['suspect'],
                $ids['weapon'],
                $ids['room'],
                $card['card_name'],
                $card['card_id'] ?? null,
                $disproverId,
                $gid
            ]);

    log_msg($gid, null, 'Предположение автоматически опровергнуто картой выбывшего игрока.');

    return [
        'card' => $card['card_name'],
        'card_id' => $card['card_id'] ?? null,
        'by_user_id' => $disproverId
    ];
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
        'SELECT card_type, card_id, card_name
        FROM player_cards
        WHERE game_id=? AND user_id=?
        ORDER BY card_type, card_name'
    );
    $cards->execute([$gid, $uid]);

    $myCards = $cards->fetchAll();

    $myCards = array_map(function ($card) {
        $meta = !empty($card['card_id']) ? card_by_id((string) $card['card_id']) : null;

        $card['title'] = $meta['title'] ?? $card['card_name'];
        $card['image'] = $meta['image'] ?? null;

        return $card;
    }, $myCards);

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
            'suspect_id' => $g['pending_suspect_card_id'] ?? null,
            'weapon_id' => $g['pending_weapon_card_id'] ?? null,
            'room_id' => $g['pending_room_card_id'] ?? null,
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
            'card_id' => $g['shown_card_id'] ?? null,
            'by' => username_by_id((int) $g['shown_by_user_id'])
        ];
    }

    $solution = null;

    if ($g['status'] === 'finished') {
        $solution = [
            'suspect' => $g['solution_suspect'],
            'weapon' => $g['solution_weapon'],
            'room' => $g['solution_room'],
            'suspect_id' => $g['solution_suspect_card_id'] ?? null,
            'weapon_id' => $g['solution_weapon_card_id'] ?? null,
            'room_id' => $g['solution_room_card_id'] ?? null,
        ];
    }
    json_out([
        'game' => $g,
        'players' => $ps,
        'myCards' => $myCards,
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

        'cardsMeta' => cards(),
        'suspectCards' => suspect_cards(),
        'weaponCards' => weapon_cards(),
        'roomCards' => room_cards(),

        'availableMaps' => available_maps(),
        'characters' => characters_for_game($gid)
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

    $suspectCards = suspect_cards();
    $weaponCards = weapon_cards();
    $roomCards = room_cards();

    $solutionSuspect = $suspectCards[array_rand($suspectCards)];
    $solutionWeapon = $weaponCards[array_rand($weaponCards)];
    $solutionRoom = $roomCards[array_rand($roomCards)];

    $sol = [
        $solutionSuspect['legacy_name'],
        $solutionWeapon['legacy_name'],
        $solutionRoom['legacy_name'],
    ];

    $solIds = [
        $solutionSuspect['id'],
        $solutionWeapon['id'],
        $solutionRoom['id'],
    ];

    $deck = [];

    foreach ($suspectCards as $card) {
        if ($card['id'] !== $solutionSuspect['id']) {
            $deck[] = [
                'type' => 'suspect',
                'id' => $card['id'],
                'name' => $card['legacy_name'],
            ];
        }
    }

    foreach ($weaponCards as $card) {
        if ($card['id'] !== $solutionWeapon['id']) {
            $deck[] = [
                'type' => 'weapon',
                'id' => $card['id'],
                'name' => $card['legacy_name'],
            ];
        }
    }

    foreach ($roomCards as $card) {
        if ($card['id'] !== $solutionRoom['id']) {
            $deck[] = [
                'type' => 'room',
                'id' => $card['id'],
                'name' => $card['legacy_name'],
            ];
        }
    }

    shuffle($deck);

    $i = 0;

    foreach ($deck as $card) {
        $p = $ps[$i % count($ps)];

        db()->prepare(
            'INSERT INTO player_cards(game_id,user_id,card_type,card_id,card_name)
            VALUES(?,?,?,?,?)'
        )->execute([
                    $gid,
                    (int) $p['user_id'],
                    $card['type'],
                    $card['id'],
                    $card['name']
                ]);

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
        solution_suspect_card_id=?,
        solution_weapon_card_id=?,
        solution_room_card_id=?,
        pending_suggester_id=NULL,
        pending_disprover_id=NULL,
        pending_suspect=NULL,
        pending_weapon=NULL,
        pending_room=NULL,
        pending_suspect_card_id=NULL,
        pending_weapon_card_id=NULL,
        pending_room_card_id=NULL,
        shown_card_name=NULL,
        shown_card_id=NULL,
        shown_by_user_id=NULL
    WHERE id=?"
    )->execute([
                $firstUid,
                $sol[0],
                $sol[1],
                $sol[2],
                $solIds[0],
                $solIds[1],
                $solIds[2],
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

if ($a === 'secretPassage') {
    $g = game($gid);

    if (!is_turn($g, $uid) || $g['phase'] !== 'roll') {
        json_out(['error' => 'Секретный проход можно использовать только в начале своего хода']);
    }

    $p = db()->prepare(
        'SELECT *
         FROM game_players
         WHERE game_id=? AND user_id=?'
    );
    $p->execute([$gid, $uid]);
    $p = $p->fetch();

    if (!$p) {
        json_out(['error' => 'Вы не состоите в этой игре']);
    }

    $currentRoom = room_at((int) $p['pos_x'], (int) $p['pos_y'], $gid);

    if (!$currentRoom) {
        json_out(['error' => 'Секретный проход доступен только из комнаты']);
    }

    $rooms = mansion_rooms($gid);
    $targetRoom = $rooms[$currentRoom]['secret'] ?? null;

    if (!$targetRoom || !isset($rooms[$targetRoom])) {
        json_out(['error' => 'В этой комнате нет секретного прохода']);
    }

    $centers = room_positions($gid);
    [$x, $y] = $centers[$targetRoom];

    db()->prepare(
        'UPDATE game_players
         SET pos_x=?, pos_y=?
         WHERE game_id=? AND user_id=?'
    )->execute([$x, $y, $gid, $uid]);

    set_character_position($gid, $p['character_name'], $x, $y);

    db()->prepare(
        "UPDATE games
         SET dice_total=0,
             phase='suggest',
             phase_started_at=NOW()
         WHERE id=?"
    )->execute([$gid]);

    reset_player_afk($gid, $uid);

    log_msg(
        $gid,
        $uid,
        'Игрок использовал секретный проход: «' . $currentRoom . '» → «' . $targetRoom . '».'
    );

    json_out([
        'ok' => 1,
        'from' => $currentRoom,
        'to' => $targetRoom,
        'x' => $x,
        'y' => $y
    ]);
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
    // Обычное перемещение
    db()->prepare(
        'UPDATE game_players
         SET pos_x=?, pos_y=?
         WHERE game_id=? AND user_id=?'
    )->execute([$x, $y, $gid, $uid]);

    set_character_position($gid, $p['character_name'], $x, $y);

    // Обновляем фазу игры
    db()->prepare(
        "UPDATE games
         SET phase=?,
             phase_started_at=NOW()
         WHERE id=?"
    )->execute([$room ? 'suggest' : 'accuse', $gid]);

    reset_player_afk($gid, $uid);

    // Логируем обычное перемещение
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

    $suspectInput = resolve_card_input(
        'suspect',
        $_POST['suspect_id'] ?? null,
        $_POST['suspect'] ?? null
    );

    $weaponInput = resolve_card_input(
        'weapon',
        $_POST['weapon_id'] ?? null,
        $_POST['weapon'] ?? null
    );

    $sus = $suspectInput['name'];
    $weap = $weaponInput['name'];

    if (!in_array($sus, suspects(), true) || !in_array($weap, weapons(), true)) {
        json_out(['error' => 'Неверные карты']);
    }
    if ($sus === $p['character_name']) {
        json_out(['error' => 'Нельзя делать предположение на самого себя']);
    }

    $cardIds = [
        'suspect' => $suspectInput['id'],
        'weapon' => $weaponInput['id'],
        'room' => legacy_card_name_to_id('room', $room),
    ];

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
    $turnUserIds = array_map(fn($pl) => (int) $pl['user_id'], $ps);
    $start = array_search($uid, $turnUserIds, true);

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
                    'card_id' => $shown['card_id'] ?? null,
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
         pending_suspect_card_id=?,
         pending_weapon_card_id=?,
         pending_room_card_id=?,
         shown_card_name=NULL,
         shown_card_id=NULL,
         shown_by_user_id=NULL
     WHERE id=?"
        )->execute([
                    $uid,
                    $otherId,
                    $sus,
                    $weap,
                    $room,
                    $cardIds['suspect'],
                    $cardIds['weapon'],
                    $cardIds['room'],
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
         pending_suspect_card_id=?,
         pending_weapon_card_id=?,
         pending_room_card_id=?,
         shown_card_name=NULL,
         shown_card_id=NULL,
         shown_by_user_id=NULL
     WHERE id=?"
    )->execute([
                $uid,
                $sus,
                $weap,
                $room,
                $cardIds['suspect'],
                $cardIds['weapon'],
                $cardIds['room'],
                $gid
            ]);

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

    $card = trim((string) ($_POST['card'] ?? ''));
    $cardId = trim((string) ($_POST['card_id'] ?? ''));

    $allowed = matching_cards_for_user(
        $gid,
        $uid,
        (string) $g['pending_suspect'],
        (string) $g['pending_weapon'],
        (string) $g['pending_room']
    );

    $selectedCard = null;

    foreach ($allowed as $candidate) {
        if ($cardId !== '' && ($candidate['card_id'] ?? null) === $cardId) {
            $selectedCard = $candidate;
            break;
        }

        if ($card !== '' && $candidate['card_name'] === $card) {
            $selectedCard = $candidate;
            break;
        }
    }

    if (!$selectedCard) {
        json_out(['error' => 'Эту карту нельзя показать']);
    }

    db()->prepare(
        "UPDATE games
     SET phase='accuse',
         phase_started_at=NOW(),
         shown_card_name=?,
         shown_card_id=?,
         shown_by_user_id=?
     WHERE id=?"
    )->execute([
                $selectedCard['card_name'],
                $selectedCard['card_id'] ?? null,
                $uid,
                $gid
            ]);

    reset_player_afk($gid, $uid);
    log_msg($gid, $uid, 'Игрок показал карту для опровержения предположения.');

    json_out(['ok' => 1]);
}

if ($a === 'accuse') {
    $g = game($gid);

    if (!$g || $g['status'] !== 'active') {
        json_out(['error' => 'Игра сейчас не активна']);
    }

    if (!is_turn($g, $uid)) {
        json_out(['error' => 'Сейчас не ваш ход']);
    }

    if (!in_array($g['phase'], ['move', 'suggest', 'accuse'], true)) {
        json_out(['error' => 'Сейчас нельзя сделать обвинение']);
    }

    $suspectInput = resolve_card_input(
        'suspect',
        $_POST['suspect_id'] ?? null,
        $_POST['suspect'] ?? null
    );

    $weaponInput = resolve_card_input(
        'weapon',
        $_POST['weapon_id'] ?? null,
        $_POST['weapon'] ?? null
    );

    $roomInput = resolve_card_input(
        'room',
        $_POST['room_id'] ?? null,
        $_POST['room'] ?? null
    );

    $sus = $suspectInput['name'];
    $weap = $weaponInput['name'];
    $room = $roomInput['name'];

    if (!in_array($sus, suspects(), true) || !in_array($weap, weapons(), true) || !in_array($room, rooms(), true)) {
        json_out(['error' => 'Некорректное обвинение']);
    }

    $hasSolutionIds =
        !empty($g['solution_suspect_card_id']) &&
        !empty($g['solution_weapon_card_id']) &&
        !empty($g['solution_room_card_id']);

    if ($hasSolutionIds) {
        $ok = (
            $suspectInput['id'] === $g['solution_suspect_card_id'] &&
            $weaponInput['id'] === $g['solution_weapon_card_id'] &&
            $roomInput['id'] === $g['solution_room_card_id']
        );
    } else {
        $ok = (
            $sus === $g['solution_suspect'] &&
            $weap === $g['solution_weapon'] &&
            $room === $g['solution_room']
        );
    }
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

    if (!$g || $g['status'] !== 'active') {
        json_out(['error' => 'Игра сейчас не активна']);
    }

    if (!is_turn($g, $uid)) {
        json_out(['error' => 'Сейчас не ваш ход']);
    }

    if (in_array($g['phase'], ['join', 'disprove', 'ended'], true)) {
        json_out(['error' => 'Сейчас нельзя завершить ход']);
    }

    reset_player_afk($gid, $uid);
    next_turn($gid);
    log_msg($gid, $uid, 'Ход завершён.');

    json_out(['ok' => 1]);
}

json_out(['error' => 'Неизвестное действие']);