-- v3.9.0 repair existing Wishlist schemas before the application relies on them.

CREATE TABLE IF NOT EXISTS store_wishlists (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_wishlists_user (user_id),
  CONSTRAINT fk_store_wishlists_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_wishlist_items (
  wishlist_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (wishlist_id,product_id),
  KEY idx_store_wishlist_items_product (product_id),
  CONSTRAINT fk_store_wishlist_items_wishlist FOREIGN KEY (wishlist_id) REFERENCES store_wishlists(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_wishlist_items_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='store_wishlists' AND COLUMN_NAME='id')=0,
  'ALTER TABLE store_wishlists ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST','SELECT 1');
PREPARE auraa_stmt FROM @sql;
EXECUTE auraa_stmt;
DEALLOCATE PREPARE auraa_stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='store_wishlists' AND COLUMN_NAME='user_id')=0,
  'ALTER TABLE store_wishlists ADD COLUMN user_id INT UNSIGNED NOT NULL AFTER id','SELECT 1');
PREPARE auraa_stmt FROM @sql;
EXECUTE auraa_stmt;
DEALLOCATE PREPARE auraa_stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='store_wishlists' AND COLUMN_NAME='created_at')=0,
  'ALTER TABLE store_wishlists ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP','SELECT 1');
PREPARE auraa_stmt FROM @sql;
EXECUTE auraa_stmt;
DEALLOCATE PREPARE auraa_stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='store_wishlist_items' AND COLUMN_NAME='wishlist_id')=0,
  'ALTER TABLE store_wishlist_items ADD COLUMN wishlist_id BIGINT UNSIGNED NOT NULL FIRST','SELECT 1');
PREPARE auraa_stmt FROM @sql;
EXECUTE auraa_stmt;
DEALLOCATE PREPARE auraa_stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='store_wishlist_items' AND COLUMN_NAME='product_id')=0,
  'ALTER TABLE store_wishlist_items ADD COLUMN product_id BIGINT UNSIGNED NOT NULL AFTER wishlist_id','SELECT 1');
PREPARE auraa_stmt FROM @sql;
EXECUTE auraa_stmt;
DEALLOCATE PREPARE auraa_stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='store_wishlist_items' AND COLUMN_NAME='created_at')=0,
  'ALTER TABLE store_wishlist_items ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP','SELECT 1');
PREPARE auraa_stmt FROM @sql;
EXECUTE auraa_stmt;
DEALLOCATE PREPARE auraa_stmt;

DELETE w FROM store_wishlists w LEFT JOIN users u ON u.id=w.user_id WHERE u.id IS NULL;
DELETE wi FROM store_wishlist_items wi LEFT JOIN store_wishlists w ON w.id=wi.wishlist_id WHERE w.id IS NULL;
DELETE wi FROM store_wishlist_items wi LEFT JOIN store_products p ON p.id=wi.product_id WHERE p.id IS NULL;

INSERT IGNORE INTO store_wishlist_items(wishlist_id,product_id,created_at)
SELECT keepers.keep_id,wi.product_id,MIN(wi.created_at)
FROM store_wishlists w
JOIN (SELECT user_id,MIN(id) AS keep_id FROM store_wishlists GROUP BY user_id) keepers ON keepers.user_id=w.user_id
JOIN store_wishlist_items wi ON wi.wishlist_id=w.id
GROUP BY keepers.keep_id,wi.product_id;

DELETE wi FROM store_wishlist_items wi
JOIN store_wishlists w ON w.id=wi.wishlist_id
JOIN (SELECT user_id,MIN(id) AS keep_id FROM store_wishlists GROUP BY user_id) keepers ON keepers.user_id=w.user_id
WHERE w.id<>keepers.keep_id;

DELETE w FROM store_wishlists w
JOIN (SELECT user_id,MIN(id) AS keep_id FROM store_wishlists GROUP BY user_id) keepers ON keepers.user_id=w.user_id
WHERE w.id<>keepers.keep_id;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='store_wishlists' AND NON_UNIQUE=0 AND COLUMN_NAME='user_id')=0,
  'ALTER TABLE store_wishlists ADD UNIQUE KEY uq_store_wishlists_user (user_id)','SELECT 1');
PREPARE auraa_stmt FROM @sql;
EXECUTE auraa_stmt;
DEALLOCATE PREPARE auraa_stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='store_wishlist_items' AND NON_UNIQUE=0 AND INDEX_NAME<>'PRIMARY' AND SEQ_IN_INDEX=1 AND COLUMN_NAME='wishlist_id')=0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='store_wishlist_items' AND CONSTRAINT_TYPE='PRIMARY KEY')=0,
  'ALTER TABLE store_wishlist_items ADD UNIQUE KEY uq_store_wishlist_items_pair (wishlist_id,product_id)','SELECT 1');
PREPARE auraa_stmt FROM @sql;
EXECUTE auraa_stmt;
DEALLOCATE PREPARE auraa_stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='store_wishlist_items' AND COLUMN_NAME='product_id')=0,
  'ALTER TABLE store_wishlist_items ADD KEY idx_store_wishlist_items_product (product_id)','SELECT 1');
PREPARE auraa_stmt FROM @sql;
EXECUTE auraa_stmt;
DEALLOCATE PREPARE auraa_stmt;
