# Biz Sale Project Billing Compatibility API

Date: 2026-06-05

Agent: api-agent / frontend-agent

## Scope

This document records the ThinkPHP compatibility slice for Java sales-project billing-adjacent endpoints. Most endpoints remain read-only; project rating includes narrow `add`/`edit` and logical `delete` support, and sale-project invoicing includes the narrow Java-style complete marker.

Implemented routes:

| Method | Path | Java source | Purpose |
| --- | --- | --- | --- |
| GET | `/biz/saleprojectinvoicing/page` | `BizSaleProjectInvoicingController.page` | Invoice application page list |
| GET | `/biz/saleprojectinvoicing/customer` | `BizSaleProjectInvoicingService.findLastEntityByCustomerId` | Latest invoice information for a customer |
| GET | `/biz/saleprojectinvoicing/detail` | `BizSaleProjectInvoicingController.detail` | Invoice application detail |
| POST | `/biz/saleprojectinvoicing/complete` | `BizSaleProjectInvoicingController.complete` | Mark invoice application as complete |
| GET | `/biz/saleprojectinvoice/page` | `BizSaleProjectInvoiceController.page` | Delivery invoice page list |
| GET | `/biz/saleprojectinvoice/list` | `BizSaleProjectInvoiceController.list` | Delivery invoices grouped with invoice items by project |
| GET | `/biz/saleprojectreissueorder/list/query` | `BizSaleProjectReissueOrderController.listQuery` | Reissue orders with nested product items |
| GET | `/biz/projectrate/page` | `SaleProjectRateController.page` | Project rating page list |
| POST | `/biz/projectrate/add` | `SaleProjectRateController.add` | Add a project rating row |
| POST | `/biz/projectrate/edit` | `SaleProjectRateService.edit` | Edit a project rating row |
| GET | `/biz/projectrate/list` | `SaleProjectRateController.list` | Project rating list by project |
| POST | `/biz/projectrate/delete` | `SaleProjectRateController.delete` | Logically delete project rating rows |
| GET | `/biz/projectrate/detail` | `SaleProjectRateService.detail/queryEntity` | Project rating detail by id |

All routes are protected by `AuthMiddleware`.

## Compatibility Notes

- The invoicing page keeps Java's invoiceable-project-state filter: `PARTIALLY_SHIPPED`, `SHIPPED`, and `COMPLETED`.
- Page responses use the existing ThinkPHP shape: `records`, `total`, `page`, `current`, `limit`, `size`, and `pages`.
- `/biz/saleprojectinvoice/list` returns Java-compatible entries with `bizSaleProjectInvoice` and `invoiceItems`.
- `/biz/saleprojectreissueorder/list/query` returns Java-compatible entries with `order` and `productItemList`.
- Reissue product items include `children`; child `extJson` is preserved, and if missing a minimal product JSON payload is synthesized for the frontend parser.
- `/biz/projectrate/detail` keeps the same normalized row shape as `/page` and `/list`, including raw `extJson`.
- `/biz/projectrate/add` requires `projectId` and `subject`, defaults missing `rateAmount` to `0.00`, defaults missing `content` to an empty string, and stores submitted `imgList` under `EXT_JSON` as `{"imgList":[...]}`.
- `/biz/projectrate/edit` updates only the rating row's project id, tenant id, score, content, subject, `EXT_JSON`, and audit update fields. It reuses the existing project-scope guard and stores submitted `imgList` as `{"imgList":[...]}` without physical file cleanup.
- `/biz/projectrate/delete` accepts Java-style `[{id: ...}]`, `idList`, `ids`, or a single `id`; it uses `DELETE_FLAG = DELETED` instead of physical removal.
- `/biz/saleprojectinvoicing/complete` accepts `{ "id": "..." }`, validates the row through the existing project scope and tenant filters, updates only `INVOICING_STATE = INVOICING_STATE_COMPLETE` plus audit update fields, and returns `data = null`.
- Query responses include project/customer display aliases where useful, such as `projectName`, `customerName`, `orgName`, and `headName`.

## Deferred

The following endpoints and behaviors are intentionally not implemented in this slice:

- Invoice application add/edit/delete
- Delivery invoice add/edit/delete
- Reissue order add/start process
- Project rate file upload/storage cleanup
- Workflow side effects
- Inventory stock mutations
- Finance, settlement, payment, refund, or cost mutations
- Database schema changes
- Java source changes
- Frontend code changes

## Test Plan

Required checks:

```powershell
php -l app\controller\biz\SaleProjectInvoicingController.php
php -l app\controller\biz\SaleProjectInvoiceController.php
php -l app\controller\biz\SaleProjectReissueOrderController.php
php -l app\controller\biz\SaleProjectRateController.php
php -l app\service\biz\SaleProjectBillingService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

Runtime smoke:

- Request one route without token and confirm API `code = 401`.
- Login locally and request representative page/list/detail routes with a token.
- Keep backend on port `82` and frontend on port `83` reachable for joint testing.

## 2026-06-15 Business Read HTTP Smoke

`scripts/business-read-http-smoke.ps1` now verifies billing-adjacent nested reads alongside the core customer and sale-project read contracts.

Covered billing checks:

- `GET /biz/saleprojectinvoicing/page`
- `GET /biz/saleprojectinvoicing/detail` when local invoicing sample data exists
- `GET /biz/saleprojectinvoicing/customer` when local invoicing/customer sample data exists
- `GET /biz/saleprojectinvoice/page`
- `GET /biz/saleprojectinvoice/list`
- `GET /biz/saleprojectinvoiceItem/page`
- `GET /biz/saleprojectinvoiceItem/page?invoiceId=...` when local invoice-item sample data exists
- `GET /biz/saleprojectreissueorder/list/query`

The smoke verifies Java-style paging keys, display aliases such as `projectName` and `customerName`, invoice-list nesting under `bizSaleProjectInvoice` and `invoiceItems`, and reissue-order nesting under `order` and `productItemList`.

This smoke is read-only. It does not call `/biz/saleprojectinvoicing/complete`, invoicing add/edit/delete, delivery invoice writes, reissue-order writes, stock, settlement, finance, workflow, file cleanup, provider, or sale-project state routes.
