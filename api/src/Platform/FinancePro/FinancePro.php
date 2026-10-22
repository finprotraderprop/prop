<?php
declare(strict_types=1);

namespace App\Platform\FinancePro;

use App\Config;
use App\Platform\TradingPlatform;

final class FinancePro implements TradingPlatform
{
    public function __construct(
        private readonly FinProHttp $http,
        private readonly ServiceTokenProvider $tokens,
        private readonly string $authBase,
        private readonly string $brokerBase,
    ) {
    }

    public static function fromConfig(): self
    {
        $http = new FinProHttp(Config::get('FINPRO_APP_ID', 'finpro-crm-client'));
        $auth = rtrim(Config::require('FINPRO_AUTH_BASE'), '/');
        $tokens = new ServiceTokenProvider(
            $http,
            $auth,
            Config::require('FINPRO_APP_SECRET'),
            dirname(__DIR__, 3) . '/storage/finpro-token.json',
        );
        return new self($http, $tokens, $auth, rtrim(Config::require('FINPRO_BROKER_BASE'), '/'));
    }

    /** Auth headers for a service-token call; operator id is set server-side only. */
    private function headers(?string $operatorId = null): array
    {
        $h = ['Authorization' => 'Bearer ' . $this->tokens->token()];
        if ($operatorId !== null && $operatorId !== '') {
            $h['X-External-Operator-Id'] = substr($operatorId, 0, 128);
        }
        return $h;
    }

    public function createAccount(array $params, string $operatorId): array
    {
        // Schema: BrokerBackendAccountCreateRequest (Email required; AccountId/UserId optional).
        $res = $this->http->request('POST', $this->brokerBase . '/api/v1/broker/accounts', $params, $this->headers($operatorId));
        return $res['Data'] ?? [];
    }

    public function listAccounts(array $query = []): array
    {
        $res = $this->http->request('GET', $this->brokerBase . '/api/v1/broker/accounts', null, $this->headers(), $query);
        return $res['Data'] ?? [];
    }

    public function getAccount(int $accountId): array
    {
        $res = $this->http->request('GET', $this->brokerBase . "/api/v1/broker/accounts/{$accountId}", null, $this->headers());
        return $res['Data'] ?? [];
    }

    public function listGroups(array $query = []): array
    {
        $res = $this->http->request('GET', $this->brokerBase . '/api/v1/broker/account-groups', null, $this->headers(), $query);
        return $res['Data'] ?? [];
    }

    public function bulkAction(array $accountIds, string $action, array $extra, string $operatorId): array
    {
        $body = array_merge(['accountIds' => array_values($accountIds), 'action' => $action], $extra);
        $res = $this->http->request('POST', $this->brokerBase . '/api/v1/broker/accounts/bulk-actions', $body, $this->headers($operatorId));
        return $res['Data'] ?? $res;
    }

    public function createInvestorSession(int $platformUserId, string $clientId, ?string $deviceId = null): array
    {
        $h = $this->headers();
        if ($deviceId !== null) {
            $h['X-Device-Id'] = $deviceId;
        }
        $res = $this->http->request('POST', $this->authBase . '/api/v1/internal/auth/investor-token', [
            'UserId' => $platformUserId,
            'ClientId' => $clientId, // finpro-investor-app | finpro-investor-web
        ], $h);
        return $res['Data'] ?? [];
    }
}
