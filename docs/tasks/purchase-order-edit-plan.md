# Purchase Order Edit Plan

Date: 2026-06-17

Agent: api-agent / test-agent

## Scope

Replace the protected `/biz/bizpurchaseorder/edit` controlled-deferred wrapper with narrow Java-compatible purchase-order edit behavior.

This slice supports the copied purchase-order edit drawer payload:

- `id`
- `amount`
- `productList`

Each `productList` entry is limited to existing purchase-order item fields exposed by Java `BizPurchaseOrderItemEditParam`:

- `id`
- `amount`
- `unitAmount`
- `discountRate`
- `freightShareAmount`
- `unitCostWithFreight`

## Compatibility Target

Java `BizPurchaseOrderServiceImpl.edit`:

- loads the purchase order;
- rejects completed orders;
- rejects orders that already have goods-expenditure records;
- copies edit-param fields to the order;
- edits submitted purchase-order item rows;
- updates the order.

The ThinkPHP slice keeps the same durable database boundary while adding conservative safety checks:

- the route stays behind `AuthMiddleware`;
- the order is locked in the current tenant;
- admin/data-scope/create-user write scope is required;
- submitted items must be active rows belonging to the same order;
- only the Java edit-param fields are updated;
- no rows are inserted or deleted.

## Guardrails

- Purchase-order add, delete, warehouse add, and one-click warehouse add remain controlled-deferred. Audit edit is covered by a separate narrow remediation slice.
- This slice does not create purchase orders, create/delete item rows, create expenditure rows, update settlement/account statements, move inventory, create delivery records, start workflow, publish Java data-change events, edit frontend source, change schema, edit `.env`, perform production data work, or commit.
- Failed validation, completed-order guard, expenditure guard, missing-order guard, and item-mismatch guard must roll back all order/item changes.

## Verification

- `php -l app\controller\biz\PurchaseOrderController.php`
- `php -l app\service\biz\PurchaseOrderService.php`
- `php -l route\app.php`
- PowerShell syntax check for `scripts\purchase-order-edit-http-smoke.ps1`
- `php think route:list | Select-String -Pattern 'biz/bizpurchaseorder/(edit|audit/edit|add|delete|warehouse/add|warehouse/one/add|cancel|page|detail)'`
- `.\scripts\web-ready.ps1`
- `.\scripts\purchase-order-edit-http-smoke.ps1`
- `.\scripts\purchase-order-cancel-http-smoke.ps1`
- `.\scripts\purchase-order-read-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
- `.\scripts\project-progress.ps1 -Lean`
- `git diff --check`
