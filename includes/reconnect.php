<?php

function get_user_active_games(int $userId): array
{
    $s = db()->prepare(
        "SELECT
            g.id,
            g.title,
            g.status,
            g.phase,
            g.map_id,
            g.current_turn_player_id,
            g.created_at,
            g.updated_at,
            gp.character_name,
            gp.is_eliminated,
            owner.username AS owner_username,
            COUNT(all_gp.id) AS players_count
         FROM game_players gp
         JOIN games g ON g.id = gp.game_id
         JOIN users owner ON owner.id = g.owner_id
         LEFT JOIN game_players all_gp ON all_gp.game_id = g.id
         WHERE gp.user_id=?
           AND g.status IN ('waiting','active')
         GROUP BY g.id, gp.id
         ORDER BY
            CASE g.status
                WHEN 'active' THEN 1
                WHEN 'waiting' THEN 2
                ELSE 3
            END,
            g.updated_at DESC,
            g.id DESC"
    );

    $s->execute([$userId]);

    return $s->fetchAll();
}

function game_status_human(string $status): string
{
    return match ($status) {
        'waiting' => 'Лобби',
        'active' => 'Идёт игра',
        'finished' => 'Завершён',
        default => $status,
    };
}

function game_phase_human(string $phase): string
{
    return match ($phase) {
        'join' => 'Набор игроков',
        'roll' => 'Бросок кубиков',
        'move' => 'Перемещение',
        'suggest' => 'Предложение',
        'disprove' => 'Опровержение',
        'accuse' => 'Обвинение',
        'ended' => 'Завершено',
        default => $phase,
    };
}