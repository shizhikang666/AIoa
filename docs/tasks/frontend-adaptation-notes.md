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

## 2026-06-06 Sale Project Product Mark Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports copied sale-project product mark helpers:

- `snowy-admin-web/src/api/biz/saleProjectProductItemRelationApi.js`
- `snowy-admin-web/src/api/biz/bizSaleProjectProductItemApi.js`

### Result

- `/biz/saleprojectproductitemrelation/mark/edit` is now routed as a protected POST endpoint.
- `/biz/saleprojectproductitem/mark/edit` is now routed as a protected POST endpoint.
- Relation mark edit updates only `sale_project_product_item_relation.MARK`.
- Product item mark edit updates only `biz_sale_project_product_item.MARK`.
- Both endpoints validate visibility through the owning active sale project and apply the existing admin/data-scope/current-user compatibility guard.

### Deferred

- Sale-project product item add/edit/delete, delivery, invoice, return, inventory, finance, workflow, and sale-project state side effects remain out of scope.

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

- `/biz/bizuservacation/page` was deferred in this slice and is handled by the later page-read slice.
- `/biz/bizuservacation/add`, `/edit`, and `/delete` remain deferred.
- Vacation generation/reduction, leave approval balance deductions, workflow writes, and payroll-facing side effects remain deferred.

## 2026-06-05 Biz User Vacation Page Read-Only Compatibility

Agent: workflow-agent / api-agent / frontend-agent

### Scope

This slice supports the copied vacation-balance API wrapper `snowy-admin-web/src/api/biz/bizUserVacationApi.js`.

### Result

- `/biz/bizuservacation/page` is now routed as a protected read-only GET endpoint.
- Page reads existing non-deleted vacation-balance rows with pagination.
- Rows expose `userId`, `userName`, `amount`, `usedAmount`, `category`, audit fields, tenant id, and version.

### Deferred

- `/biz/bizuservacation/add`, `/edit`, and `/delete` remain deferred.
- Vacation generation/reduction, leave approval deductions, workflow writes, and payroll-facing side effects remain out of scope.

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

## 2026-06-05 Sale Project Rate Detail Read-Only Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied rating API wrapper `snowy-admin-web/src/api/biz/saleProjectRateApi.js`.

### Result

- `/biz/projectrate/detail` is now routed as a protected read-only GET endpoint.
- Detail reads a single non-deleted rating row by `id`.
- The row keeps the same normalized shape used by `/biz/projectrate/page` and `/biz/projectrate/list`, including `projectName`, `customerName`, and raw `extJson`.

### Deferred

- `/biz/projectrate/add`, `/edit`, and `/delete` remain deferred.
- Rating image upload, sale-project writes, file storage, project state, and workflow/finance side effects remain out of scope.

## 2026-06-05 Team Project Comment Reply Read-Only Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports copied team-project comment wrappers:

- `snowy-admin-web/src/api/biz/bizTeamProjectCommentApi.js`
- `snowy-admin-web/src/api/biz/bizTeamProjectCommentReplyApi.js`

### Result

- `/biz/bizteamprojectcomment/detail` is now routed as a protected read-only GET endpoint.
- `/biz/bizteamprojectcommentreply/page` is now routed as a protected read-only GET endpoint.
- `/biz/bizteamprojectcommentreply/detail` is now routed as a protected read-only GET endpoint.
- Comment detail includes nested `bizTeamProjectCommentReplies`.
- Standalone reply reads use the reply target comment and owning project membership to keep the current user visibility boundary.

### Deferred

- `/biz/bizteamprojectcomment/add` and `/delete` remain deferred.
- `/biz/bizteamprojectcommentreply/add`, `/edit`, and `/delete` remain deferred.
- Notifications, data-change events, file uploads, and team-project write behavior remain out of scope.

## 2026-06-05 Sys Field Read-Only Compatibility

Agent: user-agent / api-agent / frontend-agent

### Scope

This slice supports the copied system-resource field wrapper and drawer:

- `snowy-admin-web/src/api/sys/resource/fieldApi.js`
- `snowy-admin-web/src/views/sys/resource/field/index.vue`
- `snowy-admin-web/src/views/sys/resource/field/form.vue`

### Result

- `/sys/field/page` is now routed as a protected read-only GET endpoint.
- `/sys/field/tree` is now routed as a protected read-only GET endpoint.
- `/sys/field/detail` is now routed as a protected read-only GET endpoint.
- `/sys/field/MenuTreeSelector` is now routed to the existing menu tree selector data.
- Field page/tree read `sys_resource` with `CATEGORY = FIELD`.
- The imported local database currently has no `FIELD` resource rows, so field reads return stable empty page/tree structures.

### Deferred

- `/sys/field/add`, `/edit`, and `/delete` remain deferred.
- Menu, button, module, and field write behavior remains out of scope.

