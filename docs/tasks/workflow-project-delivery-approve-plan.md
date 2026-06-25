# Workflow Project Delivery Approval Plan

Date: 2026-06-22

Status: implemented and DB-backed HTTP-smoke verified.

## Scope

- Replace `POST /biz/process/project/delivery/start` controlled-deferred behavior with a bounded `Process_sale_project_delivery` runtime start.
- Keep the BPMN shape minimal: `StartEvent_1` to `Activity_approval`, then `Event_0kb2f2q` on cancel, reject, or approval.
- On start, validate required delivery form fields, approvers, copy users, project visibility, project state not `FOLLOW`, warehouses, project product item ids, product ids, and requested quantities.
- On cancel or reject, close workflow runtime/history rows with no delivery invoice, delivery record, inventory, or project state side effects.
- On approval, create sale-project delivery invoice rows, invoice item rows, OUT delivery records, inventory decrements, project product-item delivery updates, and sale-project delivery state recalculation.

## Transaction Strategy

- Workflow start uses the existing runtime transaction boundary in `WorkflowRuntimeService::startInitialApprovalProcess()`.
- Approval side effects run inside `SaleProjectService::applyProjectDeliveryFromWorkflow()` with locked project and product-item rows.
- Approval is idempotent by `biz_sale_project_invoice.PROCESS_ID`; if an invoice already exists for the process, the method returns the existing side-effect summary.
- Inventory rows are locked by warehouse/product before decrement. Missing inventory rows are inserted with a negative current count to match Java's permissive stock-out behavior.

## Side Effects

- `biz_sale_project_invoice`: one row per approved project delivery workflow.
- `biz_sale_project_invoice_item`: one row per submitted project product item.
- `delivery_record`: OUT rows with `PROCESS_ID = processInstanceId`, `PROCESS_CATEGORY = Process_sale_project_delivery`, and `OBJECT_ID = projectId`.
- `inventory`: decremented for each delivery record product/warehouse pair.
- `biz_sale_project_product_item`: `DELIVERY` is incremented and `STATE` moves to `PART_WAIT_DELIVER` or `SHIPPED`.
- `biz_sale_project`: `PROJECT_STATE` moves to `PARTIALLY_SHIPPED`, `SHIPPED`, or `COMPLETED` based on item delivery and payment state.

## Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`
- `php -l app\service\biz\SaleProjectService.php`
- `php -l app\controller\biz\ProcessController.php`
- PowerShell parser checks for:
  - `scripts\workflow-project-delivery-approve-http-smoke.ps1`
  - `scripts\project-preflight.ps1`
  - `scripts\frontend-deferred-write-wrapper-smoke.ps1`
  - `scripts\project-progress.ps1`
- `.\scripts\workflow-project-delivery-approve-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `php think route:list | Select-String "biz/process/project/(play|init|delivery|reissue|return)/start"`

## Deferred

- Project reissue and return workflows.
- Task SSE, notifications, Java data-change events, file cleanup, and production data sync.
- Java source changes, schema changes, `.env` changes, and commits.
