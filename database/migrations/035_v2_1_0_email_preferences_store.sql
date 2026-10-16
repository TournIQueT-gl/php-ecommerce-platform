-- AURAA Perfumes v2.1.0
-- Rename the two legacy notification preference columns to store semantics.
ALTER TABLE email_preferences
  CHANGE COLUMN subscription_enabled orders_enabled TINYINT(1) NOT NULL DEFAULT 1,
  CHANGE COLUMN releases_enabled marketing_enabled TINYINT(1) NOT NULL DEFAULT 1;
