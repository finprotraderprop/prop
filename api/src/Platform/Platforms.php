<?php
declare(strict_types=1);

namespace App\Platform;

use App\Platform\FinancePro\FinancePro;

/** Single place that decides which trading platform the CRM talks to. Phase 1: FinancePro only. */
final class Platforms
{
    private static ?TradingPlatform $instance = null;

    public static function set(?TradingPlatform $p): void
    {
        self::$instance = $p;
    }

    public static function get(): TradingPlatform
    {
        return self::$instance ??= FinancePro::fromConfig();
    }
}
