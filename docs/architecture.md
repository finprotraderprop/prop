# Architecture (FinancePro-only, phase 1)

Derived from the existing CRM architecture overview, narrowed to FinancePro.

- Client portal and admin console: Vue 3 + Element Plus + Pinia + Vite, static sites.
- CRM API: PHP 8.3, Nginx + PHP-FPM, no framework. JWT per request, session row checked on every request.
- MySQL 8 is the system of record.
- Trading platform access goes only through `TradingPlatform` (`api/src/Platform/`).
  Phase 1 implements `FinancePro`; MT4/MT5 are added later as more implementations.

## Not in milestone 1

crm-sync, Swoole task server, deposits/withdrawals, IB/rebates, Flutter App.

## FinancePro integration points

| Need | FinancePro endpoint |
|---|---|
| CRM service token | `POST auth /api/v1/internal/auth/service-token` (X-App-Id: finpro-crm-client, body AppSecret) |
| Investor session for a CRM-authenticated user | `POST auth /api/v1/internal/auth/investor-token` (UserId, ClientId) |
| Create trading account | `POST broker /api/v1/broker/accounts` |
| List/get accounts | `GET broker /api/v1/broker/accounts`, `/accounts/{id}` |
| Account groups | `GET broker /api/v1/broker/account-groups` |
| Bulk enable/disable/group/leverage | `POST broker /api/v1/broker/accounts/bulk-actions` |

Rules from the FinancePro spec: cache the service token and refresh 60 s before expiry;
send `X-External-Operator-Id` (the CRM user who initiated the action, set server-side only);
never log tokens.

## Open questions

- Request/response schemas for account creation were not fully visible; confirm against Swagger.
- `/api/v1/crm/*` endpoints (referenced by the Auth spec) have not been reviewed.
  They decide how much of crm-sync is needed for trade data and commissions.
