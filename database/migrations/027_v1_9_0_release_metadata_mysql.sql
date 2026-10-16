-- v1.9.0 release metadata: move Manager release catalog from JSON to MySQL
CREATE TABLE manager_releases (
  id VARCHAR(64) NOT NULL,
  version VARCHAR(64) NOT NULL,
  notes TEXT NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  sha256 CHAR(64) NOT NULL,
  channel VARCHAR(16) NOT NULL DEFAULT 'stable',
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  mandatory_update TINYINT(1) NOT NULL DEFAULT 0,
  minimum_supported_version VARCHAR(64) NULL,
  download_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_manager_releases_stored_name (stored_name),
  KEY idx_manager_releases_channel_active_created (channel,is_active,created_at),
  KEY idx_manager_releases_created_at (created_at),
  KEY idx_manager_releases_version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations(version,description,checksum)
VALUES('027_v1_9_0_release_metadata_mysql','v1.9.0 release metadata: move Manager release catalog from JSON to MySQL','manual')
ON DUPLICATE KEY UPDATE description=VALUES(description);