## 2026-06-05 Gen Basic Metadata Read-Only Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports copied generator form metadata calls:

- `snowy-admin-web/src/api/gen/genBasicApi.js`
- `snowy-admin-web/src/views/gen/basic.vue`

### Result

- `/gen/basic/tables` is now routed as a protected read-only GET endpoint.
- `/gen/basic/tableColumns` is now routed as a protected read-only GET endpoint.
- Table metadata returns `tableName` and `tableRemark`.
- Column metadata returns upper-case `columnName`, upper-case `typeName`, and `columnRemark`, matching the Java generator form expectations.
- `ACT_` workflow engine tables are excluded from generator table options.

### Deferred

- `/gen/basic/add`, `/edit`, and `/delete` remain deferred.
- `/gen/basic/previewGen`, `/execGenZip`, and `/execGenPro` remain deferred because they generate or write code.
- Generator templates, generated code output, and frontend source remain unchanged.

## 2026-06-05 Auth Third User Page Read-Only Compatibility

Agent: auth-agent / frontend-agent

### Scope

This slice supports the copied third-party user binding wrapper:

- `snowy-admin-web/src/api/auth/thirdApi.js`

### Result

- `/auth/third/page` is now routed as a protected read-only GET endpoint.
- Page reads `auth_third_user` rows and returns Java-compatible third-party binding fields.
- The endpoint supports `category`, `searchKey`, pagination, and safe sort fields.

### Deferred

- `/auth/third/render` and `/auth/third/callback` remain deferred.
- Third-party OAuth provider configuration, login callback binding, user creation, token issuing, and frontend source changes remain out of scope.

## 2026-06-05 Customer Follow-Up Write Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied customer follow-up wrapper:

- `snowy-admin-web/src/api/biz/customerFollowUpApi.js`

### Result

- `/biz/customerfollowup/add` is now routed as a protected POST endpoint.
- `/biz/customerfollowup/edit` is now routed as a protected POST endpoint.
- `/biz/customerfollowup/delete` is now routed as a protected POST endpoint.
- The backend accepts the frontend submit-form wrapper without changing copied frontend source.
- Delete accepts Java-style array bodies and common `idList`/`ids`/single `id` payloads.
- Writes validate permission through the owning customer and use logical delete for data safety.

### Deferred

- Customer add/edit/delete and head-owner reassignment remain deferred.
- Follow-up attachment upload/storage cleanup, notifications, and customer encrypted-field writes remain out of scope.

## 2026-06-05 Sale Project Follow-Up Write Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied sale-project follow-up wrapper and visible sale-project detail follow-up tab:

- `snowy-admin-web/src/api/biz/saleProjectFollowUpApi.js`
- `snowy-admin-web/src/views/biz/saleproject/saleProjectTab/followup/index.vue`
- `snowy-admin-web/src/views/biz/saleprojectfollowup/form.vue`

### Result

- `/biz/saleprojectfollowup/add` is now routed as a protected POST endpoint.
- `/biz/saleprojectfollowup/edit` is now routed as a protected POST endpoint.
- `/biz/saleprojectfollowup/delete` is now routed as a protected POST endpoint.
- The sale-project detail tab can submit a follow-up record with `projectId`, `followUpTime`, `category`, `content`, and optional `fileList`.
- Submitted `fileList` is stored under `EXT_JSON` as `{"fileList":[...]}`, preserving the frontend's existing parser.
- Delete accepts Java-style array bodies and common `idList`/`ids`/single `id` payloads.
- Writes validate permission through the owning sale project and use logical delete for data safety.

### Deferred

- File upload/storage implementation and physical file cleanup remain deferred.
- Sale-project state, workflow, finance, inventory, and notification side effects remain out of scope.

## 2026-06-05 Sale Project Product Info Write Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied software package/version info page:

- `snowy-admin-web/src/api/biz/bizSaleProjectProductInfoApi.js`
- `snowy-admin-web/src/views/biz/saleprojectproductinfo/index.vue`
- `snowy-admin-web/src/views/biz/saleprojectproductinfo/form.vue`

### Result

- `/biz/saleprojectproductinfo/add` is now routed as a protected POST endpoint.
- `/biz/saleprojectproductinfo/edit` is now routed as a protected POST endpoint.
- `/biz/saleprojectproductinfo/delete` is now routed as a protected POST endpoint.
- Add requires `productId`, `targetId`, and `contentText`.
- Edit only requires `id` and updates submitted fields.
- Delete accepts Java-style array bodies and common `idList`/`ids`/single `id` payloads.
- Deletes use `DELETE_FLAG = DELETED` so imported rows are not physically removed.

### Deferred

- Product master-data writes, sale-project product-item changes, inventory, delivery, workflow, finance, import/export, and report-generation side effects remain out of scope.

## 2026-06-05 Sale Project Field Change Log Write Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports Java-compatible field-change-log writes for the sale-project history/change log route group:

