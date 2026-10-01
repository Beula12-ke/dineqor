-- Run once in the existing Dineqor database to enable customer password resets.
CREATE TABLE IF NOT EXISTS customer_password_resets (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_customer_password_resets_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY ix_customer_password_resets_expiry (expires_at)
) ENGINE=InnoDB;
