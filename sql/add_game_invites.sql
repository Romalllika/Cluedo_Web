CREATE TABLE IF NOT EXISTS `game_invites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int NOT NULL,
  `sender_user_id` int NOT NULL,
  `receiver_user_id` int NOT NULL,
  `status` enum('pending','accepted','rejected','cancelled','expired') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `message` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invites_game` (`game_id`),
  KEY `idx_invites_sender` (`sender_user_id`),
  KEY `idx_invites_receiver` (`receiver_user_id`),
  KEY `idx_invites_status` (`status`),
  UNIQUE KEY `uniq_pending_game_invite` (`game_id`, `sender_user_id`, `receiver_user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;