# Biz Sale Project Billing Compatibility API

Date: 2026-06-05

Agent: api-agent / frontend-agent

## Scope

This document records the ThinkPHP compatibility slice for Java sales-project billing-adjacent endpoints. Delivery invoice endpoints include readback plus narrow direct `add`/`edit`/`delete` support; standalone invoice-item endpoints remain read-only. Reissue orders now include readback plus narrow direct `add`/`edit`/`delete` support, project rating includes narrow `add`/`edit` and logical `delete` support, and sale-project invoicing includes narrow invoice-application add/edit/delete plus the Java-style complete marker.

Implemented routes:

| Method | Path | Java source | Purpose |
| --- | --- | --- | --- |
| GET | `/biz/saleprojectinvoicing/page` | `BizSaleProjectInvoicingController.page` | Invoice application page list |
| GET | `/biz/saleprojectinvoicing/customer` | `BizSaleProjectInvoicingService.findLastEntityByCustomerId` | Latest invoice information for a customer |
| GET | `/biz/saleprojectinvoicing/detail` | `BizSaleProjectInvoicingController.detail` | Invoice application detail |
| POST | `/biz/saleprojectinvoicing/complete` | `BizSaleProjectInvoicingController.complete` | Mark invoice application as complete |
| POST | `/biz/saleprojectinvoicing/add` | `BizSaleProjectInvoicingService.add` plus copied frontend route | Add one invoice application row |
| POST | `/biz/saleprojectinvoicing/edit` | `BizSaleProjectInvoicingEditParam` copied frontend route | Edit one invoice application row |
| POST | `/biz/saleprojectinvoicing/delete` | Copied frontend route | Logically delete invoice application rows |
| GET | `/biz/saleprojectinvoice/page` | `BizSaleProjectInvoiceController.page` | Delivery invoice page list |
| GET | `/biz/saleprojectinvoice/list` | `BizSaleProjectInvoiceController.list` | Delivery invoices grouped with invoice items by project |
| POST | `/biz/saleprojectinvoice/add` | `BizSaleProjectInvoiceService.add` plus invoice-item add event shape | Direct delivery invoice plus linked invoice items |
| POST | `/biz/saleprojectinvoice/edit` | `BizSaleProjectInvoiceService.edit` shape | Edit delivery invoice logistics/master fields |
| POST | `/biz/saleprojectinvoice/delete` | Copied frontend route plus reverse correction plan | Logically delete direct delivery invoices and reverse product-item delivery |
| GET | `/biz/saleprojectreissueorder/list/query` | `BizSaleProjectReissueOrderController.listQuery` | Reissue orders with nested product items |
| POST | `/biz/saleprojectreissueorder/add` | `BizSaleProjectReissueOrderService.add` / `BizSaleProjectReissueProductApproveDelegate` side-effect shape | Direct reissue order plus linked project-product items |
| POST | `/biz/saleprojectreissueorder/edit` | Compatibility endpoint for direct rows | Replace direct reissue order master fields plus linked project-product items |
| POST | `/biz/saleprojectreissueorder/delete` | Compatibility endpoint for direct rows | Logically delete direct reissue orders plus linked project-product items |
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
- `/biz/saleprojectinvoice/add` creates one active delivery invoice row and linked invoice-item rows, increments linked project-product `DELIVERY`, updates product-item delivery `STATE`, recalculates the owning sale project's shipment state, and intentionally does not create `delivery_record`, inventory, finance, workflow, notification, file-cleanup, or Java data-change side effects.
- `/biz/saleprojectinvoice/edit` updates only the invoice master/logistics fields and leaves invoice items, product-item delivery quantities, stock, finance, workflow, notification, file-cleanup, and Java data-change side effects unchanged. If the old `PROCESS_ID` already has active `delivery_record` rows, changing `PROCESS_ID` is rejected.
- `/biz/saleprojectinvoice/delete` validates the full selected invoice batch first, rejects invoices whose `PROCESS_ID` owns active `delivery_record` rows, logically deletes the invoice and invoice items, reverses linked project-product `DELIVERY`, recalculates product-item `STATE`, and recalculates sale-project shipment state in one transaction.
- `/biz/saleprojectreissueorder/list/query` returns Java-compatible entries with `order` and `productItemList`.
- Reissue product items include `children`; child `extJson` is preserved, and if missing a minimal product JSON payload is synthesized for the frontend parser.
- `/biz/saleprojectreissueorder/add` creates one active reissue-order row, linked `REISSUE_ORDER`/`WAIT_DELIVER` sale-project product items, and child relation rows, then recalculates sale-project totals and project/payment status in one transaction. Submitted `PROCESS_ID` values that already exist in `act_hi_procinst` are rejected so workflow process ids remain workflow-owned.
- `/biz/saleprojectreissueorder/edit` replaces the direct reissue order `PROCESS_ID`, `AMOUNT`, `REMARK`, and linked product rows only while existing linked items are `REISSUE_ORDER`, `WAIT_DELIVER`, undelivered, and not referenced by invoice/return rows. Workflow-owned rows are rejected by checking `act_hi_procinst.PROC_INST_ID_`.
- `/biz/saleprojectreissueorder/delete` validates the full batch before mutation, rejects workflow-owned/delivered/referenced rows, logically deletes the reissue order, linked product items, and child relations, then recalculates sale-project totals and project/payment status in one transaction.
- `/biz/projectrate/detail` keeps the same normalized row shape as `/page` and `/list`, including raw `extJson`.
- `/biz/projectrate/add` requires `projectId` and `subject`, defaults missing `rateAmount` to `0.00`, defaults missing `content` to an empty string, and stores submitted `imgList` under `EXT_JSON` as `{"imgList":[...]}`.
- `/biz/projectrate/edit` updates only the rating row's project id, tenant id, score, content, subject, `EXT_JSON`, and audit update fields. It reuses the existing project-scope guard and stores submitted `imgList` as `{"imgList":[...]}` without physical file cleanup.
- `/biz/projectrate/delete` accepts Java-style `[{id: ...}]`, `idList`, `ids`, or a single `id`; it uses `DELETE_FLAG = DELETED` instead of physical removal.
- `/biz/saleprojectinvoicing/complete` accepts `{ "id": "..." }`, validates the row through the existing project scope and tenant filters, updates only `INVOICING_STATE = INVOICING_STATE_COMPLETE` plus audit update fields, and returns `data = null`.
- `/biz/saleprojectinvoicing/add` validates the active sale project through tenant/data-scope guards, requires Java add-param fields, forces `INVOICING_STATE = INVOICING_STATE_WAIT`, writes one `biz_sale_project_invoicing` row, and returns the normalized detail payload.
- `/biz/saleprojectinvoicing/edit` validates the existing row and target project through tenant/data-scope guards, requires Java edit-param fields, allows only invoice application fields plus optional `INVOICING_STATE`, and refreshes audit fields.
- `/biz/saleprojectinvoicing/delete` accepts `idList`, `ids`, single `id`, or Java-style arrays, validates every selected active row before writing, and then sets `DELETE_FLAG = DELETED` in one transaction.
- Query responses include project/customer display aliases where useful, such as `projectName`, `customerName`, `orgName`, and `headName`.