- `/biz/salesprojectfieldchangelog/add`
- `/biz/salesprojectfieldchangelog/edit`
- `/biz/salesprojectfieldchangelog/delete`

### Result

- `/biz/salesprojectfieldchangelog/add` is now routed as a protected POST endpoint.
- `/biz/salesprojectfieldchangelog/edit` is now routed as a protected POST endpoint.
- `/biz/salesprojectfieldchangelog/delete` is now routed as a protected POST endpoint.
- Add and edit require `objectId`, `fieldName`, `fieldLabel`, `beforeValue`, `afterValue`, and `changeReason`, matching Java parameter validation.
- Delete accepts Java-style array bodies and common `idList`/`ids`/single `id` payloads.
- Deletes use `DELETE_FLAG = DELETED` so imported rows are not physically removed.

### Deferred

- Sale-project amount/change writes, generated history creation from main project forms, workflow, finance, and audit side effects remain out of scope.

## 2026-06-05 Biz History Excel Write Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied historical Excel page wrapper:

- `snowy-admin-web/src/api/biz/bizHistoryExcelApi.js`
- `snowy-admin-web/src/views/biz/bizhistoryexcel/index.vue`
- `snowy-admin-web/src/views/biz/bizhistoryexcel/form.vue`

### Result

- `/biz/bizhistoryexcel/add` is now routed as a protected POST endpoint.
- `/biz/bizhistoryexcel/edit` is now routed as a protected POST endpoint.
- `/biz/bizhistoryexcel/delete` is now routed as a protected POST endpoint.
- Add stores frontend-submitted `name` and `extJson` into `biz_history_excel`.
- Edit updates submitted `extJson`, matching the Java edit parameter shape.
- Delete accepts Java-style array bodies and common `idList`/`ids`/single `id` payloads.
- Deletes use `DELETE_FLAG = DELETED` so imported rows are not physically removed.

### Deferred

- Frontend Excel parser changes, import/export, file storage, and `biz_history_excel_row` row-table writes remain out of scope.

## 2026-06-05 Sale Project Rate Write Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied sale-project case/rating tab wrapper:

- `snowy-admin-web/src/api/biz/saleProjectRateApi.js`
- `snowy-admin-web/src/views/biz/saleproject/saleProjectTab/projectCase/index.vue`

### Result

- `/biz/projectrate/add` is now routed as a protected POST endpoint.
- `/biz/projectrate/delete` is now routed as a protected POST endpoint.
- Add accepts the frontend's `projectId`, `subject`, optional `content`, optional `rateAmount`, and `imgList`.
- Submitted `imgList` is stored under `EXT_JSON` as `{"imgList":[...]}`, preserving the frontend's existing parser.
- Delete accepts Java-style array bodies and common `idList`/`ids`/single `id` payloads.
- Deletes use `DELETE_FLAG = DELETED` so imported rows are not physically removed.

### Deferred

- `/biz/projectrate/edit` remains deferred because the Java controller does not expose it in the current reference.
- Image upload/storage, sale-project state, workflow, finance, inventory, and notification side effects remain out of scope.

## 2026-06-05 CC Records Delete Compatibility

Agent: api-agent / frontend-agent / workflow-agent

### Scope

This slice supports the copied workflow copy-task page delete action:

- `snowy-admin-web/src/api/biz/bizCcRecordsApi.js`
- `snowy-admin-web/src/views/biz/biztask/copytask.vue`

### Result

- `/biz/ccrecords/delete` is now routed as a protected POST endpoint.
- Delete accepts Java-style array bodies and common `idList`/`ids`/single `id` payloads.
- Delete is guarded by the current token user id, matching Java's `USER = StpUtil.getLoginId()` behavior.
- Deletes use `DELETE_FLAG = DELETED` so imported rows are not physically removed.

### Deferred

- `/biz/ccrecords/add` and `/edit` remain deferred.
- Workflow copy-user delegate writes, approval/reject/start/cancel side effects, and notification behavior remain out of scope.

## 2026-06-05 Team Project Comment Add Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports copied team-project timeline comment and reply submission:

- `snowy-admin-web/src/api/biz/bizTeamProjectCommentApi.js`
- `snowy-admin-web/src/api/biz/bizTeamProjectCommentReplyApi.js`
- `snowy-admin-web/src/views/biz/bizteamproject/composables/index.js`

### Result

- `/biz/bizteamprojectcomment/add` is now routed as a protected POST endpoint.
- `/biz/bizteamprojectcommentreply/add` is now routed as a protected POST endpoint.
- Comment add accepts `teamProjectId`, `status`, `statusColor`, `contentText`, and `mentionableUsers`.
- Submitted `mentionableUsers` is stored under `EXT_JSON` as `{"mentionableUsers":[...]}`.
- Reply add accepts `targetId` and `contentText`.
- Both writes are guarded by current-user membership of the owning team project.

### Deferred

