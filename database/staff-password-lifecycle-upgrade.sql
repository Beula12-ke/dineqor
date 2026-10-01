-- Run once after the existing Dineqor database upgrades.
-- Team accounts get a 90-day password lifetime; owners/managers can set a replacement.
ALTER TABLE users
  ADD COLUMN password_changed_at DATETIME NULL AFTER password_hash,
  ADD COLUMN password_expires_at DATETIME NULL AFTER password_changed_at;

-- Give existing team accounts a 90-day transition period after this upgrade.
UPDATE users u
JOIN staff s ON s.user_id = u.id
SET u.password_changed_at = NOW(),
    u.password_expires_at = DATE_ADD(NOW(), INTERVAL 90 DAY)
WHERE u.user_type = 'restaurant_staff'
  AND u.deleted_at IS NULL
  AND u.password_expires_at IS NULL;
