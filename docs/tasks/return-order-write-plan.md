# Return Order Write Plan

Date: 2026-06-18

Agent: merge-agent / main control agent

## Scope

Replace the controlled-deferred `/biz/returnorder/add`, `/edit`, and `/delete` wrappers with direct return-order master/detail maintenance.

In scope:

- `return_order` insert/update/logical delete;
- `return_order_item` insert and logical delete;
- tenant/data-scope permission checks;
- warehouse and project product item validation;
- cumulative returned quantity guard;
- linked-refund edit/delete guard;
- sale-project return total recalculation;
- DB-backed HTTP smoke coverage.

Out of scope:

- workflow/process start and approval;
- delivery-record creation;
- inventory stock mutation;
- refund/expenditure creation or deletion;
- settlement-account statement creation;
- notifications;
- Java event bus publishing;
- Java source changes;
- database schema changes.

## Java Behavior Notes

Java `ReturnOrderServiceImpl.add` creates the master row, child rows, delivery-in records, and then fires `BIZ_RETURN_ORDER` data-change handling. That handler recalculates the owning sale project's totals.

Java refund expenditure creation later calls `ReturnOrderService.updateStatus`, which updates the return order settlement status and sale-project totals.

This ThinkPHP block implements the direct row maintenance and the sale-project totals correction, but leaves inventory, delivery, refund creation, workflow, and event-publishing behavior deferred. Existing linked `ReturnAndRefund` expenditure rows make return-order edit/delete unsafe, so the ThinkPHP endpoint rejects those mutations.

## Validation Rules

- `add` requires `projectId`, `amount >= 0`, `warehousesId`, and a nonempty `productList`.
- `projectId` must resolve to an active, visible sale project in state `PARTIALLY_SHIPPED`, `SHIPPED`, or `COMPLETED`.
- `warehousesId` must resolve to an active warehouse in the same tenant and write scope.
- each submitted product line must reference an active `biz_sale_project_product_item` in the same project and tenant with `STATE = SHIPPED`;
- line `amount` must be greater than zero;
- cumulative active return quantity for each project product item must not exceed the original project product item `NUMBER`;
- `edit` requires `id` and rejects project changes unless a replacement `productList` is supplied;
- `edit` and `delete` reject active linked `biz_expenditure_record` rows with `SETTLEMENT_CATEGORY = ReturnAndRefund`;
- `delete` validates the full id batch before marking any row deleted.

## Transaction Strategy

- `add` writes the master row, child rows, and sale-project totals in one transaction.
- `edit` locks the current return order, validates all target rows, updates the master row, replaces child rows only when `productList` is submitted, and recalculates affected project totals in one transaction.
- `delete` validates all target rows, checks linked refund expenditure, logically deletes master/detail rows, and recalculates affected project totals in one transaction.

## Verification

Focused smoke:

```powershell
.\scripts\return-order-write-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
```

Regression smokes:

```powershell
.\scripts\frontend-deferred-write-wrapper-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
.\scripts\frontend-api-method-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
.\scripts\business-read-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
```
