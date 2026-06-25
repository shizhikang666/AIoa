# Purchase Order Warehouse Add Plan

Date: 2026-06-18

Agent: api-agent / test-agent

## Scope

Replace the protected `/biz/bizpurchaseorder/warehouse/add` controlled-deferred wrapper with Java-compatible batch purchase-order warehouse stock-in behavior.

This slice supports the copied purchase-order batch warehouse payload:

- `warehousesId`

## Compatibility Target

Java `BizPurchaseOrderServiceImpl.fastInWareHouse`:

- selects purchase orders with `SETTLEMENT_STATUS = COMPLETED` and `STORAGE_STATUS = NOT_IN_WAREHOUSE`;
- applies current-user data scope or creator fallback;
- converts each selected order to the same warehouse-in parameter used by one-add with `PROCESS_ID = Process_sys`, `PROCESS_CATEGORY = Process_procure_in_warehouse`, `OBJECT_ID = orderId`, current operator, and current delivery time;
- warehouses every active purchase-order item for each selected order;
- writes one `IN` delivery record per item using item `NUMBER`;
- marks each selected order and all of its items as `IN_WAREHOUSE`.

Java mutates inventory through delivery-record data-change events. The ThinkPHP slice performs durable database behavior directly in one transaction:

- lock the eligible purchase orders and affected item rows;
- validate the active warehouse and referenced products in each order tenant;
- insert `delivery_record` rows with `CATEGORY = IN`;
- create missing active `inventory` rows or increment existing rows;
- update selected purchase orders and items to `STORAGE_STATUS = IN_WAREHOUSE`;
- refresh audit fields and increment `VERSION` on changed rows.

## Guardrails

- The route remains behind `AuthMiddleware`.
- Batch selection is limited to completed, not-in-warehouse, non-deleted purchase orders visible to the current token.
- Failed validation, missing warehouse, invalid product ids, already-warehoused item rows, deleted inventory unique-key conflicts, and permission failures roll back the whole batch.
- `/biz/bizpurchaseorder/warehouse/one/add` remains the explicit single-order path and `/biz/bizpurchaseorder/add` plus `/biz/bizpurchaseorder/delete` remain controlled-deferred.
- This slice does not implement purchase-order creation/deletion, expenditure creation, settlement-account statements, workflow start/approval, Java data-change event publishing, frontend source changes, Java source changes, schema changes, `.env` changes, production data work, or commits.

## Verification

- `php -l app\controller\biz\PurchaseOrderController.php`
- `php -l app\service\biz\PurchaseOrderService.php`
- `php -l route\app.php`
- PowerShell syntax check for `scripts\purchase-order-warehouse-add-http-smoke.ps1`
- `php think route:list | Select-String -Pattern 'biz/bizpurchaseorder/(warehouse/add|warehouse/one/add|add|delete|cancel|edit|audit/edit|page|detail)'`
- `.\scripts\purchase-order-warehouse-add-http-smoke.ps1`
- `.\scripts\purchase-order-warehouse-one-add-http-smoke.ps1`
- `.\scripts\purchase-order-read-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
- `.\scripts\project-progress.ps1 -Lean`
- `git diff --check`
