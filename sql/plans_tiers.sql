-- Four subscription tiers shown on the Subscribe page (one-time validity, per edition).
-- total_count = 1 so each is a single paid cycle whose length is the validity.
-- Idempotent: slug is unique, so re-running updates in place.

UPDATE plans SET is_active = 0 WHERE slug IN ('monthly', 'annual');

INSERT INTO plans
  (slug, name, description, amount_paise, currency, period, interval_count, total_count, is_active, sort_order)
VALUES
  ('basic',    'Basic',    'Full access to your edition for 30 days',  10000,  'INR', 'monthly', 1, 1, 1, 1),
  ('standard', 'Standard', 'Full access to your edition for 180 days', 54000,  'INR', 'monthly', 6, 1, 1, 2),
  ('premium',  'Premium',  'Full access to your edition for 365 days', 99900,  'INR', 'yearly',  1, 1, 1, 3),
  ('ultimate', 'Ultimate', 'Full access to your edition for 730 days', 180000, 'INR', 'yearly',  2, 1, 1, 4)
ON DUPLICATE KEY UPDATE
  name           = VALUES(name),
  description    = VALUES(description),
  amount_paise   = VALUES(amount_paise),
  currency       = VALUES(currency),
  period         = VALUES(period),
  interval_count = VALUES(interval_count),
  total_count    = VALUES(total_count),
  is_active      = VALUES(is_active),
  sort_order     = VALUES(sort_order);
