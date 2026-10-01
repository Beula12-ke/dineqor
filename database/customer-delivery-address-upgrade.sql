-- Run once in the existing Dineqor database to save a customer's default delivery address.
ALTER TABLE customers
  ADD COLUMN default_delivery_address VARCHAR(500) NULL;
