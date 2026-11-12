-- Mathrubhumi ePaper schema (MySQL 5.7+/8, MariaDB 10.3+)
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS epaper CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE epaper;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','editor','uploader') NOT NULL DEFAULT 'uploader',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A location edition: Ernakulam, Kothamangalam-Muvattupuzha, ... (rows come from editions_seed.sql)
CREATE TABLE editions (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(80)  NOT NULL UNIQUE,     -- used in reader URLs (?e=...)
  code            VARCHAR(10)  NULL,                -- code in InDesign file names: 9pg2e-... -> E
  name            VARCHAR(120) NOT NULL,
  name_ml         VARCHAR(120) NULL,
  full_name       VARCHAR(200) NULL,                -- e.g. Muvattupuzha, Kothamangalam, Koothattukulam, Piravom
  group_name      VARCHAR(120) NULL,                -- district group shown in the edition menu
  group_sort      INT NOT NULL DEFAULT 0,
  legacy_id       INT NULL,                         -- EditionID in the old edition list
  inherit_from_id INT UNSIGNED NULL,                -- shows this edition's pages where it has none of its own
  is_main         TINYINT(1) NOT NULL DEFAULT 0,
  is_supplement   TINYINT(1) NOT NULL DEFAULT 0,
  sort_order      INT NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One edition on one date
CREATE TABLE issues (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  edition_id   INT UNSIGNED NOT NULL,
  issue_date   DATE NOT NULL,
  status       ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_issue (edition_id, issue_date),
  KEY idx_status_date (status, issue_date),
  CONSTRAINT fk_issue_edition FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  issue_id    INT UNSIGNED NOT NULL,
  page_number INT NOT NULL,
  page_label  VARCHAR(20)  NOT NULL,
  section     VARCHAR(120) NULL,
  width_px    INT NOT NULL,
  height_px   INT NOT NULL,
  img_full    VARCHAR(255) NOT NULL,   -- paths relative to storage dir
  img_medium  VARCHAR(255) NOT NULL,
  img_thumb   VARCHAR(255) NOT NULL,
  source_file VARCHAR(255) NULL,       -- original .indd name
  version     INT NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_page (issue_id, page_number),
  CONSTRAINT fk_page_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE articles (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_id    INT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  headline   VARCHAR(500) NOT NULL DEFAULT '',
  body       MEDIUMTEXT NOT NULL,
  captions   TEXT NULL,               -- JSON array
  method     VARCHAR(20) NULL,        -- article | group | story (how it was detected)
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_page (page_id),
  FULLTEXT KEY ft_article (headline, body),
  CONSTRAINT fk_article_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Where the article sits on the page (fractions 0-1) and its cropped image
CREATE TABLE article_regions (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  article_id INT UNSIGNED NOT NULL,
  seq        INT NOT NULL DEFAULT 1,
  x DECIMAL(7,5) NOT NULL, y DECIMAL(7,5) NOT NULL,
  w DECIMAL(7,5) NOT NULL, h DECIMAL(7,5) NOT NULL,
  img        VARCHAR(255) NOT NULL,
  img_width  INT NOT NULL,
  img_height INT NOT NULL,
  KEY idx_article (article_id),
  CONSTRAINT fk_region_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Next: import editions_seed.sql for the edition list.
