-- v1.9.7 visitor analytics: first-party sessions, pageviews and conversion events
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

INSERT INTO schema_migrations(version,description,checksum)
VALUES('030_v1_9_7_visitor_analytics_seo','v1.9.7 visitor analytics: first-party sessions, pageviews and conversion events','manual')
ON DUPLICATE KEY UPDATE description=VALUES(description);
