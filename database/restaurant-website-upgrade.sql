-- Run once after the existing restaurant database upgrades.
-- Adds the optional partner website URL to existing restaurant records.
ALTER TABLE restaurants
  ADD COLUMN website_url VARCHAR(2048) NULL AFTER description;
