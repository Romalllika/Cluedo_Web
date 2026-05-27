<?php

function normalize_friend_pair(int $userA, int $userB): array
{
    return [$userA, $userB];
}

function get_friend_relation(int $viewerId, int $profileUserId): array
{
    if ($viewerId === $profileUserId) {
        return [
            'type' => 'self',
            'request' => null,
        ];
    }

    $s = db()->prepare(
        "SELECT *
         FROM friend_requests
         WHERE
            (
                sender_user_id=? AND receiver_user_id=?
            )
            OR
            (
                sender_user_id=? AND receiver_user_id=?
            )
         ORDER BY
            CASE status
                WHEN 'accepted' THEN 1
                WHEN 'pending' THEN 2
                ELSE 3
            END,
            updated_at DESC,
            id DESC
         LIMIT 1"
    );

    $s->execute([
        $viewerId,
        $profileUserId,
        $profileUserId,
        $viewerId,
    ]);

    $request = $s->fetch();

    if (!$request) {
        return [
            'type' => 'none',
            'request' => null,
        ];
    }

    if ($request['status'] === 'accepted') {
        return [
            'type' => 'friends',
            'request' => $request,
        ];
    }

    if ($request['status'] === 'pending') {
        if ((int) $request['sender_user_id'] === $viewerId) {
            return [
                'type' => 'outgoing_pending',
                'request' => $request,
            ];
        }

        return [
            'type' => 'incoming_pending',
            'request' => $request,
        ];
    }

    return [
        'type' => 'none',
        'request' => $request,
    ];
}

function send_friend_request(int $senderId, int $receiverId): array
{
    if ($senderId === $receiverId) {
        return ['error' => 'Нельзя добавить в друзья самого себя'];
    }

    $receiver = db()->prepare('SELECT id FROM users WHERE id=?');
    $receiver->execute([$receiverId]);

    if (!$receiver->fetchColumn()) {
        return ['error' => 'Пользователь не найден'];
    }

    $relation = get_friend_relation($senderId, $receiverId);

    if ($relation['type'] === 'friends') {
        return ['error' => 'Вы уже друзья'];
    }

    if ($relation['type'] === 'outgoing_pending') {
        return ['error' => 'Заявка уже отправлена'];
    }

    if ($relation['type'] === 'incoming_pending') {
        return ['error' => 'Этот игрок уже отправил вам заявку. Примите её в профиле.'];
    }

    db()->prepare(
        'INSERT INTO friend_requests
            (sender_user_id, receiver_user_id, status)
         VALUES
            (?, ?, "pending")'
    )->execute([$senderId, $receiverId]);

    return ['ok' => true];
}

function accept_friend_request(int $requestId, int $receiverId): array
{
    $s = db()->prepare(
        'SELECT *
         FROM friend_requests
         WHERE id=? AND receiver_user_id=? AND status="pending"'
    );
    $s->execute([$requestId, $receiverId]);

    $request = $s->fetch();

    if (!$request) {
        return ['error' => 'Заявка не найдена'];
    }

    db()->prepare(
        'UPDATE friend_requests
         SET status="accepted", updated_at=NOW()
         WHERE id=?'
    )->execute([$requestId]);

    return ['ok' => true];
}

function reject_friend_request(int $requestId, int $receiverId): array
{
    $s = db()->prepare(
        'SELECT *
         FROM friend_requests
         WHERE id=? AND receiver_user_id=? AND status="pending"'
    );
    $s->execute([$requestId, $receiverId]);

    $request = $s->fetch();

    if (!$request) {
        return ['error' => 'Заявка не найдена'];
    }

    db()->prepare(
        'UPDATE friend_requests
         SET status="rejected", updated_at=NOW()
         WHERE id=?'
    )->execute([$requestId]);

    return ['ok' => true];
}

function cancel_friend_request(int $requestId, int $senderId): array
{
    $s = db()->prepare(
        'SELECT *
         FROM friend_requests
         WHERE id=? AND sender_user_id=? AND status="pending"'
    );
    $s->execute([$requestId, $senderId]);

    $request = $s->fetch();

    if (!$request) {
        return ['error' => 'Заявка не найдена'];
    }

    db()->prepare(
        'UPDATE friend_requests
         SET status="cancelled", updated_at=NOW()
         WHERE id=?'
    )->execute([$requestId]);

    return ['ok' => true];
}

function remove_friend(int $viewerId, int $friendId): array
{
    if ($viewerId === $friendId) {
        return ['error' => 'Некорректное действие'];
    }

    $s = db()->prepare(
        'SELECT *
         FROM friend_requests
         WHERE status="accepted"
           AND (
                (sender_user_id=? AND receiver_user_id=?)
                OR
                (sender_user_id=? AND receiver_user_id=?)
           )
         LIMIT 1'
    );

    $s->execute([
        $viewerId,
        $friendId,
        $friendId,
        $viewerId,
    ]);

    $friendship = $s->fetch();

    if (!$friendship) {
        return ['error' => 'Вы не друзья'];
    }

    db()->prepare(
        'UPDATE friend_requests
         SET status="cancelled", updated_at=NOW()
         WHERE id=?'
    )->execute([(int) $friendship['id']]);

    return ['ok' => true];
}

function get_user_friends(int $userId, int $limit = 12): array
{
    $limit = max(1, min($limit, 50));

    $s = db()->prepare(
        "SELECT
            u.id,
            u.username,
            u.last_seen_at,
            fr.updated_at AS friends_since
         FROM friend_requests fr
         JOIN users u ON u.id = CASE
            WHEN fr.sender_user_id = ? THEN fr.receiver_user_id
            ELSE fr.sender_user_id
         END
         WHERE fr.status='accepted'
           AND (fr.sender_user_id=? OR fr.receiver_user_id=?)
         ORDER BY u.username ASC
         LIMIT $limit"
    );

    $s->execute([$userId, $userId, $userId]);

    return $s->fetchAll();
}

function get_incoming_friend_requests(int $userId): array
{
    $s = db()->prepare(
        'SELECT
            fr.*,
            u.username,
            u.last_seen_at
         FROM friend_requests fr
         JOIN users u ON u.id = fr.sender_user_id
         WHERE fr.receiver_user_id=? AND fr.status="pending"
         ORDER BY fr.created_at DESC'
    );

    $s->execute([$userId]);

    return $s->fetchAll();
}

function get_outgoing_friend_requests(int $userId): array
{
    $s = db()->prepare(
        'SELECT
            fr.*,
            u.username,
            u.last_seen_at
         FROM friend_requests fr
         JOIN users u ON u.id = fr.receiver_user_id
         WHERE fr.sender_user_id=? AND fr.status="pending"
         ORDER BY fr.created_at DESC'
    );

    $s->execute([$userId]);

    return $s->fetchAll();
}

function count_user_friends(int $userId): int
{
    $s = db()->prepare(
        'SELECT COUNT(*)
         FROM friend_requests
         WHERE status="accepted"
           AND (sender_user_id=? OR receiver_user_id=?)'
    );

    $s->execute([$userId, $userId]);

    return (int) $s->fetchColumn();
}