# Frontend Controlled Deferred Write Wrappers

Date: 2026-06-16

## Scope

This slice adds frontend API exports and protected ThinkPHP wrapper routes for copied Vue write controls that are visible or reachable, but whose real business behavior is still deferred.

The wrappers return Java-style JSON envelopes with `code = 400`. They do not call service-layer write methods, write database rows, update balances, move inventory, start workflow, delete records, read provider credentials, change schema, or modify Java source.

## Added Controlled-Deferred Routes

Payment record:

- `POST /biz/bizpaymentrecord/add`
- `POST /biz/bizpaymentrecord/edit/account`
- `POST /biz/bizpaymentrecord/delete`

Expenditure record:

- `POST /biz/bizexpenditurerecord/add`
- `POST /biz/bizexpenditurerecord/edit/account`
- `POST /biz/bizexpenditurerecord/delete`

Collection receipt:

- `POST /biz/bizcollectionreceipt/add`
- `POST /biz/bizcollectionreceipt/edit`
- `POST /biz/bizcollectionreceipt/batchExpenditure/edit`
- `POST /biz/bizcollectionreceipt/delete`

Debit note:

- `POST /biz/bizdebitnote/add`
- `POST /biz/bizdebitnote/edit`
- `POST /biz/bizdebitnote/batchRepayment/edit`
- `POST /biz/bizdebitnote/history/add`
- `POST /biz/bizdebitnote/delete`

Purchase order:

- `POST /biz/bizpurchaseorder/add`
- `POST /biz/bizpurchaseorder/edit`
- `POST /biz/bizpurchaseorder/audit/edit`
- `POST /biz/bizpurchaseorder/warehouse/add`
- `POST /biz/bizpurchaseorder/warehouse/one/add`
- `POST /biz/bizpurchaseorder/cancel`
- `POST /biz/bizpurchaseorder/delete`

Inventory and delivery:

- `POST /biz/inventory/add`
- `POST /biz/inventory/delete`
- `POST /biz/warehouses/delivery/add`

Settlement account side-effect actions:

- `POST /biz/settlementaccount/delete`
- `POST /biz/settlementaccount/expenses/add`
- `POST /biz/settlementaccount/payment/add`
- `POST /biz/settlementaccount/transfer/add`

HR and workflow-adjacent records:

- `POST /biz/bizleaveapplication/add`
- `POST /biz/bizpayroll/add`
- `POST /biz/bizpayroll/import`
- `POST /biz/bizpayroll/generate/add`

Dev provider/job actions:

- `POST /dev/email/sendLocalTxt`
- `POST /dev/email/sendLocalHtml`
- `POST /dev/email/sendAliyunTxt`
- `POST /dev/email/sendAliyunHtml`
- `POST /dev/email/sendAliyunTmp`
- `POST /dev/email/sendTencentTxt`
- `POST /dev/email/sendTencentHtml`
- `POST /dev/email/sendTencentTmp`
- `POST /dev/job/stopJob`
- `POST /dev/job/runJob`
- `POST /dev/job/runJobNow`

Generator writes:

- `POST /gen/basic/execGenPro`
- `POST /gen/config/add`

Workflow, task, and sale-project state actions:

- `POST /biz/process/cancel`
- `POST /biz/process/leave/edit`
- `POST /biz/process/leave/start`
- `POST /biz/process/makePayment/start`
- `POST /biz/process/payment/start`
- `POST /biz/process/procure/start`
- `POST /biz/process/procure/warehouse/start`
- `POST /biz/process/project/delivery/start`
- `POST /biz/process/project/init/start`
- `POST /biz/process/project/play/start`
- `POST /biz/process/project/reissue/start`
- `POST /biz/process/project/return/start`
- `POST /biz/process/reimbursement/start`
- `POST /biz/task/approve`
- `POST /biz/task/reject`
- `GET /biz/task/sse/stream`
- `POST /biz/saleproject/add`
- `POST /biz/saleproject/edit`
- `POST /biz/saleproject/delete`
- `POST /biz/saleproject/amount/edit`
- `POST /biz/saleproject/deal/edit`
- `POST /biz/saleproject/cancel`
- `POST /biz/saleproject/history/add`
- `POST /biz/saleproject/repeal`
- `POST /biz/saleproject/special/add`
- `POST /biz/saleproject/visibility/edit`

Return order:

- `POST /biz/returnorder/add`
- `POST /biz/returnorder/edit`
- `POST /biz/returnorder/delete`

Sale project invoicing:

