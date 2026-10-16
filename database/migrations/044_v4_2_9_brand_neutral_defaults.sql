-- v4.2.9 brand-neutral defaults: preserve custom branding, replace only known legacy placeholders.
UPDATE store_settings
SET setting_value='Perfume Store'
WHERE setting_key='store_name' AND setting_value IN ('AURAA','HASSAN&OMAR','HASSAN & OMAR');

UPDATE mail_settings
SET from_name='Perfume Store'
WHERE from_name IN ('AURAA Perfumes','HASSAN&OMAR Perfumes','HASSAN & OMAR Perfumes','Silkroad Manager');

ALTER TABLE mail_settings
  MODIFY from_name VARCHAR(160) NOT NULL DEFAULT 'Perfume Store';
