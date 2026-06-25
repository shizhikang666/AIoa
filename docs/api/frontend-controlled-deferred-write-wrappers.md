# Frontend Controlled Deferred Write Wrappers

Date: 2026-06-18

## Scope

This document tracks frontend API exports and protected ThinkPHP wrapper routes for copied Vue write controls that are visible or reachable, but whose real business behavior is still deferred.

The remaining wrappers return Java-style JSON envelopes with `code = 400`. They do not call service-layer write methods, write database rows, update balances, move inventory, start workflow, delete records, read provider credentials, change schema, or modify Java source.

## Added Controlled-Deferred Routes

Payment record:

- `POST /biz/bizpaymentrecord/add`
- `POST /biz/bizpaymentrecord/delete`

Expenditure record:

- `POST /biz/bizexpenditurerecord/add`
- `POST /biz/bizexpenditurerecord/delete`

Collection receipt:

- `POST /biz/bizcollectionreceipt/add`
- `POST /biz/bizcollectionreceipt/edit`
- `POST /biz/bizcollectionreceipt/delete`

Debit note:

- `POST /biz/bizdebitnote/add`
- `POST /biz/bizdebitnote/edit`
- `POST /biz/bizdebitnote/delete`

Purchase order:

- `POST /biz/bizpurchaseorder/add`
- `POST /biz/bizpurchaseorder/delete`

Inventory and delivery:

- `POST /biz/inventory/delete`

HR and workflow-adjacent records:

- `POST /biz/bizleaveapplication/add`
- `POST /biz/bizpayroll/add`

Dev provider/job actions:

- `POST /dev/email/sendLocalTxt`
- `POST /dev/email/sendLocalHtml`
- `POST /dev/email/sendAliyunTxt`
- `POST /dev/email/sendAliyunHtml`
- `POST /dev/email/sendAliyunTmp`
- `POST /dev/email/sendTencentTxt`
- `POST /dev/email/sendTencentHtml`
- `POST /dev/email/sendTencentTmp`

Generator writes:

- `POST /gen/basic/execGenPro`
- `POST /gen/config/add`

Workflow, task, and sale-project state actions:

- `POST /biz/process/project/reissue/start`
- `POST /biz/process/project/return/start`
- `GET /biz/task/sse/stream`

Sale project invoicing add/edit/delete moved out of this controlled-deferred list on 2026-06-18. Those routes are now narrow invoice-application row maintenance covered by `scripts/sale-project-invoicing-write-http-smoke.ps1`.

Return-order add/edit/delete moved out of this controlled-deferred list on 2026-06-18. Those routes are now direct return-order master/detail maintenance with project-total recalculation covered by `scripts/return-order-write-http-smoke.ps1`.

Leave process start moved out of this controlled-deferred list on 2026-06-22. `POST /biz/process/leave/start` now creates a minimal `Process_ask_leave` runtime/history row set covered by `scripts/workflow-leave-start-http-smoke.ps1`.

