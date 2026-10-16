-- v2.0.0 perfume commerce foundation

CREATE TABLE IF NOT EXISTS store_settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  KEY idx_store_settings_updated_by (updated_by),
  CONSTRAINT fk_store_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_brands (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  name_ar VARCHAR(120) NULL,
  slug VARCHAR(140) NOT NULL,
  description TEXT NULL,
  description_ar TEXT NULL,
  logo_path VARCHAR(255) NULL,
  cover_path VARCHAR(255) NULL,
  seo_title VARCHAR(180) NULL,
  seo_description VARCHAR(320) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_brands_slug (slug),
  KEY idx_store_brands_status_sort (status,sort_order,id),
  KEY idx_store_brands_featured (is_featured,status,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  name_ar VARCHAR(120) NULL,
  slug VARCHAR(140) NOT NULL,
  description TEXT NULL,
  description_ar TEXT NULL,
  image_path VARCHAR(255) NULL,
  seo_title VARCHAR(180) NULL,
  seo_description VARCHAR(320) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_categories_slug (slug),
  KEY idx_store_categories_parent_sort (parent_id,sort_order,id),
  KEY idx_store_categories_status (status,sort_order,id),
  CONSTRAINT fk_store_categories_parent FOREIGN KEY (parent_id) REFERENCES store_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fragrance_families (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  name_ar VARCHAR(100) NULL,
  slug VARCHAR(120) NOT NULL,
  description TEXT NULL,
  description_ar TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (id),
  UNIQUE KEY uq_fragrance_families_slug (slug),
  KEY idx_fragrance_families_status_sort (status,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fragrance_notes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  name_ar VARCHAR(100) NULL,
  slug VARCHAR(120) NOT NULL,
  image_path VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (id),
  UNIQUE KEY uq_fragrance_notes_slug (slug),
  KEY idx_fragrance_notes_status_sort (status,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  brand_id INT UNSIGNED NULL,
  fragrance_family_id INT UNSIGNED NULL,
  name VARCHAR(180) NOT NULL,
  name_ar VARCHAR(180) NULL,
  slug VARCHAR(200) NOT NULL,
  short_description VARCHAR(500) NULL,
  short_description_ar VARCHAR(500) NULL,
  description MEDIUMTEXT NULL,
  description_ar MEDIUMTEXT NULL,
  gender VARCHAR(20) NOT NULL DEFAULT 'unisex',
  concentration VARCHAR(40) NULL,
  longevity_score TINYINT UNSIGNED NULL,
  projection_score TINYINT UNSIGNED NULL,
  sillage_score TINYINT UNSIGNED NULL,
  fresh_score TINYINT UNSIGNED NULL,
  sweet_score TINYINT UNSIGNED NULL,
  woody_score TINYINT UNSIGNED NULL,
  spicy_score TINYINT UNSIGNED NULL,
  season_tags VARCHAR(160) NULL,
  occasion_tags VARCHAR(200) NULL,
  main_image_path VARCHAR(255) NULL,
  gallery_json MEDIUMTEXT NULL,
  seo_title VARCHAR(180) NULL,
  seo_description VARCHAR(320) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_bestseller TINYINT(1) NOT NULL DEFAULT 0,
  is_new_arrival TINYINT(1) NOT NULL DEFAULT 0,
  is_exclusive TINYINT(1) NOT NULL DEFAULT 0,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_products_slug (slug),
  KEY idx_store_products_brand_status (brand_id,status,id),
  KEY idx_store_products_family_status (fragrance_family_id,status,id),
  KEY idx_store_products_featured (is_featured,status,id),
  KEY idx_store_products_bestseller (is_bestseller,status,id),
  KEY idx_store_products_published (status,published_at,id),
  CONSTRAINT fk_store_products_brand FOREIGN KEY (brand_id) REFERENCES store_brands(id) ON DELETE SET NULL,
  CONSTRAINT fk_store_products_family FOREIGN KEY (fragrance_family_id) REFERENCES fragrance_families(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_product_variants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(100) NOT NULL,
  size_ml DECIMAL(8,2) NULL,
  sku VARCHAR(80) NOT NULL,
  barcode VARCHAR(80) NULL,
  cost_price DECIMAL(12,2) NULL,
  price DECIMAL(12,2) NOT NULL,
  sale_price DECIMAL(12,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EGP',
  stock_qty INT NOT NULL DEFAULT 0,
  reserved_qty INT NOT NULL DEFAULT 0,
  low_stock_threshold INT NOT NULL DEFAULT 3,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_product_variants_sku (sku),
  KEY idx_store_product_variants_product (product_id,status,sort_order,id),
  KEY idx_store_product_variants_stock (status,stock_qty,reserved_qty),
  CONSTRAINT fk_store_product_variants_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_product_categories (
  product_id BIGINT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id,category_id),
  KEY idx_store_product_categories_category (category_id,product_id),
  CONSTRAINT fk_store_product_categories_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_product_categories_category FOREIGN KEY (category_id) REFERENCES store_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_product_notes (
  product_id BIGINT UNSIGNED NOT NULL,
  note_id INT UNSIGNED NOT NULL,
  note_layer VARCHAR(16) NOT NULL DEFAULT 'other',
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id,note_id,note_layer),
  KEY idx_store_product_notes_note (note_id,product_id),
  CONSTRAINT fk_store_product_notes_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_product_notes_note FOREIGN KEY (note_id) REFERENCES fragrance_notes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_inventory_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  variant_id BIGINT UNSIGNED NOT NULL,
  quantity_delta INT NOT NULL,
  movement_type VARCHAR(32) NOT NULL,
  reference_type VARCHAR(40) NULL,
  reference_id VARCHAR(80) NULL,
  note VARCHAR(500) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_store_inventory_variant_time (variant_id,created_at,id),
  KEY idx_store_inventory_reference (reference_type,reference_id),
  CONSTRAINT fk_store_inventory_variant FOREIGN KEY (variant_id) REFERENCES store_product_variants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_store_inventory_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_collections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(140) NOT NULL,
  name_ar VARCHAR(140) NULL,
  slug VARCHAR(160) NOT NULL,
  description TEXT NULL,
  description_ar TEXT NULL,
  image_path VARCHAR(255) NULL,
  rule_json MEDIUMTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_collections_slug (slug),
  KEY idx_store_collections_status_schedule (status,starts_at,ends_at,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_collection_products (
  collection_id INT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (collection_id,product_id),
  KEY idx_store_collection_products_product (product_id),
  CONSTRAINT fk_store_collection_products_collection FOREIGN KEY (collection_id) REFERENCES store_collections(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_collection_products_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homepage_sections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_key VARCHAR(100) NOT NULL,
  section_type VARCHAR(40) NOT NULL,
  title VARCHAR(180) NULL,
  title_ar VARCHAR(180) NULL,
  subtitle VARCHAR(500) NULL,
  subtitle_ar VARCHAR(500) NULL,
  settings_json MEDIUMTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homepage_sections_key (section_key),
  KEY idx_homepage_sections_status_sort (status,sort_order,id),
  CONSTRAINT fk_homepage_sections_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_homepage_sections_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homepage_section_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_id INT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  brand_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NULL,
  title VARCHAR(180) NULL,
  title_ar VARCHAR(180) NULL,
  subtitle VARCHAR(500) NULL,
  subtitle_ar VARCHAR(500) NULL,
  image_path VARCHAR(255) NULL,
  mobile_image_path VARCHAR(255) NULL,
  eyebrow VARCHAR(120) NULL,
  cta_label VARCHAR(100) NULL,
  cta_url VARCHAR(500) NULL,
  settings_json MEDIUMTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_homepage_section_items_section (section_id,status,sort_order,id),
  KEY idx_homepage_section_items_product (product_id),
  KEY idx_homepage_section_items_brand (brand_id),
  KEY idx_homepage_section_items_category (category_id),
  CONSTRAINT fk_homepage_items_section FOREIGN KEY (section_id) REFERENCES homepage_sections(id) ON DELETE CASCADE,
  CONSTRAINT fk_homepage_items_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE SET NULL,
  CONSTRAINT fk_homepage_items_brand FOREIGN KEY (brand_id) REFERENCES store_brands(id) ON DELETE SET NULL,
  CONSTRAINT fk_homepage_items_category FOREIGN KEY (category_id) REFERENCES store_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_pages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  title_ar VARCHAR(180) NULL,
  slug VARCHAR(200) NOT NULL,
  body MEDIUMTEXT NULL,
  body_ar MEDIUMTEXT NULL,
  seo_title VARCHAR(180) NULL,
  seo_description VARCHAR(320) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_pages_slug (slug),
  KEY idx_store_pages_status_published (status,published_at,id),
  CONSTRAINT fk_store_pages_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_store_pages_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_addresses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  label VARCHAR(80) NULL,
  recipient_name VARCHAR(160) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  governorate VARCHAR(120) NOT NULL,
  city VARCHAR(120) NOT NULL,
  area VARCHAR(120) NULL,
  address_line1 VARCHAR(255) NOT NULL,
  address_line2 VARCHAR(255) NULL,
  building VARCHAR(80) NULL,
  floor VARCHAR(40) NULL,
  apartment VARCHAR(40) NULL,
  postal_code VARCHAR(40) NULL,
  notes VARCHAR(500) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_customer_addresses_user (user_id,is_default,id),
  CONSTRAINT fk_customer_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_carts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  session_token CHAR(64) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  currency CHAR(3) NOT NULL DEFAULT 'EGP',
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_store_carts_user_status (user_id,status,updated_at),
  KEY idx_store_carts_session_status (session_token,status,updated_at),
  CONSTRAINT fk_store_carts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_cart_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_cart_items_cart_variant (cart_id,variant_id),
  CONSTRAINT fk_store_cart_items_cart FOREIGN KEY (cart_id) REFERENCES store_carts(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_cart_items_variant FOREIGN KEY (variant_id) REFERENCES store_product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS shipping_zones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(140) NOT NULL,
  governorate VARCHAR(120) NULL,
  city_pattern VARCHAR(160) NULL,
  fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  free_shipping_threshold DECIMAL(12,2) NULL,
  cod_enabled TINYINT(1) NOT NULL DEFAULT 1,
  estimated_days_min TINYINT UNSIGNED NULL,
  estimated_days_max TINYINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (id),
  KEY idx_shipping_zones_status_sort (status,sort_order,id),
  KEY idx_shipping_zones_governorate (governorate,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number VARCHAR(40) NOT NULL,
  user_id INT UNSIGNED NULL,
  guest_email VARCHAR(190) NULL,
  customer_name VARCHAR(160) NOT NULL,
  customer_phone VARCHAR(40) NOT NULL,
  shipping_address_json MEDIUMTEXT NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  payment_status VARCHAR(30) NOT NULL DEFAULT 'pending',
  fulfillment_status VARCHAR(30) NOT NULL DEFAULT 'unfulfilled',
  currency CHAR(3) NOT NULL DEFAULT 'EGP',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  shipping_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  coupon_code VARCHAR(80) NULL,
  customer_note VARCHAR(1000) NULL,
  admin_note VARCHAR(2000) NULL,
  placed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_at DATETIME NULL,
  completed_at DATETIME NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_orders_number (order_number),
  KEY idx_store_orders_user_time (user_id,placed_at,id),
  KEY idx_store_orders_status_time (status,placed_at,id),
  KEY idx_store_orders_payment_status (payment_status,placed_at,id),
  KEY idx_store_orders_phone (customer_phone),
  CONSTRAINT fk_store_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  variant_id BIGINT UNSIGNED NULL,
  product_name VARCHAR(180) NOT NULL,
  brand_name VARCHAR(120) NULL,
  variant_label VARCHAR(100) NULL,
  sku VARCHAR(80) NULL,
  image_path VARCHAR(255) NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  unit_cost DECIMAL(12,2) NULL,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_store_order_items_order (order_id,id),
  KEY idx_store_order_items_product (product_id),
  CONSTRAINT fk_store_order_items_order FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_order_items_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE SET NULL,
  CONSTRAINT fk_store_order_items_variant FOREIGN KEY (variant_id) REFERENCES store_product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_order_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(30) NOT NULL,
  payment_status VARCHAR(30) NULL,
  fulfillment_status VARCHAR(30) NULL,
  note VARCHAR(1000) NULL,
  changed_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_store_order_history_order (order_id,created_at,id),
  CONSTRAINT fk_store_order_history_order FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_order_history_changed_by FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(60) NOT NULL,
  method VARCHAR(60) NOT NULL,
  provider_reference VARCHAR(160) NULL,
  idempotency_key VARCHAR(160) NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EGP',
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  provider_payload MEDIUMTEXT NULL,
  paid_at DATETIME NULL,
  refunded_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_payments_idempotency (idempotency_key),
  KEY idx_store_payments_order (order_id,created_at,id),
  KEY idx_store_payments_provider_ref (provider,provider_reference),
  CONSTRAINT fk_store_payments_order FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_coupons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(80) NOT NULL,
  discount_type VARCHAR(20) NOT NULL,
  discount_value DECIMAL(12,2) NOT NULL,
  minimum_order DECIMAL(12,2) NULL,
  maximum_discount DECIMAL(12,2) NULL,
  usage_limit INT UNSIGNED NULL,
  per_user_limit INT UNSIGNED NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_coupons_code (code),
  KEY idx_store_coupons_status_schedule (status,starts_at,ends_at),
  CONSTRAINT fk_store_coupons_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_coupon_redemptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  coupon_id INT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  discount_amount DECIMAL(12,2) NOT NULL,
  redeemed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_coupon_redemptions_order (order_id),
  KEY idx_store_coupon_redemptions_coupon (coupon_id,redeemed_at,id),
  KEY idx_store_coupon_redemptions_user (user_id,coupon_id,redeemed_at),
  CONSTRAINT fk_store_coupon_redemptions_coupon FOREIGN KEY (coupon_id) REFERENCES store_coupons(id) ON DELETE RESTRICT,
  CONSTRAINT fk_store_coupon_redemptions_order FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_coupon_redemptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  rating TINYINT UNSIGNED NOT NULL,
  longevity_rating TINYINT UNSIGNED NULL,
  projection_rating TINYINT UNSIGNED NULL,
  value_rating TINYINT UNSIGNED NULL,
  title VARCHAR(160) NULL,
  body TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  admin_reply TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_reviews_product_user (product_id,user_id),
  KEY idx_store_reviews_product_status (product_id,status,created_at,id),
  CONSTRAINT fk_store_reviews_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_reviews_order FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_returns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'requested',
  reason VARCHAR(500) NOT NULL,
  customer_note VARCHAR(1000) NULL,
  admin_note VARCHAR(2000) NULL,
  refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  completed_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_store_returns_order (order_id,id),
  KEY idx_store_returns_status_time (status,requested_at,id),
  CONSTRAINT fk_store_returns_order FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_returns_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_store_returns_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_return_items (
  return_id BIGINT UNSIGNED NOT NULL,
  order_item_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  restock TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (return_id,order_item_id),
  CONSTRAINT fk_store_return_items_return FOREIGN KEY (return_id) REFERENCES store_returns(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_return_items_order_item FOREIGN KEY (order_item_id) REFERENCES store_order_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_redirects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_path VARCHAR(500) NOT NULL,
  destination_url VARCHAR(1000) NOT NULL,
  http_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_redirects_source (source_path),
  KEY idx_store_redirects_status (status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO store_settings(setting_key,setting_value) VALUES
('store_name','AURAA'),
('store_tagline','Find Your Signature'),
('currency','EGP'),
('locale_default','en'),
('locale_secondary','ar'),
('free_shipping_threshold','0'),
('catalog_enabled','1');

INSERT IGNORE INTO homepage_sections(section_key,section_type,title,subtitle,sort_order,status) VALUES
('hero','hero','Find Your Signature','Discover fragrances chosen for every mood, moment and personality.',10,'active'),
('categories','category_grid','Shop by Category','Find the fragrance world that fits you.',20,'active'),
('most-wanted','product_carousel','The Most Wanted','The bottles everyone keeps coming back for.',30,'active'),
('shop-by-scent','scent_grid','How Do You Want To Smell?','Shop by feeling, note and character.',40,'active'),
('editorial','editorial_banner','After Dark','Fragrances made for when the sun goes down.',50,'active'),
('brands','brand_grid','Shop by Brand','Discover iconic houses and modern niche perfumers.',60,'active'),
('wardrobe','custom','Your Fragrance Wardrobe','Wear the right scent for the moment.',70,'active');

INSERT IGNORE INTO store_categories(name,name_ar,slug,sort_order,status) VALUES
('Men','رجالي','men',10,'active'),
('Women','نسائي','women',20,'active'),
('Unisex','للجنسين','unisex',30,'active'),
('Niche','نيش','niche',40,'active'),
('Arabian Perfumes','عطور عربية','arabian-perfumes',50,'active'),
('Gift Sets','مجموعات هدايا','gift-sets',60,'active');

INSERT IGNORE INTO fragrance_families(name,name_ar,slug,sort_order,status) VALUES
('Fresh','منعش','fresh',10,'active'),
('Floral','زهري','floral',20,'active'),
('Woody','خشبي','woody',30,'active'),
('Amber','عنبر','amber',40,'active'),
('Oriental','شرقي','oriental',50,'active'),
('Citrus','حمضيات','citrus',60,'active'),
('Gourmand','جورماند','gourmand',70,'active'),
('Aromatic','عطري','aromatic',80,'active'),
('Leather','جلدي','leather',90,'active');
