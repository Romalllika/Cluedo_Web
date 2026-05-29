<?php

function profile_role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Администратор',
        'moderator' => 'Модератор',
        default => 'Игрок',
    };
}

function get_profile_user(int $userId): ?array
{
    $s = db()->prepare(
        'SELECT
            id,
            username,
            role,
            wins,
            losses,
            games_played,
            surrenders,
            account_xp,
            wrong_accusations,
            created_at,
            last_seen_at
         FROM users
         WHERE id=?'
    );

    $s->execute([$userId]);
    $user = $s->fetch();

    return $user ?: null;
}

function get_profile_stats(array $user): array
{
    $gamesPlayed = (int) ($user['games_played'] ?? 0);
    $wins = (int) ($user['wins'] ?? 0);
    $losses = (int) ($user['losses'] ?? 0);
    $surrenders = (int) ($user['surrenders'] ?? 0);
    $wrongAccusations = (int) ($user['wrong_accusations'] ?? 0);

    $winRate = $gamesPlayed > 0
        ? round(($wins / $gamesPlayed) * 100)
        : 0;

    return [
        'games_played' => $gamesPlayed,
        'wins' => $wins,
        'losses' => $losses,
        'surrenders' => $surrenders,
        'wrong_accusations' => $wrongAccusations,
        'win_rate' => $winRate,
    ];
}

function get_profile_recent_matches(int $userId, int $limit = 8): array
{
    $limit = max(1, min($limit, 20));

    $s = db()->prepare(
        "SELECT
            g.id AS game_id,
            g.title,
            g.status,
            g.phase,
            g.map_id,
            g.winner_user_id,
            g.created_at,
            g.updated_at,
            gp.character_name,
            gp.is_eliminated
         FROM game_players gp
         JOIN games g ON g.id = gp.game_id
         WHERE gp.user_id=?
         ORDER BY g.updated_at DESC, g.id DESC
         LIMIT $limit"
    );

    $s->execute([$userId]);

    return $s->fetchAll();
}

function profile_match_status_label(string $status): string
{
    return match ($status) {
        'waiting' => 'Лобби',
        'active' => 'Идёт',
        'finished' => 'Завершён',
        default => $status,
    };
}

function profile_match_result(array $match, int $userId): string
{
    if ($match['status'] === 'waiting') {
        return 'Ожидает старта';
    }

    if ($match['status'] === 'active') {
        return !empty($match['is_eliminated'])
            ? 'Выбыл'
            : 'В игре';
    }

    if ($match['status'] === 'finished') {
        if ((int) ($match['winner_user_id'] ?? 0) === $userId) {
            return 'Победа';
        }

        if (!empty($match['is_eliminated'])) {
            return 'Поражение / выбыл';
        }

        return 'Поражение';
    }

    return '—';
}

function get_profile_common_matches_count(int $userA, int $userB): int
{
    if ($userA === $userB) {
        return 0;
    }

    $s = db()->prepare(
        'SELECT COUNT(DISTINCT gp1.game_id)
         FROM game_players gp1
         JOIN game_players gp2 ON gp2.game_id = gp1.game_id
         WHERE gp1.user_id=? AND gp2.user_id=?'
    );

    $s->execute([$userA, $userB]);

    return (int) $s->fetchColumn();
}

function get_profile_moderation_stats(int $userId): array
{
    $empty = [
        'reports_on_user' => 0,
        'reports_from_user' => 0,
        'confirmed_on_user' => 0,
        'rejected_on_user' => 0,
        'open_on_user' => 0,
    ];

    $tableCheck = db()->query("SHOW TABLES LIKE 'game_reports'");

    if (!$tableCheck || !$tableCheck->fetchColumn()) {
        return $empty;
    }

    $onUser = db()->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(status = "confirmed") AS confirmed_total,
            SUM(status = "rejected") AS rejected_total,
            SUM(status IN ("open", "reviewing")) AS open_total
         FROM game_reports
         WHERE reported_user_id=?'
    );
    $onUser->execute([$userId]);
    $on = $onUser->fetch() ?: [];

    $fromUser = db()->prepare(
        'SELECT COUNT(*)
         FROM game_reports
         WHERE reporter_user_id=?'
    );
    $fromUser->execute([$userId]);

    return [
        'reports_on_user' => (int) ($on['total'] ?? 0),
        'reports_from_user' => (int) $fromUser->fetchColumn(),
        'confirmed_on_user' => (int) ($on['confirmed_total'] ?? 0),
        'rejected_on_user' => (int) ($on['rejected_total'] ?? 0),
        'open_on_user' => (int) ($on['open_total'] ?? 0),
    ];
}

