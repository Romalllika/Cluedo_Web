<?php

require 'includes/config.php';
require 'includes/profile.php';
require 'includes/reports.php';

require_auth();
update_current_user_presence();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

$uid = (int) current_user_id();
$reportedUserId = (int) ($_POST['reported_user_id'] ?? 0);
$gameId = (int) ($_POST['game_id'] ?? 0);
$reason = trim((string) ($_POST['reason'] ?? 'other'));
$comment = trim((string) ($_POST['comment'] ?? ''));

if ($reportedUserId <= 0) {
    $_SESSION['flash_error'] = 'Игрок не выбран';
    header('Location: profile.php');
    exit;
}

if ($gameId <= 0) {
    $_SESSION['flash_error'] = 'Для жалобы из профиля нужно выбрать общий матч';
    header('Location: profile.php?id=' . $reportedUserId);
    exit;
}

$result = create_game_report($gameId, $uid, $reportedUserId, $reason, $comment);

if (!empty($result['error'])) {
    $_SESSION['flash_error'] = $result['error'];
} else {
    $_SESSION['flash_success'] = 'Жалоба отправлена. Модератор сможет проверить матч по логам.';
}

header('Location: profile.php?id=' . $reportedUserId);
exit;