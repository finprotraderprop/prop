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

final class ClientAuthController
{
    public function register(Request $req): array
    {
        $email = strtolower(trim((string) $req->input('email', '')));
        $password = (string) $req->input('password', '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            throw new HttpError(422, 'invalid_email');
        }
        Passwords::validate($password);

        if (Db::one('SELECT id FROM clients WHERE email = ?', [$email]) !== null) {
            // Generic message: don't confirm which emails are registered beyond what's unavoidable.
            throw new HttpError(409, 'email_unavailable', 'This email cannot be used.');
        }
        $id = Db::insert(
            'INSERT INTO clients (email, password_hash, real_name, mobile_phone, country) VALUES (?,?,?,?,?)',
            [
                $email,
                Passwords::hash($password),
                self::str($req->input('real_name'), 128),
                self::str($req->input('mobile_phone'), 64),
                self::str($req->input('country'), 64),
            ],
        );
        $session = SessionStore::issue('client', $id, self::str($req->headers['x-device-id'] ?? null, 128));
        return [201, ['data' => ['client_id' => $id] + $session]];
    }

    public function login(Request $req): array
    {
        $email = strtolower(trim((string) $req->input('email', '')));
        $password = (string) $req->input('password', '');
        Throttle::check('client', $email, $req->ip);

        $c = Db::one('SELECT id, password_hash, status FROM clients WHERE email = ?', [$email]);
        $ok = Passwords::verify($password, $c['password_hash'] ?? null);
        if (!$ok || $c === null || $c['status'] !== 'active') {
            Throttle::fail('client', $email, $req->ip);
            throw new HttpError(401, 'invalid_credentials', 'Invalid email or password.');
        }
        Throttle::clear('client', $email);
        $session = SessionStore::issue('client', (int) $c['id'], self::str($req->headers['x-device-id'] ?? null, 128));
        return ['data' => ['client_id' => (int) $c['id']] + $session];
    }

    public function logout(Request $req): array
    {
        $c = Guard::client($req);
        SessionStore::revoke($c['sid']);
        return ['data' => ['ok' => true]];
    }

    public function me(Request $req): array
    {
        $c = Guard::client($req);
        $row = Db::one(
            'SELECT id, email, real_name, mobile_phone, country, created_at FROM clients WHERE id = ?',
            [$c['id']],
        );
        $accounts = Db::all(
            'SELECT platform, platform_account_id, group_id FROM client_trading_accounts WHERE client_id = ? ORDER BY id',
            [$c['id']],
        );
        return ['data' => $row + ['trading_accounts' => $accounts]];
    }

    private static function str(mixed $v, int $max): ?string
    {
        if (!is_string($v) || trim($v) === '') {
            return null;
        }
        return mb_substr(trim($v), 0, $max);
    }
}
