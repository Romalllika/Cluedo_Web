<?php

require 'includes/config.php';
require 'includes/profile.php';
require 'includes/friends.php';
require 'includes/invites.php';

require_auth();

update_current_user_presence();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: lobby.php');
    exit;
}

$uid = (int) current_user_id();
$action = trim((string) ($_POST['action'] ?? ''));
$inviteId = (int) ($_POST['invite_id'] ?? 0);
$gameId = (int) ($_POST['game_id'] ?? 0);
$receiverId = (int) ($_POST['receiver_user_id'] ?? 0);
$profileUserId = (int) ($_POST['profile_user_id'] ?? 0);
$message = trim((string) ($_POST['message'] ?? ''));

$result = ['error' => 'Неизвестное действие'];
$redirect = 'lobby.php';

if ($action === 'send') {
    $result = create_game_invite($gameId, $uid, $receiverId, $message);
    $redirect = $profileUserId > 0 ? 'profile.php?id=' . $profileUserId : 'profile.php?id=' . $receiverId;
}

if ($action === 'accept') {
    $result = accept_game_invite($inviteId, $uid);

    if (!empty($result['ok']) && !empty($result['game_id'])) {
        header('Location: join_game.php?game_id=' . (int) $result['game_id']);
        exit;
    }

    $redirect = 'lobby.php';
}

if ($action === 'reject') {
    $result = reject_game_invite($inviteId, $uid);
    $redirect = 'lobby.php';
}

if ($action === 'cancel') {
    $result = cancel_game_invite($inviteId, $uid);
    $redirect = $profileUserId > 0 ? 'profile.php?id=' . $profileUserId : 'profile.php';
}

if (!empty($result['error'])) {
    $_SESSION['flash_error'] = $result['error'];
} else {
    $_SESSION['flash_success'] = 'Действие выполнено';
}

header('Location: ' . $redirect);
exit;