-- Reader subscriptions, plans and payments (Razorpay).
-- Run after schema.sql. Safe to re-run: uses CREATE TABLE IF NOT EXISTS and
-- INSERT IGNORE for the default plans.
SET NAMES utf8mb4;
USE epaper;

-- Reader accounts. Separate from staff `users`: readers never get dashboard access.
CREATE TABLE IF NOT EXISTS subscribers (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email             VARCHAR(190) NOT NULL UNIQUE,
  password_hash     VARCHAR(255) NOT NULL,
  name              VARCHAR(120) NOT NULL DEFAULT '',
  phone             VARCHAR(20)  NULL,
  email_verified_at DATETIME     NULL,
  status            ENUM('active','blocked') NOT NULL DEFAULT 'active',
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Short-lived tokens for e-mail verification and password reset.
CREATE TABLE IF NOT EXISTS subscriber_tokens (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subscriber_id INT UNSIGNED NOT NULL,
  type          ENUM('verify','reset') NOT NULL,
  token_hash    CHAR(64) NOT NULL,            -- sha256 of the token sent by e-mail
  expires_at    DATETIME NOT NULL,
  used_at       DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lookup (token_hash, type),
  KEY idx_subscriber (subscriber_id),
  CONSTRAINT fk_token_subscriber FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A subscription plan. Admin-editable; mirrored in Razorpay as a Plan so it can bill.
CREATE TABLE IF NOT EXISTS plans (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug              VARCHAR(60)  NOT NULL UNIQUE,
  name              VARCHAR(120) NOT NULL,
  description       VARCHAR(255) NOT NULL DEFAULT '',
  amount_paise      INT UNSIGNED NOT NULL,              -- price per cycle, in paise (₹199 = 19900)
  currency          CHAR(3) NOT NULL DEFAULT 'INR',
  period            ENUM('daily','weekly','monthly','yearly') NOT NULL DEFAULT 'monthly',
  interval_count    INT UNSIGNED NOT NULL DEFAULT 1,    -- every N periods
  total_count       INT UNSIGNED NOT NULL DEFAULT 120,  -- billing cycles before it completes (auto-renews until then)
  razorpay_plan_id  VARCHAR(40) NULL,                   -- filled when provisioned in Razorpay
  is_active         TINYINT(1) NOT NULL DEFAULT 1,
  sort_order        INT NOT NULL DEFAULT 0,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A subscriber's subscription to a plan. One row per Razorpay subscription.
CREATE TABLE IF NOT EXISTS subscriptions (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subscriber_id            INT UNSIGNED NOT NULL,
  plan_id                  INT UNSIGNED NOT NULL,
  razorpay_subscription_id VARCHAR(40) NULL UNIQUE,
  -- Mirrors Razorpay subscription state: created, authenticated, active, pending,
  -- halted, cancelled, completed, expired, paused. Plus 'manual' for admin-granted.
  status                   VARCHAR(20) NOT NULL DEFAULT 'created',
  current_start            DATETIME NULL,
  current_end              DATETIME NULL,     -- access is granted while now() < current_end
  cancel_at_period_end     TINYINT(1) NOT NULL DEFAULT 0,
  source                   ENUM('razorpay','manual') NOT NULL DEFAULT 'razorpay',
  note                     VARCHAR(255) NOT NULL DEFAULT '',
  created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_subscriber (subscriber_id),
  KEY idx_access (subscriber_id, status, current_end),
  CONSTRAINT fk_sub_subscriber FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
  CONSTRAINT fk_sub_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every payment / renewal invoice, for the account history.
CREATE TABLE IF NOT EXISTS payments (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subscription_id      INT UNSIGNED NULL,
  subscriber_id        INT UNSIGNED NOT NULL,
  razorpay_payment_id  VARCHAR(40) NULL UNIQUE,
  razorpay_invoice_id  VARCHAR(40) NULL,
  amount_paise         INT UNSIGNED NOT NULL DEFAULT 0,
  currency             CHAR(3) NOT NULL DEFAULT 'INR',
  status               VARCHAR(20) NOT NULL DEFAULT 'created',  -- captured, failed, refunded...
  method               VARCHAR(30) NULL,
  created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_subscriber (subscriber_id),
  KEY idx_subscription (subscription_id),
  CONSTRAINT fk_pay_subscriber FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotency log so a webhook delivered twice is processed once.
CREATE TABLE IF NOT EXISTS webhook_events (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id      VARCHAR(80) NOT NULL UNIQUE,   -- Razorpay x-razorpay-event-id header
  event         VARCHAR(60) NOT NULL,
  payload       LONGTEXT NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default plans (edit prices/durations on the admin Plans page).
-- amount_paise: ₹149 = 14900, ₹1499 = 149900.
INSERT IGNORE INTO plans (slug, name, description, amount_paise, period, interval_count, total_count, sort_order) VALUES
  ('monthly', 'Monthly',  'Full archive access, billed every month', 14900,  'monthly', 1, 120, 1),
  ('annual',  'Annual',   'Full archive access, billed once a year', 149900, 'yearly',  1, 20,  2);
