-- v1.8.0 billing and sales: structured plan pricing plus manual payment orders
ALTER TABLE plans
  ADD COLUMN price_amount DECIMAL(10,2) NULL AFTER price,
  ADD COLUMN currency CHAR(3) NULL AFTER price_amount;

UPDATE plans
SET price_amount = CAST(TRIM(TRAILING '$' FROM TRIM(price)) AS DECIMAL(10,2)), currency = 'USD'
WHERE price_amount IS NULL
  AND price REGEXP '^[[:space:]]*[0-9]+([.][0-9]{1,2})?[[:space:]]*[$][[:space:]]*$';

UPDATE plans
SET price_amount = CAST(TRIM(LEADING '$' FROM TRIM(price)) AS DECIMAL(10,2)), currency = 'USD'
WHERE price_amount IS NULL
  AND price REGEXP '^[[:space:]]*[$][[:space:]]*[0-9]+([.][0-9]{1,2})?[[:space:]]*$';

CREATE TABLE payment_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  plan_id INT UNSIGNED NULL,
  plan_name VARCHAR(80) NOT NULL,
  plan_days INT UNSIGNED NOT NULL,
  plan_max_devices TINYINT UNSIGNED NOT NULL,
  price_label VARCHAR(64) NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL,
  payment_method VARCHAR(80) NOT NULL,
  payment_reference VARCHAR(120) NULL,
  user_note VARCHAR(500) NULL,
  receipt_stored_name VARCHAR(255) NULL,
  receipt_original_name VARCHAR(255) NULL,
  receipt_mime VARCHAR(100) NULL,
  receipt_size INT UNSIGNED NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(1000) NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payment_orders_user (user_id, submitted_at, id),
  KEY idx_payment_orders_status (status, submitted_at, id),
  KEY idx_payment_orders_reference (payment_reference),
  KEY idx_payment_orders_reviewed_by (reviewed_by, reviewed_at),
  CONSTRAINT fk_payment_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_orders_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL,
  CONSTRAINT fk_payment_orders_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations(version,description,checksum)
VALUES('026_v1_8_0_billing_sales','v1.8.0 billing and sales: structured plan pricing plus manual payment orders','manual')
ON DUPLICATE KEY UPDATE description=VALUES(description);
