<?php

function map_submission_statuses(): array
{
    return [
        'pending' => 'На проверке',
        'needs_changes' => 'Нужны правки',
        'approved' => 'Одобрена',
        'rejected' => 'Отклонена',
    ];
}

function map_submission_status_label(string $status): string
{
    $statuses = map_submission_statuses();

    return $statuses[$status] ?? $status;
}

function map_submission_status_class(string $status): string
{
    return match ($status) {
        'pending' => 'status-open',
        'needs_changes' => 'status-reviewing',
        'approved' => 'status-confirmed',
        'rejected' => 'status-rejected',
        default => 'status-unknown',
    };
}

function parse_submitted_map_json(string $jsonText): array
{
    $jsonText = trim($jsonText);

    if ($jsonText === '') {
        return ['error' => 'JSON карты пустой'];
    }

    if (strlen($jsonText) > 300000) {
        return ['error' => 'JSON слишком большой. Максимум около 300 KB'];
    }

    $decoded = json_decode($jsonText, true);

    if (!is_array($decoded)) {
        return ['error' => 'JSON не читается: ' . json_last_error_msg()];
    }

    $required = ['id', 'title', 'board', 'cards', 'paths', 'starts', 'rooms'];

    foreach ($required as $key) {
        if (!array_key_exists($key, $decoded)) {
            return ['error' => 'В JSON отсутствует обязательное поле: ' . $key];
        }
    }

    $mapId = trim((string) ($decoded['id'] ?? ''));
    $title = trim((string) ($decoded['title'] ?? ''));

    if ($mapId === '') {
        return ['error' => 'В JSON не указан id карты'];
    }

    if (!preg_match('/^[a-z0-9_\\-]+$/', $mapId)) {
        return ['error' => 'ID карты должен содержать только латинские буквы, цифры, дефис или подчёркивание'];
    }

    if ($title === '') {
        return ['error' => 'В JSON не указано название карты'];
    }

    return [
        'ok' => true,
        'map_id' => $mapId,
        'title' => $title,
        'decoded' => $decoded,
    ];
}

function create_map_submission(int $userId, string $jsonText, string $userComment = ''): array
{
    $parsed = parse_submitted_map_json($jsonText);

    if (!empty($parsed['error'])) {
        return ['error' => $parsed['error']];
    }

    $userComment = trim($userComment);

    if (mb_strlen($userComment) > 2000) {
        $userComment = mb_substr($userComment, 0, 2000);
    }

    db()->prepare(
        'INSERT INTO map_submissions
            (user_id, map_id, title, json_text, user_comment, status)
         VALUES
            (?, ?, ?, ?, ?, "pending")'
    )->execute([
        $userId,
        $parsed['map_id'],
        $parsed['title'],
        trim($jsonText),
        $userComment,
    ]);

    return [
        'ok' => true,
        'submission_id' => (int) db()->lastInsertId(),
    ];
}

function get_user_map_submissions(int $userId, int $limit = 10): array
{
    $limit = max(1, min($limit, 30));

    $s = db()->prepare(
        "SELECT *
         FROM map_submissions
         WHERE user_id=?
         ORDER BY created_at DESC
         LIMIT $limit"
    );

    $s->execute([$userId]);

    return $s->fetchAll();
}

function list_map_submissions(string $status = 'all'): array
{
    $params = [];
    $where = '';

    if ($status !== 'all' && array_key_exists($status, map_submission_statuses())) {
        $where = 'WHERE ms.status=?';
        $params[] = $status;
    }

    $s = db()->prepare(
        "SELECT
            ms.*,
            u.username,
            reviewer.username AS reviewer_username
         FROM map_submissions ms
         JOIN users u ON u.id = ms.user_id
         LEFT JOIN users reviewer ON reviewer.id = ms.reviewed_by_user_id
         $where
         ORDER BY
            CASE ms.status
                WHEN 'pending' THEN 1
                WHEN 'needs_changes' THEN 2
                WHEN 'approved' THEN 3
                WHEN 'rejected' THEN 4
                ELSE 5
            END,
            ms.created_at DESC"
    );

    $s->execute($params);

    return $s->fetchAll();
}

function get_map_submission_by_id(int $submissionId): ?array
{
    $s = db()->prepare(
        'SELECT
            ms.*,
            u.username,
            reviewer.username AS reviewer_username
         FROM map_submissions ms
         JOIN users u ON u.id = ms.user_id
         LEFT JOIN users reviewer ON reviewer.id = ms.reviewed_by_user_id
         WHERE ms.id=?'
    );

    $s->execute([$submissionId]);
    $submission = $s->fetch();

    return $submission ?: null;
}

function update_map_submission_review(
    int $submissionId,
    int $adminId,
    string $status,
    string $adminComment
): array {
    if (!array_key_exists($status, map_submission_statuses())) {
        return ['error' => 'Некорректный статус заявки'];
    }

    $submission = get_map_submission_by_id($submissionId);

    if (!$submission) {
        return ['error' => 'Заявка не найдена'];
    }

    $adminComment = trim($adminComment);

    if (mb_strlen($adminComment) > 3000) {
        $adminComment = mb_substr($adminComment, 0, 3000);
    }

    if (in_array($status, ['needs_changes', 'rejected'], true) && $adminComment === '') {
        return ['error' => 'Для правок или отклонения нужен комментарий администратора'];
    }

    db()->prepare(
        'UPDATE map_submissions
         SET
            status=?,
            admin_comment=?,
            reviewed_by_user_id=?,
            reviewed_at=NOW(),
            updated_at=NOW()
         WHERE id=?'
    )->execute([
        $status,
        $adminComment,
        $adminId,
        $submissionId,
    ]);

    return ['ok' => true];
}

function map_submission_download_filename(array $submission): string
{
    $mapId = trim((string) ($submission['map_id'] ?? ''));

    if ($mapId === '') {
        $mapId = 'submitted_map_' . (int) $submission['id'];
    }

    return preg_replace('/[^a-zA-Z0-9_\\-]/', '_', $mapId) . '.json';
}