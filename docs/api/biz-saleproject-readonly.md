# Biz Sale Project API Compatibility

Date: 2026-06-02

Agent: api-agent

## Java Reference

- Controller: `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\saleproject\controller\BizSaleProjectController.java`
- Service: `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\saleproject\service\impl\BizSaleProjectServiceImpl.java`
- SQL reference: `F:\AI\projects\testJava\OA\oa2026.sql`

## Implemented ThinkPHP Routes

All routes are protected by `AuthMiddleware`.

| Method | Route | ThinkPHP Handler | Behavior |
| --- | --- | --- | --- |
| GET | `/biz/saleproject/page` | `biz.SaleProjectController/page` | Paginated sale-project list with customer, user, org, account display fields and return orders |
| GET | `/biz/saleproject/case/page` | `biz.SaleProjectController/casePage` | Paginated sale-project list restricted to projects with `sale_project_rate` rows |
| GET | `/biz/saleproject/operation/page` | `biz.SaleProjectController/operationPage` | Same read query path as Java operation page |
| GET | `/biz/saleproject/public/page` | `biz.SaleProjectController/publicPage` | Paginated list filtered by `VISIBILITY = PUBLIC` |
| GET | `/biz/saleproject/list/detail` | `biz.SaleProjectController/listDetail` | Export/detail aggregate list |
| GET | `/biz/saleproject/detail` | `biz.SaleProjectController/detail` | Single sale-project aggregate detail |
| GET | `/biz/saleproject/product` | `biz.SaleProjectController/product` | Product items for one sale project |
| POST | `/biz/saleproject/cost` | `biz.SaleProjectController/cost` | Read-only aggregate sale-project product cost |
| POST | `/biz/saleproject/cost/details` | `biz.SaleProjectController/costDetails` | Read-only sale-project product cost details |
| POST | `/biz/saleproject/add` | `biz.SaleProjectController/add` | Java-compatible base project creation with optional copied-frontend `productList` sync |
| POST | `/biz/saleproject/edit` | `biz.SaleProjectController/edit` | Java-compatible base project edit for `FOLLOW` rows with optional copied-frontend `productList` sync |
| POST | `/biz/saleproject/history/add` | `biz.SaleProjectController/historyAdd` | Historical project creation with history-customer row and payment-state correction |
| POST | `/biz/saleproject/special/add` | `biz.SaleProjectController/specialAdd` | Public reimbursement project creation with history-customer row and payment-state correction |
| POST | `/biz/saleproject/visibility/edit` | `biz.SaleProjectController/visibilityEdit` | Narrow visibility/specimen field update |
| POST | `/biz/saleproject/amount/edit` | `biz.SaleProjectController/amountEdit` | Java-compatible amount/status/totals update with change log |
| POST | `/biz/saleproject/deal/edit` | `biz.SaleProjectController/dealEdit` | Java-compatible delivery/freight field update |
| POST | `/biz/saleproject/cancel` | `biz.SaleProjectController/cancel` | Java-compatible WAIT_DELIVER rollback to FOLLOW with invoicing logical delete |
| POST | `/biz/saleproject/repeal` | `biz.SaleProjectController/repeal` | Java-compatible FOLLOW to DISCARD state update with repeal content |
| POST | `/biz/saleproject/delete` | `biz.SaleProjectController/delete` | Java-compatible FOLLOW-only logical delete with DISCARD state |

## Compatibility Notes

- Main sale-project rows preserve Java/frontend camelCase fields such as `projectName`, `projectState`, `playState`, `customerName`, `customerAddress`, `customerSourceType`, `customType`, `headName`, `headPhone`, `orgName`, and `accountName`.
- Product item rows include joined product display fields and a `children` array.
- Child product rows preserve `extJson` from `sale_project_product_item_relation`. If a child row lacks `EXT_JSON`, the service creates a minimal compatible `{"product": ...}` JSON string from joined product fields so the copied frontend parser can still run.
- Detail responses include:
  - `bizSaleProject`
  - `productItems`
  - `invoicingList`
  - `invoiceList`
  - `paymentRecords`
  - `saleProjectFollowUps`
  - `changeLogs`
  - `returnOrders`
