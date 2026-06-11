<?php

require 'includes/config.php';
require 'includes/profile.php';
require 'includes/friends.php';

require_auth();
csrf_check();

update_current_user_presence();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

$uid = (int) current_user_id();
$action = trim((string) ($_POST['action'] ?? ''));
$profileUserId = (int) ($_POST['profile_user_id'] ?? 0);
$requestId = (int) ($_POST['request_id'] ?? 0);

$result = ['error' => 'Неизвестное действие'];

if ($action === 'send') {
    $result = send_friend_request($uid, $profileUserId);
}

if ($action === 'accept') {
    $result = accept_friend_request($requestId, $uid);
}

if ($action === 'reject') {
    $result = reject_friend_request($requestId, $uid);
}

if ($action === 'cancel') {
    $result = cancel_friend_request($requestId, $uid);
}

if ($action === 'remove') {
    $result = remove_friend($uid, $profileUserId);
}

if (!empty($result['error'])) {
    $_SESSION['flash_error'] = $result['error'];
} else {
    $_SESSION['flash_success'] = 'Действие выполнено';
}

$redirectId = $profileUserId > 0 ? $profileUserId : $uid;

header('Location: profile.php?id=' . $redirectId);
exit;