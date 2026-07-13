# Sale Project Foundation Closure Plan

Date: 2026-06-18

Status: implemented and DB-backed HTTP-smoke verified on 2026-06-18.

## Goal

Close the sale-project foundation workflow as one coherent feature block instead of replacing one route at a time.

The block covers project creation and base editing paths that were controlled-deferred:

- `POST /biz/saleproject/add`
- `POST /biz/saleproject/edit`
- `POST /biz/saleproject/history/add`
- `POST /biz/saleproject/special/add`

The block also verifies the downstream pages and reads that depend on those writes: sale-project page, public page, operation page, detail, list detail, product, cost, cost details, report/data-report visibility, and the already implemented state actions.

## Current Covered Behavior

Already covered and not to be reworked unless a regression is found:

- Sale-project read routes: `case/page`, `detail`, `list/detail`, `operation/page`, `page`, `product`, `public/page`, `cost`, `cost/details`.
- Sale-project field/state writes: `visibility/edit`, `amount/edit`, `deal/edit`, `cancel`, `repeal`, `delete`.
- Sale-project follow-up read contracts: `page`, `detail`.
- Related read modules used by details and reports: product item, invoicing, invoice item, reissue order, return order, payment record, field change log, and data report reads.

## Java Reference

Read-only source root: `F:\AI\projects\testJava\OA`.

Controller routes:

- `BizSaleProjectController.history/add` calls `BizSaleProjectService.add(BizHistoryProjectAddParam)`.
- `BizSaleProjectController.special/add` calls `BizSaleProjectService.add(BizSpecialProjectAddParam)`.
- `BizSaleProjectController.add` calls `BizSaleProjectService.add(BizSaleProjectAddParam)`.
- `BizSaleProjectController.edit` calls `BizSaleProjectService.edit(BizSaleProjectEditParam)`.

Service behavior found in `BizSaleProjectServiceImpl`:

- Normal `add` validates the selected customer through login data scope, copies `BizSaleProjectAddParam`, then sets `PLAY_STATE = UNPAID`, `VISIBILITY = PRIVATE`, `PROJECT_STATE = FOLLOW`, `AMOUNT_COLLECTED = 0`, and `TOTAL_PRICE = INIT_PRICE`.
- Normal `edit` validates current project scope, copies only `BizSaleProjectEditParam`, requires `PROJECT_STATE = FOLLOW`, then updates the project.
- `history/add` creates a history customer first, creates a shipped private direct project, sets user/org/customer/create/completion-related values from the payload/customer/user lookup, sets `HISTORY_AMOUNT`, `TOTAL_PRICE = INIT_PRICE`, then runs `projectPaymentStatusCorrector`.
- `special/add` creates a history customer for the current login user, creates a shipped private direct project, sets `SPECIAL_TYPE = PUBLIC_FOR_REIMBURSEMENT`, sets `ORG` from `orgId`, sets `TOTAL_PRICE = INIT_PRICE`, then runs `projectPaymentStatusCorrector`.

Important compatibility note:

- The Java `BizSaleProjectAddParam` and `BizSaleProjectEditParam` classes currently expose only base project fields. They do not expose `productList`, even though the copied Vue form sends `productList` when product rows change. Do not add sale-project product-item mutation to this block until the Java product-item ownership path is explicitly mapped and accepted as part of scope.

Java param fields:

- `BizSaleProjectAddParam`: `customer`, `projectName`, `projectCategory`, `remark`, `area`, `detailsAddress`, `projectCode`, `specimenCategory`, `specimenName`.
- `BizSaleProjectEditParam`: `id`, `projectName`, `projectCategory`, `remark`, `area`, `detailsAddress`, `projectCode`.
- `BizHistoryProjectAddParam`: `projectName`, `customerName`, `user`, `initPrice`, `historyAmount`, `completionDate`.
- `BizSpecialProjectAddParam`: `projectName`, `customerName`, `orgId`, `initPrice`, `completionDate`.

## Frontend Callers

API wrapper: `snowy-admin-web/src/api/biz/bizSaleProjectApi.js`.

