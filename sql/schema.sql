CREATE DATABASE IF NOT EXISTS cluedo_web CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cluedo_web;

DROP TABLE IF EXISTS game_logs, player_cards, game_players, games, users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(40) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  wins INT NOT NULL DEFAULT 0,
  losses INT NOT NULL DEFAULT 0,
  games_played INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE games (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(80) NOT NULL,
  owner_id INT NOT NULL,
  status ENUM('waiting','active','finished') NOT NULL DEFAULT 'waiting',
  max_players TINYINT NOT NULL DEFAULT 6,
  current_turn_player_id INT NULL,
  phase ENUM('join','roll','move','suggest','accuse','ended') NOT NULL DEFAULT 'join',
  dice_total TINYINT DEFAULT 0,
  solution_suspect VARCHAR(50) NULL,
  solution_weapon VARCHAR(50) NULL,
  solution_room VARCHAR(50) NULL,
  winner_user_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY(owner_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE game_players (
  id INT AUTO_INCREMENT PRIMARY KEY,
  game_id INT NOT NULL,
  user_id INT NOT NULL,
  character_name VARCHAR(50) NOT NULL,
  seat_no TINYINT NOT NULL,
  turn_order TINYINT NOT NULL,
  pos_x TINYINT NOT NULL DEFAULT 0,
  pos_y TINYINT NOT NULL DEFAULT 0,
  is_eliminated TINYINT(1) NOT NULL DEFAULT 0,
  joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_game_user(game_id,user_id),
  UNIQUE KEY uniq_game_seat(game_id,seat_no),
  FOREIGN KEY(game_id) REFERENCES games(id) ON DELETE CASCADE,
  FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE player_cards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  game_id INT NOT NULL,
  user_id INT NOT NULL,
  card_type ENUM('suspect','weapon','room') NOT NULL,
  card_name VARCHAR(50) NOT NULL,
  FOREIGN KEY(game_id) REFERENCES games(id) ON DELETE CASCADE,
  FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE game_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  game_id INT NOT NULL,
  user_id INT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(game_id) REFERENCES games(id) ON DELETE CASCADE,
  FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB;