- Payment records are limited to `SETTLEMENT_CATEGORY = PROJECT_PLAY`, matching Java list/detail aggregation.
- Data scope follows the existing auth payload `data_scope_org_ids`; if no org scope is available, it falls back to the current user id.
- `/biz/saleproject/add` validates the selected active customer, creates one base `biz_sale_project` row, sets `PROJECT_STATE = FOLLOW`, `PLAY_STATE = UNPAID`, `VISIBILITY = PRIVATE`, zeroes money defaults, and still ignores copied-frontend state/amount spoof fields because Java `BizSaleProjectAddParam` exposes only base fields.
- `/biz/saleproject/add` now also accepts the copied sale-project form's optional `productList`: omitted or `null` means no product rows; an array creates active `biz_sale_project_product_item` rows and `sale_project_product_item_relation` rows inside the same transaction.
- `/biz/saleproject/edit` validates active sale-project access, requires `PROJECT_STATE = FOLLOW`, updates only Java `BizSaleProjectEditParam` fields (`PROJECT_NAME`, `PROJECT_CATEGORY`, `REMARK`, `AREA`, `DETAILS_ADDRESS`, `PROJECT_CODE`), and preserves customer, state, visibility, amount, finance, invoice, workflow, and delete fields.
- `/biz/saleproject/edit` treats omitted or `null` `productList` as "preserve product rows"; an array synchronizes the active product-item set by updating submitted existing rows, inserting new rows, and logically deleting removed rows.
- Product-list sync validates enabled active products in the current tenant/data scope, requires positive integer quantities and non-negative money fields, sets normal rows to `CATEGORY = INIT`, `STATE = WAIT_DELIVER`, and `DELIVERY = 0`, auto-hydrates kit product children from `product_relation.CATEGORY = KIT_PRODUCT_DATA` when children are omitted, and writes child relation `EXT_JSON.product` snapshots for copied frontend parsers.
- Product-list sync logically deletes removed product-item and child-relation rows. If an existing product item is referenced by active `biz_sale_project_invoice_item` or `return_order_item` rows, delete and product/quantity/money/children changes are rejected and rolled back.
- `/biz/saleproject/history/add` validates the target user, creates one `customer` history row with `CUSTOM_TYPE = OLD` and `STATUS = ENABLE`, creates one direct private project, stores `HISTORY_AMOUNT`, and applies Java-style payment-state correction from submitted `initPrice/historyAmount`.
- `/biz/saleproject/special/add` validates the selected org, creates one current-user history customer, creates one direct private project with `special_type = PUBLIC_FOR_REIMBURSEMENT`, stores `HISTORY_AMOUNT = 0.00`, and applies Java-style payment-state correction from submitted `initPrice`.
- `/biz/saleproject/visibility/edit` validates `PUBLIC`/`PRIVATE`, requires `specimenCategory` for public visibility, updates only visibility/specimen/audit/version fields, and preserves specimen fields for copied frontend private toggles that omit them.
- `/biz/saleproject/amount/edit` validates active sale-project access, updates `INIT_PRICE`, recalculates collection/payment/project/return totals, writes one `INIT_PRICE` field-change log, and keeps broader sale-project workflow/finance/inventory/invoice side effects deferred.
- `/biz/saleproject/deal/edit` validates active sale-project access, updates only Java `BizDealProjectEditParam` fields (`UNIT`, `ADDRESS`, `LOGISTICS_CATEGORY`, `CONSIGNEE`, `PHONE`, `REMARK`, `FREIGHT`, `FREIGHT_CATEGORY`, `DELIVERY_NOTE`), refreshes audit/version fields, and preserves protected state, amount, invoicing, product, finance, and workflow data.
- `/biz/saleproject/cancel` validates active sale-project access, requires `PROJECT_STATE = WAIT_DELIVER`, sets `PROJECT_STATE = FOLLOW`, refreshes audit/version fields, and logically deletes active `biz_sale_project_invoicing` rows for that project and tenant.
- `/biz/saleproject/repeal` accepts Java/copied-frontend array payloads, validates every selected project is visible and `FOLLOW`, then sets `PROJECT_STATE = DISCARD`, writes `REPEAL_CONTENT`, and refreshes audit/version fields without deleting the project row.
- `/biz/saleproject/delete` accepts Java/copied-frontend array payloads, validates every selected project is visible and `FOLLOW`, then sets `PROJECT_STATE = DISCARD`, sets `DELETE_FLAG = DELETED`, and refreshes audit/version fields without touching invoicing or product rows.

## Remaining Deferred Behavior

