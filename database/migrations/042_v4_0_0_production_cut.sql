-- v4.0.0 production cut: preserve the pre-launch catalog while removing test-only metadata and paths.

UPDATE store_products
SET main_image_path = REPLACE(main_image_path, 'uploads/media/qa-catalog/v3-9-6/', 'uploads/media/catalog/curated/')
WHERE main_image_path LIKE 'uploads/media/qa-catalog/v3-9-6/%';

UPDATE store_products
SET gallery_json = REPLACE(gallery_json, 'uploads/media/qa-catalog/v3-9-6/', 'uploads/media/catalog/curated/')
WHERE gallery_json LIKE '%uploads/media/qa-catalog/v3-9-6/%';

UPDATE store_brands
SET logo_path = REPLACE(logo_path, 'uploads/media/qa-catalog/v3-9-6/', 'uploads/media/catalog/curated/')
WHERE logo_path LIKE 'uploads/media/qa-catalog/v3-9-6/%';

UPDATE store_brands
SET cover_path = REPLACE(cover_path, 'uploads/media/qa-catalog/v3-9-6/', 'uploads/media/catalog/curated/')
WHERE cover_path LIKE 'uploads/media/qa-catalog/v3-9-6/%';

UPDATE store_categories
SET image_path = REPLACE(image_path, 'uploads/media/qa-catalog/v3-9-6/', 'uploads/media/catalog/curated/')
WHERE image_path LIKE 'uploads/media/qa-catalog/v3-9-6/%';

UPDATE fragrance_notes
SET image_path = REPLACE(image_path, 'uploads/media/qa-catalog/v3-9-6/', 'uploads/media/catalog/curated/')
WHERE image_path LIKE 'uploads/media/qa-catalog/v3-9-6/%';

UPDATE homepage_section_items
SET image_path = REPLACE(image_path, 'uploads/media/qa-catalog/v3-9-6/', 'uploads/media/catalog/curated/')
WHERE image_path LIKE 'uploads/media/qa-catalog/v3-9-6/%';

UPDATE homepage_section_items
SET mobile_image_path = REPLACE(mobile_image_path, 'uploads/media/qa-catalog/v3-9-6/', 'uploads/media/catalog/curated/')
WHERE mobile_image_path LIKE 'uploads/media/qa-catalog/v3-9-6/%';

DELETE FROM store_settings WHERE setting_key IN ('qa_catalog_version','qa_catalog_notice');

UPDATE homepage_sections
SET section_key = CONCAT('curated-', id, '-', SUBSTRING(section_key, 4))
WHERE section_key LIKE 'qa-%';

UPDATE homepage_sections
SET subtitle = 'Discover fragrance houses across our curated catalog.'
WHERE subtitle = 'Real fragrance houses represented across the QA catalog.';

UPDATE homepage_sections
SET subtitle = 'Explore recently added fragrances, product cards and available sizes.'
WHERE subtitle = 'Exercise new-arrival badges, product cards and variant selection with real fragrances.';

UPDATE homepage_section_items
SET subtitle = 'Explore designer, niche and Arabian fragrances from our curated catalog.'
WHERE subtitle = 'Explore real designer, niche and Arabian fragrances in a production-like catalog.';

UPDATE store_categories
SET description = 'Middle Eastern fragrance houses and bold signature scents.'
WHERE description = 'Middle Eastern fragrance houses in the QA catalog.';

UPDATE store_product_variants
SET sku = CONCAT('CAT-', id, '-', SUBSTRING(sku, 4))
WHERE sku LIKE 'QA-%';

UPDATE store_inventory_movements
SET movement_type = 'catalog_import'
WHERE movement_type = 'catalog_seed';

UPDATE store_inventory_movements
SET reference_type = 'catalog'
WHERE reference_type = 'qa_catalog';

UPDATE store_inventory_movements
SET note = 'Catalog stock imported during store setup.'
WHERE note = 'QA catalog stock synchronized for production-like testing.';
