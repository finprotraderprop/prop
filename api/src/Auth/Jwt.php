<?php
declare(strict_types=1);

namespace App\Auth;

/** HS256 JWT. Only the HS256 algorithm is accepted; the header's alg is never trusted. */
final class Jwt
{
    public static function encode(array $claims, string $secret): string
    {
        $h = self::b64(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $p = self::b64(json_encode($claims));
        return "{$h}.{$p}." . self::b64(hash_hmac('sha256', "{$h}.{$p}", $secret, true));
    }

    /** @return array|null claims, or null if the token is malformed, forged or expired */
    public static function decode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;
        $expected = self::b64(hash_hmac('sha256', "{$h}.{$p}", $secret, true));
        if (!hash_equals($expected, $s)) {
            return null;
        }
        $header = json_decode(self::unb64($h), true);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            return null;
        }
        $claims = json_decode(self::unb64($p), true);
        if (!is_array($claims) || ($claims['exp'] ?? 0) < time()) {
            return null;
        }
        return $claims;
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function unb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'));
    }
}
