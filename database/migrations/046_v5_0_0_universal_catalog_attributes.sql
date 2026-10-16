-- v5.0 alpha 2: additive universal catalog attribute schema (preserves all legacy product/variant IDs)
-- IMPORTANT: DDL commits in MySQL/MariaDB. Apply to a restored database CLONE after backup.
CREATE TABLE IF NOT EXISTS catalog_attributes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(100) NOT NULL,
  label VARCHAR(140) NOT NULL,
  input_type VARCHAR(24) NOT NULL,
  scope VARCHAR(16) NOT NULL,
  unit VARCHAR(24) NULL,
  is_filterable TINYINT(1) NOT NULL DEFAULT 0,
  is_required_default TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_attributes_code (code),
  KEY idx_catalog_attributes_scope (scope,status,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_attribute_options (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  attribute_id INT UNSIGNED NOT NULL,
  code VARCHAR(140) NOT NULL,
  label VARCHAR(160) NOT NULL,
  numeric_value DECIMAL(16,4) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_option_attr_code (attribute_id,code),
  UNIQUE KEY uq_catalog_option_id_attr (id,attribute_id),
  KEY idx_catalog_option_attr_status (attribute_id,status,sort_order,id),
  CONSTRAINT fk_catalog_option_attribute FOREIGN KEY (attribute_id) REFERENCES catalog_attributes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_category_attributes (
  category_id INT UNSIGNED NOT NULL,
  attribute_id INT UNSIGNED NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  is_filterable TINYINT(1) NOT NULL DEFAULT 0,
  is_variant_axis TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (category_id,attribute_id),
  KEY idx_catalog_category_attribute (attribute_id,category_id),
  CONSTRAINT fk_catalog_category_attr_category FOREIGN KEY (category_id) REFERENCES store_categories(id) ON DELETE CASCADE,
  CONSTRAINT fk_catalog_category_attr_attribute FOREIGN KEY (attribute_id) REFERENCES catalog_attributes(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_product_attribute_values (
  product_id BIGINT UNSIGNED NOT NULL,
  attribute_id INT UNSIGNED NOT NULL,
  option_id BIGINT UNSIGNED NULL,
  value_text TEXT NULL,
  value_decimal DECIMAL(16,4) NULL,
  value_boolean TINYINT(1) NULL,
  value_date DATE NULL,
  PRIMARY KEY (product_id,attribute_id),
  KEY idx_catalog_product_attr_filter (attribute_id,option_id,product_id),
  KEY idx_catalog_product_attr_option_pair (option_id,attribute_id),
  CONSTRAINT fk_catalog_product_attr_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE,
  CONSTRAINT fk_catalog_product_attr_def FOREIGN KEY (attribute_id) REFERENCES catalog_attributes(id) ON DELETE RESTRICT,
  CONSTRAINT fk_catalog_product_attr_option FOREIGN KEY (option_id,attribute_id) REFERENCES catalog_attribute_options(id,attribute_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_variant_option_values (
  variant_id BIGINT UNSIGNED NOT NULL,
  attribute_id INT UNSIGNED NOT NULL,
  option_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (variant_id,attribute_id),
  KEY idx_catalog_variant_attr_filter (attribute_id,option_id,variant_id),
  KEY idx_catalog_variant_option_pair (option_id,attribute_id),
  CONSTRAINT fk_catalog_variant_attr_variant FOREIGN KEY (variant_id) REFERENCES store_product_variants(id) ON DELETE CASCADE,
  CONSTRAINT fk_catalog_variant_attr_def FOREIGN KEY (attribute_id) REFERENCES catalog_attributes(id) ON DELETE RESTRICT,
  CONSTRAINT fk_catalog_variant_attr_option FOREIGN KEY (option_id,attribute_id) REFERENCES catalog_attribute_options(id,attribute_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nullable signature deliberately does not force old unmapped variants to change.
-- Unique combination validation still occurs in the domain service before assigning it.
ALTER TABLE store_product_variants
  ADD COLUMN option_signature CHAR(64) NULL DEFAULT NULL,
  ADD UNIQUE KEY uq_catalog_variant_product_signature (product_id,option_signature);
