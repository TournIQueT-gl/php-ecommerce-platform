-- v1.9.5 release identity: separate human Manager version from internal API client release
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='manager_releases' AND COLUMN_NAME='client_release') = 0,
  'ALTER TABLE manager_releases ADD COLUMN client_release VARCHAR(64) NULL AFTER version',
  'SET @scm_noop = 1'
);
PREPARE scm_stmt FROM @sql;
EXECUTE scm_stmt;
DEALLOCATE PREPARE scm_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='manager_releases' AND COLUMN_NAME='auth_protocol') = 0,
  'ALTER TABLE manager_releases ADD COLUMN auth_protocol VARCHAR(32) NULL AFTER client_release',
  'SET @scm_noop = 1'
);
PREPARE scm_stmt FROM @sql;
EXECUTE scm_stmt;
DEALLOCATE PREPARE scm_stmt;

UPDATE manager_releases
SET client_release = version
WHERE client_release IS NULL OR client_release = '';

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='release_downloads' AND COLUMN_NAME='client_release') = 0,
  'ALTER TABLE release_downloads ADD COLUMN client_release VARCHAR(64) NULL AFTER version',
  'SET @scm_noop = 1'
);
PREPARE scm_stmt FROM @sql;
EXECUTE scm_stmt;
DEALLOCATE PREPARE scm_stmt;

UPDATE release_downloads rd
LEFT JOIN manager_releases mr ON mr.id = rd.release_id
SET rd.client_release = COALESCE(NULLIF(mr.client_release,''), rd.version)
WHERE rd.client_release IS NULL OR rd.client_release = '';

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='release_downloads' AND INDEX_NAME='idx_release_downloads_client_release') = 0,
  'ALTER TABLE release_downloads ADD KEY idx_release_downloads_client_release (client_release, downloaded_at)',
  'SET @scm_noop = 1'
);
PREPARE scm_stmt FROM @sql;
EXECUTE scm_stmt;
DEALLOCATE PREPARE scm_stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='manager_releases' AND INDEX_NAME='idx_manager_releases_client_release') = 0,
  'ALTER TABLE manager_releases ADD KEY idx_manager_releases_client_release (client_release)',
  'SET @scm_noop = 1'
);
PREPARE scm_stmt FROM @sql;
EXECUTE scm_stmt;
DEALLOCATE PREPARE scm_stmt;

INSERT INTO schema_migrations(version,description,checksum)
VALUES('029_v1_9_5_release_identity','v1.9.5 release identity: separate human Manager version from internal API client release','manual')
ON DUPLICATE KEY UPDATE description=VALUES(description);