Task approve/reject moved out of this controlled-deferred list on 2026-06-22 for `Process_ask_leave` `Activity_approval`, and later the same day `Process_payment`, `Process_reimbursement`, `Process_make_payment`, `Process_procure`, `Process_procure_in_warehouse`, `Process_sale_project_delivery`, and `Process_sale_project_play` approval moved into bounded finance/procurement/warehouse/project side effects. `POST /biz/task/approve` and `POST /biz/task/reject` complete the minimal leave workflow runtime/history row set covered by `scripts/workflow-task-transition-http-smoke.ps1`; approved leave transitions also create/read back one `biz_leave_application` row, and approved `annualLeave` transitions deduct the current-year annual-leave balance. `Process_payment` approve now writes a payment-in statement/payment record and increments the settlement account, covered by `scripts/workflow-payment-approve-http-smoke.ps1`. `Process_reimbursement` and `Process_make_payment` first approval now creates `Activity_pay_approval`, and finance approval writes an expenditure/expense statement record and decrements the settlement account, covered by `scripts/workflow-payment-out-approve-http-smoke.ps1`. `Process_procure` approve now advances through procurement confirmation and optional general-office approval before creating purchase-order rows, covered by `scripts/workflow-procure-approve-http-smoke.ps1`. `Process_procure_in_warehouse` approve now writes purchase-order stock-in rows covered by `scripts/workflow-procure-warehouse-approve-http-smoke.ps1`. `Process_sale_project_delivery` approval now writes delivery invoice rows, invoice item rows, OUT delivery records, inventory decrements, product-item delivery status, and project delivery-state recalculation, covered by `scripts/workflow-project-delivery-approve-http-smoke.ps1`. `Process_sale_project_play` first approval now creates `Activity_payment_approval`, and finance approval writes project collection statement/payment rows plus sale-project payment-status recalculation, covered by `scripts/workflow-project-play-approve-http-smoke.ps1`; remaining project process/task transition side effects are deferred except `Process_sale_project_init`, which is covered by `scripts/workflow-project-init-approve-http-smoke.ps1`, and `Process_sale_project_delivery`, which is covered by `scripts/workflow-project-delivery-approve-http-smoke.ps1`.

Leave process cancel/edit moved out of this controlled-deferred list on 2026-06-22 for active `Process_ask_leave` only. `POST /biz/process/cancel` now cancels unapproved leave processes, and `POST /biz/process/leave/edit` updates editable leave variables before approval; both are covered by `scripts/workflow-process-cancel-edit-http-smoke.ps1`.

Settlement-account delete moved out of this controlled-deferred list on 2026-06-22. `POST /biz/settlementaccount/delete` now performs protected logical deletion for unused settlement accounts and is covered by `scripts/settlement-account-delete-http-smoke.ps1`.

Non-project process starts moved out of this controlled-deferred list on 2026-06-22 for `Process_payment`, `Process_reimbursement`, `Process_make_payment`, `Process_procure`, and `Process_procure_in_warehouse`. The routes now create minimal first-step runtime/history rows, active `Activity_approval` tasks, optional CC/file relation rows, and can be cancelled before approval. Non-leave approve/reject transitions and Java delegate side effects remain deferred except for `Process_payment` income approval, `Process_reimbursement`/`Process_make_payment` two-step payment-out approval, `Process_procure` purchase-order approval, and `Process_procure_in_warehouse` stock-in approval, covered by `scripts/workflow-general-start-http-smoke.ps1`, `scripts/workflow-payment-approve-http-smoke.ps1`, `scripts/workflow-payment-out-approve-http-smoke.ps1`, `scripts/workflow-procure-approve-http-smoke.ps1`, and `scripts/workflow-procure-warehouse-approve-http-smoke.ps1`.

Project init start moved out of this controlled-deferred list on 2026-06-22. `POST /biz/process/project/init/start` now creates a `Process_sale_project_init` first-step runtime/history row set, marks the sale project `PENDING_APPROVAL`, binds workflow files, and can be cancelled before approval. Reject and cancel roll the project back to `FOLLOW`; approve applies the bounded project-init delegate side effects through sale-project delivery/account/amount fields, product items, `SALE_PROJECT` file relations, optional invoicing, customer deal amount, and `PROCESS_ID = processInstanceId`. It is covered by `scripts/workflow-project-init-approve-http-smoke.ps1`.

Project play start moved out of this controlled-deferred list on 2026-06-22. `POST /biz/process/project/play/start` now creates a `Process_sale_project_play` first-step runtime/history row set, can be cancelled before approval, advances first approval to BPMN-compatible `Activity_payment_approval`, and applies the bounded project collection side effects on finance approval. Finance approval writes `settlement_account_statement` and `biz_payment_record` rows with `PROCESS_ID = processInstanceId`, `PROCESS_CATEGORY = Process_sale_project_play`, and `SETTLEMENT_CATEGORY = PROJECT_PLAY`, then recalculates sale-project payment status. It is covered by `scripts/workflow-project-play-approve-http-smoke.ps1`.

