CREATE TABLE IF NOT EXISTS `game_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int NOT NULL,
  `reporter_user_id` int NOT NULL,
  `reported_user_id` int NOT NULL,
  `reason` enum(
    'afk',
    'stalling',
    'abuse',
    'cheating',
    'bug_abuse',
    'other'
  ) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'other',
  `comment` text COLLATE utf8mb4_general_ci,
  `status` enum(
    'open',
    'reviewing',
    'confirmed',
    'rejected',
    'closed'
  ) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'open',
  `reviewer_user_id` int DEFAULT NULL,
  `review_comment` text COLLATE utf8mb4_general_ci,
  `snapshot_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reports_game` (`game_id`),
  KEY `idx_reports_reporter` (`reporter_user_id`),
  KEY `idx_reports_reported` (`reported_user_id`),
  KEY `idx_reports_status` (`status`),
  UNIQUE KEY `uniq_open_report_per_match_pair` (
    `game_id`,
    `reporter_user_id`,
    `reported_user_id`,
    `status`
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;