function get_profile_recent_reports(int $userId, int $limit = 5): array
{
    $limit = max(1, min($limit, 20));

    $tableCheck = db()->query("SHOW TABLES LIKE 'game_reports'");

    if (!$tableCheck || !$tableCheck->fetchColumn()) {
        return [];
    }

    $s = db()->prepare(
        "SELECT
            gr.id,
            gr.game_id,
            gr.reason,
            gr.status,
            gr.created_at,
            reporter.username AS reporter_username,
            reported.username AS reported_username
         FROM game_reports gr
         JOIN users reporter ON reporter.id = gr.reporter_user_id
         JOIN users reported ON reported.id = gr.reported_user_id
         WHERE gr.reported_user_id=? OR gr.reporter_user_id=?
         ORDER BY gr.created_at DESC
         LIMIT $limit"
    );

    $s->execute([$userId, $userId]);

    return $s->fetchAll();
}

function update_current_user_presence(): void
{
    $uid = current_user_id();

    if (!$uid) {
        return;
    }

    db()->prepare(
        'UPDATE users
         SET last_seen_at = NOW()
         WHERE id=?'
    )->execute([(int) $uid]);
}

function profile_is_online(?string $lastSeenAt): bool
{
    if (!$lastSeenAt) {
        return false;
    }

    $lastSeen = strtotime($lastSeenAt);

    if (!$lastSeen) {
        return false;
    }

    return $lastSeen >= time() - 300;
}

function profile_online_label(?string $lastSeenAt): string
{
    if (!$lastSeenAt) {
        return 'Оффлайн';
    }

    if (profile_is_online($lastSeenAt)) {
        return 'Онлайн';
    }

    return 'Был в сети: ' . $lastSeenAt;
}

function profile_online_class(?string $lastSeenAt): string
{
    return profile_is_online($lastSeenAt)
        ? 'status-confirmed'
        : 'status-closed';
}

function get_profile_common_matches_for_report(int $viewerId, int $profileUserId, int $limit = 10): array
{
    if ($viewerId === $profileUserId) {
        return [];
    }

    $limit = max(1, min($limit, 20));

    $s = db()->prepare(
        "SELECT
            g.id,
            g.title,
            g.status,
            g.map_id,
            g.created_at,
            g.updated_at
         FROM games g
         JOIN game_players viewer_gp
            ON viewer_gp.game_id = g.id AND viewer_gp.user_id = ?
         JOIN game_players profile_gp
            ON profile_gp.game_id = g.id AND profile_gp.user_id = ?
         ORDER BY g.updated_at DESC, g.id DESC
         LIMIT $limit"
    );

    $s->execute([$viewerId, $profileUserId]);

    return $s->fetchAll();
}

function get_profile_header_counts(
    int $viewerId,
    int $profileUserId,
    array $stats,
    int $friendsCount,
    int $commonMatches,
    array $incomingFriendRequests,
    array $outgoingFriendRequests,
    array $incomingGameInvites,
    array $outgoingGameInvites
): array {
    if ($viewerId === $profileUserId) {
        return [
            [
                'label' => 'Друзей',
                'value' => $friendsCount,
            ],
            [
                'label' => 'Входящих заявок',
                'value' => count($incomingFriendRequests),
            ],
            [
                'label' => 'Исходящих заявок',
                'value' => count($outgoingFriendRequests),
            ],
            [
                'label' => 'Приглашений',
                'value' => count($incomingGameInvites),
            ],
            [
                'label' => 'Исходящих приглашений',
                'value' => count($outgoingGameInvites),
            ],
        ];
    }

    return [
        [
            'label' => 'Матчей',
            'value' => (int) $stats['games_played'],
        ],
        [
            'label' => 'Побед',
            'value' => (int) $stats['wins'],
        ],
        [
            'label' => 'Винрейт',
            'value' => (int) $stats['win_rate'] . '%',
        ],
        [
            'label' => 'Друзей',
            'value' => $friendsCount,
        ],
        [
            'label' => 'Общих матчей',
            'value' => $commonMatches,
        ],
    ];
}