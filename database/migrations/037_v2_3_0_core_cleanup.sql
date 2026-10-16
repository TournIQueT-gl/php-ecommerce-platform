-- AURAA Perfumes v2.3.0 core cleanup.
-- Removes the last database artifacts that belonged to the retired desktop-client platform.

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS maintenance_windows;
DROP TABLE IF EXISTS service_health_checks;
DROP TABLE IF EXISTS service_health_current;
DROP TABLE IF EXISTS service_incidents;
DROP TABLE IF EXISTS service_status;
SET FOREIGN_KEY_CHECKS=1;

DELETE FROM user_feature_flags WHERE flag_key='beta_manager';
DELETE FROM feature_flags WHERE flag_key='beta_manager';
UPDATE mail_settings SET from_name='AURAA Perfumes' WHERE from_name='Silkroad Manager';

SET @auraa_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='is_beta_tester') > 0,
  'ALTER TABLE users DROP COLUMN is_beta_tester',
  'DO 0'
);
PREPARE auraa_stmt FROM @auraa_sql;
EXECUTE auraa_stmt;
DEALLOCATE PREPARE auraa_stmt;

SET @auraa_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_preferences' AND COLUMN_NAME='subscription_enabled') > 0,
  'ALTER TABLE email_preferences DROP COLUMN subscription_enabled',
  'DO 0'
);
PREPARE auraa_stmt FROM @auraa_sql;
EXECUTE auraa_stmt;
DEALLOCATE PREPARE auraa_stmt;

SET @auraa_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_preferences' AND COLUMN_NAME='releases_enabled') > 0,
  'ALTER TABLE email_preferences DROP COLUMN releases_enabled',
  'DO 0'
);
PREPARE auraa_stmt FROM @auraa_sql;
EXECUTE auraa_stmt;
DEALLOCATE PREPARE auraa_stmt;
