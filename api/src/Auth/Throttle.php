<?php
declare(strict_types=1);

namespace App\Auth;

use App\Db;
use App\Http\HttpError;

/** Blocks credential stuffing: too many failures per identifier or per IP in a window. */
final class Throttle
{
    private const WINDOW = 900;        // 15 minutes
    private const MAX_PER_IDENT = 5;
    private const MAX_PER_IP = 20;

    public static function check(string $scope, string $identifier, string $ip): void
    {
        $since = gmdate('Y-m-d H:i:s', time() - self::WINDOW);
        $byIdent = (int) (Db::one(
            'SELECT COUNT(*) c FROM login_attempts WHERE scope = ? AND identifier = ? AND created_at >= ?',
            [$scope, strtolower($identifier), $since],
        )['c'] ?? 0);
        $byIp = $ip === '' ? 0 : (int) (Db::one(
            'SELECT COUNT(*) c FROM login_attempts WHERE scope = ? AND ip = ? AND created_at >= ?',
            [$scope, $ip, $since],
        )['c'] ?? 0);
        if ($byIdent >= self::MAX_PER_IDENT || $byIp >= self::MAX_PER_IP) {
            throw new HttpError(429, 'too_many_attempts', 'Too many failed attempts. Try again later.');
        }
    }

    public static function fail(string $scope, string $identifier, string $ip): void
    {
        Db::exec(
            'INSERT INTO login_attempts (scope, identifier, ip, created_at) VALUES (?,?,?,?)',
            [$scope, strtolower($identifier), $ip, gmdate('Y-m-d H:i:s')],
        );
    }

    public static function clear(string $scope, string $identifier): void
    {
        Db::exec('DELETE FROM login_attempts WHERE scope = ? AND identifier = ?', [$scope, strtolower($identifier)]);
    }
}
