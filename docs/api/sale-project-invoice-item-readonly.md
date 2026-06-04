# Sale Project Invoice Item Read-Only Compatibility

Date: 2026-06-04

Agent: api-agent / frontend-agent

## Scope

This slice adds read-only ThinkPHP compatibility for sale-project delivery invoice item pagination.

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Added Route

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/biz/saleprojectinvoiceItem/page` | Page delivery invoice item rows by invoice and warehouse. |

The route is protected by `AuthMiddleware`.

## Java Compatibility Notes

- Java `BizSaleProjectInvoiceItemController` exposes `/biz/saleprojectinvoiceItem/page`.
- Java `BizSaleProjectInvoiceItemServiceImpl.page` filters by `invoiceId` and `warehousesId` when supplied.
- Java defaults sorting to `PROJECT_PRODUCT_ITEM_ID` ascending.
- The ThinkPHP response keeps camelCase aliases through the existing billing row normalization.
- Product and warehouse display fields are included to match sale-project invoice detail data already returned elsewhere.

## Deferred

The following remain intentionally deferred:

- invoice item add/edit/delete routes
- invoice creation and edit writes
- delivery shipment writes
- stock changes
- project state changes
- finance/payment side effects
- Java source changes
- database schema changes
