<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Guard;
use App\Auth\SessionStore;
use App\Db;
use App\Http\HttpError;
use App\Http\Request;
use App\Platform\FinancePro\FinProException;
use App\Platform\Platforms;

final class AdminController
{
    /** Bulk actions an admin may request. `Delete`/`Restore` need their own, stronger permission. */
    private const ACTIONS = ['Enable', 'Disable', 'SetReadOnly', 'UnsetReadOnly', 'ChangeGroup', 'ChangeLeverage'];
    private const DESTRUCTIVE = ['Delete', 'Restore'];

    public function listClients(Request $req): array
    {
        Guard::admin($req, 'clients.read');
        $page = max(1, (int) ($req->query['page'] ?? 1));
        $size = min(100, max(1, (int) ($req->query['page_size'] ?? 20)));
        $kw = trim((string) ($req->query['keyword'] ?? ''));

        $where = '';
        $args = [];
        if ($kw !== '') {
            $where = 'WHERE email LIKE ? OR real_name LIKE ?';
            $like = '%' . addcslashes($kw, '%_\\') . '%';
            $args = [$like, $like];
        }
        $total = (int) Db::one("SELECT COUNT(*) c FROM clients {$where}", $args)['c'];
        $rows = Db::all(
            "SELECT id, email, real_name, country, status, finpro_user_id, created_at FROM clients {$where}
             ORDER BY id DESC LIMIT {$size} OFFSET " . (($page - 1) * $size),
            $args,
        );
        return ['data' => $rows, 'meta' => ['page' => $page, 'page_size' => $size, 'total' => $total]];
    }

    public function getClient(Request $req, array $p): array
    {
        Guard::admin($req, 'clients.read');
        $c = Db::one(
            'SELECT id, email, real_name, mobile_phone, country, status, finpro_user_id, created_at FROM clients WHERE id = ?',
            [(int) $p['id']],
        );
        if ($c === null) {
            throw new HttpError(404, 'not_found');
        }
        $c['trading_accounts'] = Db::all(
            'SELECT platform, platform_account_id, group_id, created_at FROM client_trading_accounts WHERE client_id = ?',
            [(int) $p['id']],
        );
        return ['data' => $c];
    }

    public function setClientStatus(Request $req, array $p): array
    {
        $admin = Guard::admin($req, 'clients.write');
        $status = (string) $req->input('status', '');
        if (!in_array($status, ['active', 'disabled'], true)) {
            throw new HttpError(422, 'invalid_status');
        }
        $id = (int) $p['id'];
        if (Db::exec('UPDATE clients SET status = ? WHERE id = ?', [$status, $id]) === 0
            && Db::one('SELECT id FROM clients WHERE id = ?', [$id]) === null) {
            throw new HttpError(404, 'not_found');
        }
        if ($status === 'disabled') {
            SessionStore::revokeAllFor('client', $id);
        }
        $this->audit($admin['id'], 'client.status', "client:{$id}", ['status' => $status], $req);
        return ['data' => ['id' => $id, 'status' => $status]];
    }

    public function listPlatformAccounts(Request $req): array
    {
        Guard::admin($req, 'accounts.read');
        $allowed = ['Keyword', 'GroupId', 'GroupIds', 'AccountIds', 'IncludeDisabled', 'CurrentPage', 'PageSize', 'SortBy', 'SortDirection'];
        $query = array_intersect_key($req->query, array_flip($allowed));
        return ['data' => $this->platform(fn() => Platforms::get()->listAccounts($query))];
    }

    public function getPlatformAccount(Request $req, array $p): array
    {
        Guard::admin($req, 'accounts.read');
        return ['data' => $this->platform(fn() => Platforms::get()->getAccount((int) $p['id']))];
    }

    public function listGroups(Request $req): array
    {
        Guard::admin($req, 'accounts.read');
        $allowed = ['Keyword', 'IsEnabled', 'CurrentPage', 'PageSize'];
        $query = array_intersect_key($req->query, array_flip($allowed));
        return ['data' => $this->platform(fn() => Platforms::get()->listGroups($query))];
    }

    public function bulkAction(Request $req): array
    {
        $action = (string) $req->input('action', '');
        $permission = in_array($action, self::DESTRUCTIVE, true) ? 'accounts.delete' : 'accounts.write';
        if (!in_array($action, array_merge(self::ACTIONS, self::DESTRUCTIVE), true)) {
            throw new HttpError(422, 'invalid_action');
        }
        $admin = Guard::admin($req, $permission);

        $ids = $req->input('account_ids');
        if (!is_array($ids) || $ids === [] || count($ids) > 200) {
            throw new HttpError(422, 'invalid_account_ids', 'Provide 1-200 account ids.');
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (in_array(0, $ids, true)) {
            throw new HttpError(422, 'invalid_account_ids');
        }

        $extra = [];
        if ($action === 'ChangeGroup') {
            $extra['groupId'] = (int) $req->input('group_id', 0);
            if ($extra['groupId'] <= 0) {
                throw new HttpError(422, 'group_id_required');
            }
        }
        if ($action === 'ChangeLeverage') {
            $extra['leverage'] = (int) $req->input('leverage', 0);
            if ($extra['leverage'] <= 0) {
                throw new HttpError(422, 'leverage_required');
            }
        }
        $extra['reason'] = mb_substr((string) $req->input('reason', "CRM admin {$admin['username']}"), 0, 200);

        $result = $this->platform(fn() => Platforms::get()->bulkAction($ids, $action, $extra, 'admin:' . $admin['id']));
        $this->audit($admin['id'], 'platform.bulk_action', $action, ['account_ids' => $ids] + $extra, $req);
        return ['data' => $result];
    }

    /** Run a platform call and turn upstream failures into a clean 502 (no upstream detail leaked). */
    private function platform(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (FinProException $e) {
            error_log("admin platform error http={$e->httpStatus} code={$e->errCode} msg={$e->getMessage()}");
            throw new HttpError(502, 'platform_error', 'The trading platform request failed.');
        }
    }

    private function audit(int $adminId, string $action, string $target, array $detail, Request $req): void
    {
        Db::exec(
            'INSERT INTO admin_audit_log (admin_user_id, action, target, detail, ip) VALUES (?,?,?,?,?)',
            [$adminId, $action, $target, json_encode($detail), $req->ip],
        );
    }
}
