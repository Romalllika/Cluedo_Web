<?php

function emit_game_event(
    int $gameId,
    ?int $userId,
    string $eventType,
    array $eventData = []
): void {
    if ($gameId <= 0 || $eventType === '') {
        return;
    }

    db()->prepare(
        'INSERT INTO game_events
            (game_id, user_id, event_type, event_data)
         VALUES
            (?, ?, ?, ?)'
    )->execute([
        $gameId,
        $userId,
        $eventType,
        $eventData ? json_encode($eventData, JSON_UNESCAPED_UNICODE) : null,
    ]);
}

function get_recent_game_events(int $gameId, int $limit = 80): array
{
    $limit = max(1, min($limit, 300));

    $s = db()->prepare(
        "SELECT
            ge.*,
            u.username
         FROM game_events ge
         LEFT JOIN users u ON u.id = ge.user_id
         WHERE ge.game_id=?
         ORDER BY ge.id DESC
         LIMIT $limit"
    );

    $s->execute([$gameId]);

    return array_reverse($s->fetchAll());
}