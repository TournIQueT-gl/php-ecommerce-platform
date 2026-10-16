-- v1.9.2 application hardening: pending recovery email and fail-closed admin-role normalization

UPDATE users
SET admin_role='owner'
WHERE is_admin=1 AND (admin_role IS NULL OR TRIM(admin_role)='');

UPDATE users
SET admin_role=NULL
WHERE is_admin=0;

SET @scm_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='pending_email')=0,
  'ALTER TABLE users ADD COLUMN pending_email VARCHAR(254) NULL AFTER email_verified_at',
  'SELECT 1'
);
PREPARE scm_stmt FROM @scm_sql;
EXECUTE scm_stmt;
DEALLOCATE PREPARE scm_stmt;

SET @scm_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='pending_email_requested_at')=0,
  'ALTER TABLE users ADD COLUMN pending_email_requested_at DATETIME NULL AFTER pending_email',
  'SELECT 1'
);
PREPARE scm_stmt FROM @scm_sql;
EXECUTE scm_stmt;
DEALLOCATE PREPARE scm_stmt;

SET @scm_sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='uq_users_pending_email')=0,
  'ALTER TABLE users ADD UNIQUE KEY uq_users_pending_email (pending_email)',
  'SELECT 1'
);
PREPARE scm_stmt FROM @scm_sql;
EXECUTE scm_stmt;
DEALLOCATE PREPARE scm_stmt;

INSERT INTO schema_migrations(version,description,checksum)
VALUES('028_v1_9_2_application_hardening','v1.9.2 application hardening: pending recovery email and fail-closed admin roles','manual')
ON DUPLICATE KEY UPDATE description=VALUES(description);
