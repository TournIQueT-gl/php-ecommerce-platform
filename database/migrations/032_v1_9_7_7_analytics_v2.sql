-- v1.9.7.7 analytics v2 performance, visitor intelligence queue and query indexes
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

INSERT INTO schema_migrations(version,description,checksum)
VALUES('032_v1_9_7_7_analytics_v2','v1.9.7.7 analytics v2 performance, visitor intelligence queue and query indexes','manual')
ON DUPLICATE KEY UPDATE description=VALUES(description);
