# Workflow Project Return Auto Refund Plan

Date: 2026-06-25

Status: implemented and DB-backed HTTP-smoke verified on 2026-06-25.

## Scope

- Extend the already covered `Process_sale_project_product_return` approval side effects.
- After approval creates the return order, return items, IN delivery records, inventory increments, and return-total recalculation, create an automatic `ReturnAndRefund` expenditure/settlement-account statement when the sale project has an active `ACCOUNT_ID`.
- Reuse `SettlementAccountService::expensesFromWorkflow()` so account balance movement, statement creation, expenditure creation, return-order settlement-state correction, and project return-total correction follow the same path as direct `/biz/settlementaccount/expenses/add`.
- Keep approval idempotent by `return_order.PROCESS_ID`; if the return order already exists, do not create duplicate refund rows.

## Dependency Map

- Existing workflow start/approve runtime: `WorkflowRuntimeService::startProjectReturnProcess()` and `WorkflowRuntimeService::approveProjectReturn()`.
- Existing return side effects: `ReturnOrderService::applyProjectReturnFromWorkflow()`.
- Existing refund correction path: `SettlementAccountService::expensesFromWorkflow()` and `ReturnOrderService::applyReturnRefundExpenditure()`.
- Source account: `biz_sale_project.ACCOUNT_ID`. No new request field is introduced because the copied project-return form does not submit an account.

## Transaction Strategy

- Keep all approval side effects in the return-order workflow transaction.
- Create the refund expenditure only after the return order and IN stock side effects have been written.
- Let the existing settlement-account service lock the account, decrement `CURRENT_AMOUNT`, write `settlement_account_statement`, write `biz_expenditure_record`, and invoke return-order settlement correction.
- If an existing active return order already exists for the process id, return the existing summary and do not create a duplicate refund.
- If the project has no `ACCOUNT_ID`, skip automatic refund creation and leave the return order `Unsettled`.
- If the configured account is missing or inactive, fail the approval and roll back all return-order/stock side effects.

## Side Effects

- `settlement_account_statement`: one `EXPEND` row with `SETTLEMENT_CATEGORY = ReturnAndRefund`, `PROCESS_ID = processInstanceId`, and `PROCESS_CATEGORY = Process_sale_project_product_return`.
- `biz_expenditure_record`: one row with `OBJECT_ID = returnOrderId`, `SETTLEMENT_CATEGORY = ReturnAndRefund`, and amount equal to the return order amount.
- `settlement_account`: `CURRENT_AMOUNT` is decremented by the return order amount.
- `return_order`: settlement `STATE` becomes `AlreadySettled` for a full automatic refund.
- `biz_sale_project`: `TOTAL_RETURN_AMOUNT`, `TOTAL_REFUND_AMOUNT`, and `TOTAL_PRICE` are recalculated through the existing correction path.

## Verification

- `php -l app\service\biz\ReturnOrderService.php`
- PowerShell parser check for `scripts\workflow-project-return-approve-http-smoke.ps1`
- `.\scripts\workflow-project-return-approve-http-smoke.ps1`
- `.\scripts\return-order-write-http-smoke.ps1`
- `.\scripts\settlement-account-expenses-add-http-smoke.ps1`
- Frontend route/method/deferred-wrapper smoke checks.

## Deferred

- Reverse stock or finance correction for editing/deleting return orders after delivery/refund side effects.
- Automatic refund creation when the sale project has no configured settlement account.
- Notifications, Java data-change events, file cleanup, Java source changes, schema changes, `.env` changes, production data operations, and commits.
