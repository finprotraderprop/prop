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

## Milestone 1

Auth + client onboarding, admin console. See `docs/architecture.md`.
