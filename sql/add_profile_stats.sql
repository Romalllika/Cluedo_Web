ALTER TABLE `users`
  ADD COLUMN `surrenders` int NOT NULL DEFAULT 0 AFTER `losses`,
  ADD COLUMN `wrong_accusations` int NOT NULL DEFAULT 0 AFTER `surrenders`;