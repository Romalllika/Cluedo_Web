<?php

function user_is_friend_with(int $userA, int $userB): bool
{
    if ($userA === $userB) {
        return false;
    }

    $s = db()->prepare(
        'SELECT COUNT(*)
         FROM friend_requests
         WHERE status="accepted"
           AND (
                (sender_user_id=? AND receiver_user_id=?)
                OR
                (sender_user_id=? AND receiver_user_id=?)
           )'
    );

    $s->execute([$userA, $userB, $userB, $userA]);

    return (int) $s->fetchColumn() > 0;
}

function get_invitable_games_for_user(int $senderId, int $receiverId): array
{
    $s = db()->prepare(
        "SELECT
            g.id,
            g.title,
            g.max_players,
            g.status,
            g.map_id,
            COUNT(gp.id) AS players_count,
            MAX(CASE
                WHEN gi.id IS NOT NULL AND gi.status='pending' THEN 1
                ELSE 0
            END) AS has_pending_invite
         FROM games g
         JOIN game_players me ON me.game_id = g.id AND me.user_id = ?
         LEFT JOIN game_players gp ON gp.game_id = g.id
         LEFT JOIN game_players receiver_gp ON receiver_gp.game_id = g.id AND receiver_gp.user_id = ?
         LEFT JOIN game_invites gi
            ON gi.game_id = g.id
           AND gi.sender_user_id = ?
           AND gi.receiver_user_id = ?
           AND gi.status = 'pending'
         WHERE g.status = 'waiting'
           AND receiver_gp.id IS NULL
         GROUP BY g.id
         HAVING players_count < g.max_players
         ORDER BY g.created_at DESC"
    );

    $s->execute([$senderId, $receiverId, $senderId, $receiverId]);

    return $s->fetchAll();
}

function get_pending_invite_for_pair(int $gameId, int $senderId, int $receiverId): ?array
{
    $s = db()->prepare(
        'SELECT *
         FROM game_invites
         WHERE game_id=?
           AND sender_user_id=?
           AND receiver_user_id=?
           AND status="pending"
         LIMIT 1'
    );

    $s->execute([$gameId, $senderId, $receiverId]);
    $invite = $s->fetch();

    return $invite ?: null;
}

function create_game_invite(int $gameId, int $senderId, int $receiverId, string $message = ''): array
{
    if ($senderId === $receiverId) {
        return ['error' => 'Нельзя пригласить самого себя'];
    }

    if (!user_is_friend_with($senderId, $receiverId)) {
        return ['error' => 'Приглашать в игру можно только друзей'];
    }

    $gameStmt = db()->prepare(
        'SELECT *
         FROM games
         WHERE id=?'
    );
    $gameStmt->execute([$gameId]);
    $game = $gameStmt->fetch();

    if (!$game) {
        return ['error' => 'Матч не найден'];
    }

    if ($game['status'] !== 'waiting') {
        return ['error' => 'Приглашать можно только в ожидающий матч'];
    }

    $senderInGame = db()->prepare(
        'SELECT COUNT(*)
         FROM game_players
         WHERE game_id=? AND user_id=?'
    );
    $senderInGame->execute([$gameId, $senderId]);

    if ((int) $senderInGame->fetchColumn() <= 0) {
        return ['error' => 'Вы не участвуете в этом матче'];
    }

    $receiverInGame = db()->prepare(
        'SELECT COUNT(*)
         FROM game_players
         WHERE game_id=? AND user_id=?'
    );
    $receiverInGame->execute([$gameId, $receiverId]);

    if ((int) $receiverInGame->fetchColumn() > 0) {
        return ['error' => 'Этот игрок уже находится в матче'];
    }

    $countStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM game_players
         WHERE game_id=?'
    );
    $countStmt->execute([$gameId]);

    if ((int) $countStmt->fetchColumn() >= (int) $game['max_players']) {
        return ['error' => 'В матче уже нет свободных мест'];
    }

    if (get_pending_invite_for_pair($gameId, $senderId, $receiverId)) {
        return ['error' => 'Приглашение уже отправлено'];
    }

    $message = trim($message);

    if (mb_strlen($message) > 255) {
        $message = mb_substr($message, 0, 255);
    }

    $s = db()->prepare(
        'INSERT INTO game_invites
            (game_id, sender_user_id, receiver_user_id, message, expires_at)
         VALUES
            (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))'
    );

    $s->execute([
        $gameId,
        $senderId,
        $receiverId,
        $message,
    ]);

    return [
        'ok' => true,
        'invite_id' => (int) db()->lastInsertId(),
    ];
}

