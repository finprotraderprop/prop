<?php
declare(strict_types=1);

namespace App\Auth;

use App\Db;
use App\Http\HttpError;
use App\Http\Request;

final class Guard
{
    /** @return array{id:int,sid:string,email:string,status:string} */
    public static function client(Request $req): array
    {
        $s = SessionStore::verify($req->bearer(), 'client');
        if ($s === null) {
            throw new HttpError(401, 'unauthorized');
        }
        $c = Db::one('SELECT id, email, status FROM clients WHERE id = ?', [$s['sub']]);
        if ($c === null || $c['status'] !== 'active') {
            throw new HttpError(401, 'unauthorized');
        }
        return ['id' => (int) $c['id'], 'sid' => $s['sid'], 'email' => $c['email'], 'status' => $c['status']];
    }

    /**
     * Authenticate an admin and require a permission. Permissions are read from the DB on
     * every request, so a role change applies immediately. The `*` permission grants all.
     * @return array{id:int,sid:string,username:string,permissions:string[]}
     */
    public static function admin(Request $req, string $permission): array
    {
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
        if (!in_array('*', $perms, true) && !in_array($permission, $perms, true)) {
            throw new HttpError(403, 'forbidden', 'Missing permission: ' . $permission);
        }
        return ['id' => (int) $a['id'], 'sid' => $s['sid'], 'username' => $a['username'], 'permissions' => $perms];
    }
}