- `/biz/bizteamprojectcomment/delete` remains deferred.
- `/biz/bizteamprojectcommentreply/edit` and `/delete` remain deferred.
- Notification push, data-change events, team-project mutations, task mutations, and task state/progress writes remain out of scope.

## 2026-06-05 Team Project Comment Maintenance Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice completes the remaining copied team-project comment/reply wrapper maintenance endpoints:

- `snowy-admin-web/src/api/biz/bizTeamProjectCommentApi.js`
- `snowy-admin-web/src/api/biz/bizTeamProjectCommentReplyApi.js`

### Result

- `/biz/bizteamprojectcomment/delete` is now routed as a protected POST endpoint.
- `/biz/bizteamprojectcommentreply/edit` is now routed as a protected POST endpoint.
- `/biz/bizteamprojectcommentreply/delete` is now routed as a protected POST endpoint.
- Comment delete accepts Java-style array bodies, `idList`, `ids`, or single `id` payloads.
- Reply edit requires `id`, `targetId`, and `contentText`.
- Reply delete accepts Java-style array bodies, `idList`, `ids`, or single `id` payloads.
- Comment delete requires imported project resource permission `delComment`.
- Reply edit/delete allows the reply creator or a project user with imported `delComment` permission.
- Deletes use `DELETE_FLAG = DELETED` so imported rows are not physically removed.

### Deferred

- Notification push and data-change events remain deferred.
- Team-project mutations, task/category/task-user writes, task comment writes, and task state/progress writes remain out of scope.

## 2026-06-05 Team Project Task User Edit Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied team-project task detail assignee selector:

- `snowy-admin-web/src/api/biz/bizTeamProjectTaskApi.js`
- `snowy-admin-web/src/views/biz/bizteamproject/details/task/taskDetail.vue`

### Result

- `/biz/bizteamprojecttask/user/edit` is now routed as a protected POST endpoint.
- The endpoint accepts `id` plus `user`, `users`, or `userIds`.
- Submitted assignees may be id strings, comma-separated ids, or user objects containing `id`, `userId`, or `value`.
- Assignment writes require current-user membership of the owning team project plus imported `addUser` project permission or task-level `MANAGE` role.
- Submitted assignees must already be non-deleted members of the same team project.
- Removed task-user rows use `DELETE_FLAG = DELETED` so imported rows are not physically removed.

### Deferred

- `/biz/bizteamprojecttask/add`, `/edit`, and `/delete` remain deferred.
- Task category writes, task comments, task status/progress/content writes, notification push, and data-change events remain out of scope.

## 2026-06-05 Team Project Task Comment Add Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied team-project task detail comment submit form:

- `snowy-admin-web/src/api/biz/bizTeamProjectTaskCommentApi.js`
- `snowy-admin-web/src/views/biz/bizteamproject/details/task/taskDetail.vue`

### Result

- `/biz/bizteamprojecttaskcomment/add` is now routed as a protected POST endpoint.
- Add accepts `teamProjectTaskId`, optional `contentText`, and optional `files`.
- The owning `teamProjectId` is derived from the existing task row.
- Submitted `files` are stored under `EXT_JSON` as `{"file":[...]}`, matching the copied task detail drawer parser.
- The row is stored with `CATEGORY = COMMENT` and `DELETE_FLAG = NOT_DELETE`.
- The write is guarded by current-user membership of the owning team project.

### Deferred

- `/biz/bizteamprojecttaskcomment/edit` and `/delete` remain deferred.
- Task add/edit/delete, task category writes, task status/progress/content writes, notification push, and data-change events remain out of scope.

## 2026-06-05 Team Project Task Comment Maintenance Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice completes the copied team-project task-comment wrapper maintenance endpoints:

- `snowy-admin-web/src/api/biz/bizTeamProjectTaskCommentApi.js`

### Result

- `/biz/bizteamprojecttaskcomment/edit` is now routed as a protected POST endpoint.
- `/biz/bizteamprojecttaskcomment/delete` is now routed as a protected POST endpoint.
- Edit accepts `id`, optional `contentText`, optional `files`/`file`/`fileList`, and optional raw `extJson`.
- File lists are stored under `EXT_JSON` as `{"file":[...]}`.
- Delete accepts Java-style array bodies, `idList`, `ids`, or a single `id`.
- Maintenance is limited to `CATEGORY = COMMENT` rows. Generated `CATEGORY = LOG` rows remain read-only.
- Maintenance is allowed for the comment creator, a project user with imported `delComment`, or a task-level `MANAGE` user.
- Deletes use `DELETE_FLAG = DELETED` so imported rows are not physically removed.

### Deferred

- Generated task-log edit/delete remains deferred.
- Task add/edit/delete, task category writes, task status/progress/content writes, notification push, and data-change events remain out of scope.

## 2026-06-05 Team Project Task Category Maintenance Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied team-project kanban category wrapper:

- `snowy-admin-web/src/api/biz/bizTeamProjectTaskCategoryApi.js`
- `snowy-admin-web/src/views/biz/bizteamproject/details/task/index.vue`

### Result

- `/biz/bizteamprojecttaskcategory/add` is now routed as a protected POST endpoint.
- `/biz/bizteamprojecttaskcategory/edit` is now routed as a protected POST endpoint.
- `/biz/bizteamprojecttaskcategory/sort/edit` is now routed as a protected POST endpoint.
- `/biz/bizteamprojecttaskcategory/delete` is now routed as a protected POST endpoint.
- Add accepts `teamProjectId`, `title`, optional `extJson`, and optional `sortCode`; default `SORT_CODE` is `99`.
- Edit accepts `id`, `title`, optional `teamProjectId`, optional `extJson`, and optional `sortCode`.
- Sort accepts Java-style ordered `[{id: ...}]` bodies and rewrites `SORT_CODE` by submitted order.
- Delete accepts Java-style array bodies, `idList`, `ids`, or a single `id`.
- Maintenance requires team-project `LEADER`, team-project `MANAGE`, or imported `addUser` project resource permission.
- Deletes use `DELETE_FLAG = DELETED`, and categories containing active tasks are rejected.

### Deferred

- Task add/edit/delete remains deferred.
- Task drag-to-category, task status/progress/content writes, notification push, and data-change events remain out of scope.

## 2026-06-05 Team Project Task Base Maintenance Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied team-project kanban task wrappers:

- `snowy-admin-web/src/api/biz/bizTeamProjectTaskApi.js`
- `snowy-admin-web/src/views/biz/bizteamproject/details/task/addTaskForm.vue`
- `snowy-admin-web/src/views/biz/bizteamproject/details/task/taskDetail.vue`
- `snowy-admin-web/src/views/biz/bizteamproject/details/task/taskItemListView.vue`

### Result

- `/biz/bizteamprojecttask/add` is now routed as a protected POST endpoint.
- `/biz/bizteamprojecttask/edit` is now routed as a protected POST endpoint.
- `/biz/bizteamprojecttask/delete` is now routed as a protected POST endpoint.
- Add accepts `teamProjectId`, `teamProjectTaskCategoryId`, optional `contentText`, optional `title`, optional `users`, optional `sortCode`, and optional `extJson`.
- Add inserts `STATUS = TODO`, `PROGRESS = 0`, `DELETE_FLAG = NOT_DELETE`, and `VERSION = 0`.
- Add creates the current user as task `MANAGE`, and submitted project members as task `MEMBER`.
- Edit accepts `id` and submitted base task fields: `title`, `status`, `contentText`, `progress`, `teamProjectTaskCategoryId`, `sortCode`, and `extJson`.
- Edit validates status values against `TODO`, `CANCEL`, and `COMPLETE`.
- Delete accepts Java-style array bodies, `idList`, `ids`, or a single `id`.
- Delete uses `DELETE_FLAG = DELETED` for the task and active task-user rows.

### Deferred

- Generated task `CATEGORY = LOG` comments remain deferred.
- Notification push, data-change events, workflow actions, and full drag ordering remain out of scope.

## 2026-06-05 Team Project Member Maintenance Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied team-project member wrappers:

- `snowy-admin-web/src/api/biz/bizTeamProjectUserApi.js`
- `snowy-admin-web/src/views/biz/bizteamproject/details/addUserForm.vue`
- `snowy-admin-web/src/views/biz/bizteamproject/composables/index.js`

### Result

- `/biz/bizteamprojectuser/add` is now routed as a protected POST endpoint.
- `/biz/bizteamprojectuser/manage/add` is now routed as a protected POST endpoint.
- `/biz/bizteamprojectuser/delete` is now routed as a protected POST endpoint.
- Add accepts `teamProjectId` and `users`/`user`/`userIds`, rejects users already active in the same project, and creates `ROLE_TYPE = MEMBER`.
- Manage add uses the same payload shape but creates `ROLE_TYPE = MANAGE`.
- Previously deleted member rows are restored instead of creating another duplicate active row.
- Member relation permissions are synchronized under `TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION` to match Java role defaults.
- Delete accepts Java-style array bodies, `idList`, `ids`, or a single `id`.
- Delete uses `DELETE_FLAG = DELETED`, and rejects project leader or current-user removal.

### Deferred

- `/biz/bizteamprojectuser/edit` remains deferred.
- Notification push, data-change events, team-project base writes, and frontend source changes remain out of scope.

## 2026-06-05 Customer Base Maintenance Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied customer maintenance wrapper and visible customer table/form consumers:

- `snowy-admin-web/src/api/biz/customerApi.js`
- `snowy-admin-web/src/views/biz/customer/index.vue`
- `snowy-admin-web/src/views/biz/customer/form.vue`

### Result