function expire_old_game_invites(): void
{
    db()->query(
        "UPDATE game_invites
         SET status='expired', updated_at=NOW()
         WHERE status='pending'
           AND expires_at IS NOT NULL
           AND expires_at < NOW()"
    );
}

function get_incoming_game_invites(int $userId): array
{
    expire_old_game_invites();

    $s = db()->prepare(
        'SELECT
            gi.*,
            g.title AS game_title,
            g.status AS game_status,
            g.max_players,
            g.map_id,
            sender.username AS sender_username,
            COUNT(gp.id) AS players_count
         FROM game_invites gi
         JOIN games g ON g.id = gi.game_id
         JOIN users sender ON sender.id = gi.sender_user_id
         LEFT JOIN game_players gp ON gp.game_id = g.id
         WHERE gi.receiver_user_id=?
           AND gi.status="pending"
           AND g.status="waiting"
         GROUP BY gi.id
         ORDER BY gi.created_at DESC'
    );

    $s->execute([$userId]);

    return $s->fetchAll();
}

function get_outgoing_game_invites(int $userId): array
{
    expire_old_game_invites();

    $s = db()->prepare(
        'SELECT
            gi.*,
            g.title AS game_title,
            g.status AS game_status,
            g.max_players,
            g.map_id,
            receiver.username AS receiver_username,
            COUNT(gp.id) AS players_count
         FROM game_invites gi
         JOIN games g ON g.id = gi.game_id
         JOIN users receiver ON receiver.id = gi.receiver_user_id
         LEFT JOIN game_players gp ON gp.game_id = g.id
         WHERE gi.sender_user_id=?
           AND gi.status="pending"
           AND g.status="waiting"
         GROUP BY gi.id
         ORDER BY gi.created_at DESC'
    );

    $s->execute([$userId]);

    return $s->fetchAll();
}

function get_game_invite_by_id(int $inviteId): ?array
{
    $s = db()->prepare(
        'SELECT *
         FROM game_invites
         WHERE id=?'
    );

    $s->execute([$inviteId]);
    $invite = $s->fetch();

    return $invite ?: null;
}