Project delivery start moved out of this controlled-deferred list on 2026-06-22. `POST /biz/process/project/delivery/start` now creates a `Process_sale_project_delivery` first-step runtime/history row set, can be cancelled before approval, validates warehouse/product-item quantities, and applies bounded project delivery side effects on approval. Approval writes `biz_sale_project_invoice`, `biz_sale_project_invoice_item`, and OUT `delivery_record` rows with `PROCESS_ID = processInstanceId`, decrements inventory, updates product-item delivery status, and recalculates sale-project delivery state. It is covered by `scripts/workflow-project-delivery-approve-http-smoke.ps1`.

## Frontend API Exports

Added missing copied-frontend API methods:

- `bizPaymentRecordSubmitForm`
- `bizPaymentRecordDelete`
- `returnOrderSubmitForm`
- `returnOrderDelete`
- `bizSaleProjectInvoicingDelete`

The expenditure-record frontend methods already existed; the added backend wrappers prevent copied Vue controls from falling through to 404 before the backend can return a controlled deferred response. The other frontend API exports prevent copied Vue controls from failing with `is not a function` before the backend can return a controlled deferred response.

## Deliberate Exclusions

- No real payment-record add/delete behavior. `/biz/bizpaymentrecord/edit` is now a narrow payer-time correction that syncs the linked statement timestamp, and `/biz/bizpaymentrecord/edit/account` is now a narrow account switch that moves the stored payment amount between settlement accounts and syncs the linked statement account.
- No real expenditure-record add/delete behavior. `/biz/bizexpenditurerecord/edit` is now a narrow payer-time/category correction that syncs only the linked statement timestamp, and `/biz/bizexpenditurerecord/edit/account` is now a narrow account switch that moves the stored expenditure amount between settlement accounts and syncs the linked statement account.
- No real collection-receipt add/edit/delete behavior. `/biz/bizcollectionreceipt/batchExpenditure/edit` is now a narrow repayment quick-settlement implementation that creates expenditure/statement rows and updates the receipt settlement amount.
- No real debit-note add/edit/delete behavior. `/biz/bizdebitnote/batchRepayment/edit` is now a narrow loan-repayment implementation that creates payment/statement rows and updates the debit-note settlement amount; `/biz/bizdebitnote/history/add` is now a narrow historical debit-note insert with no payment/expenditure/statement or balance side effects.
- No real purchase-order add/delete behavior. `/biz/bizpurchaseorder/cancel` is now a narrow status marker that updates only `SETTLEMENT_STATUS`, audit fields, and `VERSION`; `/biz/bizpurchaseorder/edit` is now a narrow normal-order edit that updates only order amount and existing purchase-order item amount/cost fields; `/biz/bizpurchaseorder/audit/edit` is now a narrow audit-remediation edit with the same field whitelist and Java-compatible completed/expenditure bypass; `/biz/bizpurchaseorder/warehouse/one/add` is now a narrow single-order warehouse stock-in path; `/biz/bizpurchaseorder/warehouse/add` is now a narrow batch warehouse stock-in path for completed not-in-warehouse orders.
- No real inventory delete behavior. `/biz/inventory/add` is now covered by narrow warehouse/product inventory registration with no stock movement or delivery rows; `/biz/warehouses/delivery/add` is now covered by Java-compatible system stocktake behavior that writes one IN/OUT delivery row for non-zero movement and updates the locked inventory row; `/biz/settlementaccount/payment/add`, `/biz/settlementaccount/expenses/add`, `/biz/settlementaccount/transfer/add`, and `/biz/settlementaccount/delete` are now covered by narrow quick income/expense/transfer and protected logical-delete implementations.
- No direct leave-application add or payroll add behavior. Approved `Process_ask_leave` workflow transitions now create one `biz_leave_application` row, while `/biz/bizleaveapplication/add` remains controlled-deferred. `/biz/bizpayroll/generate/add` is now Java-compatible payroll generation, and `/biz/bizpayroll/import` is now focused Java-template import; neither payroll route is a controlled-deferred wrapper.
- Vacation-balance add/edit/delete has been replaced by narrow manual `biz_user_vacation` maintenance. Approved `annualLeave` workflow transitions now deduct `USED_AMOUNT`; editable leave workflows now deduct the edited amount at approval time; direct leave-application edit/delete now adjusts current-year annual-leave balances. Workflow-approved `leaveOfAbsence` rows are consumed by explicit payroll generation. Annual-leave generation, automatic existing-payroll row recalculation, and broader workflow side effects remain deferred.
- CC-record add/edit has been replaced by narrow current-user `biz_cc_records` row maintenance, and active `Process_ask_leave`, non-project first-step workflow starts, and project-init workflow starts now generate CC rows from `copyUserIdList`. Active leave, non-project, and project-init starts now bind submitted `fileIdList` rows through workflow file relations. Notifications and workflow transitions outside the bounded workflow-start/runtime paths remain deferred.
- Dev config `editBatch` has been replaced by narrow existing-row `dev_config` value maintenance; provider sends, external service calls, and cache invalidation remain deferred.
- Dev-job add/edit/delete has been replaced by narrow `dev_job` metadata maintenance, and `stopJob`/`runJob`/`runJobNow` now perform status-only compatibility updates; real scheduler registration/removal, scheduler lifecycle, and task execution remain deferred.
- Gen-basic add/edit/delete has been replaced by narrow `gen_basic` metadata maintenance plus default `gen_config` row maintenance; gen-config edit/delete has been replaced by narrow `gen_config` row metadata maintenance; direct project generation and generator config add remain deferred.
- Tenant add/edit/delete has been replaced by narrow `tenants` row metadata maintenance; default user/role/resource/permission bootstrap, cache mutation, and data-change events remain deferred.
- `POST /biz/process/leave/start` now performs a minimal transitional `Process_ask_leave` runtime/history write, generates `biz_cc_records` rows for submitted `copyUserIdList`, and binds submitted `fileIdList` rows through `biz_file_relation`; the five non-project start routes for payment, reimbursement, make-payment, procurement, and procurement warehouse now create the same first-step runtime/history shape with process-specific validation. `POST /biz/process/project/init/start` now creates the `Process_sale_project_init` first-step runtime/history shape, marks the sale project `PENDING_APPROVAL`, and binds project-init CC/file rows. `POST /biz/process/project/delivery/start` now creates the `Process_sale_project_delivery` first-step runtime/history shape and applies project delivery invoice/stock/status side effects on approval. `POST /biz/process/project/play/start` now creates the `Process_sale_project_play` first-step runtime/history shape, advances first approval to `Activity_payment_approval`, and applies project collection statement/payment/status side effects on finance approval. `POST /biz/process/cancel|leave/edit` now perform minimal active leave-process cancellation/editing, and `POST /biz/process/cancel` also cancels active unapproved non-project first-step workflows plus project-init, project-delivery, and project-play workflows. `POST /biz/task/approve|reject` now perform minimal `Process_ask_leave` `Activity_approval` transitions; `Process_payment` approve now performs the payment-in settlement side effect; `Process_reimbursement` and `Process_make_payment` now advance through `Activity_pay_approval` and perform payment-out settlement on finance approval; `Process_procure` now advances through procurement confirmation and optional general-office approval before creating purchase-order rows; `Process_procure_in_warehouse` approve now performs the purchase-order warehouse-in side effect; `Process_sale_project_init` approve now performs bounded sale-project init side effects while reject/cancel roll the project back to `FOLLOW`; `Process_sale_project_delivery` approve now performs bounded delivery invoice, OUT stock, inventory, product-item, and project-state side effects while reject/cancel close without stock rows; and `Process_sale_project_play` finance approve now performs bounded project collection side effects while reject/cancel close without finance rows. Approved leave transitions now generate the `biz_leave_application` row, approved `annualLeave` transitions deduct current-year vacation balances, and approved `leaveOfAbsence` rows are consumed by explicit payroll generation. Project workflow starts except `Process_sale_project_init`, `Process_sale_project_delivery`, and `Process_sale_project_play`, task SSE stream behavior, remaining project BPMN delegates for reissue/return, automatic existing-payroll row updates, and broader workflow completion side effects remain deferred. Sale-project foundation `add`, `edit`, `history/add`, and `special/add` now perform Java-compatible base project/history/reimbursement maintenance; normal `/biz/saleproject/add|edit` copied-form `productList` arrays now synchronize sale-project product-item and child-relation rows; `/biz/saleproject/visibility/edit` performs narrow visibility/specimen field maintenance, `/biz/saleproject/amount/edit` performs focused amount/status/totals maintenance with one `INIT_PRICE` change-log row, `/biz/saleproject/deal/edit` performs narrow delivery/freight field maintenance, `/biz/saleproject/cancel` performs WAIT_DELIVER-to-FOLLOW rollback, `/biz/saleproject/repeal` performs FOLLOW-to-DISCARD discard maintenance, and `/biz/saleproject/delete` performs FOLLOW-only logical delete maintenance. Finance outside the bounded payment-in/payment-out/project-collection workflow paths, invoice outside project-init optional invoice request rows and project-delivery invoice rows, stock outside procurement warehouse approval and project-delivery approval, remaining workflow completion side effects, file cleanup, notification, direct standalone product-item routes, and Java data-change side effects remain deferred.
- Return-order add/edit/delete now maintain `return_order` and `return_order_item` rows and recalculate sale-project return totals; workflow, delivery, inventory, refund creation, settlement-account statements, notification, and Java event bus side effects remain deferred.
- No project workflow starts outside `/biz/process/project/init/start`, `/biz/process/project/delivery/start`, and `/biz/process/project/play/start`, no non-leave edit behavior, and no delegate side-effect behavior outside `Process_payment`, `Process_reimbursement`, `Process_make_payment`, `Process_procure`, `Process_procure_in_warehouse`, `Process_sale_project_init`, `Process_sale_project_delivery`, and `Process_sale_project_play`. Initial cancel is now covered for active unapproved leave, non-project first-step, project-init, project-delivery, and project-play workflows.
- No remaining broad settlement account balance, payment, expenditure, payroll, invoice, warehouse, inventory, stock, project-state, notification, provider, scheduler, code-generation, tenant-bootstrap, realtime, workflow, workflow-copy, task-transition, or data-change side effects.

