-- v3.7.0 wishlist integrity repair.
-- Safe to run on stores that already have the original v2 commerce tables.

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
