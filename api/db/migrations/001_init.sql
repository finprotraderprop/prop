-- Milestone 1: auth + onboarding. Money state is out of scope for now.
CREATE TABLE IF NOT EXISTS clients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  real_name VARCHAR(128) NULL,
  mobile_phone VARCHAR(64) NULL,
  country VARCHAR(64) NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  finpro_user_id BIGINT UNSIGNED NULL,           -- FinancePro investor UserId
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS client_trading_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NOT NULL,
  platform VARCHAR(32) NOT NULL DEFAULT 'financepro',
  platform_account_id BIGINT UNSIGNED NOT NULL,
  group_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_platform_account (platform, platform_account_id),
  FOREIGN KEY (client_id) REFERENCES clients(id)
);

CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission VARCHAR(96) NOT NULL,
  PRIMARY KEY (role_id, permission),
  FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE IF NOT EXISTS admin_user_roles (
  admin_user_id BIGINT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (admin_user_id, role_id),
  FOREIGN KEY (admin_user_id) REFERENCES admin_users(id),
  FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- JWTs are checked against this table on every request, so revocation is immediate.
CREATE TABLE IF NOT EXISTS sessions (
  id CHAR(32) PRIMARY KEY,
  subject_type ENUM('client','admin') NOT NULL,
  subject_id BIGINT UNSIGNED NOT NULL,
  device_id VARCHAR(128) NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_subject (subject_type, subject_id)
);