## Verification

- `php -l` for the touched controllers.
- `php think route:list` must list the controlled-deferred routes.
- `scripts/frontend-deferred-write-wrapper-smoke.ps1` should return `code = 400` with a deferred message or `data.operation` marker for each authenticated route, and `code = 401` for representative no-token routes.
- `scripts/frontend-api-method-smoke.ps1 -ShowDeferred` should not report active missing API-method calls.

`scripts/project-preflight.ps1` now runs the deferred-wrapper smoke by default. It also runs `scripts/settlement-account-delete-http-smoke.ps1`, `scripts/workflow-leave-start-http-smoke.ps1`, `scripts/workflow-general-start-http-smoke.ps1`, `scripts/workflow-payment-approve-http-smoke.ps1`, `scripts/workflow-payment-out-approve-http-smoke.ps1`, `scripts/workflow-procure-approve-http-smoke.ps1`, `scripts/workflow-procure-warehouse-approve-http-smoke.ps1`, `scripts/workflow-project-init-approve-http-smoke.ps1`, `scripts/workflow-project-delivery-approve-http-smoke.ps1`, `scripts/workflow-project-play-approve-http-smoke.ps1`, `scripts/workflow-task-transition-http-smoke.ps1`, `scripts/workflow-process-cancel-edit-http-smoke.ps1`, and `scripts/biz-leave-application-vacation-adjustment-http-smoke.ps1` by default for implemented settlement-account delete, leave workflow, non-project workflow starts, payment workflow approval, payment-out workflow approval, procurement workflow approval, procurement warehouse workflow approval, project-init workflow approval, project-delivery workflow approval, project-play workflow approval, and direct leave-row annual-leave paths. Use `-SkipFrontendDeferredWrites`, `-SkipSettlementAccountDelete`, `-SkipWorkflowLeaveStart`, `-SkipWorkflowGeneralStart`, `-SkipWorkflowPaymentApprove`, `-SkipWorkflowPaymentOutApprove`, `-SkipWorkflowProcureApprove`, `-SkipWorkflowProcureWarehouseApprove`, `-SkipWorkflowProjectInitApprove`, `-SkipWorkflowProjectDeliveryApprove`, `-SkipWorkflowProjectPlayApprove`, `-SkipWorkflowTaskTransition`, `-SkipWorkflowProcessCancelEdit`, or `-SkipBizLeaveApplicationVacationAdjustment` only when the local backend or ignored `.env` login smoke account is intentionally unavailable.

