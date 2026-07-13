# Sale Project Reissue Order Add Plan

Date: 2026-06-25

Status: implemented and DB-backed HTTP-smoke verified. Direct edit/delete were later covered by `docs/tasks/sale-project-reissue-order-edit-delete-plan.md`.

## Scope

- Add bounded direct `POST /biz/saleprojectreissueorder/add` support.
- Match the Java reissue-order service add path for the useful write surface: create one `biz_sale_project_reissue_order` row and linked `REISSUE_ORDER` project-product rows, including submitted child `sale_project_product_item_relation` rows.
- Recalculate sale-project `TOTAL_PRICE`, `TOTAL_REFUND_AMOUNT`, `TOTAL_RETURN_AMOUNT`, `PROJECT_STATE`, `PLAY_STATE`, and `AMOUNT_COLLECTED` through existing local helpers after the reissue rows are written.
- Keep workflow reissue approval behavior unchanged in this block.

## Dependency Map

- Existing readback route: `GET /biz/saleprojectreissueorder/list/query`.
- Existing workflow helper: `SaleProjectService::insertReissueProductItems()`.
- Existing product validation: `SaleProjectService::normalizedProductItems()`.
- Existing project correction helpers: `correctedProjectTotals()` and `projectPaymentStatusFields()`.

## Transaction Strategy

- Lock the owning sale project before writing.
- Reject `FOLLOW` projects to match the workflow reissue guard and avoid direct reissue creation before a project is active.
- Require a non-empty `processId`; reject duplicate active `PROCESS_ID` rows to prevent repeated direct submissions from double-counting reissue amount.
- Normalize all products before inserting the reissue order.
- Insert the reissue order, product items, product-item relations, and project correction fields in one transaction.
- Roll back the whole write on invalid project, product, child product, duplicate product, duplicate process id, tenant mismatch, or amount validation failure.

## Side Effects

- `biz_sale_project_reissue_order`: one active row with submitted amount, process id, and remark.
- `biz_sale_project_product_item`: one row per submitted product with `CATEGORY = REISSUE_ORDER`, `STATE = WAIT_DELIVER`, `DELIVERY = 0`, and `PROJECT_REISSUE_ORDER_ID = reissueOrderId`.
- `sale_project_product_item_relation`: submitted child rows with product snapshots in `EXT_JSON`.
- `biz_sale_project`: totals, payment state, and shipment state corrected after the new reissue rows exist.

## Explicit Non-Scope

- Direct reissue edit/delete was out of scope for this add-only slice and is now covered by `docs/tasks/sale-project-reissue-order-edit-delete-plan.md`.
- No delivery invoice rows, invoice item rows, inventory rows, delivery records, settlement account statements, payment records, expenditure records, workflow runtime rows, notifications, file cleanup, Java data-change event bus, Java source changes, schema changes, `.env` changes, production data operations, or commits.

## Verification

- `php -l app\controller\biz\SaleProjectReissueOrderController.php`
- `php -l app\service\biz\SaleProjectService.php`
- PowerShell parser checks for `scripts\sale-project-reissue-order-add-http-smoke.ps1`, `scripts\project-preflight.ps1`, and `scripts\project-progress.ps1`
- `php think route:list | Select-String "saleprojectreissueorder/add"`
- `php think route:list` concrete route count: 582
- `.\scripts\sale-project-reissue-order-add-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`
- Existing workflow reissue smoke regression.
- `.\scripts\sale-project-product-item-standalone-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\project-progress.ps1 -Lean`
- `git diff --check` with CRLF warnings only