function accept_game_invite(int $inviteId, int $receiverId): array
{
    $db = db();
    $db->beginTransaction();

    try {
        $inviteStmt = $db->prepare(
            'SELECT *
             FROM game_invites
             WHERE id=? AND receiver_user_id=? AND status="pending"
             FOR UPDATE'
        );
        $inviteStmt->execute([$inviteId, $receiverId]);
        $invite = $inviteStmt->fetch();

        if (!$invite) {
            $db->rollBack();
            return ['error' => 'Приглашение не найдено'];
        }

        if (!empty($invite['expires_at']) && strtotime($invite['expires_at']) < time()) {
            $db->prepare(
                'UPDATE game_invites
                 SET status="expired", updated_at=NOW()
                 WHERE id=?'
            )->execute([$inviteId]);

            $db->commit();

            return ['error' => 'Приглашение истекло'];
        }

        $gameStmt = $db->prepare(
            'SELECT *
             FROM games
             WHERE id=?
             FOR UPDATE'
        );
        $gameStmt->execute([(int) $invite['game_id']]);
        $game = $gameStmt->fetch();

        if (!$game || $game['status'] !== 'waiting') {
            $db->prepare(
                'UPDATE game_invites
                 SET status="expired", updated_at=NOW()
                 WHERE id=?'
            )->execute([$inviteId]);

            $db->commit();

            return ['error' => 'Матч уже недоступен'];
        }

        $already = $db->prepare(
            'SELECT id
             FROM game_players
             WHERE game_id=? AND user_id=?
             FOR UPDATE'
        );
        $already->execute([(int) $invite['game_id'], $receiverId]);

        if ($already->fetchColumn()) {
            $db->prepare(
                'UPDATE game_invites
                 SET status="accepted", updated_at=NOW()
                 WHERE id=?'
            )->execute([$inviteId]);

            $db->commit();

            return [
                'ok' => true,
                'game_id' => (int) $invite['game_id'],
            ];
        }

        $countStmt = $db->prepare(
            'SELECT COUNT(*)
             FROM game_players
             WHERE game_id=?'
        );
        $countStmt->execute([(int) $invite['game_id']]);
        $count = (int) $countStmt->fetchColumn();

        if ($count >= (int) $game['max_players']) {
            $db->prepare(
                'UPDATE game_invites
                 SET status="expired", updated_at=NOW()
                 WHERE id=?'
            )->execute([$inviteId]);

            $db->commit();

            return ['error' => 'В матче уже нет свободных мест'];
        }

        $db->prepare(
            'UPDATE game_invites
             SET status="accepted", updated_at=NOW()
             WHERE id=?'
        )->execute([$inviteId]);

        $db->commit();

        return [
            'ok' => true,
            'game_id' => (int) $invite['game_id'],
        ];
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function reject_game_invite(int $inviteId, int $receiverId): array
{
    $s = db()->prepare(
        'SELECT *
         FROM game_invites
         WHERE id=? AND receiver_user_id=? AND status="pending"'
    );

    $s->execute([$inviteId, $receiverId]);

    if (!$s->fetch()) {
        return ['error' => 'Приглашение не найдено'];
    }

    db()->prepare(
        'UPDATE game_invites
         SET status="rejected", updated_at=NOW()
         WHERE id=?'
    )->execute([$inviteId]);

    return ['ok' => true];
}

function cancel_game_invite(int $inviteId, int $senderId): array
{
    $s = db()->prepare(
        'SELECT *
         FROM game_invites
         WHERE id=? AND sender_user_id=? AND status="pending"'
    );

    $s->execute([$inviteId, $senderId]);

    if (!$s->fetch()) {
        return ['error' => 'Приглашение не найдено'];
    }

    db()->prepare(
        'UPDATE game_invites
         SET status="cancelled", updated_at=NOW()
         WHERE id=?'
    )->execute([$inviteId]);

    return ['ok' => true];
}

function get_online_friends_for_game_invite(int $userId, int $gameId): array
{
    $s = db()->prepare(
        "SELECT
            u.id,
            u.username,
            u.last_seen_at,
            gi.id AS pending_invite_id
         FROM friend_requests fr
         JOIN users u ON u.id = CASE
            WHEN fr.sender_user_id = ? THEN fr.receiver_user_id
            ELSE fr.sender_user_id
         END
         LEFT JOIN game_players gp
            ON gp.game_id = ? AND gp.user_id = u.id
         LEFT JOIN game_invites gi
            ON gi.game_id = ?
           AND gi.sender_user_id = ?
           AND gi.receiver_user_id = u.id
           AND gi.status = 'pending'
         WHERE fr.status = 'accepted'
           AND (fr.sender_user_id = ? OR fr.receiver_user_id = ?)
           AND gp.id IS NULL
           AND u.last_seen_at IS NOT NULL
           AND u.last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
         ORDER BY u.username ASC"
    );

    $s->execute([
        $userId,
        $gameId,
        $gameId,
        $userId,
        $userId,
        $userId,
    ]);

    return $s->fetchAll();
}