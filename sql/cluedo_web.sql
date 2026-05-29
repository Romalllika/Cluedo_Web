-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Май 29 2026 г., 05:12
-- Версия сервера: 8.0.39
-- Версия PHP: 8.2.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `cluedo_web`
--

-- --------------------------------------------------------

--
-- Структура таблицы `friend_requests`
--

CREATE TABLE `friend_requests` (
  `id` int NOT NULL,
  `sender_user_id` int NOT NULL,
  `receiver_user_id` int NOT NULL,
  `status` enum('pending','accepted','rejected','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `friend_requests`
--

INSERT INTO `friend_requests` (`id`, `sender_user_id`, `receiver_user_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'cancelled', '2026-05-29 00:05:32', '2026-05-29 00:05:33');

-- --------------------------------------------------------

--
-- Структура таблицы `games`
--

CREATE TABLE `games` (
  `id` int NOT NULL,
  `title` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `owner_id` int NOT NULL,
  `status` enum('waiting','active','finished') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'waiting',
  `max_players` tinyint NOT NULL DEFAULT '6',
  `map_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'classic_mansion',
  `current_turn_player_id` int DEFAULT NULL,
  `phase` enum('join','roll','move','suggest','disprove','accuse','ended') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'join',
  `phase_started_at` datetime DEFAULT NULL,
  `dice_total` tinyint DEFAULT '0',
  `solution_suspect` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `solution_suspect_card_id` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `solution_weapon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `solution_weapon_card_id` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `solution_room` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `solution_room_card_id` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `winner_user_id` int DEFAULT NULL,
  `stats_applied` tinyint(1) NOT NULL DEFAULT '0',
  `pending_suggester_id` int DEFAULT NULL,
  `pending_disprover_id` int DEFAULT NULL,
  `pending_suspect` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pending_suspect_card_id` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pending_weapon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pending_weapon_card_id` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pending_room` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pending_room_card_id` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shown_card_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shown_card_id` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shown_by_user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `game_character_positions`
--

CREATE TABLE `game_character_positions` (
  `id` int NOT NULL,
  `game_id` int NOT NULL,
  `character_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pos_x` int NOT NULL,
  `pos_y` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `game_invites`
--

CREATE TABLE `game_invites` (
  `id` int NOT NULL,
  `game_id` int NOT NULL,
  `sender_user_id` int NOT NULL,
  `receiver_user_id` int NOT NULL,
  `status` enum('pending','accepted','rejected','cancelled','expired') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `game_logs`
--

CREATE TABLE `game_logs` (
  `id` int NOT NULL,
  `game_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `game_players`
--

CREATE TABLE `game_players` (
  `id` int NOT NULL,
  `game_id` int NOT NULL,
  `user_id` int NOT NULL,
  `character_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `seat_no` tinyint NOT NULL,
  `turn_order` tinyint NOT NULL,
  `pos_x` tinyint NOT NULL DEFAULT '0',
  `pos_y` tinyint NOT NULL DEFAULT '0',
  `is_eliminated` tinyint(1) NOT NULL DEFAULT '0',
  `afk_misses` int NOT NULL DEFAULT '0',
  `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `game_reports`
--

CREATE TABLE `game_reports` (
  `id` int NOT NULL,
  `game_id` int NOT NULL,
  `reporter_user_id` int NOT NULL,
  `reported_user_id` int NOT NULL,
  `reason` enum('afk','stalling','abuse','cheating','bug_abuse','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'other',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `status` enum('open','reviewing','confirmed','rejected','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'open',
  `reviewer_user_id` int DEFAULT NULL,
  `review_comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `snapshot_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `player_cards`
--

CREATE TABLE `player_cards` (
  `id` int NOT NULL,
  `game_id` int NOT NULL,
  `user_id` int NOT NULL,
  `card_type` enum('suspect','weapon','room') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `card_id` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `card_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('player','moderator','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'player',
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `wins` int NOT NULL DEFAULT '0',
  `losses` int NOT NULL DEFAULT '0',
  `surrenders` int NOT NULL DEFAULT '0',
  `wrong_accusations` int NOT NULL DEFAULT '0',
  `warnings_count` int NOT NULL DEFAULT '0',
  `create_blocked_until` datetime DEFAULT NULL,
  `game_banned_until` datetime DEFAULT NULL,
  `games_played` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at` datetime DEFAULT NULL,
  `create_ban_permanent` tinyint(1) NOT NULL DEFAULT '0',
  `game_ban_permanent` tinyint(1) NOT NULL DEFAULT '0',
  `account_xp` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `role`, `password_hash`, `wins`, `losses`, `surrenders`, `wrong_accusations`, `warnings_count`, `create_blocked_until`, `game_banned_until`, `games_played`, `created_at`, `last_seen_at`, `create_ban_permanent`, `game_ban_permanent`, `account_xp`) VALUES
(1, 'Romalllika', 'admin', '$2y$10$zFldMeNfUXKYrPzMURl5cOnEdeviqtZoaPFYCvlz.IVxzfvU9EASa', 0, 0, 0, 0, 0, NULL, NULL, 0, '2026-04-25 13:26:17', '2026-05-29 08:12:09', 0, 0, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `user_daily_tasks`
--

CREATE TABLE `user_daily_tasks` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `task_key` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `task_date` date NOT NULL,
  `progress` int NOT NULL DEFAULT '0',
  `target` int NOT NULL DEFAULT '1',
  `xp_reward` int NOT NULL DEFAULT '50',
  `is_claimed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `claimed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `user_daily_tasks`
--

INSERT INTO `user_daily_tasks` (`id`, `user_id`, `task_key`, `task_date`, `progress`, `target`, `xp_reward`, `is_claimed`, `created_at`, `completed_at`, `claimed_at`) VALUES
(1, 1, 'make_1_accusation', '2026-05-29', 0, 1, 90, 0, '2026-05-29 04:48:16', NULL, NULL),
(2, 1, 'make_3_suggestions', '2026-05-29', 0, 3, 130, 0, '2026-05-29 04:48:16', NULL, NULL),
(3, 1, 'make_1_suggestion', '2026-05-29', 0, 1, 60, 0, '2026-05-29 04:48:16', NULL, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `user_moderation_actions`
--

CREATE TABLE `user_moderation_actions` (
  `id` int NOT NULL,
  `report_id` int DEFAULT NULL,
  `target_user_id` int NOT NULL,
  `moderator_user_id` int NOT NULL,
  `action_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'none',
  `duration_value` int DEFAULT NULL,
  `duration_unit` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reason` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_friend_pair_status` (`sender_user_id`,`receiver_user_id`,`status`),
  ADD KEY `idx_friend_sender` (`sender_user_id`),
  ADD KEY `idx_friend_receiver` (`receiver_user_id`),
  ADD KEY `idx_friend_status` (`status`);

--
-- Индексы таблицы `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Индексы таблицы `game_character_positions`
--
ALTER TABLE `game_character_positions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_game_character` (`game_id`,`character_name`);

--
-- Индексы таблицы `game_invites`
--
ALTER TABLE `game_invites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_pending_game_invite` (`game_id`,`sender_user_id`,`receiver_user_id`,`status`),
  ADD KEY `idx_invites_game` (`game_id`),
  ADD KEY `idx_invites_sender` (`sender_user_id`),
  ADD KEY `idx_invites_receiver` (`receiver_user_id`),
  ADD KEY `idx_invites_status` (`status`);

--
-- Индексы таблицы `game_logs`
--
ALTER TABLE `game_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `game_players`
--
ALTER TABLE `game_players`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_game_user` (`game_id`,`user_id`),
  ADD UNIQUE KEY `uniq_game_seat` (`game_id`,`seat_no`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `game_reports`
--
ALTER TABLE `game_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_open_report_per_match_pair` (`game_id`,`reporter_user_id`,`reported_user_id`,`status`),
  ADD KEY `idx_reports_game` (`game_id`),
  ADD KEY `idx_reports_reporter` (`reporter_user_id`),
  ADD KEY `idx_reports_reported` (`reported_user_id`),
  ADD KEY `idx_reports_status` (`status`);

--
-- Индексы таблицы `player_cards`
--
ALTER TABLE `player_cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_player_cards_card_id` (`card_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Индексы таблицы `user_daily_tasks`
--
ALTER TABLE `user_daily_tasks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_task_day` (`user_id`,`task_key`,`task_date`),
  ADD KEY `idx_user_daily_tasks_user_day` (`user_id`,`task_date`),
  ADD KEY `idx_user_daily_tasks_claimed` (`is_claimed`);

--
-- Индексы таблицы `user_moderation_actions`
--
ALTER TABLE `user_moderation_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_moderation_target` (`target_user_id`),
  ADD KEY `idx_moderation_moderator` (`moderator_user_id`),
  ADD KEY `idx_moderation_report` (`report_id`),
  ADD KEY `idx_moderation_action_type` (`action_type`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `friend_requests`
--
ALTER TABLE `friend_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `games`
--
ALTER TABLE `games`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT для таблицы `game_character_positions`
--
ALTER TABLE `game_character_positions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21375;

--
-- AUTO_INCREMENT для таблицы `game_invites`
--
ALTER TABLE `game_invites`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `game_logs`
--
ALTER TABLE `game_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `game_players`
--
ALTER TABLE `game_players`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT для таблицы `game_reports`
--
ALTER TABLE `game_reports`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `player_cards`
--
ALTER TABLE `player_cards`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `user_daily_tasks`
--
ALTER TABLE `user_daily_tasks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `user_moderation_actions`
--
ALTER TABLE `user_moderation_actions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `games`
--
ALTER TABLE `games`
  ADD CONSTRAINT `games_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`);

--
-- Ограничения внешнего ключа таблицы `game_character_positions`
--
ALTER TABLE `game_character_positions`
  ADD CONSTRAINT `fk_character_positions_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `game_logs`
--
ALTER TABLE `game_logs`
  ADD CONSTRAINT `game_logs_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `game_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ограничения внешнего ключа таблицы `game_players`
--
ALTER TABLE `game_players`
  ADD CONSTRAINT `game_players_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `game_players_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ограничения внешнего ключа таблицы `player_cards`
--
ALTER TABLE `player_cards`
  ADD CONSTRAINT `player_cards_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `player_cards_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