Verified on 2026-06-22:

- `scripts/settlement-account-delete-http-smoke.ps1`: passed for no-token, missing-id, missing-account, referenced-account rejection, mixed-batch rollback, valid logical delete, detail 404 after delete, and row-count preservation.
- `scripts/workflow-payment-out-approve-http-smoke.ps1`: passed for `Process_reimbursement` and `Process_make_payment` first approval into `Activity_pay_approval`, no business rows before finance approval, finance-form variable merge, expenditure/expense-statement creation with `PROCESS_ID = processInstanceId`, account decrement, and runtime cleanup.
- `scripts/workflow-procure-approve-http-smoke.ps1`: passed for `Process_procure` start, leader approval into procurement confirmation, procurement confirmation into general-office approval, final purchase-order creation, item creation, runtime cleanup, and zero delivery side effects.
- `scripts/workflow-procure-warehouse-approve-http-smoke.ps1`: passed for `Process_procure_in_warehouse` start plus approve, workflow runtime cleanup, completed history variables, order/item storage status updates, delivery rows with `PROCESS_ID = processInstanceId`, and inventory increments.
- `scripts/workflow-project-init-approve-http-smoke.ps1`: passed for project-init no-token and validation guards, start to `PENDING_APPROVAL`, cancel rollback to `FOLLOW`, reject rollback to `FOLLOW`, approve to `WAIT_DELIVER`, workflow runtime cleanup, sale-project product/file/invoicing side effects, `PROCESS_ID = processInstanceId`, and customer deal-amount increment.
- `scripts/workflow-project-play-approve-http-smoke.ps1`: passed for project-play no-token and validation guards, cancel/reject with no finance side effects, first approval into `Activity_payment_approval`, finance reject with no payment/account/project changes, finance approve creating one statement/payment row with `PROCESS_CATEGORY = Process_sale_project_play` and `SETTLEMENT_CATEGORY = PROJECT_PLAY`, account increment, project payment-status recalculation, and workflow runtime cleanup.
- `scripts/workflow-project-delivery-approve-http-smoke.ps1`: passed for project-delivery no-token and validation guards, cancel/reject with no invoice/stock side effects, approval creating one delivery invoice, one invoice item, one OUT delivery record with `PROCESS_CATEGORY = Process_sale_project_delivery`, inventory decrement, product-item `DELIVERY`/`STATE` update, project delivery-state recalculation, and workflow runtime cleanup.
- `scripts/workflow-general-start-http-smoke.ps1`: passed for no-token, missing-approver, missing-amount validation, successful `Process_payment`, `Process_reimbursement`, `Process_make_payment`, `Process_procure`, and `Process_procure_in_warehouse` starts, runtime/history/variable/CC/file assertions, activity detail and process-query readback, payment-out first approval into finance task plus finance reject with unchanged business rows, procurement first approval into procurement confirmation plus procurement reject with unchanged business rows, procurement-warehouse fake-order approve rollback, cancel runtime cleanup, final cancel variables, and unchanged payment/expenditure/purchase/delivery business-table counts.
- `scripts/workflow-process-cancel-edit-http-smoke.ps1`: passed for no-token, validation, unapproved leave cancellation, runtime cleanup, final `cancel` variables, zero leave-row/vacation-change on cancel, editable leave variable updates, second-edit rejection, approval after edit using edited annual-leave amount, and non-editable rejection.
- `scripts/biz-leave-application-vacation-adjustment-http-smoke.ps1`: passed for direct leave edit/delete no-token and missing-id guards, annual amount-delta vacation adjustment, annual-to-nonannual restoration, insufficient-balance rollback, annual delete restoration, and cleanup.
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed for all twenty-seven authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and representative POST no-token checks plus one process-cancel no-token check plus one task-SSE no-token check after the five non-project process starts plus project-init, project-delivery, and project-play starts moved into runtime behavior.
- `scripts/workflow-task-transition-http-smoke.ps1`: passed for no-token, validation, approve, reject, runtime row cleanup, history task/process/activity completion, final variables, approved leave-application row creation/read-back, approved annual-leave deduction, insufficient-balance rollback, rejected zero leave-row check, history task page read-back, and cleanup.
- `scripts/workflow-leave-start-http-smoke.ps1`: passed for no-token, validation, successful start, `act_*` runtime/history row verification, read-back through task/process/activity-detail APIs, and cleanup.