## Deferred

The following endpoints and behaviors are intentionally not implemented in this slice:

- Delivery invoice item replacement/edit writes
- Standalone invoice-item add/edit/delete
- Reissue start process and workflow runtime mutation outside the covered `Process_project_reissue_product` start/approval path
- Project rate file upload/storage cleanup
- Workflow side effects
- Inventory stock mutations outside workflow-owned project delivery approval
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
- Run `.\scripts\sale-project-invoicing-write-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82` for DB-backed add/edit/delete/complete and rollback coverage.
- Run `.\scripts\sale-project-invoice-add-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82` for DB-backed direct delivery-invoice add/edit/delete, product-item delivery-state coverage, and delete-time reverse correction.
- Run `.\scripts\sale-project-reissue-order-add-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82` for DB-backed direct reissue-order add/edit/delete, product-item replacement/logical-delete coverage, and project total/status correction.
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

This read smoke is read-only. It does not call `/biz/saleprojectinvoicing/complete`, invoicing add/edit/delete, delivery invoice writes, reissue-order writes, stock, settlement, finance, workflow, file cleanup, provider, or sale-project state routes. Direct delivery-invoice add/edit/delete and direct reissue-order add/edit/delete are covered by separate smokes below.

## 2026-06-25 Sale Project Invoice Add/Edit/Delete HTTP Smoke

`scripts/sale-project-invoice-add-http-smoke.ps1` verifies the focused direct delivery-invoice add/edit/delete write block against the local authenticated backend.

Covered checks:

- no-token, missing item list, `FOLLOW` project, over-delivery, duplicate `processId`, edit project guard, delete missing id, and mixed-delete rollback guards;
- successful `POST /biz/saleprojectinvoice/add` row creation;
- successful `POST /biz/saleprojectinvoice/edit` logistics/master-field update without item mutation;
- successful `POST /biz/saleprojectinvoice/delete` logical delete with project-product delivery reverse correction;
- one `biz_sale_project_invoice` row and one linked `biz_sale_project_invoice_item` row;
- linked project-product `DELIVERY` increment, product-item `STATE` correction, and sale-project shipment-state correction;
- readback through `/biz/saleprojectinvoice/list` and `/biz/saleprojectinvoiceItem/page` before and after delete;
- unchanged delivery-record, inventory, invoicing, payment, expenditure, settlement-statement, and workflow row counts.

## 2026-06-25 Sale Project Reissue Order Add/Edit/Delete HTTP Smoke

`scripts/sale-project-reissue-order-add-http-smoke.ps1` verifies the focused direct reissue add/edit/delete write block against the local authenticated backend.

Covered checks:

- no-token, missing `productList`, missing product, `FOLLOW` project, workflow-process `processId`, duplicate `processId`, missing edit id, edit project guard, missing delete id list, and mixed-delete rollback guards;
- successful `POST /biz/saleprojectreissueorder/add` row creation;
- successful `POST /biz/saleprojectreissueorder/edit` master-field/product-list replacement with old item/relation logical delete;
- successful `POST /biz/saleprojectreissueorder/delete` order/item/relation logical delete and project total restoration;
- active and historical `biz_sale_project_reissue_order`, `biz_sale_project_product_item`, and `sale_project_product_item_relation` row state;
- sale-project total and status recalculation after add/edit/delete;
- readback through `/biz/saleprojectreissueorder/list/query` before and after delete;
- unchanged delivery, inventory, invoice, invoicing, payment, expenditure, settlement-statement, and workflow row counts.

## 2026-06-18 Sale Project Invoicing Write HTTP Smoke

`scripts/sale-project-invoicing-write-http-smoke.ps1` verifies the focused invoicing write block against the local authenticated backend.

Covered checks:

- no-token, missing-field, invalid category/state, and missing-project guards;
- add creates one invoice application row with `INVOICING_STATE_WAIT`;
- page, customer, and detail reads expose the new row;
- invalid edit rolls back without changing the row;
- valid edit updates amount, category, contact, tax, bank, address, and audit fields;
- complete sets `INVOICING_STATE_COMPLETE`;
- mixed existing/missing delete rolls back;
- valid delete logically deletes the row;
- linked sale project and invoice/payment/expenditure/statement/delivery/return/rating side-effect table counts stay unchanged.
