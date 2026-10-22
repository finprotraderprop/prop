<?php
declare(strict_types=1);

// Run: php api/tests/run.php   (uses in-memory SQLite and a fake trading platform; no network)
require __DIR__ . '/../src/autoload.php';

use App\App;
use App\Auth\Jwt;
use App\Auth\Passwords;
use App\Db;
use App\Http\Request;
use App\Platform\FinancePro\FinProException;
use App\Platform\Platforms;
use App\Platform\TradingPlatform;

putenv('JWT_SECRET=test-secret-test-secret-test-secret');
putenv('FINPRO_DEFAULT_GROUP_ID=7');
putenv('FINPRO_CLIENT_GROUP_IDS=7,8');

class FakePlatform implements TradingPlatform
{
    public array $created = [];
    public array $bulk = [];
    public bool $fail = false;
    private int $next = 60001;

    public function createAccount(array $params, string $operatorId): array
    {
        if ($this->fail) {
            throw new FinProException('boom', 500);
        }
        $this->created[] = [$params, $operatorId];
        return ['AccountId' => $this->next++, 'UserId' => 900];
    }
    public function listAccounts(array $query = []): array { return [['AccountId' => 1]]; }
    public function getAccount(int $accountId): array { return ['AccountId' => $accountId]; }
    public function listGroups(array $query = []): array { return [['Id' => 7]]; }
    public function bulkAction(array $accountIds, string $action, array $extra, string $operatorId): array
    {
        $this->bulk[] = [$accountIds, $action, $extra, $operatorId];
        return ['done' => count($accountIds)];
    }
    public function createInvestorSession(int $platformUserId, string $clientId, ?string $deviceId = null): array { return []; }
}

$pass = 0;
$fail = 0;
function check(string $name, bool $cond, string $detail = ''): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok   {$name}\n"; }
    else { $fail++; echo "  FAIL {$name} {$detail}\n"; }
}

function fresh(): FakePlatform
{
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec(file_get_contents(__DIR__ . '/schema.sqlite.sql'));
    Db::set($pdo);
    $p = new FakePlatform();
    Platforms::set($p);
    return $p;
}

function call(string $method, string $path, array $body = [], ?string $token = null, array $query = [], string $ip = '1.1.1.1'): array
{
    $headers = $token ? ['authorization' => "Bearer {$token}"] : [];
    [$status, $resp] = App::handle(new Request($method, $path, $query, $body, $headers, $ip));
    return [$status, $resp];
}

function makeAdmin(string $username, array $perms): int
{
    $id = Db::insert('INSERT INTO admin_users (username, password_hash) VALUES (?,?)', [$username, Passwords::hash('admin-password-1')]);
    $role = Db::insert('INSERT INTO roles (name) VALUES (?)', ["role-{$username}"]);
    foreach ($perms as $perm) {
        Db::exec('INSERT INTO role_permissions (role_id, permission) VALUES (?,?)', [$role, $perm]);
    }
    Db::exec('INSERT INTO admin_user_roles (admin_user_id, role_id) VALUES (?,?)', [$id, $role]);
    return $id;
}

function adminToken(string $username): string
{
    return call('POST', '/api/admin/login', ['username' => $username, 'password' => 'admin-password-1'])[1]['data']['access_token'];
}

function registerClient(string $email = 'a@example.com'): string
{
    return call('POST', '/api/client/register', ['email' => $email, 'password' => 'correct-horse-9'])[1]['data']['access_token'];
}

echo "client auth\n";
$plat = fresh();
[$s, $r] = call('POST', '/api/client/register', ['email' => 'a@example.com', 'password' => 'correct-horse-9']);
check('register returns 201 + token', $s === 201 && !empty($r['data']['access_token']));
$tok = $r['data']['access_token'];
check('me works with token', call('GET', '/api/client/me', [], $tok)[0] === 200);
check('duplicate email -> 409', call('POST', '/api/client/register', ['email' => 'A@example.com', 'password' => 'correct-horse-9'])[0] === 409);
check('weak password -> 422', call('POST', '/api/client/register', ['email' => 'b@example.com', 'password' => 'short'])[0] === 422);
check('bad email -> 422', call('POST', '/api/client/register', ['email' => 'nope', 'password' => 'correct-horse-9'])[0] === 422);
check('password stored hashed', !str_contains(Db::one('SELECT password_hash h FROM clients WHERE id=1')['h'], 'correct-horse'));
check('no token -> 401', call('GET', '/api/client/me')[0] === 401);
check('login ok', call('POST', '/api/client/login', ['email' => 'a@example.com', 'password' => 'correct-horse-9'])[0] === 200);
check('login wrong password -> 401', call('POST', '/api/client/login', ['email' => 'a@example.com', 'password' => 'wrong-password-1'])[0] === 401);
check('login unknown user -> 401 (same as wrong password)', call('POST', '/api/client/login', ['email' => 'zzz@example.com', 'password' => 'whatever-123'])[0] === 401);

