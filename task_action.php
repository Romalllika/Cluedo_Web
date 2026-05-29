<?php

require 'includes/config.php';
require 'includes/profile.php';
require 'includes/progression.php';

require_auth();
update_current_user_presence();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: lobby.php');
    exit;
}

$uid = (int) current_user_id();
$taskId = (int) ($_POST['task_id'] ?? 0);

$result = claim_daily_task($uid, $taskId);

if (!empty($result['error'])) {
    $_SESSION['flash_error'] = $result['error'];
} else {
    $_SESSION['flash_success'] = 'Награда получена: +' . (int) $result['xp'] . ' XP';
}

header('Location: lobby.php');
exit;