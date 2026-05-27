<?php

function report_reasons(): array
{
    return [
        'afk' => 'AFK / не играет',
        'stalling' => 'Затягивание хода',
        'abuse' => 'Оскорбления / неспортивное поведение',
        'cheating' => 'Подозрение на нечестную игру',
        'bug_abuse' => 'Злоупотребление багом',
        'other' => 'Другое',
    ];
}

function report_statuses(): array
{
    return [
        'open' => 'Новый',
        'reviewing' => 'На проверке',
        'confirmed' => 'Подтверждён',
        'rejected' => 'Отклонён',
        'closed' => 'Закрыт',
    ];
}

function is_valid_report_reason(string $reason): bool
{
    return array_key_exists($reason, report_reasons());
}

function user_is_moderator_or_admin(?int $uid = null): bool
{
    $uid = $uid ?? current_user_id();

    if (!$uid) {
        return false;
    }

    $s = db()->prepare('SELECT role FROM users WHERE id=?');
    $s->execute([$uid]);

    $role = (string) $s->fetchColumn();

    return in_array($role, ['moderator', 'admin'], true);
}

function require_moderator_or_admin(): void
{
    require_auth();

    if (!user_is_moderator_or_admin()) {
        http_response_code(403);
        echo 'Доступ запрещён';
        exit;
    }
}

function report_user_is_in_game(int $gameId, int $userId): bool
{
    $s = db()->prepare(
        'SELECT COUNT(*)
         FROM game_players
         WHERE game_id=? AND user_id=?'
    );
    $s->execute([$gameId, $userId]);

    return (int) $s->fetchColumn() > 0;
}

function report_open_exists(int $gameId, int $reporterId, int $reportedId): bool
{
    $s = db()->prepare(
        "SELECT COUNT(*)
         FROM game_reports
         WHERE game_id=?
           AND reporter_user_id=?
           AND reported_user_id=?
           AND status IN ('open', 'reviewing')"
    );
    $s->execute([$gameId, $reporterId, $reportedId]);

    return (int) $s->fetchColumn() > 0;
}

function report_count_by_reporter_in_game(int $gameId, int $reporterId): int
{
    $s = db()->prepare(
        'SELECT COUNT(*)
         FROM game_reports
         WHERE game_id=? AND reporter_user_id=?'
    );
    $s->execute([$gameId, $reporterId]);

    return (int) $s->fetchColumn();
}

function report_count_by_reporter_today(int $reporterId): int
{
    $s = db()->prepare(
        'SELECT COUNT(*)
         FROM game_reports
         WHERE reporter_user_id=?
           AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)'
    );
    $s->execute([$reporterId]);

    return (int) $s->fetchColumn();
}

function build_report_snapshot(int $gameId): array
{
    $gameStmt = db()->prepare(
        'SELECT *
         FROM games
         WHERE id=?'
    );
    $gameStmt->execute([$gameId]);
    $game = $gameStmt->fetch();

    $playersStmt = db()->prepare(
        'SELECT
            gp.user_id,
            u.username,
            gp.character_name,
            gp.seat_no,
            gp.turn_order,
            gp.pos_x,
            gp.pos_y,
            gp.is_eliminated,
            gp.afk_misses
         FROM game_players gp
         JOIN users u ON u.id = gp.user_id
         WHERE gp.game_id=?
         ORDER BY gp.turn_order'
    );
    $playersStmt->execute([$gameId]);

    $logsStmt = db()->prepare(
        'SELECT
            gl.id,
            gl.user_id,
            u.username,
            gl.message,
            gl.created_at
         FROM game_logs gl
         LEFT JOIN users u ON u.id = gl.user_id
         WHERE gl.game_id=?
         ORDER BY gl.id DESC
         LIMIT 50'
    );
    $logsStmt->execute([$gameId]);

    return [
        'captured_at' => date('Y-m-d H:i:s'),
        'game' => $game,
        'players' => $playersStmt->fetchAll(),
        'recent_logs' => array_reverse($logsStmt->fetchAll()),
    ];
}

function create_game_report(
    int $gameId,
    int $reporterId,
    int $reportedId,
    string $reason,
    string $comment
): array {
    if ($reportedId === $reporterId) {
        return ['error' => 'Нельзя отправить репорт на самого себя'];
    }

    if (!is_valid_report_reason($reason)) {
        return ['error' => 'Некорректная причина репорта'];
    }

    if (!report_user_is_in_game($gameId, $reporterId)) {
        return ['error' => 'Вы не участвуете в этом матче'];
    }

    if (!report_user_is_in_game($gameId, $reportedId)) {
        return ['error' => 'Этот игрок не участвует в матче'];
    }

    if (report_open_exists($gameId, $reporterId, $reportedId)) {
        return ['error' => 'Вы уже отправили открытый репорт на этого игрока в этом матче'];
    }

    if (report_count_by_reporter_in_game($gameId, $reporterId) >= 3) {
        return ['error' => 'Лимит репортов в этом матче исчерпан'];
    }

    if (report_count_by_reporter_today($reporterId) >= 10) {
        return ['error' => 'Суточный лимит репортов исчерпан'];
    }

    $comment = trim($comment);

    if (mb_strlen($comment) > 1000) {
        $comment = mb_substr($comment, 0, 1000);
    }

    $snapshot = build_report_snapshot($gameId);

    $s = db()->prepare(
        'INSERT INTO game_reports
            (game_id, reporter_user_id, reported_user_id, reason, comment, snapshot_json)
         VALUES
            (?, ?, ?, ?, ?, ?)'
    );

    $s->execute([
        $gameId,
        $reporterId,
        $reportedId,
        $reason,
        $comment,
        json_encode($snapshot, JSON_UNESCAPED_UNICODE),
    ]);

    return [
        'ok' => true,
        'report_id' => (int) db()->lastInsertId(),
    ];
}

