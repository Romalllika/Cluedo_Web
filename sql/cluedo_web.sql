-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Май 21 2026 г., 12:53
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
-- Структура таблицы `games`
--

CREATE TABLE `games` (
  `id` int NOT NULL,
  `title` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `owner_id` int NOT NULL,
  `status` enum('waiting','active','finished') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'waiting',
  `max_players` tinyint NOT NULL DEFAULT '6',
  `map_id` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'classic_mansion',
  `current_turn_player_id` int DEFAULT NULL,
  `phase` enum('join','roll','move','suggest','disprove','accuse','ended') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'join',
  `phase_started_at` datetime DEFAULT NULL,
  `dice_total` tinyint DEFAULT '0',
  `solution_suspect` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `solution_suspect_card_id` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `solution_weapon` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `solution_weapon_card_id` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `solution_room` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `solution_room_card_id` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `winner_user_id` int DEFAULT NULL,
  `stats_applied` tinyint(1) NOT NULL DEFAULT '0',
  `pending_suggester_id` int DEFAULT NULL,
  `pending_disprover_id` int DEFAULT NULL,
  `pending_suspect` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pending_suspect_card_id` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pending_weapon` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pending_weapon_card_id` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pending_room` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pending_room_card_id` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shown_card_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shown_card_id` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `shown_by_user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `games`
--

INSERT INTO `games` (`id`, `title`, `owner_id`, `status`, `max_players`, `map_id`, `current_turn_player_id`, `phase`, `phase_started_at`, `dice_total`, `solution_suspect`, `solution_suspect_card_id`, `solution_weapon`, `solution_weapon_card_id`, `solution_room`, `solution_room_card_id`, `winner_user_id`, `stats_applied`, `pending_suggester_id`, `pending_disprover_id`, `pending_suspect`, `pending_suspect_card_id`, `pending_weapon`, `pending_weapon_card_id`, `pending_room`, `pending_room_card_id`, `shown_card_name`, `shown_card_id`, `shown_by_user_id`, `created_at`, `updated_at`) VALUES
(32, 'Матч 13:39', 1, 'waiting', 6, 'classic_mansion', NULL, 'join', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-20 13:39:21', '2026-05-20 13:39:21');

-- --------------------------------------------------------

--
-- Структура таблицы `game_character_positions`
--

CREATE TABLE `game_character_positions` (
  `id` int NOT NULL,
  `game_id` int NOT NULL,
  `character_name` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `pos_x` int NOT NULL,
  `pos_y` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `game_character_positions`
--

INSERT INTO `game_character_positions` (`id`, `game_id`, `character_name`, `pos_x`, `pos_y`, `created_at`) VALUES
(15211, 32, 'Алекс Громов', 8, 9, '2026-05-20 13:39:21'),
(15212, 32, 'Мария Скарлет', 7, 9, '2026-05-20 13:39:21'),
(15213, 32, 'Профессор Фиолетов', 9, 9, '2026-05-20 13:39:21'),
(15214, 32, 'Виктор Олив', 8, 8, '2026-05-20 13:39:21'),
(15215, 32, 'Елена Белая', 7, 8, '2026-05-20 13:39:21'),
(15216, 32, 'София Синяя', 9, 8, '2026-05-20 13:39:21');

-- --------------------------------------------------------

--
-- Структура таблицы `game_logs`
--

CREATE TABLE `game_logs` (
  `id` int NOT NULL,
  `game_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
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
  `character_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `seat_no` tinyint NOT NULL,
  `turn_order` tinyint NOT NULL,
  `pos_x` tinyint NOT NULL DEFAULT '0',
  `pos_y` tinyint NOT NULL DEFAULT '0',
  `is_eliminated` tinyint(1) NOT NULL DEFAULT '0',
  `afk_misses` int NOT NULL DEFAULT '0',
  `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `game_players`
--

INSERT INTO `game_players` (`id`, `game_id`, `user_id`, `character_name`, `seat_no`, `turn_order`, `pos_x`, `pos_y`, `is_eliminated`, `afk_misses`, `joined_at`) VALUES
(30, 32, 1, 'Алекс Громов', 0, 1, 8, 9, 0, 0, '2026-05-20 13:39:21');

-- --------------------------------------------------------

--
-- Структура таблицы `player_cards`
--

CREATE TABLE `player_cards` (
  `id` int NOT NULL,
  `game_id` int NOT NULL,
  `user_id` int NOT NULL,
  `card_type` enum('suspect','weapon','room') COLLATE utf8mb4_general_ci NOT NULL,
  `card_id` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `card_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(40) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('player','moderator','admin') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'player',
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `wins` int NOT NULL DEFAULT '0',
  `losses` int NOT NULL DEFAULT '0',
  `games_played` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `role`, `password_hash`, `wins`, `losses`, `games_played`, `created_at`) VALUES
(1, 'Romalllika', 'player', '$2y$10$zFldMeNfUXKYrPzMURl5cOnEdeviqtZoaPFYCvlz.IVxzfvU9EASa', 0, 0, 0, '2026-04-25 13:26:17');

--
-- Индексы сохранённых таблиц
--

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
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `games`
--
ALTER TABLE `games`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT для таблицы `game_character_positions`
--
ALTER TABLE `game_character_positions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15223;

--
-- AUTO_INCREMENT для таблицы `game_logs`
--
ALTER TABLE `game_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `game_players`
--
ALTER TABLE `game_players`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT для таблицы `player_cards`
--
ALTER TABLE `player_cards`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
