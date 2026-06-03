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

## 2026-06-03 Sale Project Page Smoke

Agent: api-agent / frontend-agent

### Scope

This smoke followed the sale-project cost read-only API slice. No frontend files were changed.

### Result

- Direct route `/biz/saleproject` loaded.
- Browser title was `销售项目管理 - 福地科技`.
- Visible table header content included project name/status/payment columns.
- The page was not stuck in a loading state.
- The current visible table showed `暂无数据`.
- Backend API smoke with the same local login reached `/biz/saleproject/page` successfully, so the empty frontend table should be investigated later as a frontend query/filter/display compatibility issue rather than as a blocker for the cost route registration.

### Observed Non-Blocking Issues

- Realtime message connection console noise still appears from the layout message panel.
- Vite `docx-templates` browser compatibility warnings still appear.
- Cost tab deep smoke needs a currently visible project with product items; the current local account/page result did not expose one in the browser flow.

## 2026-06-03 Sale Project Follow-Up Read Smoke

Agent: api-agent / frontend-agent

### Scope

This smoke followed the sale-project follow-up read-only API slice. No frontend files were changed.

### Backend Result

- `/biz/saleprojectfollowup/page` returned `code = 200` with local authenticated requests.
- `/biz/saleprojectfollowup/detail` returned `code = 200` for a sampled follow-up record.
- Unauthenticated `/biz/saleprojectfollowup/page` returned `code = 401`.
- Direct service smoke found 836 follow-up rows in the local database and preserved `extJson` for file-list parsing.

### Browser Result

- Direct route `/biz/saleprojectfollowup` returned the copied Vue 404 page.
- The frontend source contains `snowy-admin-web/src/views/biz/saleprojectfollowup/index.vue`, but the current route/menu data does not expose it as a standalone browser path.
- The sale-project detail follow-up tab uses the same API wrapper, so the backend read route is available once a sale-project detail flow reaches that component.
- Browser was restored to `/biz/saleproject` after the smoke.

### Observed Non-Blocking Issues

- Standalone sale-project follow-up page route/menu exposure remains a frontend adaptation task.
- Sale-project detail tab deep smoke remains tied to the existing sale-project table visibility mismatch.

## 2026-06-03 Sale Project Product Item Relation Read Smoke

Agent: api-agent / frontend-agent

### Scope

This smoke followed the sale-project product item relation read-only API slice. No frontend files were changed.

### Backend Result

- `/biz/saleprojectproductitemrelation/list` was added as a protected read-only POST route.
- Direct service smoke used a sampled sale-project product item id and returned 10 combo child relation rows.
- Response rows include relation ids, `objectId`, `targetId`, `productId`, `mark`, `number`, product display fields, and `extJson`.

### Browser Result

- No standalone browser page was opened for this slice because the copied frontend currently references this API from sale-project delivery/invoice helpers rather than a direct page route.
- The current browser tab remains on `/biz/saleproject`.

### Observed Non-Blocking Issues

- Relation mark editing remains deferred because `/biz/saleprojectproductitemrelation/mark/edit` mutates data.
- Product item mark editing remains deferred because `/biz/saleprojectproductitem/mark/edit` mutates data.

## 2026-06-03 Sale Project Page Data Scope Smoke Fix

Agent: api-agent / frontend-agent

### Scope

This smoke followed the sale-project product item relation slice. Frontend source files were not changed.

### Result

- Root cause: the copied sale-project page forces `projectState=FOLLOW`; ThinkPHP `SaleProjectService` then applied fallback current-user data scope to the local admin smoke account, hiding all imported `FOLLOW` projects.
- Fix: `SaleProjectService` now mirrors existing customer/follow-up/billing services by allowing admin-compatible accounts and roles to bypass fallback current-user filtering.
- Frontend-shaped HTTP smoke returned `/biz/saleproject/page` `code = 200`, `total = 254`, and 10 rows for `projectState=FOLLOW`.
- `/biz/process/query` returned `code = 200` and 10 workflow lookup items for those page rows.
- Browser reload of `/biz/saleproject` showed pagination `1-10 共 254 条` instead of the previous empty-table state.

### Observed Non-Blocking Issues

- Realtime message connection console noise still appears from the layout message panel.
- Vite `docx-templates` browser compatibility warnings still appear.
- Deep browser smoke for delivery/invoice helper actions still depends on a visible sale-project detail flow and should be handled in a later frontend-agent pass.
