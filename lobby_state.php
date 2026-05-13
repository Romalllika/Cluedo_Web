<?php

require 'includes/config.php';
require 'includes/data.php';
require_auth();
/**
 * Удаляем только реально пустые ожидающие лобби.
 * После правки create_game.php новое лобби уже не будет пустым,
 * потому что создатель сразу добавляется в game_players.
 */
db()->query(
    "DELETE g 
     FROM games g 
     LEFT JOIN game_players gp ON gp.game_id = g.id 
     WHERE g.status = 'waiting' 
       AND gp.id IS NULL"
);

$games = db()->query(
    "SELECT 
        g.*,
        u.username AS owner_name,
        COUNT(gp.id) AS players_count
     FROM games g
     JOIN users u ON u.id = g.owner_id
     LEFT JOIN game_players gp ON gp.game_id = g.id
     WHERE g.status <> 'finished'
     GROUP BY g.id
     ORDER BY 
        CASE WHEN g.status = 'waiting' THEN 0 ELSE 1 END,
        g.created_at DESC"
)->fetchAll();

$maps = available_maps();

foreach ($games as &$game) {
    $mapId = normalize_map_id($game['map_id'] ?? 'classic_mansion');
    $game['map_id'] = $mapId;
    $game['map_title'] = $maps[$mapId]['title'] ?? $mapId;
}

unset($game);

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'games' => $games
], JSON_UNESCAPED_UNICODE);

exit;