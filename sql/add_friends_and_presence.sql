ALTER TABLE `users`
  ADD COLUMN `last_seen_at` datetime DEFAULT NULL AFTER `created_at`;

CREATE TABLE IF NOT EXISTS `friend_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sender_user_id` int NOT NULL,
  `receiver_user_id` int NOT NULL,
  `status` enum('pending','accepted','rejected','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_friend_sender` (`sender_user_id`),
  KEY `idx_friend_receiver` (`receiver_user_id`),
  KEY `idx_friend_status` (`status`),
  UNIQUE KEY `uniq_friend_pair_status` (`sender_user_id`, `receiver_user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;