The sale-project foundation routes and copied-form product-list mutation are implemented. Broader side effects still remain intentionally deferred until separate feature-closure plans map their Java ownership paths:

- Standalone invoice item writes, payment, expenditure, settlement-account, delivery-record, inventory, workflow, file cleanup, notification, and Java data-change event side effects. Direct standalone product-item add/edit/delete, return-order master/detail writes, sale-project invoice-application row add/edit/delete, direct delivery-invoice add/edit/delete with reverse correction, and direct reissue-order add/edit/delete are covered separately.

## Test Commands

```powershell
php -l app\controller\biz\SaleProjectController.php
php -l app\service\biz\SaleProjectService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

## Local Smoke Result

Date: 2026-06-02

- Local MySQL/Redis helper path confirmed as `E:\project\socket\AI\testPhp\files\startServer1.bat`.
- ThinkPHP backend was smoke-tested on `http://127.0.0.1:82`.
- Vue frontend was smoke-tested on `http://127.0.0.1:83`.
- Login with the local super-admin account returned API `code = 200` and a bearer token.
- `/biz/saleproject/page`, `/detail`, `/product`, `/case/page`, `/operation/page`, `/public/page`, and `/list/detail` all returned API `code = 200` with a valid token.
- Unauthenticated `/biz/saleproject/page` returned API `code = 401`.
- `php think route:list` lists all seven saleproject read routes.
- `case/page` uses ThinkORM `join(..., 'INNER')`; `innerJoin()` is not available in the installed ThinkORM version.

## Frontend Page Smoke Follow-Up

Date: 2026-06-03

- The copied Vue `/biz/saleproject` page sends `projectState=FOLLOW`, `showDiscard=false`, `sortField=createTime`, and `sortOrder=descend`.
- Admin-compatible data scope now matches the existing customer/follow-up/billing service pattern and does not fall back to current-user-only filtering.
- Authenticated frontend-shaped `/biz/saleproject/page` smoke returned `code = 200`, `total = 254`, and 10 rows.
- The page's secondary `/biz/process/query` lookup returned `code = 200` and 10 items.
- Browser reload of `/biz/saleproject` showed pagination `1-10 共 254 条` instead of `暂无数据`.

## 2026-06-15 Business Read HTTP Smoke

`scripts/business-read-http-smoke.ps1` now verifies the copied frontend sale-project read payloads against the local authenticated backend.

Covered sale-project checks:

- `GET /biz/saleproject/page`
- `GET /biz/saleproject/case/page`
- `GET /biz/saleproject/operation/page`
- `GET /biz/saleproject/public/page`
- `GET /biz/saleproject/detail`
- `GET /biz/saleproject/list/detail`
- `GET /biz/saleproject/product`
- `POST /biz/saleproject/cost`
- `POST /biz/saleproject/cost/details`
- Billing nested reads through `scripts/business-read-http-smoke.ps1`:
  - `GET /biz/saleprojectinvoicing/page`
  - `GET /biz/saleprojectinvoicing/detail`
  - `GET /biz/saleprojectinvoicing/customer`
  - `GET /biz/saleprojectinvoice/page`
  - `GET /biz/saleprojectinvoice/list`
  - `GET /biz/saleprojectinvoiceItem/page`
  - `GET /biz/saleprojectreissueorder/list/query`

The smoke loads an existing active sale-project id from the local database, verifies Java-style paging keys, checks display fields such as `projectName`, `projectState`, `playState`, `customerName`, `headName`, `orgName`, and `accountName`, and checks detail aggregate buckets including product items, invoicing, invoices, payment records, follow-ups, change logs, and return orders.

This smoke is read-only. It does not call sale-project add, edit, delete, deal edit, visibility edit, amount edit, repeal/cancel, history/special creation, invoicing complete, workflow actions, finance effects, stock effects, settlement effects, or file cleanup.

## 2026-06-18 Foundation Closure HTTP Smoke

`scripts/sale-project-foundation-closure-http-smoke.ps1` verifies the four foundation writes against the local authenticated backend.

Covered checks:

- no-token, missing-field, missing-row, invalid-state, invalid-money, and over-collected guards;
- normal add Java defaults and ignored state/amount spoof fields;
- normal edit Java field whitelist, `FOLLOW` guard, and version refresh;
- history add customer/project creation and paid/completed correction;
- special add reimbursement customer/project creation and unpaid/shipped correction;
- readback through detail, product, cost, and cost details;
- no unexpected product-item, invoicing, payment-record, field-change-log, or rating side effects.

