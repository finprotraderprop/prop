-- SQLite mirror of api/db/migrations/*.sql, used only by tests/run.php.
-- Keep in sync with the MySQL migrations when columns change.
CREATE TABLE clients (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  real_name TEXT, mobile_phone TEXT, country TEXT,
  status TEXT NOT NULL DEFAULT 'active',
  finpro_user_id INTEGER,
  open_lock_until TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE client_trading_accounts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  client_id INTEGER NOT NULL,
  platform TEXT NOT NULL DEFAULT 'financepro',
  platform_account_id INTEGER NOT NULL,
  group_id INTEGER,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (platform, platform_account_id)
);
CREATE TABLE admin_users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'active',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE);
CREATE TABLE role_permissions (role_id INTEGER NOT NULL, permission TEXT NOT NULL, PRIMARY KEY (role_id, permission));
CREATE TABLE admin_user_roles (admin_user_id INTEGER NOT NULL, role_id INTEGER NOT NULL, PRIMARY KEY (admin_user_id, role_id));
CREATE TABLE sessions (
  id TEXT PRIMARY KEY,
  subject_type TEXT NOT NULL,
  subject_id INTEGER NOT NULL,
  device_id TEXT,
  expires_at TEXT NOT NULL,
  revoked_at TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE admin_audit_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  admin_user_id INTEGER NOT NULL,
  action TEXT NOT NULL,
  target TEXT NOT NULL DEFAULT '',
  detail TEXT,
  ip TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  scope TEXT NOT NULL,
  identifier TEXT NOT NULL,
  ip TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
