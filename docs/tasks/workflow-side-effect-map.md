# Workflow Side Effect Map

## Purpose

Java Camunda delegates perform business writes after process approval. ThinkPHP must replace those delegates with explicit PHP services, one process at a time.

## Delegate Replacement Queue

| Process Key | Java Delegate | Future PHP Direction | Risk |
| --- | --- | --- | --- |
| `Process_ask_leave` | `LeaveApproveDelegate` | Create leave/travel application record | Medium |
| `Process_reimbursement` | `BizReimbursementApproveDelegate` | Covered: two-step approval creates expense statement/expenditure record and decrements settlement account through PHP settlement-account service | High |
| `Process_make_payment` | `BizReimbursementApproveDelegate` or payment-out delegate path | Covered: two-step approval creates expense statement/expenditure record and decrements settlement account through PHP settlement-account service | High |
| `Process_payment` | `BizPaymentApproveDelegate` | Covered: create payment-in statement/record and increment settlement account through PHP settlement-account service | High |
| `Process_procure` | `BizProcureApproveDelegate` | Covered: staged approval creates purchase order and purchase-order items through PHP purchase-order service | High |
| `Process_procure_in_warehouse` | `BizProcureInWareHouseJavaDelegate` | Covered: update purchase storage state, delivery rows, inventory through PHP purchase-order warehouse-in service | Medium |
| `Process_sale_project_init` | `BizSaleProjectInitStateApproveDelegate` | Covered: start marks `PENDING_APPROVAL`; approve writes sale-project fields, product items, project file relations, optional invoicing, `PROCESS_ID`, and customer deal amount; reject/cancel rolls back to `FOLLOW` | High |
| `Process_sale_project_play` | `BizSaleProjectPlayStateApproveDelegate` | Covered: two-step approval creates project collection statement/payment rows and recalculates sale-project payment status | High |
| `Process_sale_project_delivery` | `BizSaleProjectDeliveryApproveDelegate` | Update delivery and warehouse records | High |
| `Process_project_reissue_product` | `BizSaleProjectReissueProductApproveDelegate` | Create reissue order | High |
| `Process_sale_project_product_return` | `BizSaleProjectReturnProductApproveDelegate` | Create return records | High |
| all configured copy users | `CopyUserDelegate` | Write `biz_cc_records` | Medium |

## Current ThinkPHP Runtime Coverage

- `Process_ask_leave` start, initial cancel/edit, and `Activity_approval` approve/reject are covered with bounded runtime/history writes; approved leave rows and annual-leave deduction are covered.
- `Process_payment`, `Process_reimbursement`, `Process_make_payment`, `Process_procure`, and `Process_procure_in_warehouse` are covered for first-step start, runtime/history variables, CC/file relation rows, readback, and initial cancel.
- `Process_payment` approval is covered for payment-in side effects: settlement-account income statement, `biz_payment_record` with the workflow process id, and account balance increment.
- `Process_reimbursement` and `Process_make_payment` approval are covered for payment-out side effects: first approval advances to `Activity_pay_approval`, finance approval creates settlement-account expense statement and `biz_expenditure_record` rows with the workflow process id, and account balance decrement.
- `Process_procure` approval is covered for purchase-order side effects: first approval advances to procurement confirmation, procurement confirmation stores `productList`/`amount`, optional general-office approval is supported, and final approval creates `biz_purchase_order` plus `biz_purchase_order_item` rows with the workflow process id.
- `Process_procure_in_warehouse` approval is covered for purchase-order stock-in side effects: order/item storage state, `delivery_record` rows with the workflow process id, and inventory increments.
- `Process_sale_project_init` is covered for project-init side effects: start moves a `FOLLOW` sale project to `PENDING_APPROVAL`; approval writes delivery/account/amount fields, product items, `SALE_PROJECT` file relations, optional invoicing rows, `PROCESS_ID`, and customer deal amount; reject/cancel rolls back to `FOLLOW`.
- `Process_sale_project_play` is covered for project collection side effects: start creates first-step runtime rows; first approval advances to `Activity_payment_approval`; finance approval writes `settlement_account_statement` and `biz_payment_record` rows with `PROCESS_ID`, `PROCESS_CATEGORY = Process_sale_project_play`, and `SETTLEMENT_CATEGORY = PROJECT_PLAY`, then recalculates sale-project `AMOUNT_COLLECTED`, `PLAY_STATE`, and `PROJECT_STATE`; reject/cancel close without finance side effects.
- Remaining project delegates for delivery, reissue, and return are not implemented yet.

## First Implementation Recommendation

Start with read-only APIs. For mutation flows, choose a low-blast-radius process first. `Process_ask_leave` is the best first candidate because it is narrower than sales/finance/warehouse flows.

## Guardrails

- Do not delete or overwrite existing process/task/history records.
- Do not update business state without an idempotency plan.
- Do not implement financial or warehouse side effects before model coverage and tests are ready.
- Keep side effects explicit, named, and process-key based.
- Record every process mutation in a future audit-friendly structure or existing compatible tables.

## Final Data Sync Reminder

The project still needs online real-time data synchronization after completion. Workflow runtime tables and side-effect business tables must be included in that final sync plan.