## 2026-06-18 Product Item Mutation Coverage

`scripts/sale-project-product-item-mutation-http-smoke.ps1` verifies copied-form product-list writes through `/biz/saleproject/add` and `/biz/saleproject/edit`.

Covered checks:

- no-token rejection and invalid product rollback;
- add with direct product rows and kit-product child relation auto-hydration;
- detail and product endpoint readback after product-list add;
- edit updating an existing product item, inserting a new item, and logically deleting a removed item/relation;
- `productList = null` edit preserving active product rows;
- active return-order item reference blocking product-item deletion with rollback;
- empty-array edit clearing unreferenced product items.

## 2026-06-18 Visibility Edit Coverage

`POST /biz/saleproject/visibility/edit` is now covered as narrow field maintenance. `scripts/sale-project-visibility-edit-http-smoke.ps1` creates a temporary sale project and is ready to verify:

- no-token rejection;
- missing project id rejection;
- invalid visibility rejection;
- missing specimen category rejection when switching to public;
- missing project rejection;
- public update of `VISIBILITY`, `SPECIMEN_CATEGORY`, `SPECIMEN_NAME`, audit fields, and `VERSION`;
- private update without specimen input while preserving existing specimen fields;
- no product-item, invoicing, change-log, payment-record, or delivery-record side effects.

DB-backed execution is pending while local MySQL `MySQL80` is stopped.

## 2026-06-18 Delete Coverage

`POST /biz/saleproject/delete` is now covered as Java-compatible logical delete maintenance. `scripts/sale-project-delete-http-smoke.ps1` creates temporary sale projects and an invoicing row and is ready to verify:

- no-token rejection;
- missing list rejection;
- missing project rejection;
- non-`FOLLOW` state rollback without project mutation;
- batch update to `PROJECT_STATE = DISCARD`;
- logical delete via `DELETE_FLAG = DELETED`;
- audit and `VERSION` refresh;
- no invoicing row mutation.

DB-backed execution is pending while local MySQL `MySQL80` is stopped.

## 2026-06-18 Repeal Coverage

`POST /biz/saleproject/repeal` is now covered as Java-compatible discard state maintenance. `scripts/sale-project-repeal-http-smoke.ps1` creates temporary sale projects and an invoicing row and is ready to verify:

- no-token rejection;
- missing list rejection;
- missing project rejection;
- non-`FOLLOW` state rollback without project mutation;
- batch update to `PROJECT_STATE = DISCARD`;
- first submitted `repealContent` propagation to all selected rows;
- audit and `VERSION` refresh;
- no project logical delete and no invoicing row mutation.

DB-backed execution is pending while local MySQL `MySQL80` is stopped.

## 2026-06-18 Cancel Coverage

`POST /biz/saleproject/cancel` is now covered as Java-compatible status rollback. `scripts/sale-project-cancel-http-smoke.ps1` creates temporary sale projects and invoicing rows and is ready to verify:

- no-token rejection;
- missing id rejection;
- missing project rejection;
- non-`WAIT_DELIVER` state rollback without project or invoicing mutation;
- successful `PROJECT_STATE = FOLLOW`, audit, and `VERSION` update;
- logical deletion of active target `biz_sale_project_invoicing` rows;
- preservation of unrelated invoicing rows.

DB-backed execution is pending while local MySQL `MySQL80` is stopped.

## 2026-06-18 Amount Edit Coverage

`POST /biz/saleproject/amount/edit` is now covered as focused Java-compatible amount maintenance. `scripts/sale-project-amount-edit-http-smoke.ps1` creates a temporary sale project and is ready to verify:

- no-token rejection;
- missing id rejection;
- negative amount rejection;
- missing project rejection;
- successful `INIT_PRICE`, `TOTAL_PRICE`, `AMOUNT_COLLECTED`, `PLAY_STATE`, `PROJECT_STATE`, `TOTAL_RETURN_AMOUNT`, `TOTAL_REFUND_AMOUNT`, audit, and `VERSION` updates;
- one `sales_project_field_change_log` row for `INIT_PRICE`;
- no unrelated product-item, invoicing, reissue-order, return-order, expenditure, or payment side effects on successful edit;
- over-collected rollback without extra version or change-log changes.

DB-backed execution is pending while local MySQL `MySQL80` is stopped.
