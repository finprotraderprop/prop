<?php
declare(strict_types=1);

// Usage: php api/bin/create-admin.php <username>      (password is prompted; never passed as an argument)
require __DIR__ . '/../src/autoload.php';

use App\Auth\Passwords;
use App\Db;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$username = strtolower(trim($argv[1] ?? ''));
if ($username === '') {
    fwrite(STDERR, "Usage: php api/bin/create-admin.php <username>\n");
    exit(1);
}
fwrite(STDOUT, 'Password (min ' . Passwords::MIN_LENGTH . ' chars): ');
system('stty -echo 2>/dev/null');
$password = rtrim((string) fgets(STDIN), "\r\n");
system('stty echo 2>/dev/null');
fwrite(STDOUT, "\n");
Passwords::validate($password);

$pdo = Db::pdo();
$pdo->beginTransaction();
Db::exec('INSERT IGNORE INTO roles (name) VALUES (?)', ['super_admin']);
$roleId = (int) Db::one('SELECT id FROM roles WHERE name = ?', ['super_admin'])['id'];
Db::exec('INSERT IGNORE INTO role_permissions (role_id, permission) VALUES (?, ?)', [$roleId, '*']);
$adminId = Db::insert('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)', [$username, Passwords::hash($password)]);
Db::exec('INSERT INTO admin_user_roles (admin_user_id, role_id) VALUES (?, ?)', [$adminId, $roleId]);
$pdo->commit();
fwrite(STDOUT, "Created super_admin '{$username}' (id {$adminId}).\n");
