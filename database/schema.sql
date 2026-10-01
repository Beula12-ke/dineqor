-- Dineqor MVP schema (MySQL 8.0+, InnoDB, utf8mb4)
-- Import this file once in MySQL/phpMyAdmin before opening the application.
CREATE DATABASE IF NOT EXISTS dineqor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dineqor;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  password_changed_at DATETIME NULL,
  password_expires_at DATETIME NULL,
  full_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NULL,
  user_type ENUM('platform_admin','restaurant_owner','restaurant_staff','customer') NOT NULL DEFAULT 'customer',
  status ENUM('active','disabled','pending') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  last_login_ip VARBINARY(16) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_users_email (email), KEY ix_users_type_status (user_type,status), KEY ix_users_deleted (deleted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS restaurants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(80) NOT NULL,
  name VARCHAR(150) NOT NULL,
  status ENUM('pending','active','suspended','rejected') NOT NULL DEFAULT 'pending',
  cuisine VARCHAR(80) NULL, description TEXT NULL, website_url VARCHAR(2048) NULL,
  phone VARCHAR(30) NULL, email VARCHAR(190) NULL, city VARCHAR(80) NULL, country VARCHAR(80) NOT NULL DEFAULT 'Kenya', address VARCHAR(500) NULL,
  logo_path VARCHAR(500) NULL, cover_path VARCHAR(500) NULL,
  primary_color CHAR(7) NOT NULL DEFAULT '#e63946', secondary_color CHAR(7) NOT NULL DEFAULT '#1d3557',
  price_range TINYINT UNSIGNED NULL,
  delivery_enabled TINYINT(1) NOT NULL DEFAULT 1, pickup_enabled TINYINT(1) NOT NULL DEFAULT 1, dinein_enabled TINYINT(1) NOT NULL DEFAULT 1,
  approved_at DATETIME NULL, approved_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, deleted_at DATETIME NULL,
  UNIQUE KEY uq_restaurants_slug (slug), KEY ix_restaurants_status (status,deleted_at), KEY ix_restaurants_owner (owner_user_id),
  CONSTRAINT fk_restaurants_owner FOREIGN KEY (owner_user_id) REFERENCES users(id),
  CONSTRAINT fk_restaurants_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS subscription_plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, code VARCHAR(40) NOT NULL, name VARCHAR(80) NOT NULL,
  description TEXT NULL, price DECIMAL(10,2) NOT NULL DEFAULT 0.00, currency VARCHAR(10) NOT NULL DEFAULT 'KES',
  billing_cycle ENUM('monthly','yearly','lifetime') NOT NULL DEFAULT 'monthly', features_json JSON NULL,
  restaurant_limit SMALLINT UNSIGNED NOT NULL DEFAULT 1, is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_subscription_plan_code (code),
  KEY ix_subscription_plans_active (is_active,name)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL, plan_id BIGINT UNSIGNED NOT NULL,
  status ENUM('active','past_due','cancelled','expired','trialing') NOT NULL DEFAULT 'active', starts_at DATE NOT NULL,
  ends_at DATE NULL, trial_ends_at DATE NULL, cancelled_at DATETIME NULL, notes VARCHAR(500) NULL, assigned_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_subscription_restaurant (restaurant_id,status,ends_at), KEY ix_subscription_plan (plan_id),
  CONSTRAINT fk_subscription_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  CONSTRAINT fk_subscription_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id),
  CONSTRAINT fk_subscription_admin FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS restaurant_settings (
  restaurant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  currency CHAR(3) NOT NULL DEFAULT 'KES', timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Nairobi', settings_json JSON NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS restaurant_domains (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL, domain VARCHAR(253) NOT NULL, is_verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_domain (domain), KEY ix_domain_restaurant (restaurant_id),
  CONSTRAINT fk_domains_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS restaurant_onboarding_progress (
  restaurant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, current_step TINYINT UNSIGNED NOT NULL DEFAULT 1, completed_at DATETIME NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_onboarding_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS restaurant_hours (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL, weekday TINYINT UNSIGNED NOT NULL,
  open_time TIME NULL, close_time TIME NULL, is_closed TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_hours_slot (restaurant_id,weekday,open_time), KEY ix_hours_restaurant (restaurant_id,weekday),
  CONSTRAINT fk_hours_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS loyalty_settings (
  restaurant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, is_enabled TINYINT(1) NOT NULL DEFAULT 0,
  shillings_per_point INT UNSIGNED NOT NULL DEFAULT 100, points_per_earn INT UNSIGNED NOT NULL DEFAULT 1,
  redemption_points INT UNSIGNED NOT NULL DEFAULT 100, redemption_value DECIMAL(10,2) NOT NULL DEFAULT 10.00,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_loyalty_settings_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS loyalty_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, restaurant_id BIGINT UNSIGNED NOT NULL,
  points_balance BIGINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_loyalty_user_restaurant (user_id,restaurant_id), KEY ix_loyalty_restaurant (restaurant_id),
  CONSTRAINT fk_loyalty_account_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_loyalty_account_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS staff (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('owner','manager','cashier','chef','waiter','delivery','inventory') NOT NULL DEFAULT 'waiter', status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_staff_restaurant_user (restaurant_id,user_id), KEY ix_staff_user (user_id,status),
  CONSTRAINT fk_staff_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS permissions (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, code VARCHAR(60) NOT NULL, label VARCHAR(120) NOT NULL, UNIQUE KEY uq_permission_code (code)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS role_permissions (
  role VARCHAR(40) NOT NULL, permission_id SMALLINT UNSIGNED NOT NULL, PRIMARY KEY (role,permission_id),
  CONSTRAINT fk_roleperm_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, deleted_at DATETIME NULL,
  KEY ix_categories_restaurant (restaurant_id,deleted_at,is_active,sort_order),
  CONSTRAINT fk_categories_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL, slug VARCHAR(180) NOT NULL, description TEXT NULL, price DECIMAL(12,2) NOT NULL DEFAULT 0,
  image_path VARCHAR(500) NULL, preparation_time SMALLINT UNSIGNED NULL, sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1, is_available TINYINT(1) NOT NULL DEFAULT 1, is_featured TINYINT(1) NOT NULL DEFAULT 0,
  track_stock TINYINT(1) NOT NULL DEFAULT 0, stock_quantity DECIMAL(12,3) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, deleted_at DATETIME NULL,
  UNIQUE KEY uq_product_slug (restaurant_id,slug), KEY ix_products_menu (restaurant_id,deleted_at,is_active,is_available,sort_order), KEY ix_products_category (category_id),
  CONSTRAINT fk_products_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS product_variations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, product_id BIGINT UNSIGNED NOT NULL, name VARCHAR(100) NOT NULL, price_delta DECIMAL(12,2) NOT NULL DEFAULT 0,
  is_default TINYINT(1) NOT NULL DEFAULT 0, is_available TINYINT(1) NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0,
  KEY ix_variations_product (product_id,is_available,sort_order), CONSTRAINT fk_variations_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS product_addons (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, product_id BIGINT UNSIGNED NOT NULL, name VARCHAR(100) NOT NULL, price DECIMAL(12,2) NOT NULL DEFAULT 0,
  max_qty TINYINT UNSIGNED NOT NULL DEFAULT 1, is_available TINYINT(1) NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0,
  KEY ix_addons_product (product_id,is_available,sort_order), CONSTRAINT fk_addons_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS restaurant_tables (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL, table_number INT UNSIGNED NOT NULL, label VARCHAR(50) NULL,
  capacity TINYINT UNSIGNED NOT NULL DEFAULT 4, section VARCHAR(50) NULL, status ENUM('available','occupied','reserved','inactive') NOT NULL DEFAULT 'available',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_table_number (restaurant_id,table_number), KEY ix_tables_restaurant (restaurant_id,status),
  CONSTRAINT fk_tables_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS qr_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL, table_id BIGINT UNSIGNED NULL,
  qr_type ENUM('restaurant','table') NOT NULL, token VARCHAR(60) NOT NULL, target_url VARCHAR(1000) NOT NULL,
  scan_count BIGINT UNSIGNED NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_qr_token (token), UNIQUE KEY uq_qr_table_type (table_id,qr_type), KEY ix_qr_restaurant (restaurant_id,qr_type,is_active),
  CONSTRAINT fk_qr_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  CONSTRAINT fk_qr_table FOREIGN KEY (table_id) REFERENCES restaurant_tables(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS qr_scans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, qr_id BIGINT UNSIGNED NOT NULL, restaurant_id BIGINT UNSIGNED NOT NULL, table_id BIGINT UNSIGNED NULL,
  ip_address VARBINARY(16) NULL, user_agent VARCHAR(500) NULL, referer VARCHAR(500) NULL, scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_qr_scans_restaurant_date (restaurant_id,scanned_at), KEY ix_qr_scans_qr_date (qr_id,scanned_at),
  CONSTRAINT fk_scans_qr FOREIGN KEY (qr_id) REFERENCES qr_codes(id) ON DELETE CASCADE,
  CONSTRAINT fk_scans_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  CONSTRAINT fk_scans_table FOREIGN KEY (table_id) REFERENCES restaurant_tables(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS delivery_zones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL,
  fee DECIMAL(12,2) NOT NULL DEFAULT 0, min_order DECIMAL(12,2) NOT NULL DEFAULT 0, estimated_time SMALLINT UNSIGNED NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_delivery_zone (restaurant_id,name), KEY ix_delivery_zones (restaurant_id,is_active),
  CONSTRAINT fk_zones_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL, customer_user_id BIGINT UNSIGNED NULL, table_id BIGINT UNSIGNED NULL,
  order_number VARCHAR(32) NOT NULL, order_type ENUM('pickup','delivery','qr_table','dine_in','takeaway','pos') NOT NULL,
  source ENUM('website','qr','pos','waiter') NOT NULL DEFAULT 'website',
  status ENUM('new','confirmed','preparing','ready','out_for_delivery','completed','delivered','cancelled') NOT NULL DEFAULT 'new',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0, delivery_fee DECIMAL(12,2) NOT NULL DEFAULT 0, discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0, total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_status ENUM('unpaid','pending','paid','refunded','failed') NOT NULL DEFAULT 'unpaid', payment_method ENUM('cash','card','mpesa','bank','wallet','other') NOT NULL DEFAULT 'cash',
  customer_name VARCHAR(150) NULL, customer_phone VARCHAR(30) NULL, customer_email VARCHAR(190) NULL, delivery_notes VARCHAR(1000) NULL, special_instructions VARCHAR(1000) NULL,
  staff_user_id BIGINT UNSIGNED NULL, cancellation_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, accepted_at DATETIME NULL, preparing_at DATETIME NULL, ready_at DATETIME NULL, completed_at DATETIME NULL, cancelled_at DATETIME NULL,
  UNIQUE KEY uq_order_number (restaurant_id,order_number), KEY ix_orders_restaurant_status_date (restaurant_id,status,created_at), KEY ix_orders_customer (customer_user_id,created_at), KEY ix_orders_table (table_id),
  CONSTRAINT fk_orders_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_orders_table FOREIGN KEY (table_id) REFERENCES restaurant_tables(id) ON DELETE SET NULL,
  CONSTRAINT fk_orders_staff FOREIGN KEY (staff_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NULL, variation_id BIGINT UNSIGNED NULL,
  product_name VARCHAR(150) NOT NULL, variation_name VARCHAR(100) NULL, unit_price DECIMAL(12,2) NOT NULL, quantity SMALLINT UNSIGNED NOT NULL,
  line_total DECIMAL(12,2) NOT NULL, addons_json JSON NULL, addons_total DECIMAL(12,2) NOT NULL DEFAULT 0, notes VARCHAR(255) NULL,
  KEY ix_order_items_order (order_id), CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_items_variation FOREIGN KEY (variation_id) REFERENCES product_variations(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS loyalty_transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, account_id BIGINT UNSIGNED NOT NULL,
  restaurant_id BIGINT UNSIGNED NOT NULL, customer_user_id BIGINT UNSIGNED NOT NULL, order_id BIGINT UNSIGNED NULL,
  points BIGINT NOT NULL, type ENUM('earn','redeem','adjust','expire') NOT NULL, description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY ix_loyalty_order (order_id),
  KEY ix_loyalty_customer (restaurant_id,customer_user_id,created_at),
  CONSTRAINT fk_loyalty_tx_account FOREIGN KEY (account_id) REFERENCES loyalty_accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_loyalty_tx_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  CONSTRAINT fk_loyalty_tx_customer FOREIGN KEY (customer_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_loyalty_tx_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS order_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL, status VARCHAR(32) NOT NULL, changed_by BIGINT UNSIGNED NULL, notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY ix_order_history (order_id,created_at),
  CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS customer_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, order_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(32) NOT NULL, title VARCHAR(100) NOT NULL, message VARCHAR(500) NOT NULL,
  read_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_notification_order_status (order_id,status),
  KEY ix_customer_notifications_unread (user_id,read_at,created_at),
  CONSTRAINT fk_customer_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_customer_notification_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL, order_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL, method ENUM('cash','card','mpesa','bank','wallet','other') NOT NULL DEFAULT 'cash', status ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  notes VARCHAR(255) NULL, merchant_request_id VARCHAR(100) NULL, checkout_request_id VARCHAR(100) NULL,
  mpesa_receipt VARCHAR(40) NULL, mpesa_phone VARCHAR(16) NULL, callback_received_at DATETIME NULL,
  paid_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payments_checkout_request (checkout_request_id),
  KEY ix_payments_restaurant_status (restaurant_id,status,created_at), KEY ix_payments_order (order_id),
  CONSTRAINT fk_payments_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS reservations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL, customer_user_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL, phone VARCHAR(30) NOT NULL, email VARCHAR(190) NULL, reservation_date DATE NOT NULL, reservation_time TIME NOT NULL,
  guests TINYINT UNSIGNED NOT NULL, special_request VARCHAR(500) NULL,
  status ENUM('pending','confirmed','rejected','cancelled','completed','no_show') NOT NULL DEFAULT 'pending', confirmed_at DATETIME NULL, cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY ix_reservations_restaurant_date (restaurant_id,reservation_date,status), KEY ix_reservations_customer (customer_user_id),
  CONSTRAINT fk_reservations_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  CONSTRAINT fk_reservations_customer FOREIGN KEY (customer_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS ingredients (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL, unit VARCHAR(20) NOT NULL DEFAULT 'g', current_stock DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  min_stock DECIMAL(12,3) NOT NULL DEFAULT 0.000, cost_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_ingredients_restaurant_name (restaurant_id,name), KEY ix_ingredients_stock (restaurant_id,current_stock,min_stock),
  CONSTRAINT fk_ingredients_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS recipes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, product_id BIGINT UNSIGNED NOT NULL, ingredient_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(12,3) NOT NULL, UNIQUE KEY uq_recipe_product_ingredient (product_id,ingredient_id), KEY ix_recipes_ingredient (ingredient_id),
  CONSTRAINT fk_recipes_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_recipes_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
  CONSTRAINT chk_recipe_quantity CHECK (quantity > 0)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS suppliers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL, phone VARCHAR(30) NULL, email VARCHAR(150) NULL, address TEXT NULL, notes TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_suppliers_restaurant (restaurant_id,is_active,name),
  CONSTRAINT fk_suppliers_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS purchases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL, supplier_id BIGINT UNSIGNED NULL,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00, status ENUM('pending','received','cancelled') NOT NULL DEFAULT 'pending',
  purchased_at DATE NULL, notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_purchases_restaurant_date (restaurant_id,purchased_at,created_at), KEY ix_purchases_supplier (supplier_id),
  CONSTRAINT fk_purchases_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  CONSTRAINT fk_purchases_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS purchase_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, purchase_id BIGINT UNSIGNED NOT NULL, ingredient_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(12,3) NOT NULL DEFAULT 0.000, unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00, line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  KEY ix_purchase_items_purchase (purchase_id), KEY ix_purchase_items_ingredient (ingredient_id),
  CONSTRAINT fk_purchase_items_purchase FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  CONSTRAINT fk_purchase_items_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS stock_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL, ingredient_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(12,3) NOT NULL, type ENUM('purchase','sale','waste','adjustment','return') NOT NULL,
  reference_id BIGINT UNSIGNED NULL, notes VARCHAR(255) NULL, user_id BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_stock_movements_restaurant_date (restaurant_id,created_at), KEY ix_stock_movements_ingredient (ingredient_id,created_at),
  CONSTRAINT fk_stock_movements_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_movements_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_movements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS favorites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
  restaurant_id BIGINT UNSIGNED NULL, product_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_favorites_user_restaurant (user_id,restaurant_id), UNIQUE KEY uq_favorites_user_product (user_id,product_id),
  KEY ix_favorites_restaurant (restaurant_id), KEY ix_favorites_product (product_id),
  CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_favorites_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  CONSTRAINT fk_favorites_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, restaurant_id BIGINT UNSIGNED NOT NULL,
  customer_user_id BIGINT UNSIGNED NOT NULL, order_id BIGINT UNSIGNED NULL, rating TINYINT UNSIGNED NOT NULL,
  title VARCHAR(150) NULL, comment TEXT NULL, is_approved TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reviews_customer_order (customer_user_id,order_id), KEY ix_reviews_restaurant_approved (restaurant_id,is_approved,created_at), KEY ix_reviews_customer (customer_user_id),
  CONSTRAINT fk_reviews_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS activity_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL, restaurant_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL, entity_type VARCHAR(60) NULL, entity_id BIGINT UNSIGNED NULL, description VARCHAR(500) NULL, meta_json JSON NULL,
  ip_address VARBINARY(16) NULL, user_agent VARCHAR(500) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_activity_action_date (action,created_at), KEY ix_activity_restaurant_date (restaurant_id,created_at), KEY ix_activity_user_date (user_id,created_at), KEY ix_activity_ip_date (ip_address,created_at),
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_activity_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO permissions (code,label) VALUES
 ('view_orders','View orders'),('manage_orders','Manage orders'),('view_products','View products'),('manage_products','Manage products'),
 ('view_customers','View customers'),('manage_customers','Manage customers'),('view_reports','View reports'),('manage_staff','Manage staff'),
 ('manage_inventory','Manage inventory'),('manage_tables','Manage tables and QR codes'),('manage_qr','Manage QR codes'),
 ('manage_reservations','Manage reservations'),('manage_pos','Use POS'),('manage_settings','Manage restaurant settings');
INSERT IGNORE INTO role_permissions (role,permission_id)
 SELECT 'owner',id FROM permissions;
INSERT IGNORE INTO role_permissions (role,permission_id)
 SELECT 'manager',id FROM permissions WHERE code NOT IN ('manage_staff');
INSERT IGNORE INTO role_permissions (role,permission_id)
 SELECT 'cashier',id FROM permissions WHERE code IN ('view_orders','manage_orders','manage_pos','view_products','view_customers','manage_reservations');
INSERT IGNORE INTO role_permissions (role,permission_id)
 SELECT 'chef',id FROM permissions WHERE code IN ('view_orders','manage_orders','view_products');
INSERT IGNORE INTO role_permissions (role,permission_id)
 SELECT 'waiter',id FROM permissions WHERE code IN ('view_orders','manage_orders','manage_tables','manage_reservations','view_products');
INSERT IGNORE INTO role_permissions (role,permission_id)
 SELECT 'delivery',id FROM permissions WHERE code IN ('view_orders','manage_orders');
INSERT IGNORE INTO role_permissions (role,permission_id)
 SELECT 'inventory',id FROM permissions WHERE code IN ('view_products','manage_inventory');



