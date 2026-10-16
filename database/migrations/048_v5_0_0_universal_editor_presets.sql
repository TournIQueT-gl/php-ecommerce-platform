-- v5.0 Alpha 3: definition presets only. No categories, products or client data are created.
-- Preserve historical migration files 001-047 unchanged.
INSERT INTO catalog_attributes (code,label,input_type,scope,unit,is_filterable,sort_order) VALUES
 ('size','Clothing size','select','variant',NULL,1,10),
 ('color','Color','select','variant',NULL,1,20),
 ('storage_gb','Storage','select','variant','GB',1,30),
 ('material','Material','text','product',NULL,1,40),
 ('fit','Fit','select','product',NULL,1,50),
 ('ram_gb','RAM','select','product','GB',1,60),
 ('warranty_months','Warranty','number','product','months',0,70),
 ('weight_grams','Shipping weight','number','product','g',0,80)
ON DUPLICATE KEY UPDATE code=code;

INSERT INTO catalog_attribute_options (attribute_id,code,label,sort_order)
SELECT a.id, presets.code, presets.label, presets.sort_order
FROM catalog_attributes a JOIN (
 SELECT 'size' attribute_code,'s' code,'S' label,10 sort_order UNION ALL
 SELECT 'size','m','M',20 UNION ALL SELECT 'size','l','L',30 UNION ALL SELECT 'size','xl','XL',40 UNION ALL
 SELECT 'color','black','Black',10 UNION ALL SELECT 'color','white','White',20 UNION ALL SELECT 'color','navy','Navy',30 UNION ALL
 SELECT 'storage_gb','64','64 GB',10 UNION ALL SELECT 'storage_gb','128','128 GB',20 UNION ALL
 SELECT 'storage_gb','256','256 GB',30 UNION ALL SELECT 'storage_gb','512','512 GB',40 UNION ALL
 SELECT 'fit','regular','Regular',10 UNION ALL SELECT 'fit','oversized','Oversized',20 UNION ALL
 SELECT 'ram_gb','4','4 GB',10 UNION ALL SELECT 'ram_gb','8','8 GB',20 UNION ALL
 SELECT 'ram_gb','12','12 GB',30 UNION ALL SELECT 'ram_gb','16','16 GB',40
) presets ON presets.attribute_code=a.code
WHERE a.input_type='select'
ON DUPLICATE KEY UPDATE numeric_value=numeric_value;
