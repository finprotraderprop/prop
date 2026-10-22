-- Every admin write is recorded here.
CREATE TABLE IF NOT EXISTS admin_audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(64) NOT NULL,
  target VARCHAR(128) NOT NULL DEFAULT '',
  detail TEXT NULL,
  ip VARCHAR(64) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_admin (admin_user_id, created_at)
);

-- Failed-login throttling (per account identifier and per IP).
CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scope VARCHAR(16) NOT NULL,          -- client | admin
  identifier VARCHAR(255) NOT NULL,    -- email / username, lower-cased
  ip VARCHAR(64) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ident (scope, identifier, created_at),
  KEY idx_ip (scope, ip, created_at)
);
