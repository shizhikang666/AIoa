# Biz Sale Project Billing Read-Only API

Date: 2026-06-02

Agent: api-agent

## Scope

This document records the ThinkPHP read-only compatibility slice for Java sales-project billing-adjacent endpoints.

Implemented routes:

| Method | Path | Java source | Purpose |
| --- | --- | --- | --- |
| GET | `/biz/saleprojectinvoicing/page` | `BizSaleProjectInvoicingController.page` | Invoice application page list |
| GET | `/biz/saleprojectinvoicing/customer` | `BizSaleProjectInvoicingService.findLastEntityByCustomerId` | Latest invoice information for a customer |
| GET | `/biz/saleprojectinvoicing/detail` | `BizSaleProjectInvoicingController.detail` | Invoice application detail |
| GET | `/biz/saleprojectinvoice/page` | `BizSaleProjectInvoiceController.page` | Delivery invoice page list |
| GET | `/biz/saleprojectinvoice/list` | `BizSaleProjectInvoiceController.list` | Delivery invoices grouped with invoice items by project |
| GET | `/biz/saleprojectreissueorder/list/query` | `BizSaleProjectReissueOrderController.listQuery` | Reissue orders with nested product items |
| GET | `/biz/projectrate/page` | `SaleProjectRateController.page` | Project rating page list |
| GET | `/biz/projectrate/list` | `SaleProjectRateController.list` | Project rating list by project |
| GET | `/biz/projectrate/detail` | `SaleProjectRateService.detail/queryEntity` | Project rating detail by id |

All routes are protected by `AuthMiddleware`.

## Compatibility Notes

- The invoicing page keeps Java's invoiceable-project-state filter: `PARTIALLY_SHIPPED`, `SHIPPED`, and `COMPLETED`.
- Page responses use the existing ThinkPHP shape: `records`, `total`, `page`, `current`, `limit`, `size`, and `pages`.
- `/biz/saleprojectinvoice/list` returns Java-compatible entries with `bizSaleProjectInvoice` and `invoiceItems`.
- `/biz/saleprojectreissueorder/list/query` returns Java-compatible entries with `order` and `productItemList`.
- Reissue product items include `children`; child `extJson` is preserved, and if missing a minimal product JSON payload is synthesized for the frontend parser.
- `/biz/projectrate/detail` keeps the same normalized row shape as `/page` and `/list`, including raw `extJson`.
- Query responses include project/customer display aliases where useful, such as `projectName`, `customerName`, `orgName`, and `headName`.

## Deferred

The following endpoints and behaviors are intentionally not implemented in this slice:

- Invoice application add/edit/complete
- Delivery invoice add/edit/delete
- Reissue order add/start process
- Project rate add/edit/delete
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
