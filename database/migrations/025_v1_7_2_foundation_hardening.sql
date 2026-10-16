-- v1.7.2 foundation hardening: analytics indexes and encrypted TOTP storage capacity
ALTER TABLE users
  MODIFY admin_totp_secret VARCHAR(255) NULL,
  ADD KEY idx_users_created_at (created_at);

ALTER TABLE release_downloads
  ADD KEY idx_release_downloads_downloaded_at (downloaded_at);

ALTER TABLE promo_redemptions
  ADD KEY idx_promo_redemptions_redeemed_at (redeemed_at);

ALTER TABLE account_activity
  ADD KEY idx_account_activity_created_at (created_at);

ALTER TABLE support_tickets
  ADD KEY idx_support_tickets_created_at (created_at);
