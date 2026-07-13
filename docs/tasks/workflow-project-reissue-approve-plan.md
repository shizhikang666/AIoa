# Workflow Project Reissue Approval Plan

Date: 2026-06-25

Status: in progress.

## Scope

- Replace `POST /biz/process/project/reissue/start` controlled-deferred behavior with a bounded `Process_project_reissue_product` runtime start.
- Keep the BPMN shape minimal: `StartEvent_1` to `Activity_approval`, then `Event_0kb2f2q` on cancel, reject, or approval.
- On start, validate project access, approvers, copy users, product list, positive product quantities, non-negative price fields, enabled product ids, and submitted amount.
- On cancel or reject, close workflow runtime/history rows with no reissue-order, product-item, inventory, delivery, invoice, finance, or project status side effects.
- On approval, create one `biz_sale_project_reissue_order` row and append linked `biz_sale_project_product_item` rows with `CATEGORY = REISSUE_ORDER`, `STATE = WAIT_DELIVER`, and `PROJECT_REISSUE_ORDER_ID = reissueOrderId`.

## Dependency Map

- Java start route: `BizProcessProjectController#processStart(BizProcessProjectReissueProductParam)`.
- Java start service: `BizProjectProcessServiceImpl#startProcess(BizProcessProjectReissueProductParam)`.
- Java approve delegate: `BizSaleProjectReissueProductApproveDelegate`.
- Java business service: `BizSaleProjectReissueOrderServiceImpl#add()` plus `BizSaleProjectProductItemServiceImpl#addBathWithReissueOrder()`.
- Copied frontend form: `startProjectReissueFlowForm.vue`, which posts `projectId`, `productList`, `amount`, `remark`, `approveUserIdList`, and `copyUserIdList`.
- Existing ThinkPHP readback: `/biz/saleprojectreissueorder/list/query` returns reissue orders and linked product items.

## Transaction Strategy

- Workflow start uses the existing runtime transaction boundary in `WorkflowRuntimeService::startInitialApprovalProcess()`.
- Approval side effects run inside `SaleProjectService::applyProjectReissueFromWorkflow()` with the sale-project row locked.
- Approval is idempotent by `biz_sale_project_reissue_order.PROCESS_ID`; if a reissue order already exists for the process, the method returns the existing side-effect summary.
- Product rows and child relation rows are inserted in the same transaction as the reissue order. Any invalid product or child product rolls back the whole approval.

## Side Effects

- `biz_sale_project_reissue_order`: one row per approved project reissue workflow.
- `biz_sale_project_product_item`: one appended row per submitted top-level product, linked to the new reissue order.
- `sale_project_product_item_relation`: one appended row per submitted or auto-hydrated kit child relation.
- No `delivery_record`, `inventory`, `biz_sale_project_invoice`, settlement, payment, expenditure, file cleanup, notification, Java data-change event, or project status update is performed in this block.

## Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`
- `php -l app\service\biz\SaleProjectService.php`
- `php -l app\controller\biz\ProcessController.php`
- PowerShell parser checks for:
  - `scripts\workflow-project-reissue-approve-http-smoke.ps1`
  - `scripts\project-preflight.ps1`
  - `scripts\frontend-deferred-write-wrapper-smoke.ps1`
  - `scripts\project-progress.ps1`
- `.\scripts\workflow-project-reissue-approve-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `php think route:list | Select-String "biz/process/project/(play|init|delivery|reissue|return)/start"`

## Deferred

- Project return workflow.
- Reissue delivery/stock-in side effects beyond the appended wait-deliver product rows.
- Task SSE, notifications, Java data-change events, file cleanup, and production data sync.
- Java source changes, schema changes, `.env` changes, and commits.
