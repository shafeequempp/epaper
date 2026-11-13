-- Per-date archive purchases: a reader buys one old issue date of one (main) edition.
-- Grants access to that date's paper for the whole group (all its sub-editions/pages).
CREATE TABLE IF NOT EXISTS archive_purchases (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subscriber_id       INT UNSIGNED NOT NULL,
  edition_id          INT UNSIGNED NOT NULL,          -- the main edition of the group
  issue_date          DATE NOT NULL,
  amount_paise        INT UNSIGNED NOT NULL DEFAULT 2500,
  currency            CHAR(3) NOT NULL DEFAULT 'INR',
  razorpay_order_id   VARCHAR(40) NULL,
  razorpay_payment_id VARCHAR(40) NULL,
  status              VARCHAR(20) NOT NULL DEFAULT 'created',  -- created | paid
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order (razorpay_order_id),
  KEY idx_access (subscriber_id, edition_id, issue_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
