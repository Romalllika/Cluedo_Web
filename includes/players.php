<?php

function search_players(string $query, int $viewerId, int $limit = 30): array
{
    $limit = max(5, min($limit, 50));
    $query = trim($query);

    if ($query === '') {
        $s = db()->prepare(
            "SELECT
                u.id,
                u.username,
                u.games_played,
                u.wins,
                u.losses,
                u.last_seen_at,
                u.created_at,
                (
                    SELECT COUNT(*)
                    FROM friend_requests fr
                    WHERE fr.status='accepted'
                      AND (fr.sender_user_id=u.id OR fr.receiver_user_id=u.id)
                ) AS friends_count,
                (
                    SELECT COUNT(DISTINCT gp1.game_id)
                    FROM game_players gp1
                    JOIN game_players gp2 ON gp2.game_id = gp1.game_id
                    WHERE gp1.user_id=? AND gp2.user_id=u.id
                ) AS common_matches
             FROM users u
             WHERE u.id <> ?
             ORDER BY
                u.last_seen_at IS NULL ASC,
                u.last_seen_at DESC,
                u.created_at DESC
             LIMIT $limit"
        );

        $s->execute([$viewerId, $viewerId]);

        return $s->fetchAll();
    }

    $like = '%' . $query . '%';

    $s = db()->prepare(
        "SELECT
            u.id,
            u.username,
            u.games_played,
            u.wins,
            u.losses,
            u.last_seen_at,
            u.created_at,
            (
                SELECT COUNT(*)
                FROM friend_requests fr
                WHERE fr.status='accepted'
                  AND (fr.sender_user_id=u.id OR fr.receiver_user_id=u.id)
            ) AS friends_count,
            (
                SELECT COUNT(DISTINCT gp1.game_id)
                FROM game_players gp1
                JOIN game_players gp2 ON gp2.game_id = gp1.game_id
                WHERE gp1.user_id=? AND gp2.user_id=u.id
            ) AS common_matches
         FROM users u
         WHERE u.id <> ?
           AND u.username LIKE ?
         ORDER BY
            CASE
                WHEN u.username = ? THEN 1
                WHEN u.username LIKE ? THEN 2
                ELSE 3
            END,
            u.last_seen_at DESC,
            u.username ASC
         LIMIT $limit"
    );

    $s->execute([
        $viewerId,
        $viewerId,
        $like,
        $query,
        $query . '%',
    ]);

    return $s->fetchAll();
}

function player_win_rate(array $player): int
{
    $games = (int) ($player['games_played'] ?? 0);
    $wins = (int) ($player['wins'] ?? 0);

    if ($games <= 0) {
        return 0;
    }

    return (int) round(($wins / $games) * 100);
}