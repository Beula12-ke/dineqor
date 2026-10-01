-- Dineqor (1).sql upgrade: apply once AFTER importing that dump into the selected database.
-- This preserves the existing loyalty_accounts, loyalty_transactions, subscription_plans and subscriptions data.

ALTER TABLE subscription_plans
  ADD COLUMN restaurant_limit SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER features_json;

ALTER TABLE loyalty_accounts
  MODIFY COLUMN points_balance BIGINT(20) NOT NULL DEFAULT 0;

ALTER TABLE subscriptions
  ADD COLUMN notes VARCHAR(500) NULL AFTER cancelled_at,
  ADD COLUMN assigned_by BIGINT(20) UNSIGNED NULL AFTER notes,
  ADD KEY ix_subscription_assigned_by (assigned_by),
  ADD CONSTRAINT fk_subscription_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE loyalty_transactions
  ADD COLUMN restaurant_id BIGINT(20) UNSIGNED NULL AFTER account_id,
  ADD COLUMN customer_user_id BIGINT(20) UNSIGNED NULL AFTER restaurant_id,
  MODIFY COLUMN points BIGINT(20) NOT NULL;

UPDATE loyalty_transactions lt
JOIN loyalty_accounts la ON la.id=lt.account_id
SET lt.restaurant_id=la.restaurant_id,lt.customer_user_id=la.user_id;

ALTER TABLE loyalty_transactions
  MODIFY COLUMN restaurant_id BIGINT(20) UNSIGNED NOT NULL,
  MODIFY COLUMN customer_user_id BIGINT(20) UNSIGNED NOT NULL,
  ADD KEY ix_loyalty_restaurant_customer (restaurant_id,customer_user_id,created_at),
  ADD CONSTRAINT fk_loyalty_tx_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_loyalty_tx_customer FOREIGN KEY (customer_user_id) REFERENCES users(id) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS loyalty_settings (
  restaurant_id BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY,
  is_enabled TINYINT(1) NOT NULL DEFAULT 0,
  shillings_per_point INT UNSIGNED NOT NULL DEFAULT 100,
  points_per_earn INT UNSIGNED NOT NULL DEFAULT 1,
  redemption_points INT UNSIGNED NOT NULL DEFAULT 100,
  redemption_value DECIMAL(10,2) NOT NULL DEFAULT 10.00,
  updated_at TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  CONSTRAINT fk_loyalty_settings_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
