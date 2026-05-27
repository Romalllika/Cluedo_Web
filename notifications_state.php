<?php

require 'includes/config.php';
require 'includes/profile.php';
require 'includes/friends.php';
require 'includes/invites.php';

require_auth();
update_current_user_presence();

$uid = (int) current_user_id();

$friendRequests = get_incoming_friend_requests($uid);
$gameInvites = get_incoming_game_invites($uid);

json_out([
    'friend_requests' => array_map(static function (array $r): array {
        return [
            'id' => (int) $r['id'],
            'sender_user_id' => (int) $r['sender_user_id'],
            'username' => $r['username'],
            'created_at' => $r['created_at'],
        ];
    }, $friendRequests),

    'game_invites' => array_map(static function (array $i): array {
        return [
            'id' => (int) $i['id'],
            'game_id' => (int) $i['game_id'],
            'game_title' => $i['game_title'],
            'sender_username' => $i['sender_username'],
            'map_id' => $i['map_id'],
            'players_count' => (int) $i['players_count'],
            'max_players' => (int) $i['max_players'],
            'message' => $i['message'],
        ];
    }, $gameInvites),
]);