-- v1.9.7.6 visitor intelligence and IP/CIDR access control
CREATE TABLE IF NOT EXISTS access_ip_blocks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cidr VARCHAR(64) NOT NULL,
  reason VARCHAR(64) NOT NULL DEFAULT 'manual',
  admin_note VARCHAR(500) NULL,
  expires_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  blocked_by_user_id INT UNSIGNED NULL,
  hit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  last_hit_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_access_ip_blocks_active_expiry (is_active,expires_at),
  KEY idx_access_ip_blocks_cidr (cidr),
  KEY idx_access_ip_blocks_last_hit (last_hit_at),
  CONSTRAINT fk_access_ip_blocks_admin FOREIGN KEY (blocked_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visitor_ip_intelligence (
  ip_address VARCHAR(45) NOT NULL,
  country_code CHAR(2) NULL,
  country_name VARCHAR(100) NULL,
  asn VARCHAR(32) NULL,
  organization VARCHAR(190) NULL,
  timezone_name VARCHAR(64) NULL,
  source VARCHAR(48) NOT NULL,
  resolved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ip_address),
  KEY idx_visitor_ip_intelligence_country (country_code),
  KEY idx_visitor_ip_intelligence_resolved (resolved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations(version,description,checksum)
VALUES('031_v1_9_7_6_visitor_intelligence_access_control','v1.9.7.6 visitor intelligence and IP/CIDR access control','manual')
ON DUPLICATE KEY UPDATE description=VALUES(description);
