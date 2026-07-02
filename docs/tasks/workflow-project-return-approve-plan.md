# Workflow Project Return Approval Plan

Date: 2026-06-25

Status: implemented and DB-backed HTTP-smoke verified on 2026-06-25. Extended on 2026-06-25 with automatic `ReturnAndRefund` creation when the sale project has an active settlement account.

## Scope

- Replace `POST /biz/process/project/return/start` controlled-deferred behavior with a bounded `Process_sale_project_product_return` runtime start.
- Match the Java BPMN shape: `Event_09dguuq` start, `Activity_approval`, and `Event_1q6ckfm` on cancel, reject, or approval.
- On start, validate project access, returnable project state, approvers, copy users, warehouse access, submitted `productList`, shipped project-product items, return quantities, logistics fields, and non-negative refund amount.
- On cancel or reject, close workflow runtime/history rows with no return-order, delivery, inventory, finance, refund, statement, invoice, or project amount side effects.
- On approval, create one `return_order` row, linked `return_order_item` rows, Java-compatible return IN `delivery_record` rows with `PROCESS_CATEGORY = Process_sale_project_product_return`, `PROCESS_ID = processInstanceId`, and `OBJECT_ID = returnOrderId`, and an automatic `ReturnAndRefund` expenditure when the sale project has an active `ACCOUNT_ID`.

## Dependency Map

- Java start route: `BizProcessProjectController#processStart(BizProcessProjectReturnParam)`.
- Java start service: `BizProjectProcessServiceImpl#startProcess(BizProcessProjectReturnParam)`.
- Java approve delegate: `BizSaleProjectReturnProductApproveDelegate`.
- Java business service: `ReturnOrderServiceImpl#add()` plus `DeliveryRecordServiceImpl#addBathInRecord()`.
- Copied frontend API wrapper: `snowy-admin-web/src/api/biz/bizProcessApi.js`, `processProjectReturnStart(data)`.
- Existing ThinkPHP readback: `/biz/returnorder/query` and `/biz/returnorder/detail`.

## Transaction Strategy

- Workflow start uses the existing runtime transaction boundary in `WorkflowRuntimeService::startInitialApprovalProcess()`.
- Approval side effects run inside `ReturnOrderService::applyProjectReturnFromWorkflow()` with the sale-project row locked.
- Approval is idempotent by `return_order.PROCESS_ID`; if a return order already exists for the process, the method returns the existing side-effect summary.
- Return-order, return-order-item, delivery-record rows, inventory increments, optional auto-refund expenditure/statement rows, settlement-account decrement, and sale-project return-total recalculation are written in the same transaction. Any invalid item, warehouse, relation, inventory, account, or amount rolls back the whole approval.

## Side Effects

- `return_order`: one row per approved project return workflow.
- `return_order_item`: one row per submitted returned project-product item.
- `delivery_record`: one IN row per merged returned product. Kit relations decompose the returned project-product item into child product rows before merging, matching Java `createDeliveryOutRecordStrategy()`.
- `inventory`: one locked warehouse/product row is incremented per returned product; a missing active inventory row is created, matching the Java delivery-record event handler plus `InventoryService.inInventory()` behavior.
- `settlement_account_statement` and `biz_expenditure_record`: when the project has an active `ACCOUNT_ID`, one `ReturnAndRefund` expense is created with `PROCESS_ID = processInstanceId`, statement `PROCESS_CATEGORY = Process_sale_project_product_return`, and `OBJECT_ID = returnOrderId`.
- `settlement_account`: when auto-refund is created, `CURRENT_AMOUNT` is decremented by the return-order amount.
- `return_order`: settlement `STATE` becomes `AlreadySettled` after full automatic refund.
- `biz_sale_project`: `TOTAL_REFUND_AMOUNT`, `TOTAL_RETURN_AMOUNT`, and `TOTAL_PRICE` are recalculated through the existing return-order total logic.
- No payment, invoice, project state, notification, Java data-change event, or file cleanup side effect is performed in this approval block.

## Verification

- `php -l app\service\workflow\WorkflowRuntimeService.php`
- `php -l app\service\biz\ReturnOrderService.php`
- `php -l app\controller\biz\ProcessController.php`
- PowerShell parser checks for:
  - `scripts\workflow-project-return-approve-http-smoke.ps1`
  - `scripts\project-preflight.ps1`
  - `scripts\frontend-deferred-write-wrapper-smoke.ps1`
  - `scripts\project-progress.ps1`
- `.\scripts\workflow-project-return-approve-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `php think route:list | Select-String "biz/process/project/(play|init|delivery|reissue|return)/start"`

## Deferred

- Automatic refund expenditure creation when the sale project has no configured settlement account.
- Reverse stock or finance correction for editing/deleting return orders after delivery or refund side effects.
- Notifications, Java data-change events, file cleanup, and production data sync.
- Java source changes, schema changes, `.env` changes, and commits.
