-- v4.2.0 stabilization: complete return/refund audit trail.
ALTER TABLE store_returns
  ADD COLUMN refund_method VARCHAR(40) NULL AFTER refund_amount,
  ADD COLUMN refund_reference VARCHAR(160) NULL AFTER refund_method,
  ADD COLUMN refunded_at DATETIME NULL AFTER refund_reference;

INSERT INTO store_settings(setting_key,setting_value) VALUES ('return_window_days','14') ON DUPLICATE KEY UPDATE setting_value=setting_value;
