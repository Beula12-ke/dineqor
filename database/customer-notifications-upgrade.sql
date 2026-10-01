-- Apply once AFTER the Dineqor database schema and existing v12 loyalty/subscription upgrade.
-- Creates the in-app customer notification inbox and imports existing order status milestones.

CREATE TABLE IF NOT EXISTS customer_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(32) NOT NULL,
  title VARCHAR(100) NOT NULL,
  message VARCHAR(500) NOT NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_notification_order_status (order_id,status),
  KEY ix_customer_notifications_unread (user_id,read_at,created_at),
  CONSTRAINT fk_customer_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_customer_notification_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO customer_notifications (user_id,order_id,status,title,message,read_at,created_at)
SELECT o.customer_user_id,o.id,h.status,'Order update',
  CONCAT('Order #',o.order_number,': ',
    CASE h.status
      WHEN 'confirmed' THEN 'The restaurant confirmed your order.'
      WHEN 'preparing' THEN 'The restaurant started preparing your order.'
      WHEN 'ready' THEN 'Your order is ready.'
      WHEN 'out_for_delivery' THEN 'Your order is on the way.'
      WHEN 'delivered' THEN 'Your order was delivered.'
      WHEN 'completed' THEN 'Your order is complete.'
      WHEN 'cancelled' THEN CONCAT('The restaurant cancelled your order.',IF(h.notes IS NULL OR h.notes='','',' '),COALESCE(h.notes,''))
    END),
  NOW(),h.created_at
FROM order_status_history h
JOIN orders o ON o.id=h.order_id
WHERE o.customer_user_id IS NOT NULL
  AND h.status IN ('confirmed','preparing','ready','out_for_delivery','delivered','completed','cancelled');