Main form: `snowy-admin-web/src/views/biz/saleproject/form.vue`.

- New and follow-state edit submit through `bizSaleProjectSubmitForm(data, edit)`, which posts to `add` or `edit`.
- Completed or non-follow projects submit through `bizSaleProjectEditDealProject(data)`, already implemented as `deal/edit`.
- The form passes `productList` only when the local product array changed, but Java normal add/edit params do not define that field.

History form: `snowy-admin-web/src/views/biz/saleproject/form/historyForm.vue`.

- Submits `projectName`, `customerName`, selected `user`, `initPrice`, `historyAmount`, and `completionDate` to `history/add`.

Visible entry pages and actions that need readback after this block:

- `index.vue`
- `publicList.vue`
- `dealProjectCaseList.vue`
- `waitShipment.vue`
- `cancelProject.vue`
- `dealProjectList.vue`
- modal paths that create special reimbursement projects

## Implemented ThinkPHP State

Implemented controller methods:

- `SaleProjectController::add()`
- `SaleProjectController::edit()`
- `SaleProjectController::historyAdd()`
- `SaleProjectController::specialAdd()`

Current related services:

- `SaleProjectService` owns sale-project reads, cost reads, state writes, amount writes, visibility writes, deal edit, cancel, repeal, and delete.
- `SaleProjectBillingService` owns invoicing, invoice, reissue, and rating-adjacent reads and writes.
- `SaleProjectFollowUpService` owns follow-up reads/writes.
- `SaleProjectProductInfoService` owns sale-project product-info reads/writes.

Current related tables already used by sale-project services:

- `biz_sale_project`
- `customer`
- `biz_sale_project_product_item`
- `sale_project_product_item_relation`
- `biz_sale_project_invoicing`
- `biz_sale_project_invoice`
- `biz_sale_project_invoice_item`
- `biz_payment_record`
- `return_order`
- `return_order_item`
- `biz_sale_project_reissue_order`
- `sales_project_field_change_log`
- `sale_project_rate`

## In Scope

- Replace the four remaining sale-project foundation wrappers with guarded ThinkPHP behavior.
- Keep Java-compatible field whitelists for normal add/edit.
- Validate customer, user, organization, tenant, and data-scope ownership before writing.
- Preserve current read compatibility and already implemented state/action writes.
- Use transactions for every write path.
- Create a focused HTTP smoke script for the complete block, not one script per route.
- Verify downstream readback through existing sale-project and data-report smoke where practical.
- Update API docs, gap map, dashboard, bootstrap notes, and status after implementation.

## Out Of Scope

- Java source edits.
- Frontend source edits unless a copied frontend compatibility bug blocks the verified workflow.
- Schema changes.
- Product-item mutation through normal sale-project add/edit until the Java ownership path is explicitly mapped.
- Workflow start/approval transitions.
- Payment, settlement-account, invoice mutation, delivery, inventory, return-order, reissue-order, file cleanup, provider sends, notifications, Java data-change events, and final online data sync.
- Production data operations.
- Commits unless explicitly requested.

## Side-Effect Boundary

Allowed side effects:

- Normal add: one `biz_sale_project` row with Java-compatible defaults.
- Normal edit: one existing `biz_sale_project` row updated only for Java edit fields while it remains `FOLLOW`.
- History add: one history customer row plus one sale-project row, with payment-state correction based on the submitted amounts.
- Special add: one history customer row plus one sale-project row, with reimbursement special type and payment-state correction.

Disallowed side effects for this block:

- Product-item create/update/delete.
- Invoicing, invoice item, payment record, expenditure record, statement, inventory, delivery, return-order, workflow, file relation, field-change-log, rate, and notification mutation.

## Implementation Order

