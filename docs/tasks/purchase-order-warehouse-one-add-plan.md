# Purchase Order Warehouse One Add Plan

Date: 2026-06-18

Agent: api-agent / test-agent

## Scope

Replace the protected `/biz/bizpurchaseorder/warehouse/one/add` controlled-deferred wrapper with Java-compatible single purchase-order warehouse stock-in behavior.

This slice supports the copied purchase-order one-click warehouse form payload:

- `orderId`
- `warehousesId`
- optional `remark`

## Compatibility Target

Java `BizPurchaseOrderServiceImpl.fastInWareOneHouse`:

- finds one active purchase order by `orderId` whose `STORAGE_STATUS` is `NOT_IN_WAREHOUSE`;
- checks the current user's data scope or creator fallback;
- converts the request into a warehouse-in parameter with `PROCESS_ID = Process_sys`, `PROCESS_CATEGORY = Process_procure_in_warehouse`, `OBJECT_ID = orderId`, current operator, and current delivery time;
- warehouses every active purchase-order item for that order;
- writes one `IN` delivery record per item using item `NUMBER`;
- marks the order and all items as `IN_WAREHOUSE`.

Java mutates inventory through delivery-record data-change events. The ThinkPHP slice performs the durable database behavior directly in one transaction:

- lock the purchase order, item rows, and affected inventory rows;
- validate the active warehouse and referenced products in the order tenant;
- insert `delivery_record` rows with `CATEGORY = IN`;
- create missing active `inventory` rows or increment existing rows;
- update purchase-order and item `STORAGE_STATUS` to `IN_WAREHOUSE`;
- refresh audit fields and increment `VERSION` on changed rows.

## Guardrails

- The route remains behind `AuthMiddleware`.
- Purchase order and warehouse writes use conservative admin/data-scope/create-user checks.
- Only `/biz/bizpurchaseorder/warehouse/one/add` moves out of deferred behavior in this slice.
- `/biz/bizpurchaseorder/warehouse/add`, `/biz/bizpurchaseorder/add`, and `/biz/bizpurchaseorder/delete` remain controlled-deferred.
- Failed validation, missing warehouse, invalid product ids, already-warehoused orders/items, deleted inventory unique-key conflicts, and permission failures must roll back delivery, inventory, order, and item writes.
- This slice does not implement batch stock-in, purchase-order creation/deletion, expenditure creation, settlement-account statements, workflow start/approval, Java data-change event publishing, frontend source changes, Java source changes, schema changes, `.env` changes, production data work, or commits.

## Verification

- `php -l app\controller\biz\PurchaseOrderController.php`
- `php -l app\service\biz\PurchaseOrderService.php`
- `php -l route\app.php`
- PowerShell syntax check for `scripts\purchase-order-warehouse-one-add-http-smoke.ps1`
- `php think route:list | Select-String -Pattern 'biz/bizpurchaseorder/(warehouse/one/add|warehouse/add|add|delete|cancel|edit|audit/edit|page|detail)'`
- `.\scripts\web-ready.ps1`
- `.\scripts\purchase-order-warehouse-one-add-http-smoke.ps1`
- `.\scripts\purchase-order-read-http-smoke.ps1`
- `.\scripts\purchase-order-cancel-http-smoke.ps1`
- `.\scripts\purchase-order-edit-http-smoke.ps1`
- `.\scripts\purchase-order-audit-edit-http-smoke.ps1`
- `.\scripts\inventory-delivery-read-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
- `.\scripts\project-progress.ps1 -Lean`
- `git diff --check`
