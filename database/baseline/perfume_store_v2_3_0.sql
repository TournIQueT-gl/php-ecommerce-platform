-- Perfume Store clean fresh-install schema baseline
-- Baseline level: v2.3.0 / migration 037
-- Contains only the current perfume-store platform schema. No legacy desktop-client,
-- release, device-entitlement, subscription-plan or obsolete service-monitor tables.
-- Contains no production users, passwords, secrets, order history or customer data.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE `account_activity` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `event_type` varchar(48) NOT NULL,
  `title` varchar(160) NOT NULL,
  `details` varchar(1000) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_notes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `note` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `announcements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(160) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(16) NOT NULL DEFAULT 'info',
  `audience` varchar(24) NOT NULL DEFAULT 'all',
  `priority` varchar(16) NOT NULL DEFAULT 'normal',
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `notification_sent_at` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `details` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `domain_event_outbox` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_key` varchar(64) NOT NULL,
  `subject_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `actor_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payload_json` longtext NOT NULL,
  `occurred_at` datetime NOT NULL,
  `processed_at` datetime DEFAULT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `last_error` varchar(1000) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_deliveries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `recipient` varchar(254) NOT NULL,
  `category` varchar(32) NOT NULL,
  `subject` varchar(190) NOT NULL,
  `body_text` longtext DEFAULT NULL,
  `body_html` longtext DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `last_error` varchar(1000) DEFAULT NULL,
  `dedupe_key` varchar(160) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `next_attempt_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_attempt_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_preferences` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `support_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `orders_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `marketing_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `maintenance_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `announcements_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_verification_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(254) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `feature_flags` (
  `flag_key` varchar(48) NOT NULL,
  `label` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `default_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `login_security_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `username_snapshot` varchar(64) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `auth_stage` varchar(32) NOT NULL DEFAULT 'password',
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `details` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mail_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `smtp_host` varchar(255) NOT NULL DEFAULT '',
  `smtp_port` smallint(5) UNSIGNED NOT NULL DEFAULT 587,
  `smtp_encryption` varchar(16) NOT NULL DEFAULT 'tls',
  `smtp_username` varchar(255) DEFAULT NULL,
  `smtp_password_cipher` longtext DEFAULT NULL,
  `from_email` varchar(254) NOT NULL DEFAULT '',
  `from_name` varchar(160) NOT NULL DEFAULT 'Perfume Store',
  `reply_to_email` varchar(254) DEFAULT NULL,
  `site_url` varchar(255) DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(64) NOT NULL,
  `title` varchar(160) NOT NULL,
  `body` varchar(1000) NOT NULL,
  `data_json` longtext DEFAULT NULL,
  `dedupe_key` varchar(100) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_recovery_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `request_hash` char(64) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `requested_ip` varchar(64) DEFAULT NULL,
  `requested_user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `reset_expires_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `scheduler_state` (
  `task_key` varchar(64) NOT NULL,
  `last_started_at` datetime DEFAULT NULL,
  `last_finished_at` datetime DEFAULT NULL,
  `last_status` varchar(16) DEFAULT NULL,
  `last_error` varchar(1000) DEFAULT NULL,
  `last_duration_ms` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `schema_migrations` (
  `version` varchar(64) NOT NULL,
  `description` varchar(255) NOT NULL,
  `checksum` char(64) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `support_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ticket_id` bigint(20) UNSIGNED NOT NULL,
  `sender_user_id` bigint(20) UNSIGNED NOT NULL,
  `sender_is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `message` text DEFAULT NULL,
  `attachment_original_name` varchar(255) DEFAULT NULL,
  `attachment_stored_name` varchar(255) DEFAULT NULL,
  `attachment_size` bigint(20) UNSIGNED DEFAULT NULL,
  `attachment_sha256` char(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `support_tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `category` varchar(32) NOT NULL DEFAULT 'other',
  `subject` varchar(160) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'open',
  `priority` varchar(16) NOT NULL DEFAULT 'normal',
  `assigned_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `support_ticket_reads` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `ticket_id` bigint(20) UNSIGNED NOT NULL,
  `last_read_message_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `last_read_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(32) NOT NULL,
  `email` varchar(254) DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `pending_email` varchar(254) DEFAULT NULL,
  `pending_email_requested_at` datetime DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `admin_role` varchar(16) DEFAULT NULL,
  `admin_totp_secret` varchar(64) DEFAULT NULL,
  `admin_totp_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `admin_totp_recovery_codes` text DEFAULT NULL,
  `admin_totp_enabled_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_feature_flags` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `flag_key` varchar(48) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `web_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `session_hash` char(64) NOT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `revoked_at` datetime DEFAULT NULL,
  `revoked_reason` varchar(160) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `account_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_account_activity_user_time` (`user_id`,`created_at`),
  ADD KEY `idx_account_activity_type_time` (`event_type`,`created_at`);

ALTER TABLE `admin_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_notes_user` (`user_id`,`created_at`,`id`),
  ADD KEY `idx_admin_notes_admin` (`admin_id`,`created_at`,`id`);

ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_announcement_active_window` (`is_active`,`starts_at`,`ends_at`);

ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_created` (`created_at`),
  ADD KEY `fk_audit_admin` (`admin_id`),
  ADD KEY `fk_audit_user` (`user_id`);

ALTER TABLE `domain_event_outbox`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_domain_events_pending` (`processed_at`,`attempts`,`id`),
  ADD KEY `idx_domain_events_subject` (`subject_user_id`,`id`);

ALTER TABLE `email_deliveries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email_delivery_dedupe` (`recipient`,`dedupe_key`),
  ADD KEY `idx_email_delivery_pending` (`status`,`next_attempt_at`,`id`),
  ADD KEY `idx_email_delivery_user` (`user_id`,`id`),
  ADD KEY `idx_email_delivery_created` (`created_at`);

ALTER TABLE `email_preferences`
  ADD PRIMARY KEY (`user_id`);

ALTER TABLE `email_verification_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email_verification_hash` (`token_hash`),
  ADD KEY `idx_email_verification_user` (`user_id`,`used_at`,`cancelled_at`,`expires_at`);

ALTER TABLE `feature_flags`
  ADD PRIMARY KEY (`flag_key`);

ALTER TABLE `login_security_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_security_user` (`user_id`,`created_at`,`id`),
  ADD KEY `idx_login_security_username` (`username_snapshot`,`created_at`,`id`),
  ADD KEY `idx_login_security_created` (`created_at`);

ALTER TABLE `mail_settings`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_notification_dedupe` (`user_id`,`dedupe_key`),
  ADD KEY `idx_notifications_user_unread` (`user_id`,`read_at`,`id`),
  ADD KEY `idx_notifications_created` (`created_at`);

ALTER TABLE `password_recovery_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_password_recovery_hash` (`request_hash`),
  ADD KEY `idx_password_recovery_user` (`user_id`,`status`,`created_at`,`id`),
  ADD KEY `idx_password_recovery_status` (`status`,`expires_at`,`id`);

ALTER TABLE `scheduler_state`
  ADD PRIMARY KEY (`task_key`);

ALTER TABLE `schema_migrations`
  ADD PRIMARY KEY (`version`);

ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_support_message_ticket` (`ticket_id`,`created_at`);

ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_support_ticket_user` (`user_id`,`updated_at`),
  ADD KEY `idx_support_ticket_status` (`status`,`updated_at`);

ALTER TABLE `support_ticket_reads`
  ADD PRIMARY KEY (`user_id`,`ticket_id`),
  ADD KEY `idx_support_reads_ticket` (`ticket_id`,`last_read_message_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_pending_email` (`pending_email`),
  ADD KEY `idx_active` (`is_active`);

ALTER TABLE `user_feature_flags`
  ADD PRIMARY KEY (`user_id`,`flag_key`);

ALTER TABLE `web_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_web_sessions_hash` (`session_hash`),
  ADD KEY `idx_web_sessions_user_active` (`user_id`,`revoked_at`,`last_seen_at`),
  ADD KEY `idx_web_sessions_seen` (`last_seen_at`);

ALTER TABLE `account_activity`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `admin_notes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `announcements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `domain_event_outbox`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `email_deliveries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `email_verification_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `login_security_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `password_recovery_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `support_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `support_tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `web_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE users
  MODIFY admin_totp_secret VARCHAR(255) NULL,
  ADD KEY idx_users_created_at (created_at);

ALTER TABLE account_activity
  ADD KEY idx_account_activity_created_at (created_at);

ALTER TABLE support_tickets
  ADD KEY idx_support_tickets_created_at (created_at);

CREATE TABLE IF NOT EXISTS visitor_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visitor_key CHAR(64) NOT NULL,
  session_key CHAR(64) NOT NULL,
  user_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  browser VARCHAR(48) NULL,
  os_name VARCHAR(48) NULL,
  device_type VARCHAR(24) NULL,
  country_code CHAR(2) NULL,
  first_referrer_host VARCHAR(190) NULL,
  last_referrer_host VARCHAR(190) NULL,
  landing_path VARCHAR(255) NOT NULL,
  last_path VARCHAR(255) NOT NULL,
  utm_source VARCHAR(100) NULL,
  utm_medium VARCHAR(100) NULL,
  utm_campaign VARCHAR(150) NULL,
  pageview_count INT UNSIGNED NOT NULL DEFAULT 1,
  is_bot TINYINT(1) NOT NULL DEFAULT 0,
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_visitor_sessions_session_key (session_key),
  KEY idx_visitor_sessions_visitor_time (visitor_key,last_seen_at),
  KEY idx_visitor_sessions_last_seen (last_seen_at),
  KEY idx_visitor_sessions_user_time (user_id,last_seen_at),
  KEY idx_visitor_sessions_human_time (is_bot,last_seen_at),
  CONSTRAINT fk_visitor_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visitor_pageviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id BIGINT UNSIGNED NOT NULL,
  visitor_key CHAR(64) NOT NULL,
  user_id INT UNSIGNED NULL,
  path VARCHAR(255) NOT NULL,
  referrer_host VARCHAR(190) NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_visitor_pageviews_time (occurred_at),
  KEY idx_visitor_pageviews_path_time (path,occurred_at),
  KEY idx_visitor_pageviews_visitor_time (visitor_key,occurred_at),
  KEY idx_visitor_pageviews_user_time (user_id,occurred_at),
  CONSTRAINT fk_visitor_pageviews_session FOREIGN KEY (session_id) REFERENCES visitor_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_visitor_pageviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visitor_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id BIGINT UNSIGNED NULL,
  visitor_key CHAR(64) NULL,
  user_id INT UNSIGNED NULL,
  event_key VARCHAR(64) NOT NULL,
  path VARCHAR(255) NULL,
  metadata_json LONGTEXT NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_visitor_events_key_time (event_key,occurred_at),
  KEY idx_visitor_events_visitor_time (visitor_key,occurred_at),
  KEY idx_visitor_events_user_time (user_id,occurred_at),
  CONSTRAINT fk_visitor_events_session FOREIGN KEY (session_id) REFERENCES visitor_sessions(id) ON DELETE SET NULL,
  CONSTRAINT fk_visitor_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS access_ip_blocks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cidr VARCHAR(64) NOT NULL,
  reason VARCHAR(64) NOT NULL DEFAULT 'manual',
  admin_note VARCHAR(500) NULL,
  expires_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  blocked_by_user_id INT UNSIGNED NULL,
  hit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  last_hit_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_access_ip_blocks_active_expiry (is_active,expires_at),
  KEY idx_access_ip_blocks_cidr (cidr),
  KEY idx_access_ip_blocks_last_hit (last_hit_at),
  CONSTRAINT fk_access_ip_blocks_admin FOREIGN KEY (blocked_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visitor_ip_intelligence (
  ip_address VARCHAR(45) NOT NULL,
  country_code CHAR(2) NULL,
  country_name VARCHAR(100) NULL,
  asn VARCHAR(32) NULL,
  organization VARCHAR(190) NULL,
  timezone_name VARCHAR(64) NULL,
  source VARCHAR(48) NOT NULL,
  resolved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ip_address),
  KEY idx_visitor_ip_intelligence_country (country_code),
  KEY idx_visitor_ip_intelligence_resolved (resolved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE visitor_ip_intelligence
  MODIFY resolved_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN resolution_status VARCHAR(16) NOT NULL DEFAULT 'resolved' AFTER source,
  ADD COLUMN last_error VARCHAR(255) NULL AFTER resolution_status,
  ADD COLUMN attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_error,
  ADD COLUMN next_retry_at DATETIME NULL AFTER attempt_count,
  ADD COLUMN queued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER next_retry_at,
  ADD KEY idx_visitor_ip_intelligence_status_retry (resolution_status,next_retry_at);

UPDATE visitor_ip_intelligence
SET resolution_status=CASE WHEN country_code IS NOT NULL AND country_code<>'' THEN 'resolved' ELSE 'pending' END,
    queued_at=COALESCE(resolved_at,UTC_TIMESTAMP())
WHERE resolution_status='resolved';

ALTER TABLE visitor_sessions
  ADD KEY idx_visitor_sessions_ip_time (ip_address,last_seen_at),
  ADD KEY idx_visitor_sessions_first_seen (first_seen_at);

ALTER TABLE visitor_events
  ADD KEY idx_visitor_events_key_visitor_time (event_key,visitor_key,occurred_at);

ALTER TABLE visitor_ip_intelligence
  ADD COLUMN region_name VARCHAR(120) NULL AFTER country_name,
  ADD COLUMN city_name VARCHAR(100) NULL AFTER region_name;

CREATE TABLE IF NOT EXISTS store_settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  KEY idx_store_settings_updated_by (updated_by),
  CONSTRAINT fk_store_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_brands (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  name_ar VARCHAR(120) NULL,
  slug VARCHAR(140) NOT NULL,
  description TEXT NULL,
  description_ar TEXT NULL,
  logo_path VARCHAR(255) NULL,
  cover_path VARCHAR(255) NULL,
  seo_title VARCHAR(180) NULL,
  seo_description VARCHAR(320) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_brands_slug (slug),
  KEY idx_store_brands_status_sort (status,sort_order,id),
  KEY idx_store_brands_featured (is_featured,status,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  name_ar VARCHAR(120) NULL,
  slug VARCHAR(140) NOT NULL,
  description TEXT NULL,
  description_ar TEXT NULL,
  image_path VARCHAR(255) NULL,
  seo_title VARCHAR(180) NULL,
  seo_description VARCHAR(320) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_categories_slug (slug),
  KEY idx_store_categories_parent_sort (parent_id,sort_order,id),
  KEY idx_store_categories_status (status,sort_order,id),
  CONSTRAINT fk_store_categories_parent FOREIGN KEY (parent_id) REFERENCES store_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fragrance_families (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  name_ar VARCHAR(100) NULL,
  slug VARCHAR(120) NOT NULL,
  description TEXT NULL,
  description_ar TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (id),
  UNIQUE KEY uq_fragrance_families_slug (slug),
  KEY idx_fragrance_families_status_sort (status,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fragrance_notes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  name_ar VARCHAR(100) NULL,
  slug VARCHAR(120) NOT NULL,
  image_path VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (id),
  UNIQUE KEY uq_fragrance_notes_slug (slug),
  KEY idx_fragrance_notes_status_sort (status,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  brand_id INT UNSIGNED NULL,
  fragrance_family_id INT UNSIGNED NULL,
  name VARCHAR(180) NOT NULL,
  name_ar VARCHAR(180) NULL,
  slug VARCHAR(200) NOT NULL,
  short_description VARCHAR(500) NULL,
  short_description_ar VARCHAR(500) NULL,
  description MEDIUMTEXT NULL,
  description_ar MEDIUMTEXT NULL,
  gender VARCHAR(20) NOT NULL DEFAULT 'unisex',
  concentration VARCHAR(40) NULL,
  longevity_score TINYINT UNSIGNED NULL,
  projection_score TINYINT UNSIGNED NULL,
  sillage_score TINYINT UNSIGNED NULL,
  fresh_score TINYINT UNSIGNED NULL,
  sweet_score TINYINT UNSIGNED NULL,
  woody_score TINYINT UNSIGNED NULL,
  spicy_score TINYINT UNSIGNED NULL,
  season_tags VARCHAR(160) NULL,
  occasion_tags VARCHAR(200) NULL,
  main_image_path VARCHAR(255) NULL,
  gallery_json MEDIUMTEXT NULL,
  seo_title VARCHAR(180) NULL,
  seo_description VARCHAR(320) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_bestseller TINYINT(1) NOT NULL DEFAULT 0,
  is_new_arrival TINYINT(1) NOT NULL DEFAULT 0,
  is_exclusive TINYINT(1) NOT NULL DEFAULT 0,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_products_slug (slug),
  KEY idx_store_products_brand_status (brand_id,status,id),
  KEY idx_store_products_family_status (fragrance_family_id,status,id),
  KEY idx_store_products_featured (is_featured,status,id),
  KEY idx_store_products_bestseller (is_bestseller,status,id),
  KEY idx_store_products_published (status,published_at,id),
  CONSTRAINT fk_store_products_brand FOREIGN KEY (brand_id) REFERENCES store_brands(id) ON DELETE SET NULL,
  CONSTRAINT fk_store_products_family FOREIGN KEY (fragrance_family_id) REFERENCES fragrance_families(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_product_variants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(100) NOT NULL,
  size_ml DECIMAL(8,2) NULL,
  sku VARCHAR(80) NOT NULL,
  barcode VARCHAR(80) NULL,
  cost_price DECIMAL(12,2) NULL,
  price DECIMAL(12,2) NOT NULL,
  sale_price DECIMAL(12,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EGP',
  stock_qty INT NOT NULL DEFAULT 0,
  reserved_qty INT NOT NULL DEFAULT 0,
  low_stock_threshold INT NOT NULL DEFAULT 3,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_product_variants_sku (sku),
  KEY idx_store_product_variants_product (product_id,status,sort_order,id),
  KEY idx_store_product_variants_stock (status,stock_qty,reserved_qty),
  CONSTRAINT fk_store_product_variants_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_product_categories (
  product_id BIGINT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (product_id,category_id),
  KEY idx_store_product_categories_category (category_id,product_id),
  CONSTRAINT fk_store_product_categories_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_product_categories_category FOREIGN KEY (category_id) REFERENCES store_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_product_notes (
  product_id BIGINT UNSIGNED NOT NULL,
  note_id INT UNSIGNED NOT NULL,
  note_layer VARCHAR(16) NOT NULL DEFAULT 'other',
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id,note_id,note_layer),
  KEY idx_store_product_notes_note (note_id,product_id),
  CONSTRAINT fk_store_product_notes_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_product_notes_note FOREIGN KEY (note_id) REFERENCES fragrance_notes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_inventory_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  variant_id BIGINT UNSIGNED NOT NULL,
  quantity_delta INT NOT NULL,
  movement_type VARCHAR(32) NOT NULL,
  reference_type VARCHAR(40) NULL,
  reference_id VARCHAR(80) NULL,
  note VARCHAR(500) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_store_inventory_variant_time (variant_id,created_at,id),
  KEY idx_store_inventory_reference (reference_type,reference_id),
  CONSTRAINT fk_store_inventory_variant FOREIGN KEY (variant_id) REFERENCES store_product_variants(id) ON DELETE RESTRICT,
  CONSTRAINT fk_store_inventory_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_collections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(140) NOT NULL,
  name_ar VARCHAR(140) NULL,
  slug VARCHAR(160) NOT NULL,
  description TEXT NULL,
  description_ar TEXT NULL,
  image_path VARCHAR(255) NULL,
  rule_json MEDIUMTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_collections_slug (slug),
  KEY idx_store_collections_status_schedule (status,starts_at,ends_at,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_collection_products (
  collection_id INT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (collection_id,product_id),
  KEY idx_store_collection_products_product (product_id),
  CONSTRAINT fk_store_collection_products_collection FOREIGN KEY (collection_id) REFERENCES store_collections(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_collection_products_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homepage_sections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_key VARCHAR(100) NOT NULL,
  section_type VARCHAR(40) NOT NULL,
  title VARCHAR(180) NULL,
  title_ar VARCHAR(180) NULL,
  subtitle VARCHAR(500) NULL,
  subtitle_ar VARCHAR(500) NULL,
  settings_json MEDIUMTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homepage_sections_key (section_key),
  KEY idx_homepage_sections_status_sort (status,sort_order,id),
  CONSTRAINT fk_homepage_sections_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_homepage_sections_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homepage_section_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_id INT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  brand_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NULL,
  title VARCHAR(180) NULL,
  title_ar VARCHAR(180) NULL,
  subtitle VARCHAR(500) NULL,
  subtitle_ar VARCHAR(500) NULL,
  image_path VARCHAR(255) NULL,
  mobile_image_path VARCHAR(255) NULL,
  eyebrow VARCHAR(120) NULL,
  cta_label VARCHAR(100) NULL,
  cta_url VARCHAR(500) NULL,
  settings_json MEDIUMTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_homepage_section_items_section (section_id,status,sort_order,id),
  KEY idx_homepage_section_items_product (product_id),
  KEY idx_homepage_section_items_brand (brand_id),
  KEY idx_homepage_section_items_category (category_id),
  CONSTRAINT fk_homepage_items_section FOREIGN KEY (section_id) REFERENCES homepage_sections(id) ON DELETE CASCADE,
  CONSTRAINT fk_homepage_items_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE SET NULL,
  CONSTRAINT fk_homepage_items_brand FOREIGN KEY (brand_id) REFERENCES store_brands(id) ON DELETE SET NULL,
  CONSTRAINT fk_homepage_items_category FOREIGN KEY (category_id) REFERENCES store_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_pages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  title_ar VARCHAR(180) NULL,
  slug VARCHAR(200) NOT NULL,
  body MEDIUMTEXT NULL,
  body_ar MEDIUMTEXT NULL,
  seo_title VARCHAR(180) NULL,
  seo_description VARCHAR(320) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_pages_slug (slug),
  KEY idx_store_pages_status_published (status,published_at,id),
  CONSTRAINT fk_store_pages_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_store_pages_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_addresses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  label VARCHAR(80) NULL,
  recipient_name VARCHAR(160) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  governorate VARCHAR(120) NOT NULL,
  city VARCHAR(120) NOT NULL,
  area VARCHAR(120) NULL,
  address_line1 VARCHAR(255) NOT NULL,
  address_line2 VARCHAR(255) NULL,
  building VARCHAR(80) NULL,
  floor VARCHAR(40) NULL,
  apartment VARCHAR(40) NULL,
  postal_code VARCHAR(40) NULL,
  notes VARCHAR(500) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_customer_addresses_user (user_id,is_default,id),
  CONSTRAINT fk_customer_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_carts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  session_token CHAR(64) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  currency CHAR(3) NOT NULL DEFAULT 'EGP',
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_store_carts_user_status (user_id,status,updated_at),
  KEY idx_store_carts_session_status (session_token,status,updated_at),
  CONSTRAINT fk_store_carts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_cart_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_cart_items_cart_variant (cart_id,variant_id),
  CONSTRAINT fk_store_cart_items_cart FOREIGN KEY (cart_id) REFERENCES store_carts(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_cart_items_variant FOREIGN KEY (variant_id) REFERENCES store_product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_wishlists (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_wishlists_user (user_id),
  CONSTRAINT fk_store_wishlists_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_wishlist_items (
  wishlist_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (wishlist_id,product_id),
  KEY idx_store_wishlist_items_product (product_id),
  CONSTRAINT fk_store_wishlist_items_wishlist FOREIGN KEY (wishlist_id) REFERENCES store_wishlists(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_wishlist_items_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipping_zones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(140) NOT NULL,
  governorate VARCHAR(120) NULL,
  city_pattern VARCHAR(160) NULL,
  fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  free_shipping_threshold DECIMAL(12,2) NULL,
  cod_enabled TINYINT(1) NOT NULL DEFAULT 1,
  estimated_days_min TINYINT UNSIGNED NULL,
  estimated_days_max TINYINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (id),
  KEY idx_shipping_zones_status_sort (status,sort_order,id),
  KEY idx_shipping_zones_governorate (governorate,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number VARCHAR(40) NOT NULL,
  user_id INT UNSIGNED NULL,
  guest_email VARCHAR(190) NULL,
  customer_name VARCHAR(160) NOT NULL,
  customer_phone VARCHAR(40) NOT NULL,
  shipping_address_json MEDIUMTEXT NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  payment_status VARCHAR(30) NOT NULL DEFAULT 'pending',
  fulfillment_status VARCHAR(30) NOT NULL DEFAULT 'unfulfilled',
  currency CHAR(3) NOT NULL DEFAULT 'EGP',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  shipping_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  coupon_code VARCHAR(80) NULL,
  customer_note VARCHAR(1000) NULL,
  admin_note VARCHAR(2000) NULL,
  placed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_at DATETIME NULL,
  completed_at DATETIME NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_orders_number (order_number),
  KEY idx_store_orders_user_time (user_id,placed_at,id),
  KEY idx_store_orders_status_time (status,placed_at,id),
  KEY idx_store_orders_payment_status (payment_status,placed_at,id),
  KEY idx_store_orders_phone (customer_phone),
  CONSTRAINT fk_store_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  variant_id BIGINT UNSIGNED NULL,
  product_name VARCHAR(180) NOT NULL,
  brand_name VARCHAR(120) NULL,
  variant_label VARCHAR(100) NULL,
  sku VARCHAR(80) NULL,
  image_path VARCHAR(255) NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  unit_cost DECIMAL(12,2) NULL,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_store_order_items_order (order_id,id),
  KEY idx_store_order_items_product (product_id),
  CONSTRAINT fk_store_order_items_order FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_order_items_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE SET NULL,
  CONSTRAINT fk_store_order_items_variant FOREIGN KEY (variant_id) REFERENCES store_product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_order_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(30) NOT NULL,
  payment_status VARCHAR(30) NULL,
  fulfillment_status VARCHAR(30) NULL,
  note VARCHAR(1000) NULL,
  changed_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_store_order_history_order (order_id,created_at,id),
  CONSTRAINT fk_store_order_history_order FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_order_history_changed_by FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(60) NOT NULL,
  method VARCHAR(60) NOT NULL,
  provider_reference VARCHAR(160) NULL,
  idempotency_key VARCHAR(160) NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EGP',
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  provider_payload MEDIUMTEXT NULL,
  paid_at DATETIME NULL,
  refunded_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_payments_idempotency (idempotency_key),
  KEY idx_store_payments_order (order_id,created_at,id),
  KEY idx_store_payments_provider_ref (provider,provider_reference),
  CONSTRAINT fk_store_payments_order FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_coupons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(80) NOT NULL,
  discount_type VARCHAR(20) NOT NULL,
  discount_value DECIMAL(12,2) NOT NULL,
  minimum_order DECIMAL(12,2) NULL,
  maximum_discount DECIMAL(12,2) NULL,
  usage_limit INT UNSIGNED NULL,
  per_user_limit INT UNSIGNED NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_coupons_code (code),
  KEY idx_store_coupons_status_schedule (status,starts_at,ends_at),
  CONSTRAINT fk_store_coupons_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_coupon_redemptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  coupon_id INT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  discount_amount DECIMAL(12,2) NOT NULL,
  redeemed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_coupon_redemptions_order (order_id),
  KEY idx_store_coupon_redemptions_coupon (coupon_id,redeemed_at,id),
  KEY idx_store_coupon_redemptions_user (user_id,coupon_id,redeemed_at),
  CONSTRAINT fk_store_coupon_redemptions_coupon FOREIGN KEY (coupon_id) REFERENCES store_coupons(id) ON DELETE RESTRICT,
  CONSTRAINT fk_store_coupon_redemptions_order FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_coupon_redemptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  rating TINYINT UNSIGNED NOT NULL,
  longevity_rating TINYINT UNSIGNED NULL,
  projection_rating TINYINT UNSIGNED NULL,
  value_rating TINYINT UNSIGNED NULL,
  title VARCHAR(160) NULL,
  body TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  admin_reply TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_reviews_product_user (product_id,user_id),
  KEY idx_store_reviews_product_status (product_id,status,created_at,id),
  CONSTRAINT fk_store_reviews_product FOREIGN KEY (product_id) REFERENCES store_products(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_reviews_order FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_returns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'requested',
  reason VARCHAR(500) NOT NULL,
  customer_note VARCHAR(1000) NULL,
  admin_note VARCHAR(2000) NULL,
  refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  completed_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_store_returns_order (order_id,id),
  KEY idx_store_returns_status_time (status,requested_at,id),
  CONSTRAINT fk_store_returns_order FOREIGN KEY (order_id) REFERENCES store_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_returns_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_store_returns_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_return_items (
  return_id BIGINT UNSIGNED NOT NULL,
  order_item_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  restock TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (return_id,order_item_id),
  CONSTRAINT fk_store_return_items_return FOREIGN KEY (return_id) REFERENCES store_returns(id) ON DELETE CASCADE,
  CONSTRAINT fk_store_return_items_order_item FOREIGN KEY (order_item_id) REFERENCES store_order_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_redirects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_path VARCHAR(500) NOT NULL,
  destination_url VARCHAR(1000) NOT NULL,
  http_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_store_redirects_source (source_path),
  KEY idx_store_redirects_status (status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO store_settings(setting_key,setting_value) VALUES
('store_name','Perfume Store'),
('store_tagline','Find Your Signature'),
('currency','EGP'),
('free_shipping_threshold','0'),
('catalog_enabled','1');

-- Storefront content is intentionally empty on a fresh install.
-- Use Admin → Homepage and Catalog Data to create the real store content.

INSERT INTO feature_flags(flag_key,label,description,default_enabled) VALUES
('diagnostic_uploads','Diagnostic uploads','Allows diagnostic/log attachments in support tickets.',1),
('email_delivery','Email delivery','Allows verified accounts to receive transactional email when SMTP is configured.',1),
('notification_center','Notification center','Shows in-app account notifications and unread counters.',1),
('priority_support','Priority support','Marks the account for priority handling in support.',0),
('support_unread','Support unread badges','Shows unread support reply/request counters for users and administrators.',1);

INSERT INTO schema_migrations(version,description,checksum) VALUES
('037_v2_3_0_core_cleanup','v2.3.0 perfume-store core cleanup baseline','manual')
ON DUPLICATE KEY UPDATE description=VALUES(description),checksum=VALUES(checksum);

SET FOREIGN_KEY_CHECKS=1;
