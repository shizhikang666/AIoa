# Delivery Record Add Plan

Date: 2026-06-18

Agent: api-agent / test-agent

## Scope

Replace the protected `/biz/warehouses/delivery/add` controlled-deferred wrapper with Java-compatible system inventory adjustment behavior.

This slice supports the copied inventory page's stocktaking action that submits:

- `warehousesId`
- `productId`
- `amount`
- `deliveryTime`
- optional `remark`

## Compatibility Target

Java `DeliveryRecordServiceImpl.add` treats `amount` as the target stock count. It compares the submitted target count with the current `inventory.CURRENT_COUNT` for the warehouse/product row:

- target greater than current: create one `IN` delivery record for the difference;
- target lower than current: create one `OUT` delivery record for the absolute difference;
- target equal to current: no delivery movement is needed.

Java publishes a delivery-record data-change event whose handler mutates inventory. The ThinkPHP slice performs the durable database behavior directly in one transaction:

- lock the active inventory row;
- validate active warehouse and enabled product in the current tenant;
- write one `delivery_record` row only when there is a non-zero difference;
- set `PROCESS_ID` and `PROCESS_CATEGORY` to `Process_sys`;
- set delivery `CATEGORY` to `IN` or `OUT`;
- update `inventory.CURRENT_COUNT` to the submitted target count;
- refresh inventory audit fields and increment `VERSION`.

## Guardrails

- The route remains behind `AuthMiddleware`.
- Warehouse and product writes use conservative admin/data-scope/create-user checks.
- Inventory must already exist; registration remains owned by `/biz/inventory/add`.
- Failed validation, missing warehouse/product/inventory, and invalid amount/time cases do not write delivery rows or mutate inventory.
- This slice does not implement delivery edit/delete, inventory delete, purchase-order warehouse stock-in, sale-project delivery, return stock-in, workflow hooks, Java data-change event publishing, frontend source changes, Java source changes, schema changes, `.env` changes, production data work, or commits.

## Verification

- `php -l app\controller\biz\DeliveryRecordController.php`
- `php -l app\service\biz\DeliveryRecordService.php`
- `php -l route\app.php`
- PowerShell syntax check for `scripts\delivery-record-add-http-smoke.ps1`
- `php think route:list | Select-String -Pattern 'biz/warehouses/delivery/(add|page|detail|exportOtherCompanyRecordsList)'`
- `.\scripts\web-ready.ps1`
- `.\scripts\delivery-record-add-http-smoke.ps1`
- `.\scripts\inventory-delivery-read-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
- `.\scripts\project-progress.ps1 -Lean`
- `git diff --check`
