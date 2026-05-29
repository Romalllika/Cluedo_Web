<?php

function progression_xp_per_level(): int
{
    return 500;
}

function account_level_from_xp(int $xp): int
{
    return max(1, intdiv(max(0, $xp), progression_xp_per_level()) + 1);
}

function account_level_progress(int $xp): array
{
    $xp = max(0, $xp);
    $perLevel = progression_xp_per_level();
    $level = account_level_from_xp($xp);
    $currentLevelXp = ($level - 1) * $perLevel;
    $nextLevelXp = $level * $perLevel;
    $intoLevel = $xp - $currentLevelXp;

    return [
        'level' => $level,
        'xp' => $xp,
        'current_level_xp' => $currentLevelXp,
        'next_level_xp' => $nextLevelXp,
        'into_level' => $intoLevel,
        'needed_for_next' => $perLevel,
        'percent' => $perLevel > 0 ? min(100, (int) round(($intoLevel / $perLevel) * 100)) : 0,
    ];
}

function daily_task_pool(): array
{
    return [
        'play_1_game' => [
            'title' => 'Сыграть 1 матч',
            'description' => 'Заверши один матч до конца.',
            'target' => 1,
            'xp' => 80,
        ],
        'play_2_games' => [
            'title' => 'Сыграть 2 матча',
            'description' => 'Заверши два матча за день.',
            'target' => 2,
            'xp' => 140,
        ],
        'win_1_game' => [
            'title' => 'Победить 1 раз',
            'description' => 'Выиграй один матч.',
            'target' => 1,
            'xp' => 160,
        ],
        'make_1_suggestion' => [
            'title' => 'Сделать 1 предположение',
            'description' => 'Сделай предположение в комнате.',
            'target' => 1,
            'xp' => 60,
        ],
        'make_3_suggestions' => [
            'title' => 'Сделать 3 предположения',
            'description' => 'Сделай три предположения в матчах.',
            'target' => 3,
            'xp' => 130,
        ],
        'make_1_accusation' => [
            'title' => 'Сделать обвинение',
            'description' => 'Сделай финальное обвинение.',
            'target' => 1,
            'xp' => 90,
        ],
        'show_1_card' => [
            'title' => 'Показать карту',
            'description' => 'Покажи карту другому игроку при опровержении.',
            'target' => 1,
            'xp' => 70,
        ],
        'show_3_cards' => [
            'title' => 'Показать 3 карты',
            'description' => 'Покажи три карты в разных опровержениях.',
            'target' => 3,
            'xp' => 140,
        ],
        'finish_5_turns' => [
            'title' => 'Завершить 5 ходов',
            'description' => 'Сделай и заверши пять своих ходов.',
            'target' => 5,
            'xp' => 100,
        ],
        'use_secret_passage' => [
            'title' => 'Использовать тайный проход',
            'description' => 'Воспользуйся тайным проходом на карте.',
            'target' => 1,
            'xp' => 100,
        ],
    ];
}

function daily_task_label(string $taskKey): string
{
    $pool = daily_task_pool();

    return $pool[$taskKey]['title'] ?? $taskKey;
}

function ensure_daily_tasks(int $userId): void
{
    $today = date('Y-m-d');

    $countStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM user_daily_tasks
         WHERE user_id=? AND task_date=?'
    );
    $countStmt->execute([$userId, $today]);

    if ((int) $countStmt->fetchColumn() > 0) {
        return;
    }

    $pool = daily_task_pool();
    $keys = array_keys($pool);

    $seed = crc32($userId . ':' . $today);
    mt_srand($seed);
    shuffle($keys);
    mt_srand();

    $selected = array_slice($keys, 0, 3);

    $insert = db()->prepare(
        'INSERT INTO user_daily_tasks
            (user_id, task_key, task_date, progress, target, xp_reward)
         VALUES
            (?, ?, ?, 0, ?, ?)'
    );

    foreach ($selected as $key) {
        $task = $pool[$key];

        $insert->execute([
            $userId,
            $key,
            $today,
            (int) $task['target'],
            (int) $task['xp'],
        ]);
    }
}

function get_daily_tasks(int $userId): array
{
    ensure_daily_tasks($userId);

    $today = date('Y-m-d');

    $s = db()->prepare(
        'SELECT *
         FROM user_daily_tasks
         WHERE user_id=? AND task_date=?
         ORDER BY id ASC'
    );
    $s->execute([$userId, $today]);

    $tasks = $s->fetchAll();
    $pool = daily_task_pool();

    foreach ($tasks as &$task) {
        $meta = $pool[$task['task_key']] ?? [
            'title' => $task['task_key'],
            'description' => '',
            'target' => (int) $task['target'],
            'xp' => (int) $task['xp_reward'],
        ];

        $task['title'] = $meta['title'];
        $task['description'] = $meta['description'];
        $task['is_completed'] = (int) $task['progress'] >= (int) $task['target'];
    }

    return $tasks;
}

function progress_daily_task(int $userId, string $taskKey, int $amount = 1): void
{
    if ($amount <= 0) {
        return;
    }

    ensure_daily_tasks($userId);

    $today = date('Y-m-d');

    $s = db()->prepare(
        'SELECT *
         FROM user_daily_tasks
         WHERE user_id=? AND task_date=? AND task_key=?'
    );
    $s->execute([$userId, $today, $taskKey]);

    $task = $s->fetch();

    if (!$task) {
        return;
    }

    if ((int) $task['is_claimed'] === 1) {
        return;
    }

    $newProgress = min((int) $task['target'], (int) $task['progress'] + $amount);
    $completedAtSql = $newProgress >= (int) $task['target'] && empty($task['completed_at'])
        ? ', completed_at = NOW()'
        : '';

    db()->prepare(
        "UPDATE user_daily_tasks
         SET progress = ?
             $completedAtSql
         WHERE id=?"
    )->execute([
        $newProgress,
        (int) $task['id'],
    ]);
}

function progress_daily_tasks_bulk(int $userId, array $taskKeys): void
{
    foreach ($taskKeys as $taskKey) {
        progress_daily_task($userId, (string) $taskKey, 1);
    }
}

function claim_daily_task(int $userId, int $taskId): array
{
    $s = db()->prepare(
        'SELECT *
         FROM user_daily_tasks
         WHERE id=? AND user_id=?'
    );
    $s->execute([$taskId, $userId]);

    $task = $s->fetch();

    if (!$task) {
        return ['error' => 'Задание не найдено'];
    }

    if ((int) $task['is_claimed'] === 1) {
        return ['error' => 'Награда уже получена'];
    }

    if ((int) $task['progress'] < (int) $task['target']) {
        return ['error' => 'Задание ещё не выполнено'];
    }

    $db = db();
    $db->beginTransaction();

    try {
        $db->prepare(
            'UPDATE user_daily_tasks
             SET is_claimed=1, claimed_at=NOW()
             WHERE id=? AND user_id=? AND is_claimed=0'
        )->execute([$taskId, $userId]);

        $db->prepare(
            'UPDATE users
             SET account_xp = account_xp + ?
             WHERE id=?'
        )->execute([
            (int) $task['xp_reward'],
            $userId,
        ]);

        $db->commit();

        return [
            'ok' => true,
            'xp' => (int) $task['xp_reward'],
        ];
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function get_user_account_xp(int $userId): int
{
    $s = db()->prepare(
        'SELECT account_xp
         FROM users
         WHERE id=?'
    );
    $s->execute([$userId]);

    return (int) $s->fetchColumn();
}