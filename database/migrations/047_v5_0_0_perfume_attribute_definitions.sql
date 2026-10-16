-- v5.0 alpha 2: seed legacy perfume ATTRIBUTE DEFINITIONS ONLY, not client records
-- Individual perfume values are written by the explicit dry-run/apply backfill CLI.
INSERT INTO catalog_attributes (code,label,input_type,scope,unit,is_filterable,sort_order)
VALUES
 ('volume_ml','Volume','select','variant','ml',1,10),
 ('fragrance_family','Fragrance family','select','product',NULL,1,20),
 ('concentration','Concentration','select','product',NULL,1,30),
 ('fragrance_gender','Fragrance category','select','product',NULL,1,40),
 ('longevity_score','Longevity score','number','product',NULL,0,50),
 ('projection_score','Projection score','number','product',NULL,0,60),
 ('sillage_score','Sillage score','number','product',NULL,0,70),
 ('fresh_score','Fresh score','number','product',NULL,0,80),
 ('sweet_score','Sweet score','number','product',NULL,0,90),
 ('woody_score','Woody score','number','product',NULL,0,100),
 ('spicy_score','Spicy score','number','product',NULL,0,110),
 ('season_tags','Seasons','text','product',NULL,0,120),
 ('occasion_tags','Occasions','text','product',NULL,0,130)
ON DUPLICATE KEY UPDATE code=code;
