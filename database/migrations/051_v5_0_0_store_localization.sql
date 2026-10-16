-- Alpha 7.4: add optional content translations without changing the English catalog.
-- This migration is additive; legacy name_ar/description_ar continue working.
CREATE TABLE IF NOT EXISTS store_entity_translations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type VARCHAR(24) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  locale VARCHAR(12) NOT NULL,
  field_key VARCHAR(48) NOT NULL,
  text_value MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_translation_entity (entity_type,entity_id,locale,field_key),
  KEY idx_translation_locale (locale,entity_type,entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
