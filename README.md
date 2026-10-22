# prop — Broker CRM on FinancePro

A broker CRM (client portal, admin console, API) built on top of FinancePro.
FinancePro is the only trading platform for now; MT4/MT5 will be added later
behind the same `TradingPlatform` interface.

## Layout

| Path | What |
|---|---|
| `api/` | PHP 8.3 API (no framework), FinancePro adapter |
| `admin/` | Vue 3 admin console |
| `portal/` | Vue 3 client portal |
| `docs/` | Architecture and FinancePro API mapping |
| `docker-compose.yml` | Local MySQL 8 |

## Quick start

```bash
cp .env.example .env          # fill in FINPRO_APP_SECRET locally, never commit it
docker compose up -d mysql
php -S 127.0.0.1:8080 -t api/public
curl http://127.0.0.1:8080/api/health

cd admin  && npm install && npm run dev
cd portal && npm install && npm run dev
```

First admin (prompts for the password, grants all permissions):

```bash
php api/bin/create-admin.php <username>
```

Tests (in-memory SQLite and a fake platform, no network or MySQL needed):

```bash
php api/tests/run.php
```

`api/tests/schema.sqlite.sql` mirrors the MySQL migrations; update both when columns change.

## API (milestone 1)

| Route | Auth | Purpose |
|---|---|---|
| `POST /api/client/register`, `/login`, `/logout`; `GET /api/client/me` | client | Portal accounts |
| `GET/POST /api/client/trading-accounts` | client | List / open a FinancePro account |
| `POST /api/admin/login`, `/logout`; `GET /api/admin/me` | admin | Admin session |
| `GET /api/admin/clients[/{id}]`, `POST .../status` | `clients.read` / `clients.write` | Client management |
| `GET /api/admin/trading-accounts[/{id}]`, `/account-groups` | `accounts.read` | FinancePro accounts and groups |
| `POST /api/admin/trading-accounts/bulk-actions` | `accounts.write` (`accounts.delete` for Delete/Restore) | Enable, disable, regroup, leverage |

## Milestone 1

Auth + client onboarding, admin console. See `docs/architecture.md`.
