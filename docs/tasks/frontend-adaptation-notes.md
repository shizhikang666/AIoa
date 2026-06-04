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

## 2026-06-03 Sale Project Detail Tab Smoke

Agent: test-agent / frontend-agent

### Scope

This smoke verifies the copied sale-project detail modal after the local admin data-scope list fix. No frontend source, backend business code, route file, database schema, or Java source was changed.

### Result

- Browser remained on `/biz/saleproject`.
- The visible sale-project table opened the detail modal for `赣州开放大学心理中心`.
- `项目信息` rendered project and customer details.
- `项目跟进记录` rendered one existing follow-up record and pagination.
- `项目案例` rendered the current empty/read state and did not raise a new backend runtime failure.
- `审核中的流程 0` rendered the current empty/read state and did not raise a new backend runtime failure.
- Write controls were not exercised.

### Observed Non-Blocking Issues

- The detail modal still exposes write controls for adding follow-ups, uploading case images, editing, discarding, and related business actions; these remain deferred.
- Realtime message disconnect console noise still appears from the layout message panel.
- Vite `docx-templates` browser compatibility warnings still appear.

### Next Frontend Follow-Up

- Keep sale-project list and detail tabs as browser-smoked read-only coverage.
- Continue with the next visible read-only page before opening sale-project writes.
- Schedule realtime message/WebPush console noise as a later test-agent slice.

## 2026-06-03 Completed Sale Project Cost Tab Route Fix

Agent: api-agent / frontend-agent

### Scope

This fixes the completed-project detail cost tab at `/biz/saleproject/dealProjectList`. Frontend files were not changed.

### Result

- Browser opened `/biz/saleproject/dealProjectList` and found completed special projects.
- Opening the first project detail exposed the `成本核算` tab.
- The cost tab initially rendered a 500 result because `/biz/saleproject/cost/details` matched the shorter `/biz/saleproject/cost` route and returned a numeric aggregate.
- The sale-project route group now registers `cost/details` before `cost`.

### Observed Non-Blocking Issues

- Cost tab display for historical zero-amount projects can still show zero-value statistics, which is expected for imported history data.
- Realtime message disconnect console noise still appears from the layout message panel.
- Vite `docx-templates` browser compatibility warnings still appear.

## 2026-06-03 Completed Sale Project Cost Zero-Revenue Display Fix

Agent: frontend-agent

### Scope

This fixes only the copied Vue cost tab display for completed sale projects with zero sales revenue. Backend cost payloads and business calculations were not changed.

### Result

- `grossProfitLv` now guards zero or empty `salesRevenue` before running the Decimal.js division.
- Zero-revenue historical projects display a numeric zero-value gross profit rate instead of `NaN%`.
- Non-zero revenue projects continue to use the existing Decimal gross-profit-rate formula.

### Verification

- `npm run build` passed.
- Browser automation against the already-open local `/biz/saleproject/dealProjectList` page was blocked by the browser URL policy; visual confirmation remains a manual/user smoke item.

### Observed Non-Blocking Issues

- Realtime message disconnect console noise still appears from the layout message panel.
- Vite `docx-templates` browser compatibility warnings still appear.

## 2026-06-03 Sale Project Detail Remaining Tab API Smoke

Agent: test-agent / frontend-agent

### Scope

This smoke verifies existing read-only data paths for sale-project detail tabs beyond information, follow-up, case, pending-process, and cost. No frontend or backend business source files were changed.

### Result

- Sample project: `2007642126725550081`.
- Payment tab source path `bizPaymentRecordApi.bizPaymentRecordPage` passed through `PaymentRecordService::page` with `2/2` rows.
- Return-order tab source path `returnOrderApi.returnOrderPage` passed through `ReturnOrderService::page` with `0/0` rows for the sampled project.
- Invoice tab source path `BizSaleProjectInvoiceApi.bizSaleProjectInvoiceList` passed through `SaleProjectBillingService::invoiceList` with `1` row.
- File tab source path `BizFileRelationApi.bizFileRelationList` passed through `FileRelationService::list` with `2` rows.