Verified on 2026-06-18:

- `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed for all forty-one authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and nine representative POST no-token checks plus one task-SSE no-token check after sale-project copied-form product-list mutation moved into direct project product-item maintenance.

Verified on 2026-06-16:

- `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed for all seventy-six authenticated deferred wrappers and seventeen representative no-token checks after vacation-balance add/edit/delete, CC-record add/edit, dev-config editBatch, dev-job add/edit, gen-basic add/edit/delete, gen-config edit/delete, tenant add/edit/delete, payroll export, payment-record payer-time edit, and expenditure-record payer-time/category edit moved to narrow manual/download/correction maintenance.
- `scripts/frontend-api-method-smoke.ps1 -ShowDeferred`: passed with no remaining active missing API-method calls.
- `php think route:list`: listed all remaining controlled-deferred wrapper routes.

Verified on 2026-06-17:

- `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed for all seventy-five authenticated deferred wrappers and seventeen representative no-token checks after payment-record account switch moved to narrow balance/link maintenance.

Verified again on 2026-06-17:

- `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed for all seventy-four authenticated deferred wrappers and seventeen representative no-token checks after expenditure-record account switch moved to narrow balance/link maintenance.

Verified again on 2026-06-17:

- `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed for all seventy-four authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and sixteen representative no-token checks after settlement-account payment add moved to narrow quick-income creation.

Verified again on 2026-06-17:

- `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed for all seventy-three authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and sixteen representative no-token checks after settlement-account expenses add moved to narrow quick-expense creation.