echo "sessions and tokens\n";
call('POST', '/api/client/logout', [], $tok);
check('logout revokes token immediately', call('GET', '/api/client/me', [], $tok)[0] === 401);

$plat = fresh();
$tok = registerClient();
$parts = explode('.', $tok);
$claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
$forged = Jwt::encode(['sub' => 1, 'typ' => 'client', 'sid' => 'nonexistent', 'exp' => time() + 999], 'wrong-secret');
check('token signed with wrong secret -> 401', call('GET', '/api/client/me', [], $forged)[0] === 401);
$noneHeader = rtrim(strtr(base64_encode('{"alg":"none","typ":"JWT"}'), '+/', '-_'), '=');
check('alg=none token -> 401', call('GET', '/api/client/me', [], "{$noneHeader}.{$parts[1]}.")[0] === 401);
check('tampered payload -> 401', call('GET', '/api/client/me', [], "{$parts[0]}." . rtrim(strtr(base64_encode(json_encode($claims + ['x' => 1])), '+/', '-_'), '=') . ".{$parts[2]}")[0] === 401);
$ownSecretNoSession = Jwt::encode(['sub' => 1, 'typ' => 'client', 'sid' => 'no-such-session', 'exp' => time() + 999], 'test-secret-test-secret-test-secret');
check('valid signature but no session row -> 401', call('GET', '/api/client/me', [], $ownSecretNoSession)[0] === 401);
Db::exec("UPDATE sessions SET expires_at = ? WHERE id = ?", [gmdate('Y-m-d H:i:s', time() - 10), $claims['sid']]);
check('expired session row -> 401', call('GET', '/api/client/me', [], $tok)[0] === 401);

echo "throttle\n";
$plat = fresh();
registerClient();
for ($i = 0; $i < 5; $i++) {
    call('POST', '/api/client/login', ['email' => 'a@example.com', 'password' => 'bad-password-1'], null, [], "9.9.9.{$i}");
}
check('6th attempt -> 429 even with right password', call('POST', '/api/client/login', ['email' => 'a@example.com', 'password' => 'correct-horse-9'], null, [], '9.9.9.99')[0] === 429);

echo "admin auth + permissions\n";
$plat = fresh();
makeAdmin('root', ['*']);
makeAdmin('viewer', ['clients.read']);
$root = adminToken('root');
$viewer = adminToken('viewer');
$clientTok = registerClient();
check('admin me lists permissions', call('GET', '/api/admin/me', [], $viewer)[1]['data']['permissions'] === ['clients.read']);
check('viewer can list clients', call('GET', '/api/admin/clients', [], $viewer)[0] === 200);
check('viewer cannot list platform accounts -> 403', call('GET', '/api/admin/trading-accounts', [], $viewer)[0] === 403);
check('viewer cannot change client status -> 403', call('POST', '/api/admin/clients/1/status', ['status' => 'disabled'], $viewer)[0] === 403);
check('root can list platform accounts', call('GET', '/api/admin/trading-accounts', [], $root)[0] === 200);
check('client token on admin route -> 401', call('GET', '/api/admin/clients', [], $clientTok)[0] === 401);
check('admin token on client route -> 401', call('GET', '/api/client/me', [], $root)[0] === 401);
check('unknown route -> 404', call('GET', '/api/nope')[0] === 404);
check('wrong method -> 405', call('DELETE', '/api/client/login')[0] === 405);

echo "disabling a client\n";
[$s] = call('POST', '/api/admin/clients/1/status', ['status' => 'disabled'], $root);
check('admin disables client', $s === 200);
check('disabled client token dies immediately', call('GET', '/api/client/me', [], $clientTok)[0] === 401);
check('disabled client cannot log in', call('POST', '/api/client/login', ['email' => 'a@example.com', 'password' => 'correct-horse-9'])[0] === 401);
check('status change audited', (int) Db::one('SELECT COUNT(*) c FROM admin_audit_log')['c'] === 1);

