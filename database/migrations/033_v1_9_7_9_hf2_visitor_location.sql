-- v1.9.7.9 HF2 visitor city/region intelligence
ALTER TABLE visitor_ip_intelligence
  ADD COLUMN region_name VARCHAR(120) NULL AFTER country_name,
  ADD COLUMN city_name VARCHAR(100) NULL AFTER region_name;

INSERT INTO schema_migrations(version,description,checksum)
VALUES('033_v1_9_7_9_hf2_visitor_location','v1.9.7.9 HF2 visitor city/region intelligence','manual')
ON DUPLICATE KEY UPDATE description=VALUES(description);
