-- AURAA Perfumes v2.1.0: remove obsolete Silkroad Manager business data.
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS launch_tickets;
DROP TABLE IF EXISTS api_tokens;
DROP TABLE IF EXISTS app_releases;
DROP TABLE IF EXISTS manager_releases;
DROP TABLE IF EXISTS release_downloads;
DROP TABLE IF EXISTS device_action_history;
DROP TABLE IF EXISTS device_replacement_history;
DROP TABLE IF EXISTS devices;
DROP TABLE IF EXISTS payment_orders;
DROP TABLE IF EXISTS promo_redemptions;
DROP TABLE IF EXISTS promo_codes;
DROP TABLE IF EXISTS subscription_history;
DROP TABLE IF EXISTS plans;
SET FOREIGN_KEY_CHECKS=1;

ALTER TABLE users
  DROP COLUMN expires_at,
  DROP COLUMN subscription_expires_at_epoch,
  DROP COLUMN max_devices,
  DROP COLUMN device_bonus_slots;