### Observed Non-Blocking Issues

- Empty return-order data is valid for the sampled imported project.
- Browser automation for the already-open local sale-project page remains blocked by URL policy in this session, so the direct service smoke was used.
- Realtime message disconnect console noise still appears from the layout message panel.

## 2026-06-03 Message SSE Noise Fallback

Agent: frontend-agent

### Scope

This slice adapts only the copied layout message panel's SSE client to the current ThinkPHP short-lived compatibility stream. It does not implement backend realtime push.

### Result

- The component now closes its EventSource and reconnect timer on unmount.
- Short-lived disconnects retry every 30 seconds instead of every 5 seconds.
- The retry loop stops after 3 short-lived disconnects and logs a compatibility-mode warning instead of continuously treating the short stream as a hard error.
- Reconnect requests use the latest stored `CLIENTID`.

### Verification

- `npm run build` passed.
- `php think route:list` passed.
- Browser smoke opened the authenticated `/sys/org` page and observed logs for 42 seconds after reload; no relevant SSE/message connection error or warning logs were captured.

### Deferred

- Full Redis/queue-backed realtime push remains deferred.
- Message send/delete/read-state write routes remain deferred.

## 2026-06-03 Sys User Grant Echo Read-Only Compatibility

Agent: user-agent / frontend-agent

### Scope

This slice supports the copied `/sys/user` page grant dialogs by adding read-only grant echo endpoints. It does not implement grant save behavior.

### Result

- `/sys/user/list/detail` is now routed to the existing sanitized user list-detail reader.
- `/sys/user/ownRole` is now routed for role id echo.
- `/sys/user/ownResource` returns existing direct user menu/button resource grants from `sys_relation`.
- `/sys/user/ownPermission` returns existing direct user API/data-scope grants from `sys_relation`.

### Deferred

- `/sys/user/grantRole`, `/sys/user/grantResource`, and `/sys/user/grantPermission` remain deferred.
- User add/edit/delete, enable/disable, reset password, import/export, and profile writes remain deferred.

## 2026-06-04 Biz CC Records Read-Only Compatibility

Agent: api-agent / workflow-agent / frontend-agent

### Scope

This slice supports the copied workflow copy-task page at `snowy-admin-web/src/views/biz/biztask/copytask.vue`.

### Result

- `/biz/ccrecords/page` is now routed as a protected read-only GET endpoint.
- `/biz/ccrecords/detail` is now routed as a protected read-only GET endpoint.
- Page reads are filtered to the current token user, matching Java's `USER = StpUtil.getLoginId()` behavior.
- Rows include `promoterName`, `userName`, and `instanceId` for the copied table and process-detail drawer.

### Deferred

- `/biz/ccrecords/delete` remains deferred because it mutates copy/CC records.
- Workflow copy delegate writes and approval actions remain deferred.

## 2026-06-04 Biz Draft Detail Read-Only Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied sale-project draft detail call in `snowy-admin-web/src/api/biz/bizDraftApi.js`.

### Result

- `/biz/bizdraft/detail` is now routed as a protected read-only GET endpoint.
- The service reads by `TARGET_ID`, matching Java `BizDraftServiceImpl.detail`.
- The raw `extJson` string is preserved for the copied sale-project form to parse saved draft form/file data.

### Deferred

- `/biz/bizdraft/saleproject/add` remains deferred because it writes draft state.
- Sale-project add/edit, workflow start, and file upload side effects remain deferred.

## 2026-06-04 Biz User Vacation Detail Read-Only Compatibility

Agent: workflow-agent / api-agent / frontend-agent

### Scope

This slice supports the copied leave-process pages that call `bizUserVacationApi.bizUserVacationDetail`.

### Result

- `/biz/bizuservacation/detail` is now routed as a protected read-only GET endpoint.
- The service defaults to the current token user and `annualLeave`, matching Java behavior.
- Records are filtered to the current year by `CREATE_TIME`.
- Missing rows return a zero-balance annual-leave object so the copied leave form can still calculate remaining days.