echo "onboarding: open account\n";
$plat = fresh();
$tok = registerClient();
[$s, $r] = call('POST', '/api/client/trading-accounts', [], $tok);
check('opens account with default group', $s === 201 && $r['data']['account_id'] === 60001 && $r['data']['group_id'] === 7);
check('platform got Email + GroupId + operator id', ($plat->created[0][0]['Email'] ?? '') === 'a@example.com' && $plat->created[0][0]['GroupId'] === 7 && $plat->created[0][1] === 'client:1');
check('account linked in CRM', (int) Db::one('SELECT COUNT(*) c FROM client_trading_accounts')['c'] === 1);
check('finpro user id stored', (int) Db::one('SELECT finpro_user_id f FROM clients WHERE id=1')['f'] === 900);
check('second open passes UserId to platform', call('POST', '/api/client/trading-accounts', ['group_id' => 8], $tok)[0] === 201 && ($plat->created[1][0]['UserId'] ?? 0) === 900);
check('group outside allow-list -> 422, nothing created', call('POST', '/api/client/trading-accounts', ['group_id' => 999], $tok)[0] === 422 && count($plat->created) === 2);

Db::exec("UPDATE clients SET open_lock_until = ? WHERE id = 1", [gmdate('Y-m-d H:i:s', time() + 20)]);
check('in-flight lock -> 409, nothing created', call('POST', '/api/client/trading-accounts', [], $tok)[0] === 409 && count($plat->created) === 2);
Db::exec("UPDATE clients SET open_lock_until = NULL WHERE id = 1");

$plat->fail = true;
$before = (int) Db::one('SELECT COUNT(*) c FROM client_trading_accounts')['c'];
check('platform failure -> 502, nothing saved', call('POST', '/api/client/trading-accounts', [], $tok)[0] === 502 && (int) Db::one('SELECT COUNT(*) c FROM client_trading_accounts')['c'] === $before);
check('lock released after failure', Db::one('SELECT open_lock_until o FROM clients WHERE id=1')['o'] === null);
$plat->fail = false;
for ($i = 0; $i < 3; $i++) { call('POST', '/api/client/trading-accounts', [], $tok); }
check('account limit enforced (5)', call('POST', '/api/client/trading-accounts', [], $tok)[0] === 422 && (int) Db::one('SELECT COUNT(*) c FROM client_trading_accounts')['c'] === 5);

echo "admin bulk actions\n";
$plat = fresh();
makeAdmin('root', ['*']);
makeAdmin('ops', ['accounts.read', 'accounts.write']);
$root = adminToken('root');
$ops = adminToken('ops');
check('invalid action -> 422', call('POST', '/api/admin/trading-accounts/bulk-actions', ['action' => 'DropTable', 'account_ids' => [1]], $ops)[0] === 422);
check('empty ids -> 422', call('POST', '/api/admin/trading-accounts/bulk-actions', ['action' => 'Disable', 'account_ids' => []], $ops)[0] === 422);
check('ChangeLeverage without leverage -> 422', call('POST', '/api/admin/trading-accounts/bulk-actions', ['action' => 'ChangeLeverage', 'account_ids' => [1]], $ops)[0] === 422);
check('ops disables accounts', call('POST', '/api/admin/trading-accounts/bulk-actions', ['action' => 'Disable', 'account_ids' => [60001, 60002, 60001]], $ops)[0] === 200);
check('ids de-duplicated, operator id is admin', $plat->bulk[0][0] === [60001, 60002] && str_starts_with($plat->bulk[0][3], 'admin:'));
check('ops cannot Delete -> 403', call('POST', '/api/admin/trading-accounts/bulk-actions', ['action' => 'Delete', 'account_ids' => [60001]], $ops)[0] === 403);
check('root can Delete', call('POST', '/api/admin/trading-accounts/bulk-actions', ['action' => 'Delete', 'account_ids' => [60001]], $root)[0] === 200);
check('bulk actions audited', (int) Db::one("SELECT COUNT(*) c FROM admin_audit_log WHERE action='platform.bulk_action'")['c'] === 2);
$plat2 = new class extends FakePlatform { public function listGroups(array $q = []): array { throw new FinProException('secret upstream detail', 500); } };
Platforms::set($plat2);
[$s, $r] = call('GET', '/api/admin/account-groups', [], $ops);
check('upstream failure -> 502 without leaking detail', $s === 502 && !str_contains(json_encode($r), 'secret upstream detail'));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