- `/biz/customer/add` is now routed as a protected POST endpoint.
- `/biz/customer/edit` is now routed as a protected POST endpoint.
- `/biz/customer/delete` is now routed as a protected POST endpoint.
- Add accepts the copied form fields, requires `fileId`, defaults owner/user plus organization from the current token user when the payload does not submit them, and rejects submitted owner/org values outside the token user's write scope.
- Edit validates write scope through the existing customer owner/org data-scope and updates only submitted mutable fields.
- Delete accepts Java-style array bodies, `idList`, `ids`, or a single `id`.
- Delete uses `DELETE_FLAG = DELETED`; imported customer rows are not physically removed.

### Deferred

- SM4 plaintext phone/detail-address compatibility, file upload/storage cleanup, customer data-change events, and sale-project/customer side effects remain out of scope.

## 2026-06-06 Customer Head Reassignment Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied customer owner reassignment wrapper:

- `snowy-admin-web/src/api/biz/customerApi.js`

### Result

- `/biz/customer/head/edit` is now routed as a protected POST endpoint.
- The endpoint accepts `id` and `user`.
- It validates current-user customer write scope and validates that the target user is assignable through admin-compatible roles, token data-scope org ids, or current-user fallback.
- It updates only `customer.USER`, `customer.ORG`, update audit fields, and `VERSION`.

### Deferred

- Customer import/export, file upload/storage cleanup, SM4 plaintext search, sale-project/customer side effects, notifications, and Java data-change events remain out of scope.

## 2026-06-05 Supplier Base Maintenance Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied supplier maintenance wrapper and visible supplier table/form consumers:

- `snowy-admin-web/src/api/biz/supplierApi.js`
- `snowy-admin-web/src/views/biz/supplier/index.vue`
- `snowy-admin-web/src/views/biz/supplier/form.vue`

### Result

- `/biz/supplier/add` is now routed as a protected POST endpoint.
- `/biz/supplier/edit` is now routed as a protected POST endpoint.
- `/biz/supplier/delete` is now routed as a protected POST endpoint.
- Add requires `name`, `contacts`, and `phone`; empty `status` defaults to `ENABLE`.
- Add writes the current token user's organization to the lower-case physical `supplier.org` column.
- Edit requires `id`, `name`, `contacts`, `phone`, and `status`, and validates supplier write scope.
- Delete accepts Java-style array bodies, `idList`, `ids`, or a single `id`.
- Delete uses `DELETE_FLAG = DELETED`; imported supplier rows are not physically removed.

### Deferred

- Supplier import/export remains deferred.
- Purchase, payment, procurement, inventory, workflow, and other supplier side effects remain out of scope.

## 2026-06-06 Warehouse Base Maintenance Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied warehouse maintenance wrapper and visible warehouse table/form consumers:

- `snowy-admin-web/src/api/biz/warehousesApi.js`
- `snowy-admin-web/src/views/biz/warehouses/index.vue`
- `snowy-admin-web/src/views/biz/warehouses/form.vue`

### Result

- `/biz/warehouses/add` is now routed as a protected POST endpoint.
- `/biz/warehouses/edit` is now routed as a protected POST endpoint.
- `/biz/warehouses/delete` is now routed as a protected POST endpoint.
- Add accepts `name`, `code`, `address`, `sortCode`, and optional `extJson`.
- Add writes `USER` from the current token user and `ORG` from the current token user's organization.
- Edit accepts submitted base fields and validates submitted `org` against token write scope.
- Delete accepts Java-style array bodies, `idList`, `ids`, or a single `id`.
- Delete uses `DELETE_FLAG = DELETED`; imported warehouse rows are not physically removed.

### Deferred

- Inventory stock updates, delivery records, purchase-order writes, sale-project invoice writes, workflow behavior, and warehouse side effects remain out of scope.

## 2026-06-06 Product Status And Reconciliation Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports two copied product list operations:

- `snowy-admin-web/src/api/biz/bizProductApi.js`
- `snowy-admin-web/src/views/biz/bizproduct/index.vue`

### Result

- `/biz/bizproduct/edit/status` is now routed as a protected POST endpoint.
- `/biz/bizproduct/reconciliation/edit` is now routed as a protected POST endpoint.
- Status edit accepts only `ENABLE` and `DISABLE`.
- Reconciliation edit accepts selected product ids, `reconciliationType`, and optional non-negative `reconciliationAmount`.
- Both writes validate active product visibility through admin-compatible roles, scoped organization ids, or matching product creator.

### Deferred

- Product add, edit, delete, kit product relation writes, inventory, purchase, sale-project, finance transaction, workflow, file upload/storage, and Java data-change/cache event behavior remain out of scope.

## 2026-06-06 Product Base Maintenance Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports the copied product table and form maintenance flow:

- `snowy-admin-web/src/api/biz/bizProductApi.js`
- `snowy-admin-web/src/views/biz/bizproduct/index.vue`
- `snowy-admin-web/src/views/biz/bizproduct/form.vue`

