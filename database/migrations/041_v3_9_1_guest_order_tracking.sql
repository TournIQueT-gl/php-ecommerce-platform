-- v3.9.1 add durable, unguessable guest order tracking access.

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='store_orders' AND COLUMN_NAME='guest_access_hash')=0,
  'ALTER TABLE store_orders ADD COLUMN guest_access_hash CHAR(64) NULL AFTER guest_email','SELECT 1');
PREPARE auraa_stmt FROM @sql;
EXECUTE auraa_stmt;
DEALLOCATE PREPARE auraa_stmt;
