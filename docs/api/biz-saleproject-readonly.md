# Biz Sale Project Read-Only API Compatibility

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

## Deferred Routes

The following Java routes remain intentionally unimplemented in this slice:

| Route | Reason |
| --- | --- |
| `POST /biz/saleproject/add` | Creates project, product items, invoices, files, customer deal count, and workflow-sensitive state |
| `POST /biz/saleproject/edit` | Updates project and product bindings; needs transaction plan |
| `POST /biz/saleproject/deal/edit` | Deal-state mutation and payment/status side effects |
| `POST /biz/saleproject/delete` | Delete behavior must preserve Java soft-delete and dependent-state rules |
| `POST /biz/saleproject/repeal` | Project-state mutation |
| `POST /biz/saleproject/cancel` | Status rollback plus invoice deletion |
| `POST /biz/saleproject/history/add` | History order creation |
| `POST /biz/saleproject/special/add` | Special reimbursement project creation |
| `POST /biz/saleproject/visibility/edit` | Write route requiring project field mutation |
| `POST /biz/saleproject/amount/edit` | Amount mutation and change-log side effect |

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

- Local MySQL/Redis helper path confirmed as `F:\project\socket\AI\testPhp\files\startServer1.bat`.
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
