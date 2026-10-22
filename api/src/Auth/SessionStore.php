<?php
declare(strict_types=1);

namespace App\Auth;

use App\Config;
use App\Db;

/**
 * Issues JWTs backed by a row in `sessions`. Every request re-checks the row,
 * so revoking a session or disabling an account takes effect immediately.
 */
final class SessionStore
{
    public const CLIENT_TTL = 7200;     // 2 hours
    public const ADMIN_TTL = 28800;     // 8 hours

    public static function issue(string $subjectType, int $subjectId, ?string $deviceId = null): array
    {
        $ttl = $subjectType === 'admin' ? self::ADMIN_TTL : self::CLIENT_TTL;
        $sid = bin2hex(random_bytes(16));
        $expires = time() + $ttl;
        Db::exec(
            'INSERT INTO sessions (id, subject_type, subject_id, device_id, expires_at) VALUES (?,?,?,?,?)',
            [$sid, $subjectType, $subjectId, $deviceId, gmdate('Y-m-d H:i:s', $expires)],
        );
        $token = Jwt::encode(
            ['sid' => $sid, 'typ' => $subjectType, 'sub' => $subjectId, 'iat' => time(), 'exp' => $expires],
            Config::require('JWT_SECRET'),
        );
        return ['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => $ttl];
    }

    /**
     * Validate a bearer token and its session row.
     * @return array{sid:string,typ:string,sub:int}|null
     */
    public static function verify(?string $bearer, string $expectedType): ?array
    {
        if ($bearer === null || $bearer === '') {
            return null;
        }
        $claims = Jwt::decode($bearer, Config::require('JWT_SECRET'));
        if ($claims === null || ($claims['typ'] ?? null) !== $expectedType) {
            return null;
        }
        $row = Db::one(
            'SELECT subject_type, subject_id, expires_at, revoked_at FROM sessions WHERE id = ?',
            [$claims['sid'] ?? ''],
        );
        if ($row === null || $row['revoked_at'] !== null
            || $row['subject_type'] !== $expectedType
            || (int) $row['subject_id'] !== (int) $claims['sub']
            || strtotime($row['expires_at'] . ' UTC') < time()) {
            return null;
        }
        return ['sid' => $claims['sid'], 'typ' => $expectedType, 'sub' => (int) $claims['sub']];
    }

    public static function revoke(string $sid): void
    {
        Db::exec('UPDATE sessions SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL', [gmdate('Y-m-d H:i:s'), $sid]);
    }

    public static function revokeAllFor(string $subjectType, int $subjectId): void
    {
        Db::exec(
            'UPDATE sessions SET revoked_at = ? WHERE subject_type = ? AND subject_id = ? AND revoked_at IS NULL',
            [gmdate('Y-m-d H:i:s'), $subjectType, $subjectId],
        );
    }
}
