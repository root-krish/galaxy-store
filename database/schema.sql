-- Sports Galaxy / reference MySQL 8+ schema (not used by the JSON runtime)
-- Import this file in phpMyAdmin or with: mysql -u root -p < database/schema.sql

CREATE DATABASE IF NOT EXISTS sports_galaxy
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sports_galaxy;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  profile_image VARCHAR(255) NULL,
  role ENUM('customer', 'admin', 'delivery') NOT NULL DEFAULT 'customer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role_active (role, is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS addresses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  recipient_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  line1 VARCHAR(255) NOT NULL,
  line2 VARCHAR(255) NULL,
  city VARCHAR(100) NOT NULL,
  state VARCHAR(100) NULL,
  postal_code VARCHAR(15) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_addresses_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  slug VARCHAR(120) NOT NULL UNIQUE,
  icon VARCHAR(16) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  sku VARCHAR(64) NOT NULL UNIQUE,
  slug VARCHAR(180) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  brand VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  image_path VARCHAR(255) NULL,
  cost_price DECIMAL(12,2) NOT NULL,
  selling_price DECIMAL(12,2) NOT NULL,
  stock_qty INT NOT NULL DEFAULT 0,
  min_stock_qty INT NOT NULL DEFAULT 5,
  expected_restock_date DATE NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id),
  CONSTRAINT chk_product_stock CHECK (stock_qty >= 0),
  CONSTRAINT chk_product_prices CHECK (cost_price >= 0 AND selling_price >= 0),
  INDEX idx_products_catalog (category_id, is_active),
  INDEX idx_products_stock (stock_qty)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS discounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(140) NOT NULL,
  discount_type ENUM('percent', 'fixed') NOT NULL,
  discount_value DECIMAL(12,2) NOT NULL,
  minimum_subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_discount_value CHECK (discount_value > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(40) NOT NULL UNIQUE,
  tracking_code VARCHAR(50) NOT NULL UNIQUE,
  delivery_code CHAR(6) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  recipient_name VARCHAR(120) NOT NULL,
  recipient_phone VARCHAR(30) NOT NULL,
  delivery_address TEXT NOT NULL,
  delivery_city VARCHAR(100) NOT NULL,
  delivery_postal_code VARCHAR(15) NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  shipping_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(12,2) NOT NULL,
  payment_method ENUM('cod', 'upi', 'card') NOT NULL,
  payment_status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  order_status ENUM('placed', 'confirmed', 'packed', 'assigned', 'out_for_delivery', 'delivered', 'cancelled') NOT NULL DEFAULT 'placed',
  cancellation_reason VARCHAR(500) NULL,
  delivery_partner_id BIGINT UNSIGNED NULL,
  placed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_orders_delivery_partner FOREIGN KEY (delivery_partner_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_orders_status_placed (order_status, placed_at),
  INDEX idx_orders_delivery_status (delivery_partner_id, order_status),
  INDEX idx_orders_user_placed (user_id, placed_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  sku_snapshot VARCHAR(64) NOT NULL,
  product_name_snapshot VARCHAR(180) NOT NULL,
  image_path_snapshot VARCHAR(255) NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  unit_cost DECIMAL(12,2) NOT NULL,
  quantity INT NOT NULL,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT chk_order_item_qty CHECK (quantity > 0),
  INDEX idx_order_items_order (order_id),
  INDEX idx_order_items_product (product_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  transaction_reference VARCHAR(120) NULL UNIQUE,
  method ENUM('cod', 'upi', 'card') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL,
  response_note VARCHAR(255) NULL,
  paid_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX idx_payments_order_status (order_id, payment_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  movement_type ENUM('receipt', 'sale', 'adjustment', 'return', 'cancellation') NOT NULL,
  quantity_delta INT NOT NULL,
  unit_cost DECIMAL(12,2) NULL,
  reference_type VARCHAR(40) NULL,
  reference_id BIGINT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inventory_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_inventory_actor FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_inventory_product_created (product_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS delivery_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  cancellation_reason VARCHAR(500) NULL,
  delivery_partner_id BIGINT UNSIGNED NULL,
  status ENUM('placed', 'confirmed', 'packed', 'assigned', 'out_for_delivery', 'delivered', 'cancelled') NOT NULL,
  location_note VARCHAR(180) NULL,
  note VARCHAR(255) NOT NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_delivery_events_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_delivery_events_partner FOREIGN KEY (delivery_partner_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_delivery_events_order_time (order_id, occurred_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS expenses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expense_date DATE NOT NULL,
  category VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  recorded_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_expenses_actor FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_expense_amount CHECK (amount > 0),
  INDEX idx_expenses_date (expense_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  notification_type VARCHAR(50) NOT NULL,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  related_type VARCHAR(50) NULL,
  related_id BIGINT UNSIGNED NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notifications_user_read (user_id, is_read, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- External income is independent of product sales and contributes to monthly profit.
CREATE TABLE IF NOT EXISTS incomes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  income_date DATE NOT NULL,
  category VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  recorded_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_income_actor FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_income_amount CHECK (amount > 0),
  INDEX idx_income_date (income_date)
) ENGINE=InnoDB;