Verified again on 2026-06-17:

- `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed for all seventy-two authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and sixteen representative no-token checks after settlement-account transfer add moved to narrow account-transfer creation.

Verified again on 2026-06-17:

- `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed for all seventy-one authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and sixteen representative no-token checks after collection-receipt batch expenditure moved to narrow repayment quick-settlement creation.

Verified again on 2026-06-17:

- `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed for all seventy authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and sixteen representative no-token checks after debit-note batch repayment moved to narrow loan-repayment quick-settlement creation.

Verified again on 2026-06-17:

- `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed for all sixty-nine authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and fifteen representative no-token checks after debit-note history add moved to narrow historical debit-note creation.

Verified again on 2026-06-17:

- `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed for all sixty-eight authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and fourteen representative no-token checks after `/biz/bizpurchaseorder/cancel` moved to narrow purchase-order status marking.

Verified again on 2026-06-17:

- `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed for all sixty-seven authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and fourteen representative no-token checks after `/biz/inventory/add` moved to narrow inventory registration.

Verified again on 2026-06-17:

- `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed for all sixty-six authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and fourteen representative no-token checks after `/biz/bizpurchaseorder/edit` moved to narrow purchase-order edit.

Verified again on 2026-06-17:

- `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed for all sixty-five authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, and fourteen representative no-token checks after `/biz/bizpurchaseorder/audit/edit` moved to narrow purchase-order audit edit.