### Result

- `/biz/bizproduct/add` is now routed as a protected POST endpoint.
- `/biz/bizproduct/edit` is now routed as a protected POST endpoint.
- `/biz/bizproduct/delete` is now routed as a protected POST endpoint.
- Add validates Java-required product fields and writes active `biz_product` rows with audit, tenant, organization, and default `status = ENABLE`.
- Kit product add/edit validates unique child products and quantities, then writes Java-compatible `product_relation` rows with `CATEGORY = KIT_PRODUCT_DATA`.
- Edit updates only submitted base fields and does not change `CATEGORY`, matching Java `BizProductEditParam`.
- Delete accepts Java-style array bodies and common `idList`/`ids`/single `id` payloads, blocks products referenced as kit children, and uses logical deletion.

### Deferred

- Inventory stock updates, purchase-order writes, sale-project item writes, finance transaction writes, workflow actions, file upload/storage implementation, and Java data-change/cache event behavior remain out of scope.

## 2026-06-06 User Center Self-Service Write Compatibility

Agent: user-agent / frontend-agent

### Scope

This slice supports copied personal-center API wrappers and forms:

- `snowy-admin-web/src/api/sys/userCenterApi.js`
- `snowy-admin-web/src/api/biz/bizUserApi.js`
- `snowy-admin-web/src/views/sys/user/userCenter.vue`
- `snowy-admin-web/src/views/sys/user/userTab/bindForm/updatePassword.vue`
- `snowy-admin-web/src/views/sys/user/userTab/bindForm/updateUserInfo.vue`
- `snowy-admin-web/src/views/sys/user/userTab/shortcutSetting.vue`
- `snowy-admin-web/src/views/sys/user/userTab/userProcessConfig.vue`

### Result

- `/sys/userCenter/updatePassword` is now routed as a protected POST endpoint.
- `/sys/userCenter/updateAvatar` is now routed as a protected POST endpoint.
- `/sys/userCenter/updateSignature` is now routed as a protected POST endpoint.
- `/sys/userCenter/updateUserInfo` is now routed as a protected POST endpoint.
- `/sys/userCenter/updateUserWorkbench` is now routed as a protected POST endpoint.
- `/sys/userCenter/process/config/edit` is now routed as a protected POST endpoint.
- `/biz/user/center/edit` is now routed as a protected POST endpoint for the copied "more info" form.
- All writes are constrained to the current token user.

### Deferred

- Admin-side user CRUD, enable/disable, reset password, grants, import/export.
- Java SM4 encrypted-field migration.
- Full file-provider storage and avatar cleanup.

## 2026-06-06 User Message Detail Mark-Read Compatibility

Agent: user-agent / frontend-agent

### Scope

This slice supports copied user-center message detail behavior:

- `snowy-admin-web/src/api/sys/userCenterApi.js`
- `snowy-admin-web/src/views/sys/user/userTab/userMessage.vue`

### Result

- `/sys/userCenter/loginUnreadMessageDetail` now marks only the current token user's `dev_relation` receiver row as read.
- The response detail returns `read = true` after the detail call.
- The current user's `receiveInfoList` entry also returns `read = true`.
- No frontend source change is required for this compatibility slice.

### Deferred

- Message send/delete, all-mark-read, WebPush, and full realtime push remain out of scope.

## 2026-06-06 Index Message All-Mark-Read Compatibility

Agent: user-agent / frontend-agent

### Scope

This slice supports copied homepage message drawer behavior:

- `snowy-admin-web/src/api/sys/indexApi.js`
- `snowy-admin-web/src/layout/components/message.vue`
- `snowy-admin-web/src/views/index/components/miniMessage.vue`

### Result

- `/sys/index/message/allMessageMarkRead` is now routed as a protected POST endpoint.
- The endpoint marks only the current token user's `dev_relation` rows with `CATEGORY = MSG_TO_USER` as read.
- Existing valid `EXT_JSON` keys are preserved while `read` is set to `true`.
- No frontend source change is required for this compatibility slice.

### Deferred

- Message send/delete, WebPush, and full realtime push remain out of scope.

## 2026-06-06 Index Schedule Self-Service Compatibility

Agent: user-agent / frontend-agent

### Scope

This slice supports copied homepage schedule widget behavior:

- `snowy-admin-web/src/api/sys/indexApi.js`
- `snowy-admin-web/src/views/index/components/schedule.vue`

### Result

- `/sys/index/schedule/add` is now routed as a protected POST endpoint.
- `/sys/index/schedule/deleteSchedule` is now routed as a protected POST endpoint.
- Add stores current-user schedule rows in `sys_relation` with `CATEGORY = SYS_USER_SCHEDULE_DATA`.
- Delete accepts Java-style array bodies, `idList`, `ids`, or a single `id`, and is constrained to current-user schedule rows.
- No frontend source change is required for this compatibility slice.