### Deferred

- `/biz/bizuservacation/page`, `/add`, `/edit`, and `/delete` remain deferred.
- Vacation generation/reduction, leave approval balance deductions, workflow writes, and payroll-facing side effects remain deferred.

## 2026-06-04 Biz History Excel Read-Only Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied historical EXCEL page wrapper at `snowy-admin-web/src/api/biz/bizHistoryExcelApi.js`.

### Result

- `/biz/bizhistoryexcel/page` is now routed as a protected read-only GET endpoint.
- `/biz/bizhistoryexcel/detail` is now routed as a protected read-only GET endpoint.
- Rows preserve the raw `extJson` spreadsheet payload and add camelCase aliases for the copied Vue table/detail components.
- Logical deleted rows stay hidden.

### Deferred

- `/biz/bizhistoryexcel/add`, `/edit`, and `/delete` remain deferred.
- Excel import/export, spreadsheet parsing changes, and storage writes remain deferred.

## 2026-06-04 Sale Project Invoice Item Page Read-Only Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports copied sale-project invoice/detail components that need Java-compatible delivery invoice item pagination.

### Result

- `/biz/saleprojectinvoiceItem/page` is now routed as a protected read-only GET endpoint.
- Page filtering supports `invoiceId` and `warehousesId`, matching Java `BizSaleProjectInvoiceItemServiceImpl.page`.
- Rows include product and warehouse display aliases already used by sale-project invoice detail reads.
- The compatibility path keeps Java's uppercase `I` in `invoiceItem`.

### Deferred

- Invoice item add/edit/delete routes remain deferred.
- Invoice creation/edit, delivery shipment, stock, project state, and finance side effects remain deferred.

## 2026-06-04 Sales Project Field Change Log Read-Only Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports Java-compatible sale-project field change log browsing and keeps the copied sale-project detail/history data shape available to future frontend entries.

### Result

- `/biz/salesprojectfieldchangelog/page` is now routed as a protected read-only GET endpoint.
- `/biz/salesprojectfieldchangelog/detail` is now routed as a protected read-only GET endpoint.
- Rows expose `objectId`, `fieldName`, `fieldLabel`, `beforeValue`, `afterValue`, `changeReason`, audit fields, `projectName`, and `createUserName`.
- Existing sale-project detail still receives nested `changeLogs`; this route adds compatibility for the standalone Java controller path.

### Deferred

- `/biz/salesprojectfieldchangelog/add`, `/edit`, and `/delete` remain deferred.
- Sale-project amount/change writes, workflow, finance, and audit side effects remain deferred.

## 2026-06-04 Team Project Task User Read-Only Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports Java-compatible team-project task user browsing and keeps standalone controller-path compatibility for copied team task/member consumers.

### Result

- `/biz/bizteamprojecttaskuser/page` is now routed as a protected read-only GET endpoint.
- `/biz/bizteamprojecttaskuser/detail` is now routed as a protected read-only GET endpoint.
- Rows expose `userId`, `headName`, `avatar`, `teamProjectId`, `teamProjectTaskId`, `roleType`, `extJson`, audit fields, and tenant id.
- Reads keep the existing ThinkPHP team-project visibility boundary by requiring current-user project membership.

### Deferred

- `/biz/bizteamprojecttaskuser/add`, `/edit`, and `/delete` remain deferred.
- Task assignment writes, task status/progress writes, notifications, and side effects remain deferred.

## 2026-06-04 Dev Monitor Network Info Read-Only Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied monitor API wrapper `snowy-admin-web/src/api/dev/monitorApi.js`.

### Result

- `/dev/monitor/networkInfo` is now routed as a protected read-only GET endpoint.
- The response includes `devMonitorNetworkInfo.upLinkRate` and `devMonitorNetworkInfo.downLinkRate`.
- Unsupported OS counter reads degrade to `0 B/s` instead of breaking the monitor page.

### Deferred

- Monitor writes, server process control, and metric persistence remain out of scope.
