# Inventory Add Registration Plan

Date: 2026-06-17

Agent: api-agent / test-agent

## Scope

Replace the protected `/biz/inventory/add` controlled-deferred wrapper with narrow Java-compatible warehouse/product inventory registration.

This slice supports the copied inventory page action that submits:

- `warehousesId`
- `productIds`

## Compatibility Target

Java `InventoryServiceImpl.add` creates missing inventory rows for selected products in a warehouse and refreshes existing rows without changing their count. It also emits data-change events.

The ThinkPHP slice keeps only the durable database registration behavior:

- validate the active warehouse in the current tenant;
- validate active enabled products in the same tenant;
- reject duplicate product ids;
- reject deleted unique-key conflicts;
- insert missing `inventory` rows with `CURRENT_COUNT = 0`;
- preserve existing active row `CURRENT_COUNT`, only normalizing null to zero;
- refresh audit fields and increment `VERSION` for existing rows.

## Guardrails

- The route remains behind `AuthMiddleware`.
- Warehouse and product writes use conservative admin/data-scope/create-user checks.
- Inventory tenant is derived from the warehouse, not trusted from the request.
- Failed validation and missing product/warehouse cases do not write inventory rows or delivery rows.
- This slice does not implement inventory delete, stock movement, delivery creation, purchase-order warehouse entry, workflow hooks, Java data-change event publishing, frontend source changes, Java source changes, schema changes, `.env` changes, production data work, or commits.

## Verification

- `php -l app\controller\biz\InventoryController.php`
- `php -l app\service\biz\InventoryService.php`
- `php -l route\app.php`
- PowerShell syntax check for `scripts\inventory-add-http-smoke.ps1`
- `php think route:list | Select-String -Pattern 'biz/inventory/(add|delete|page|list|detail)'`
- `.\scripts\web-ready.ps1`
- `.\scripts\inventory-add-http-smoke.ps1`
- `.\scripts\inventory-delivery-read-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
- `.\scripts\project-progress.ps1 -Lean`
- `git diff --check`
