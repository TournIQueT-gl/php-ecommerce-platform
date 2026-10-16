-- v5 Alpha 5: additive department organization. Independent client DB, shared SKU/order engine.
-- Intentionally does not auto-assign historic categories or products: manual review prevents misclassification.
CREATE TABLE IF NOT EXISTS commerce_departments (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT,
 code VARCHAR(80) NOT NULL,
 name VARCHAR(120) NOT NULL,
 description VARCHAR(500) NULL,
 status VARCHAR(12) NOT NULL DEFAULT 'active',
 sort_order INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id), UNIQUE KEY uq_commerce_departments_code(code),
 KEY idx_commerce_departments_sort(status,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_department_categories (
 category_id INT UNSIGNED NOT NULL,
 department_id INT UNSIGNED NOT NULL,
 PRIMARY KEY(category_id),
 KEY idx_department_categories_department(department_id,category_id),
 CONSTRAINT fk_department_categories_category FOREIGN KEY(category_id) REFERENCES store_categories(id) ON DELETE CASCADE,
 CONSTRAINT fk_department_categories_department FOREIGN KEY(department_id) REFERENCES commerce_departments(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_product_departments (
 product_id BIGINT UNSIGNED NOT NULL,
 department_id INT UNSIGNED NOT NULL,
 PRIMARY KEY(product_id),
 KEY idx_product_departments_department(department_id,product_id),
 CONSTRAINT fk_product_departments_product FOREIGN KEY(product_id) REFERENCES store_products(id) ON DELETE CASCADE,
 CONSTRAINT fk_product_departments_department FOREIGN KEY(department_id) REFERENCES commerce_departments(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO commerce_departments(code,name,description,sort_order) VALUES
 ('perfumes','Perfumes','Fragrance notes, families, concentration and volume.',10),
 ('fashion','Fashion','Clothing, footwear, accessories and size/color options.',20),
 ('beauty','Beauty & Cosmetics','Makeup, skincare, shades and skin type.',30),
 ('electronics','Electronics','Devices, storage, specifications and warranty.',40),
 ('general','General Retail','Products that do not belong in a specialized department.',50);