### Deferred

- Shared calendars, schedule editing, notifications, and cross-user schedule management remain out of scope.

## 2026-06-06 Auth Session And Token Exit Compatibility

Agent: auth-agent / frontend-agent

### Scope

This slice supports copied auth monitor behavior:

- `snowy-admin-web/src/api/auth/monitorApi.js`
- `snowy-admin-web/src/views/auth/monitor/bTab.vue`
- `snowy-admin-web/src/views/auth/monitor/cTab.vue`
- `snowy-admin-web/src/views/auth/monitor/tokenInfoList.vue`

### Result

- `/auth/session/b/exit` is now routed as a protected POST endpoint.
- `/auth/session/c/exit` is now routed as a protected POST endpoint with client-auth no-op compatibility.
- `/auth/token/b/exit` is now routed as a protected POST endpoint.
- `/auth/token/c/exit` is now routed as a protected POST endpoint with client-auth no-op compatibility.
- B-side tokens created after this slice are indexed in cache by user id so monitor pages can revoke active tokens.
- Ordinary users can only operate on their own user id/token; admin-compatible accounts or roles may manage all indexed B-side sessions.
- No frontend source change is required for this compatibility slice.

### Deferred

- C-side login/client token storage, third-party OAuth render/callback, and fine-grained route permission middleware remain out of scope.

## 2026-06-06 Dev Message Delete Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports copied station-message management behavior:

- `snowy-admin-web/src/api/dev/messageApi.js`
- `snowy-admin-web/src/views/dev/message/index.vue`

### Result

- `/dev/message/delete` is now routed as a protected POST endpoint.
- The endpoint accepts Java-style arrays of `{ id }`, `idList`, `ids`, or a single `id`.
- Delete removes selected `dev_message` rows and their `MSG_TO_USER` receiver relations.
- Admin-compatible accounts or roles may delete tenant messages; ordinary users may delete only messages they created.
- No frontend source change is required for this compatibility slice.

### Deferred

- Message send, SSE/WebPush realtime push behavior, and file/storage cleanup remain out of scope.

## 2026-06-06 Dev Message Send Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports copied station-message send behavior:

- `snowy-admin-web/src/api/dev/messageApi.js`
- `snowy-admin-web/src/views/dev/message/form.vue`

### Result

- `/dev/message/send` is now routed as a protected POST endpoint.
- The endpoint accepts copied frontend fields: `subject`, `category`, `content`, `href`, and `receiverIdList`.
- Receiver values can be strings or selector objects containing `id`, `userId`, `value`, or `key`.
- Send creates one `dev_message` row and one `MSG_TO_USER` `dev_relation` row per active receiver.
- `content` defaults to `subject`; `category` defaults to `SYS`; relation `EXT_JSON.read` is initialized as `false`.
- No frontend source change is required for this compatibility slice.

### Deferred

- Full SSE/WebPush realtime push parity, message templates, file/storage cleanup, and unrelated user/workflow/business writes remain out of scope.

## 2026-06-06 Dev Message Detail Mark-Read Compatibility

Agent: api-agent / frontend-agent

### Scope

This slice supports Java-compatible station-message detail behavior:

- `snowy-admin-web/src/api/dev/messageApi.js`
- `snowy-admin-web/src/views/dev/message/detail.vue`

### Result

- `/dev/message/detail` keeps the same protected GET route and response shape.
- When the current token user is one of the message receivers, that user's `MSG_TO_USER` relation is marked as read.
- Existing relation `EXT_JSON` keys are preserved while `read` is set to `true`.
- `receiveInfoList` and `readCount` reflect the updated relation state.
- No frontend source change is required for this compatibility slice.

### Deferred

- Full SSE/WebPush detail refresh parity and unrelated user/workflow/business writes remain out of scope.

## 2026-06-06 User Role Grant Save Compatibility

Agent: user-agent / frontend-agent

### Scope

This slice supports copied system and business user role-grant dialogs:

- `snowy-admin-web/src/api/sys/userApi.js`
- `snowy-admin-web/src/api/biz/bizUserApi.js`
- `snowy-admin-web/src/views/sys/user/index.vue`
- `snowy-admin-web/src/views/biz/user/index.vue`

### Result

- `/sys/user/grantRole` is now routed as a protected POST endpoint.
- `/biz/user/grantRole` is now routed as a protected POST endpoint.
- Both endpoints accept `{ id, roleIdList }` from the copied frontend forms.
- Save clears existing direct user role relations and rewrites `SYS_USER_HAS_ROLE` rows with active role ids.
- Empty `roleIdList` clears direct role grants.
- No frontend source change is required for this compatibility slice.

### Deferred

- Resource grants, permission grants, admin-side user CRUD, enable/disable, reset-password-by-admin, import/export, and encrypted profile-field migration remain out of scope.
