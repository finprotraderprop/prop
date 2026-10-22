<?php
declare(strict_types=1);

namespace App;

use App\Controllers\AdminAuthController;
use App\Controllers\AdminController;
use App\Controllers\ClientAuthController;
use App\Controllers\OnboardingController;
use App\Http\HttpError;
use App\Http\Request;
use App\Http\Router;

final class App
{
    public static function router(): Router
    {
        $r = new Router();
        $r->add('GET', '/api/health', fn() => ['ok' => true]);

        $ca = new ClientAuthController();
        $r->add('POST', '/api/client/register', [$ca, 'register']);
        $r->add('POST', '/api/client/login', [$ca, 'login']);
        $r->add('POST', '/api/client/logout', [$ca, 'logout']);
        $r->add('GET', '/api/client/me', [$ca, 'me']);

        $ob = new OnboardingController();
        $r->add('GET', '/api/client/trading-accounts', [$ob, 'listAccounts']);
        $r->add('POST', '/api/client/trading-accounts', [$ob, 'openAccount']);

        $aa = new AdminAuthController();
        $r->add('POST', '/api/admin/login', [$aa, 'login']);
        $r->add('POST', '/api/admin/logout', [$aa, 'logout']);
        $r->add('GET', '/api/admin/me', [$aa, 'me']);

        $ad = new AdminController();
        $r->add('GET', '/api/admin/clients', [$ad, 'listClients']);
        $r->add('GET', '/api/admin/clients/{id}', [$ad, 'getClient']);
        $r->add('POST', '/api/admin/clients/{id}/status', [$ad, 'setClientStatus']);
        $r->add('GET', '/api/admin/trading-accounts', [$ad, 'listPlatformAccounts']);
        $r->add('GET', '/api/admin/trading-accounts/{id}', [$ad, 'getPlatformAccount']);
        $r->add('POST', '/api/admin/trading-accounts/bulk-actions', [$ad, 'bulkAction']);
        $r->add('GET', '/api/admin/account-groups', [$ad, 'listGroups']);
        return $r;
    }

    /** @return array{0:int,1:array} */
    public static function handle(Request $req): array
    {
        try {
            return self::router()->dispatch($req);
        } catch (HttpError $e) {
            return [$e->status, ['error' => $e->errorCode, 'message' => $e->getMessage()]];
        } catch (\Throwable $e) {
            error_log('unhandled: ' . $e::class . ': ' . $e->getMessage());
            return [500, ['error' => 'server_error']];
        }
    }
}
