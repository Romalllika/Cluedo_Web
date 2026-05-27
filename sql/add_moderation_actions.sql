ALTER TABLE `users`
  ADD COLUMN `warnings_count` int NOT NULL DEFAULT 0 AFTER `wrong_accusations`,
  ADD COLUMN `create_blocked_until` datetime DEFAULT NULL AFTER `warnings_count`,
  ADD COLUMN `game_banned_until` datetime DEFAULT NULL AFTER `create_blocked_until`;

CREATE TABLE IF NOT EXISTS `user_moderation_actions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `report_id` int DEFAULT NULL,
  `target_user_id` int NOT NULL,
  `moderator_user_id` int NOT NULL,
  `action_type` enum(
    'none',
    'warning',
    'block_create_games_24h',
    'block_games_24h'
  ) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'none',
  `reason` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_moderation_target` (`target_user_id`),
  KEY `idx_moderation_moderator` (`moderator_user_id`),
  KEY `idx_moderation_report` (`report_id`),
  KEY `idx_moderation_action_type` (`action_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;