- `POST /biz/saleprojectinvoicing/add`
- `POST /biz/saleprojectinvoicing/edit`
- `POST /biz/saleprojectinvoicing/delete`

`POST /biz/saleprojectinvoicing/complete` remains the existing narrow implemented status marker and is not changed by this slice.

## Frontend API Exports

Added missing copied-frontend API methods:

- `bizPaymentRecordSubmitForm`
- `bizPaymentRecordDelete`
- `returnOrderSubmitForm`
- `returnOrderDelete`
- `bizSaleProjectInvoicingDelete`

The expenditure-record frontend methods already existed; the added backend wrappers prevent copied Vue controls from falling through to 404 before the backend can return a controlled deferred response. The other frontend API exports prevent copied Vue controls from failing with `is not a function` before the backend can return a controlled deferred response.

## Deliberate Exclusions

- No real payment-record add/delete or account-switch behavior. `/biz/bizpaymentrecord/edit` is now a narrow payer-time correction that syncs the linked statement timestamp only.
- No real expenditure-record add/delete or account-switch behavior. `/biz/bizexpenditurerecord/edit` is now a narrow payer-time/category correction that syncs only the linked statement timestamp.
- No real collection-receipt add/edit/delete or batch-expenditure generation behavior.
- No real debit-note add/edit/delete, batch-repayment, or repayment-history behavior.
- No real purchase-order add/edit/audit/cancel/delete or warehouse stock-in behavior.
- No real inventory add/delete, delivery record add, settlement-account delete, income/expense, payment, or transfer behavior.
- No real leave-application add or payroll add/import/generate behavior.
- Vacation-balance add/edit/delete has been replaced by narrow manual `biz_user_vacation` maintenance; annual-leave generation/reduction, leave approval deductions, workflow, and payroll recalculation remain deferred.
- CC-record add/edit has been replaced by narrow current-user `biz_cc_records` row maintenance; workflow copy-user delegate generation, file-relation binding, notifications, and workflow transitions remain deferred.
- Dev config `editBatch` has been replaced by narrow existing-row `dev_config` value maintenance; provider sends, external service calls, and cache invalidation remain deferred.
- Dev-job add/edit has been replaced by narrow `dev_job` metadata maintenance; real scheduler registration/removal, run, stop, run-now, and task execution remain deferred.
- Gen-basic add/edit/delete has been replaced by narrow `gen_basic` metadata maintenance plus default `gen_config` row maintenance; gen-config edit/delete has been replaced by narrow `gen_config` row metadata maintenance; direct project generation and generator config add remain deferred.
- Tenant add/edit/delete has been replaced by narrow `tenants` row metadata maintenance; default user/role/resource/permission bootstrap, cache mutation, and data-change events remain deferred.
- No real workflow process start/edit/cancel, task approve/reject, task SSE stream, sale-project add/edit/delete/state/amount/deal/history/special/visibility/repeal behavior.
- No return-order add/edit/delete behavior.
- No sale-project invoicing add/edit/delete behavior.
- No workflow start, approval, reject, cancel, or delegate behavior.
- No settlement account balance, payment, expenditure, payroll, invoice, return, warehouse, inventory, stock, project-state, notification, provider, scheduler, code-generation, tenant-bootstrap, realtime, workflow, workflow-copy, task-transition, or data-change side effects.

## Verification

- `php -l` for the touched controllers.
- `php think route:list` must list the controlled-deferred routes.
- `scripts/frontend-deferred-write-wrapper-smoke.ps1` should return `code = 400` with a deferred message or `data.operation` marker for each authenticated route, and `code = 401` for representative no-token routes.
- `scripts/frontend-api-method-smoke.ps1 -ShowDeferred` should not report active missing API-method calls.

`scripts/project-preflight.ps1` now runs the deferred-wrapper smoke by default. Use `-SkipFrontendDeferredWrites` only when the local backend or ignored `.env` login smoke account is intentionally unavailable.

Verified on 2026-06-16:

- `scripts/frontend-deferred-write-wrapper-smoke.ps1`: passed for all seventy-six authenticated deferred wrappers and seventeen representative no-token checks after vacation-balance add/edit/delete, CC-record add/edit, dev-config editBatch, dev-job add/edit, gen-basic add/edit/delete, gen-config edit/delete, tenant add/edit/delete, payroll export, payment-record payer-time edit, and expenditure-record payer-time/category edit moved to narrow manual/download/correction maintenance.
- `scripts/frontend-api-method-smoke.ps1 -ShowDeferred`: passed with no remaining active missing API-method calls.
- `php think route:list`: listed all remaining controlled-deferred wrapper routes.
