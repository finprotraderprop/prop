<?php
declare(strict_types=1);

namespace App\Platform;

/**
 * Everything the CRM needs from a trading platform.
 * Phase 1: FinancePro only. MT4/MT5 will implement this later.
 */
interface TradingPlatform
{
    /** Create a trading account. $params follow the platform's create-account schema. */
    public function createAccount(array $params, string $operatorId): array;

    /** @return array<int,array> accounts matching $query (keyword, groupIds, page, pageSize ...) */
    public function listAccounts(array $query = []): array;

    public function getAccount(int $accountId): array;

    /** @return array<int,array> */
    public function listGroups(array $query = []): array;

    /**
     * Bulk enable / disable / change group / change leverage.
     * @param int[] $accountIds
     */
    public function bulkAction(array $accountIds, string $action, array $extra, string $operatorId): array;

    /** Mint a platform session for a CRM-authenticated investor. */
    public function createInvestorSession(int $platformUserId, string $clientId, ?string $deviceId = null): array;
}
