-- Login history + a 7-day free trial that starts on a reader's first login.
CREATE TABLE IF NOT EXISTS login_logs (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subscriber_id INT UNSIGNED NOT NULL,
  ip            VARCHAR(45)  NULL,
  user_agent    VARCHAR(255) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sub (subscriber_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- trial_started_at: set on the reader's first login; free access for 7 days after it.
-- (Added conditionally by the apply script if the column does not already exist.)
