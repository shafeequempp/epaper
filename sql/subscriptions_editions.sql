-- Per-edition subscriptions: a subscription covers one MAIN edition and its whole
-- group (sub-editions + supplements). NULL edition_id = all editions (complimentary
-- / legacy grants). Run after subscriptions.sql. Safe to re-run.
SET NAMES utf8mb4;
USE epaper;

-- Add subscriptions.edition_id only if it isn't there yet (idempotent).
SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'subscriptions'
    AND COLUMN_NAME = 'edition_id'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE subscriptions ADD COLUMN edition_id INT UNSIGNED NULL AFTER plan_id, ADD KEY idx_sub_edition (edition_id)',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
