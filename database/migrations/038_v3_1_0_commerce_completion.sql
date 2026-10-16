-- v3.1.0 commerce completion

CREATE TABLE IF NOT EXISTS store_newsletter_subscribers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  unsubscribed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_newsletter_email (email),
  KEY idx_store_newsletter_status_time (status,subscribed_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO store_settings(setting_key,setting_value) VALUES
('standard_shipping_fee','0'),
('cod_enabled','1')
ON DUPLICATE KEY UPDATE setting_value=setting_value;
