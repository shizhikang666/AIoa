# Workflow Side Effect Map

## Purpose

Java Camunda delegates perform business writes after process approval. ThinkPHP must replace those delegates with explicit PHP services, one process at a time.

## Delegate Replacement Queue

| Process Key | Java Delegate | Future PHP Direction | Risk |
| --- | --- | --- | --- |
| `Process_ask_leave` | `LeaveApproveDelegate` | Create leave/travel application record | Medium |
| `Process_reimbursement` | `BizReimbursementApproveDelegate` | Create expenditure record and serial flow | High |
| `Process_make_payment` | `BizReimbursementApproveDelegate` or payment-out delegate path | Create payment-out/expenditure records | High |
| `Process_payment` | `BizPaymentApproveDelegate` | Create payment-in record and serial flow | High |
| `Process_procure` | `BizProcureApproveDelegate` | Create purchase order data | High |
| `Process_procure_in_warehouse` | `BizProcureInWareHouseJavaDelegate` | Update warehouse/purchase state | High |
| `Process_sale_project_init` | `BizSaleProjectInitStateApproveDelegate` | Update sale project state | High |
| `Process_sale_project_play` | `BizSaleProjectPlayStateApproveDelegate` | Create sale project collection record | High |
| `Process_sale_project_delivery` | `BizSaleProjectDeliveryApproveDelegate` | Update delivery and warehouse records | High |
| `Process_project_reissue_product` | `BizSaleProjectReissueProductApproveDelegate` | Create reissue order | High |
| `Process_sale_project_product_return` | `BizSaleProjectReturnProductApproveDelegate` | Create return records | High |
| all configured copy users | `CopyUserDelegate` | Write `biz_cc_records` | Medium |

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
