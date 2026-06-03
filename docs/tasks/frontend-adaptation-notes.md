# Frontend Adaptation Notes

Date: 2026-06-01

Agent: frontend-agent

## Scope

This slice keeps the imported Vue frontend as a copied baseline and only adapts the request boundary needed for local ThinkPHP joint testing.

## Changes

- Use `VITE_API_PREFIX` as the browser request prefix so Vite can proxy local development traffic to ThinkPHP.
- Keep `VITE_API_BASEURL` as the proxy target in Vite configuration.
- Send tokens with `Authorization: Bearer <token>` to match the ThinkPHP `AuthMiddleware`.
- Apply the same token header convention to upload and SSE connections.
- Use `VITE_PUBLIC_KEY` for SM2 encryption. If no public key is configured in local development, password submission falls back to plaintext so the current ThinkPHP password compatibility path can be tested.
- Leave the Axios instance `baseURL` empty so `baseRequest` does not double-prefix requests with `/api`.
- Treat menu nodes with `children: []` as leaf nodes when picking the first route after login.

## Local URLs

- Backend: `http://127.0.0.1:82`
- Frontend: `http://127.0.0.1:83`
- Frontend API prefix: `/api`
- Production API prefix: `/backend`

## Verification

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed.
- `npm ci --no-audit --no-fund`: passed.
- `npm run build`: passed with upstream warnings only.
- Local MySQL/Redis helper: started.
- ThinkPHP dev server: `http://127.0.0.1:82`, started.
- Vue dev server: `http://127.0.0.1:83`, started.
- Browser smoke on a fresh local origin: login succeeded and redirected to `/sys/org`.
- Browser smoke: `/sys/org` and `/sys/user` pages loaded with menus, tables, and pagination.

## Deferred

- Missing frontend API gap map.
- API wrapper cleanup for routes that are still read-only or not yet implemented.
- Field/dictionary display alignment for org/user tables.
- Missing SSE route `/dev/message/createSseConnect`.

## 2026-06-03 Summary Statistics Joint Smoke

Agent: test-agent

### Scope

This smoke verifies the existing frontend page against the new ThinkPHP read-only summary-statistics endpoint. No frontend page code or backend business code was changed.

### Services

- Backend: `http://127.0.0.1:82`
- Frontend: `http://127.0.0.1:83`

### Result

- Login reached the authenticated layout.
- Direct route `/biz/bizdatareport/summaryStatistics` loaded successfully.
- Browser title was `汇总统计 - 福地科技`.
- Visible page content included `汇总统计表`, annual month columns, company finance rows, and `未回款统计表`.
- The page finished loading without a visible loading state.
- ThinkPHP runtime log did not show a new runtime exception for this smoke.

### Observed Non-Blocking Issues

- Browser console still reports WebPush permission failure in local development.
- Browser console still reports repeated realtime message connection disconnects from `src/layout/components/panel-message/index.vue`.
- Vite still reports upstream `docx-templates` browser compatibility warnings for Node built-ins.
- In-app browser screenshot capture timed out on this heavy table page; visible DOM text was used as the smoke evidence.

### Next Frontend Follow-Up

- Keep summary-statistics as browser-smoked read-only coverage.
- Add a dedicated test-agent slice for realtime message/SSE console noise after remaining read-only pages are covered.
- Continue joint backend/frontend smoke for the next visible business report or detail page.
