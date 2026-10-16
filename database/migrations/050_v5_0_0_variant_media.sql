-- Alpha 7.3: optional images per SKU. Older SKUs inherit product media.
-- Never rewrite previous applied migration files.
ALTER TABLE store_product_variants
  ADD COLUMN main_image_path VARCHAR(255) NULL AFTER barcode,
  ADD COLUMN gallery_json MEDIUMTEXT NULL AFTER main_image_path;
