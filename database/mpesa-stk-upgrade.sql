-- Run once after the existing Dineqor dump and the loyalty/notifications upgrades.
-- Adds callback references to existing payment rows; no order or loyalty data is removed.
ALTER TABLE payments
  ADD COLUMN merchant_request_id VARCHAR(100) NULL AFTER notes,
  ADD COLUMN checkout_request_id VARCHAR(100) NULL AFTER merchant_request_id,
  ADD COLUMN mpesa_receipt VARCHAR(40) NULL AFTER checkout_request_id,
  ADD COLUMN mpesa_phone VARCHAR(16) NULL AFTER mpesa_receipt,
  ADD COLUMN callback_received_at DATETIME NULL AFTER mpesa_phone,
  ADD UNIQUE KEY uq_payments_checkout_request (checkout_request_id);
