<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Guard;
use App\Config;
use App\Db;
use App\Http\HttpError;
use App\Http\Request;
use App\Platform\FinancePro\FinProException;
use App\Platform\Platforms;

final class OnboardingController
{
    private const MAX_ACCOUNTS_PER_CLIENT = 5;
    private const LOCK_SECONDS = 30;

    /** POST /api/client/trading-accounts — open a FinancePro account for the logged-in client. */
    public function openAccount(Request $req): array
    {
        $client = Guard::client($req);

        $row = Db::one('SELECT email, real_name, finpro_user_id FROM clients WHERE id = ?', [$client['id']]);
        $groupId = $this->resolveGroup($req->input('group_id'));

        $count = (int) Db::one('SELECT COUNT(*) c FROM client_trading_accounts WHERE client_id = ?', [$client['id']])['c'];
        if ($count >= self::MAX_ACCOUNTS_PER_CLIENT) {
            throw new HttpError(422, 'account_limit_reached');
        }

        // Atomic compare-and-set lock: only one in-flight open per client.
        $now = gmdate('Y-m-d H:i:s');
        $until = gmdate('Y-m-d H:i:s', time() + self::LOCK_SECONDS);
        $locked = Db::exec(
            'UPDATE clients SET open_lock_until = ? WHERE id = ? AND (open_lock_until IS NULL OR open_lock_until < ?)',
            [$until, $client['id'], $now],
        );
        if ($locked !== 1) {
            throw new HttpError(409, 'request_in_progress');
        }

        try {
            $created = Platforms::get()->createAccount(
                $this->toPlatformRequest($row, $groupId),
                'client:' . $client['id'],
            );
        } catch (FinProException $e) {
            Db::exec('UPDATE clients SET open_lock_until = NULL WHERE id = ?', [$client['id']]);
            error_log("open-account platform error client={$client['id']} http={$e->httpStatus} code={$e->errCode}");
            throw new HttpError(502, 'platform_unavailable', 'Could not open the trading account. Please try again later.');
        }

        $accountId = (int) ($created['AccountId'] ?? $created['Id'] ?? 0);
        $userId = isset($created['UserId']) ? (int) $created['UserId'] : null;
        if ($accountId <= 0) {
            Db::exec('UPDATE clients SET open_lock_until = NULL WHERE id = ?', [$client['id']]);
            error_log("open-account: platform response had no account id client={$client['id']}");
            throw new HttpError(502, 'platform_bad_response');
        }

        try {
            Db::insert(
                'INSERT INTO client_trading_accounts (client_id, platform, platform_account_id, group_id) VALUES (?,?,?,?)',
                [$client['id'], 'financepro', $accountId, $groupId],
            );
            if ($userId !== null && $row['finpro_user_id'] === null) {
                Db::exec('UPDATE clients SET finpro_user_id = ? WHERE id = ?', [$userId, $client['id']]);
            }
        } catch (\Throwable $e) {
            // The account exists on the platform but we failed to record it: log loudly for reconciliation.
            error_log("RECONCILE: financepro account {$accountId} created for client {$client['id']} but not saved: " . $e->getMessage());
            throw new HttpError(500, 'account_created_not_saved', 'Account opened; please contact support to link it.');
        } finally {
            Db::exec('UPDATE clients SET open_lock_until = NULL WHERE id = ?', [$client['id']]);
        }

        return [201, ['data' => ['platform' => 'financepro', 'account_id' => $accountId, 'group_id' => $groupId]]];
    }

    /** GET /api/client/trading-accounts */
    public function listAccounts(Request $req): array
    {
        $client = Guard::client($req);
        return ['data' => Db::all(
            'SELECT platform, platform_account_id, group_id, created_at FROM client_trading_accounts WHERE client_id = ? ORDER BY id',
            [$client['id']],
        )];
    }

    /** Clients may only pick from an allow-list of groups; they can never choose arbitrary ones. */
    private function resolveGroup(mixed $requested): int
    {
        $default = (int) Config::require('FINPRO_DEFAULT_GROUP_ID');
        $allowed = array_map('intval', array_filter(explode(',', (string) Config::get('FINPRO_CLIENT_GROUP_IDS', ''))));
        $allowed[] = $default;
        if ($requested === null || $requested === '') {
            return $default;
        }
        if (!in_array((int) $requested, $allowed, true)) {
            throw new HttpError(422, 'group_not_allowed');
        }
        return (int) $requested;
    }

    /**
     * TODO(confirm against Swagger): field names for BrokerBackendAccountCreateRequest.
     * Email is documented as required; GroupId and the display-name field are best guesses.
     * Everything FinancePro-specific about the request shape lives in this one method.
     */
    private function toPlatformRequest(array $client, int $groupId): array
    {
        return array_filter([
            'Email' => $client['email'],
            'RealName' => $client['real_name'],
            'GroupId' => $groupId,
            'UserId' => $client['finpro_user_id'] !== null ? (int) $client['finpro_user_id'] : null,
        ], static fn($v) => $v !== null);
    }
}