1. Re-read the current ThinkPHP schema/model field names for `biz_sale_project`, `customer`, user/org, and tenant metadata.
2. Implement service helpers for base sale-project input normalization, money validation, customer scope validation, user/org validation, and audit/default fields.
3. Implement normal add/edit first with strict Java field whitelists and no product-item mutation.
4. Implement history add and special add with history-customer creation only after customer service behavior is mapped.
5. Remove the four routes from the deferred-wrapper smoke only after the new block smoke passes.
6. Update sale-project API docs, controlled-deferred docs, API gap map, dashboard, startup notes, `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md`.

## Implementation Result

- `POST /biz/saleproject/add` now creates one `biz_sale_project` row with Java-compatible defaults, validates active customer access, and ignores copied-frontend spoofed product/amount/state fields.
- `POST /biz/saleproject/edit` now updates only Java `BizSaleProjectEditParam` fields, requires the project to be visible and `FOLLOW`, and preserves protected amount/state/customer/product/workflow fields.
- `POST /biz/saleproject/history/add` now creates one history customer plus one direct private sale-project row, stores `HISTORY_AMOUNT`, applies payment-state correction, and creates no product/finance/stock/workflow rows.
- `POST /biz/saleproject/special/add` now creates one history customer plus one direct private reimbursement project with `special_type = PUBLIC_FOR_REIMBURSEMENT`, applies payment-state correction, ignores submitted `historyAmount`/`remark`, and creates no product/finance/stock/workflow rows.
- The four routes were removed from `scripts/frontend-deferred-write-wrapper-smoke.ps1`; the remaining deferred smoke now covers 47 authenticated POST wrappers plus the task-SSE deferred GET wrapper.

## Smoke Plan

Added one DB-backed authenticated HTTP smoke script:

`scripts/sale-project-foundation-closure-http-smoke.ps1`

Covered checks:

- Runtime readiness uses `scripts/runtime-ready.ps1` or `scripts/project-preflight.ps1`; do not use the Windows `MySQL80` service as the database readiness signal.
- No-token checks return auth failure for representative routes.
- Normal add rejects missing customer, missing required fields, invalid customer, and out-of-scope customer without partial writes.
- Normal add creates one project with Java defaults and no related product/invoice/payment/delivery rows.
- Normal edit rejects missing id, missing row, out-of-scope row, and non-`FOLLOW` row without partial writes.
- Normal edit updates only Java edit fields and preserves protected state, amount, visibility, specimen, customer, user, org, tenant, product, invoice, payment, delivery, workflow, and delete fields.
- History add rejects invalid user, invalid money, missing completion date, and missing customer name without partial writes.
- History add creates a customer and a shipped private direct sale-project row, runs payment-state correction, and creates no finance/stock/workflow rows.
- Special add rejects invalid org and invalid money without partial writes.
- Special add creates a customer and a shipped private direct reimbursement project with `SPECIAL_TYPE = PUBLIC_FOR_REIMBURSEMENT`, then creates no finance/stock/workflow rows.
- Readback through `detail`, `page`, `list/detail`, `public/page`, `operation/page`, `product`, `cost`, and `cost/details` does not crash on created rows.
- Temporary data cleanup is deterministic and restricted to rows created by the smoke.

Focused smoke result:

- `.\scripts\sale-project-foundation-closure-http-smoke.ps1`: passed on 2026-06-18 against the local ThinkPHP backend at `http://127.0.0.1:82`.
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`: passed after the four sale-project foundation routes were removed from the deferred list.

After the focused smoke passes, rerun:

```powershell
php -l app\controller\biz\SaleProjectController.php
php -l app\service\biz\SaleProjectService.php
php -l route\app.php
.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing
.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred
.\scripts\frontend-deferred-write-wrapper-smoke.ps1
.\scripts\project-progress.ps1 -Lean
git diff --check
```

Run browser smoke for the sale-project pages when backend and frontend web ports are available.

## Stop Conditions

Stop before implementation or pause the block if:

- Customer history creation cannot be mapped safely from current ThinkPHP services.
- The local `.env` credentials are unavailable for DB-backed smoke.
- Product-item mutation is required for frontend acceptance but the Java-compatible ownership path is still unclear.
- Any write would require schema changes, Java source changes, production data mutation, or provider/cloud integration.