Historical pending notes from earlier 2026-06-18 slices. The deferred-wrapper count in these notes is superseded by the verified forty-one-wrapper run above:

- `/biz/warehouses/delivery/add` moved out of the controlled-deferred list into Java-compatible system stocktake behavior. The deferred wrapper smoke should now cover sixty-four authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, but DB-backed verification is pending because local MySQL `MySQL80` was stopped and failed to start.
- `/biz/bizpurchaseorder/warehouse/one/add` moved out of the controlled-deferred list into Java-compatible single-order warehouse stock-in behavior. The deferred wrapper smoke should now cover sixty-three authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, but DB-backed verification is pending because local MySQL `MySQL80` was stopped and failed to start.
- `/biz/bizpurchaseorder/warehouse/add` moved out of the controlled-deferred list into Java-compatible batch warehouse stock-in behavior. The deferred wrapper smoke should now cover sixty-two authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, but DB-backed verification is pending because local MySQL `MySQL80` is stopped.
- `/biz/bizpayroll/generate/add` moved out of the controlled-deferred list into Java-compatible payroll generation. The deferred wrapper smoke should now cover sixty-one authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, but DB-backed verification is pending because local MySQL `MySQL80` is stopped.
- `/dev/job/stopJob`, `/dev/job/runJob`, and `/dev/job/runJobNow` moved out of the controlled-deferred list into status-only compatibility behavior. The deferred wrapper smoke should now cover fifty-eight authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, but DB-backed verification is pending because local MySQL `MySQL80` is stopped.
- `/biz/saleproject/visibility/edit` moved out of the controlled-deferred list into narrow visibility/specimen field maintenance. The deferred wrapper smoke should now cover fifty-seven authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, but DB-backed verification is pending because local MySQL `MySQL80` is stopped.
- `/biz/saleproject/amount/edit` moved out of the controlled-deferred list into focused amount/status/totals maintenance. The deferred wrapper smoke should now cover fifty-six authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, but DB-backed verification is pending because local MySQL `MySQL80` is stopped.
- `/biz/bizpayroll/import` moved out of the controlled-deferred list into focused Java-template payroll import. The deferred wrapper smoke should now cover fifty-five authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, but DB-backed verification is pending because local MySQL `MySQL80` is stopped.
- `/biz/saleproject/cancel` moved out of the controlled-deferred list into Java-compatible status rollback plus invoicing logical delete. The deferred wrapper smoke should now cover fifty-four authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, but DB-backed verification is pending because local MySQL `MySQL80` is stopped.
- `/biz/saleproject/repeal` moved out of the controlled-deferred list into Java-compatible FOLLOW-to-DISCARD state maintenance. The deferred wrapper smoke should now cover fifty-three authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, but DB-backed verification is pending because local MySQL `MySQL80` is stopped.
- `/biz/saleproject/delete` moved out of the controlled-deferred list into Java-compatible FOLLOW-only logical delete maintenance. The deferred wrapper smoke should now cover fifty-two authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, but DB-backed verification is pending because local MySQL `MySQL80` is stopped.
- `/biz/saleproject/deal/edit` moved out of the controlled-deferred list into Java-compatible delivery/freight field maintenance. The deferred wrapper smoke should now cover fifty-one authenticated POST deferred wrappers plus the task-SSE deferred GET wrapper, but DB-backed verification is pending because local MySQL `MySQL80` is stopped.
