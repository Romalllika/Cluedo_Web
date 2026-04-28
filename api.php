<?php
require 'includes/config.php';
require 'includes/data.php';
require_auth();
$uid = current_user_id();
$a = $_POST['action'] ?? $_GET['action'] ?? '';
$gid = (int) ($_POST['game_id'] ?? $_GET['game_id'] ?? 0);
function game($gid)
{
    $s = db()->prepare('SELECT * FROM games WHERE id=?');
    $s->execute([$gid]);
    return $s->fetch();
}
function log_msg($gid, $uid, $msg)
{
    $s = db()->prepare('INSERT INTO game_logs(game_id,user_id,message) VALUES(?,?,?)');
    $s->execute([$gid, $uid, $msg]);
}
function players($gid)
{
    $s = db()->prepare('SELECT gp.*,u.username FROM game_players gp JOIN users u ON u.id=gp.user_id WHERE gp.game_id=? ORDER BY gp.turn_order');
    $s->execute([$gid]);
    return $s->fetchAll();
}
function is_turn($g, $uid)
{
    return $g && (int) $g['current_turn_player_id'] === $uid && $g['status'] === 'active';
}
function next_turn($gid)
{
    $ps = players($gid);
    $g = game($gid);
    $ids = array_values(array_filter(array_map(fn($p) => $p['is_eliminated'] ? null : (int) $p['user_id'], $ps)));
    if (count($ids) === 0)
        return;
    $idx = array_search((int) $g['current_turn_player_id'], $ids, true);
    $next = $ids[($idx === false ? 0 : $idx + 1) % count($ids)];
    db()->prepare("UPDATE games SET current_turn_player_id=?, phase='roll', dice_total=0 WHERE id=?")->execute([$next, $gid]);
}
function finish_game($gid, $winner)
{
    $ps = players($gid);
    foreach ($ps as $p) {
        $win = ((int) $p['user_id'] === $winner);
        db()->prepare('UPDATE users SET games_played=games_played+1,wins=wins+?,losses=losses+? WHERE id=?')->execute([$win ? 1 : 0, $win ? 0 : 1, $p['user_id']]);
    }
    db()->prepare("UPDATE games SET status='finished', phase='ended', winner_user_id=? WHERE id=?")->execute([$winner, $gid]);
}
if ($a === 'state') {
    $g = game($gid);
    if (!$g)
        json_out(['error' => 'Игра не найдена']);
    $ps = players($gid);
    $cards = db()->prepare('SELECT card_type,card_name FROM player_cards WHERE game_id=? AND user_id=? ORDER BY card_type,card_name');
    $cards->execute([$gid, $uid]);
    $logs = db()->prepare('SELECT gl.*,u.username FROM game_logs gl LEFT JOIN users u ON u.id=gl.user_id WHERE gl.game_id=? ORDER BY gl.id DESC LIMIT 40');
    $logs->execute([$gid]);
    $me = null;
    foreach ($ps as $pp) {
        if ((int) $pp['user_id'] === $uid) {
            $me = $pp;
            break;
        }
    }
    $reachable = [];
    if ($me && $g['phase'] === 'move' && (int) $g['current_turn_player_id'] === $uid) {
        $reachable = reachable_targets((int) $me['pos_x'], (int) $me['pos_y'], (int) $g['dice_total'], $gid);
    }
    json_out(['game' => $g, 'players' => $ps, 'myCards' => $cards->fetchAll(), 'logs' => array_reverse($logs->fetchAll()), 'board' => board_cells($gid), 'reachable' => $reachable, 'suspects' => suspects(), 'weapons' => weapons(), 'roomNames' => rooms(), 'characters' => characters()]);
}
if ($a === 'start') {
    $g = game($gid);
    if (!$g || (int) $g['owner_id'] !== $uid)
        json_out(['error' => 'Только создатель может начать']);
    $ps = players($gid);
    if (count($ps) < 3)
        json_out(['error' => 'Нужно минимум 3 игрока']);
    $sol = [suspects()[array_rand(suspects())], weapons()[array_rand(weapons())], rooms()[array_rand(rooms())]];
    $deck = [];
    foreach (suspects() as $c)
        if ($c !== $sol[0])
            $deck[] = ['suspect', $c];
    foreach (weapons() as $c)
        if ($c !== $sol[1])
            $deck[] = ['weapon', $c];
    foreach (rooms() as $c)
        if ($c !== $sol[2])
            $deck[] = ['room', $c];
    shuffle($deck);
    $i = 0;
    foreach ($deck as $card) {
        $p = $ps[$i % count($ps)];
        db()->prepare('INSERT INTO player_cards(game_id,user_id,card_type,card_name) VALUES(?,?,?,?)')->execute([$gid, $p['user_id'], $card[0], $card[1]]);
        $i++;
    }
    db()->prepare("UPDATE games SET status='active',phase='roll',current_turn_player_id=?,solution_suspect=?,solution_weapon=?,solution_room=? WHERE id=?")->execute([$ps[0]['user_id'], $sol[0], $sol[1], $sol[2], $gid]);
    log_msg($gid, $uid, 'Игра началась. Тайное дело спрятано в конверте.');
    json_out(['ok' => 1]);
}
if ($a === 'roll') {
    $g = game($gid);
    if (!is_turn($g, $uid) || $g['phase'] !== 'roll')
        json_out(['error' => 'Сейчас нельзя бросать кубики']);
    $d1 = random_int(1, 6);
    $d2 = random_int(1, 6);
    db()->prepare("UPDATE games SET dice_total=?, phase='move' WHERE id=?")->execute([$d1 + $d2, $gid]);
    log_msg($gid, $uid, "Бросок кубиков: $d1 + $d2 = " . ($d1 + $d2));
    json_out(['ok' => 1, 'd1' => $d1, 'd2' => $d2]);
}
if ($a === 'move') {
    $g = game($gid);
    if (!is_turn($g, $uid) || $g['phase'] !== 'move')
        json_out(['error' => 'Сейчас нельзя двигаться']);
    $size = board_size($gid);
    $x = max(0, min($size['w'] - 1, (int) $_POST['x']));
    $y = max(0, min($size['h'] - 1, (int) $_POST['y']));
    $p = db()->prepare('SELECT * FROM game_players WHERE game_id=? AND user_id=?');
    $p->execute([$gid, $uid]);
    $p = $p->fetch();
    $dist = distance_to_target((int) $p['pos_x'], (int) $p['pos_y'], $x, $y, $gid);
    if ($dist === null)
        json_out(['error' => 'Сюда нельзя перейти. Двигайтесь по коридорам или входите в комнату через дверь.']);
    if ($dist > (int) $g['dice_total'])
        json_out(['error' => 'Не хватает очков кубика. Нужно: ' . $dist . ', выпало: ' . $g['dice_total']]);
    $room = room_at($x, $y, $gid);
    if ($room) {
        $center = room_positions($gid)[$room];
        $x = $center[0];
        $y = $center[1];
    }
    db()->prepare('UPDATE game_players SET pos_x=?,pos_y=? WHERE game_id=? AND user_id=?')->execute([$x, $y, $gid, $uid]);
    db()->prepare("UPDATE games SET phase=? WHERE id=?")->execute([$room ? 'suggest' : 'accuse', $gid]);
    log_msg($gid, $uid, 'Фишка перемещена' . ($room ? ' в комнату «' . $room . '»' : '') . '. Потрачено очков: ' . $dist . '.');
    json_out(['ok' => 1, 'room' => $room, 'distance' => $dist]);
}
if ($a === 'suggest') {
    $g = game($gid);
    if (!is_turn($g, $uid) || $g['phase'] !== 'suggest')
        json_out(['error' => 'Предложение можно сделать только после входа в комнату']);
    $p = db()->prepare('SELECT * FROM game_players WHERE game_id=? AND user_id=?');
    $p->execute([$gid, $uid]);
    $p = $p->fetch();
    $room = room_at((int) $p['pos_x'], (int) $p['pos_y'], $gid);
    if (!$room)
        json_out(['error' => 'Вы не в комнате']);
    $sus = $_POST['suspect'] ?? '';
    $weap = $_POST['weapon'] ?? '';
    if (!in_array($sus, suspects()) || !in_array($weap, weapons()))
        json_out(['error' => 'Неверные карты']);
    $ps = players($gid);
    $ids = array_column($ps, 'user_id');
    $start = array_search($uid, $ids);
    $shown = null;
    for ($i = 1; $i < count($ps); $i++) {
        $other = $ps[($start + $i) % count($ps)];
        $q = db()->prepare('SELECT card_name FROM player_cards WHERE game_id=? AND user_id=? AND card_name IN (?,?,?) LIMIT 1');
        $q->execute([$gid, $other['user_id'], $sus, $weap, $room]);
        $c = $q->fetch();
        if ($c) {
            $shown = ['by' => $other['username'], 'card' => $c['card_name']];
            break;
        }
    }
    log_msg($gid, $uid, "Предложение: $sus, $weap, $room. " . ($shown ? "Карта показана игроком {$shown['by']}." : 'Никто не смог опровергнуть.'));
    db()->prepare("UPDATE games SET phase='accuse' WHERE id=?")->execute([$gid]);
    json_out(['ok' => 1, 'shown' => $shown, 'room' => $room]);
}
if ($a === 'accuse') {
    $g = game($gid);
    if (!is_turn($g, $uid))
        json_out(['error' => 'Сейчас не ваш ход']);
    $sus = $_POST['suspect'] ?? '';
    $weap = $_POST['weapon'] ?? '';
    $room = $_POST['room'] ?? '';
    $ok = ($sus === $g['solution_suspect'] && $weap === $g['solution_weapon'] && $room === $g['solution_room']);
    if ($ok) {
        log_msg($gid, $uid, "Финальное обвинение верное: $sus, $weap, $room. Игра окончена!");
        finish_game($gid, $uid);
        json_out(['ok' => 1, 'win' => 1]);
    }
    db()->prepare('UPDATE game_players SET is_eliminated=1 WHERE game_id=? AND user_id=?')->execute([$gid, $uid]);
    log_msg($gid, $uid, 'Обвинение неверное. Игрок выбывает из расследования.');
    next_turn($gid);
    json_out(['ok' => 1, 'win' => 0]);
}
if ($a === 'endTurn') {
    $g = game($gid);
    if (!is_turn($g, $uid))
        json_out(['error' => 'Сейчас не ваш ход']);
    next_turn($gid);
    log_msg($gid, $uid, 'Ход завершён.');
    json_out(['ok' => 1]);
}
json_out(['error' => 'Неизвестное действие']);
