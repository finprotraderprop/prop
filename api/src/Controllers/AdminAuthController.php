<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Guard;
use App\Auth\Passwords;
use App\Auth\SessionStore;
use App\Auth\Throttle;
use App\Db;
use App\Http\HttpError;
use App\Http\Request;

final class AdminAuthController
{
    public function login(Request $req): array
    {
        $username = strtolower(trim((string) $req->input('username', '')));
        $password = (string) $req->input('password', '');
        Throttle::check('admin', $username, $req->ip);

        $a = Db::one('SELECT id, password_hash, status FROM admin_users WHERE username = ?', [$username]);
        $ok = Passwords::verify($password, $a['password_hash'] ?? null);
        if (!$ok || $a === null || $a['status'] !== 'active') {
            Throttle::fail('admin', $username, $req->ip);
            throw new HttpError(401, 'invalid_credentials', 'Invalid username or password.');
        }
        Throttle::clear('admin', $username);
        return ['data' => SessionStore::issue('admin', (int) $a['id'])];
    }

    public function logout(Request $req): array
    {
        // Any authenticated admin may log out; no specific permission needed.
        $s = SessionStore::verify($req->bearer(), 'admin');
        if ($s === null) {
            throw new HttpError(401, 'unauthorized');
        }
        SessionStore::revoke($s['sid']);
        return ['data' => ['ok' => true]];
    }

    public function me(Request $req): array
    {
        // `me` needs only a valid session; the permission list drives the admin UI menu.
        $s = SessionStore::verify($req->bearer(), 'admin');
        if ($s === null) {
            throw new HttpError(401, 'unauthorized');
        }
        $a = Db::one('SELECT id, username, status FROM admin_users WHERE id = ?', [$s['sub']]);
        if ($a === null || $a['status'] !== 'active') {
            throw new HttpError(401, 'unauthorized');
        }
        $perms = array_column(Db::all(
            'SELECT DISTINCT rp.permission FROM admin_user_roles ur
             JOIN role_permissions rp ON rp.role_id = ur.role_id WHERE ur.admin_user_id = ?',
            [$a['id']],
        ), 'permission');
        return ['data' => ['id' => (int) $a['id'], 'username' => $a['username'], 'permissions' => $perms]];
    }
}