function get_report_by_id(int $reportId): ?array
{
    $s = db()->prepare(
        'SELECT
            gr.*,
            g.title AS game_title,
            g.status AS game_status,
            g.phase AS game_phase,
            reporter.username AS reporter_username,
            reported.username AS reported_username,
            reviewer.username AS reviewer_username
         FROM game_reports gr
         JOIN games g ON g.id = gr.game_id
         JOIN users reporter ON reporter.id = gr.reporter_user_id
         JOIN users reported ON reported.id = gr.reported_user_id
         LEFT JOIN users reviewer ON reviewer.id = gr.reviewer_user_id
         WHERE gr.id=?'
    );

    $s->execute([$reportId]);
    $report = $s->fetch();

    return $report ?: null;
}

function list_reports(string $status = 'all'): array
{
    $allowedStatuses = array_keys(report_statuses());

    $where = '';
    $params = [];

    if ($status !== 'all' && in_array($status, $allowedStatuses, true)) {
        $where = 'WHERE gr.status = ?';
        $params[] = $status;
    }

    $s = db()->prepare(
        "SELECT
            gr.id,
            gr.game_id,
            gr.reason,
            gr.status,
            gr.created_at,
            gr.updated_at,
            gr.reporter_user_id,
            gr.reported_user_id,
            g.title AS game_title,
            reporter.username AS reporter_username,
            reported.username AS reported_username,
            reviewer.username AS reviewer_username
         FROM game_reports gr
         JOIN games g ON g.id = gr.game_id
         JOIN users reporter ON reporter.id = gr.reporter_user_id
         JOIN users reported ON reported.id = gr.reported_user_id
         LEFT JOIN users reviewer ON reviewer.id = gr.reviewer_user_id
         $where
         ORDER BY
            CASE gr.status
                WHEN 'open' THEN 1
                WHEN 'reviewing' THEN 2
                WHEN 'confirmed' THEN 3
                WHEN 'rejected' THEN 4
                WHEN 'closed' THEN 5
                ELSE 6
            END,
            gr.created_at DESC"
    );

    $s->execute($params);

    return $s->fetchAll();
}

function update_report_status(
    int $reportId,
    int $reviewerId,
    string $status,
    string $reviewComment
): array {
    if (!array_key_exists($status, report_statuses())) {
        return ['error' => 'Некорректный статус репорта'];
    }

    $report = get_report_by_id($reportId);

    if (!$report) {
        return ['error' => 'Репорт не найден'];
    }

    $reviewComment = trim($reviewComment);

    if (mb_strlen($reviewComment) > 2000) {
        $reviewComment = mb_substr($reviewComment, 0, 2000);
    }

    $reviewedAtSql = in_array($status, ['confirmed', 'rejected', 'closed'], true)
        ? ', reviewed_at = NOW()'
        : '';

    $s = db()->prepare(
        "UPDATE game_reports
         SET
            status = ?,
            reviewer_user_id = ?,
            review_comment = ?,
            updated_at = NOW()
            $reviewedAtSql
         WHERE id = ?"
    );

    $s->execute([
        $status,
        $reviewerId,
        $reviewComment,
        $reportId,
    ]);

    return ['ok' => true];
}

function decode_report_snapshot(?string $snapshotJson): array
{
    if (!$snapshotJson) {
        return [];
    }

    $decoded = json_decode($snapshotJson, true);

    return is_array($decoded) ? $decoded : [];
}

function report_reason_label(string $reason): string
{
    $reasons = report_reasons();

    return $reasons[$reason] ?? $reason;
}

function report_status_label(string $status): string
{
    $statuses = report_statuses();

    return $statuses[$status] ?? $status;
}

function report_status_class(string $status): string
{
    return match ($status) {
        'open' => 'status-open',
        'reviewing' => 'status-reviewing',
        'confirmed' => 'status-confirmed',
        'rejected' => 'status-rejected',
        'closed' => 'status-closed',
        default => 'status-unknown',
    };
}

function report_action_types(): array
{
    return [
        'none' => 'Без санкции',
        'warning' => 'Предупреждение',
        'block_create_games_24h' => 'Запрет создавать игры на 24 часа',
        'block_games_24h' => 'Запрет участвовать в играх на 24 часа',
    ];
}

function report_action_label(string $actionType): string
{
    $types = report_action_types();

    return $types[$actionType] ?? $actionType;
}

