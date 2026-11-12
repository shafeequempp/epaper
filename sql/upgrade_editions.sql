-- Run once on a database created with the earlier schema.sql, then import editions_seed.sql.
SET NAMES utf8mb4;
ALTER TABLE editions
  MODIFY slug VARCHAR(80) NOT NULL,
  ADD COLUMN code            VARCHAR(10)  NULL AFTER slug,
  ADD COLUMN full_name       VARCHAR(200) NULL AFTER name_ml,
  ADD COLUMN group_name      VARCHAR(120) NULL AFTER full_name,
  ADD COLUMN group_sort      INT NOT NULL DEFAULT 0 AFTER group_name,
  ADD COLUMN legacy_id       INT NULL AFTER group_sort,
  ADD COLUMN inherit_from_id INT UNSIGNED NULL AFTER legacy_id,
  ADD COLUMN is_main         TINYINT(1) NOT NULL DEFAULT 0 AFTER inherit_from_id,
  ADD COLUMN is_supplement   TINYINT(1) NOT NULL DEFAULT 0 AFTER is_main,
  ADD KEY idx_code (code);
