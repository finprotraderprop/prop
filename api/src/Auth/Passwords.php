<?php
declare(strict_types=1);

namespace App\Auth;

use App\Http\HttpError;

final class Passwords
{
    public const MIN_LENGTH = 10;

    public static function validate(string $password): void
    {
        if (strlen($password) < self::MIN_LENGTH) {
            throw new HttpError(422, 'weak_password', 'Password must be at least ' . self::MIN_LENGTH . ' characters.');
        }
        if (strlen($password) > 256) {
            throw new HttpError(422, 'weak_password', 'Password is too long.');
        }
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Constant-ish time: always runs a hash verification, even for unknown users,
     * so response timing doesn't reveal which emails exist.
     */
    public static function verify(string $password, ?string $hash): bool
    {
        static $dummy = null;
        $dummy ??= password_hash('not-a-real-password', PASSWORD_DEFAULT);
        $ok = password_verify($password, $hash ?? $dummy);
        return $hash !== null && $ok;
    }
}