function apply_report_decision(
    int $reportId,
    int $moderatorId,
    string $decision,
    string $reviewComment,
    string $actionType = 'none'
): array {
    $allowedDecisions = ['reviewing', 'confirmed', 'rejected', 'closed'];

    if (!in_array($decision, $allowedDecisions, true)) {
        return ['error' => 'Некорректное решение по репорту'];
    }

    if (!array_key_exists($actionType, report_action_types())) {
        return ['error' => 'Некорректная санкция'];
    }

    $report = get_report_by_id($reportId);

    if (!$report) {
        return ['error' => 'Репорт не найден'];
    }

    $reviewComment = trim($reviewComment);

    if (mb_strlen($reviewComment) > 2000) {
        $reviewComment = mb_substr($reviewComment, 0, 2000);
    }

    if ($decision === 'confirmed' && $reviewComment === '') {
        return ['error' => 'Для подтверждения нарушения нужен комментарий модератора'];
    }

    if ($decision !== 'confirmed') {
        $actionType = 'none';
    }

    $db = db();
    $db->beginTransaction();

    try {
        $reviewedAtSql = in_array($decision, ['confirmed', 'rejected', 'closed'], true)
            ? ', reviewed_at = NOW()'
            : '';

        $s = $db->prepare(
            "UPDATE game_reports
             SET
                status = ?,
                reviewer_user_id = ?,
                review_comment = ?,
                updated_at = NOW()
                $reviewedAtSql
             WHERE id = ?"
        );

        $s->execute([
            $decision,
            $moderatorId,
            $reviewComment,
            $reportId,
        ]);

        if ($decision === 'confirmed' && $actionType !== 'none') {
            apply_user_moderation_action(
                $reportId,
                (int) $report['reported_user_id'],
                $moderatorId,
                $actionType,
                $reviewComment
            );
        }

        $db->commit();

        return ['ok' => true];
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function apply_user_moderation_action(
    int $reportId,
    int $targetUserId,
    int $moderatorId,
    string $actionType,
    string $reason
): void {
    $expiresAt = null;

    if ($actionType === 'block_create_games_24h' || $actionType === 'block_games_24h') {
        $expiresAt = date('Y-m-d H:i:s', time() + 86400);
    }

    db()->prepare(
        'INSERT INTO user_moderation_actions
            (report_id, target_user_id, moderator_user_id, action_type, reason, expires_at)
         VALUES
            (?, ?, ?, ?, ?, ?)'
    )->execute([
        $reportId,
        $targetUserId,
        $moderatorId,
        $actionType,
        $reason,
        $expiresAt,
    ]);

    if ($actionType === 'warning') {
        db()->prepare(
            'UPDATE users
             SET warnings_count = warnings_count + 1
             WHERE id=?'
        )->execute([$targetUserId]);

        return;
    }

    if ($actionType === 'block_create_games_24h') {
        db()->prepare(
            'UPDATE users
             SET create_blocked_until = GREATEST(
                COALESCE(create_blocked_until, NOW()),
                DATE_ADD(NOW(), INTERVAL 1 DAY)
             )
             WHERE id=?'
        )->execute([$targetUserId]);

        return;
    }

    if ($actionType === 'block_games_24h') {
        db()->prepare(
            'UPDATE users
             SET game_banned_until = GREATEST(
                COALESCE(game_banned_until, NOW()),
                DATE_ADD(NOW(), INTERVAL 1 DAY)
             )
             WHERE id=?'
        )->execute([$targetUserId]);
    }
}

function get_user_game_restriction_message(int $userId): ?string
{
    $s = db()->prepare(
        'SELECT game_banned_until
         FROM users
         WHERE id=?'
    );
    $s->execute([$userId]);

    $until = $s->fetchColumn();

    if ($until && strtotime((string) $until) > time()) {
        return 'Вам временно запрещено участвовать в играх до ' . $until;
    }

    return null;
}

function get_user_create_restriction_message(int $userId): ?string
{
    $s = db()->prepare(
        'SELECT game_banned_until, create_blocked_until
         FROM users
         WHERE id=?'
    );
    $s->execute([$userId]);

    $row = $s->fetch();

    if (!$row) {
        return null;
    }

    if (!empty($row['game_banned_until']) && strtotime((string) $row['game_banned_until']) > time()) {
        return 'Вам временно запрещено участвовать в играх до ' . $row['game_banned_until'];
    }

    if (!empty($row['create_blocked_until']) && strtotime((string) $row['create_blocked_until']) > time()) {
        return 'Вам временно запрещено создавать игры до ' . $row['create_blocked_until'];
    }

    return null;
}

function get_user_moderation_actions(int $userId, int $limit = 10): array
{
    $limit = max(1, min($limit, 30));

    $s = db()->prepare(
        "SELECT
            uma.*,
            moderator.username AS moderator_username
         FROM user_moderation_actions uma
         JOIN users moderator ON moderator.id = uma.moderator_user_id
         WHERE uma.target_user_id=?
         ORDER BY uma.created_at DESC
         LIMIT $limit"
    );

    $s->execute([$userId]);

    return $s->fetchAll();
}