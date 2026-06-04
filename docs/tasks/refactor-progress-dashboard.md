# Java OA To ThinkPHP Refactor Progress Dashboard

Last updated: 2026-06-04 10:40 +08:00

Agent: merge-agent / main control agent

## Current Estimate

Overall production-ready completion: **55%**

Read-only API compatibility completion: **87%**

This estimate uses the final goal as the denominator: one complete runnable ThinkPHP OA system with login, RBAC, user/org, workflow, business APIs, frontend adaptation, tests, deployment, and final production data sync.

## Current Evidence

| Metric | Current Value | Notes |
| --- | ---: | --- |
| SQL tables analyzed/imported | 121 | From `F:\AI\projects\testJava\OA\oa2026.sql` |
| ThinkPHP Models | 67 | Database/model foundation is mostly in place |
| ThinkPHP Controllers | 69 | Includes auth, sys, dev, biz, mobile, gen, tenant, workflow read adapters |
| ThinkPHP Services | 60 | Includes auth/RBAC, user directory, workflow reads, and business read-only services |
| Registered route entries | 263 | Most are protected read-only compatibility routes |
| API compatibility docs | 63 | Stored under `docs/api` |
| Database docs | 10 | Stored under `docs/database` |
| Java Controllers in original project | 84 | Read-only reference baseline |
| Frontend API files in copied project | 76 | Static scan source: `snowy-admin-web/src/api` |
| Unique frontend API endpoints | 545 | Normalized static wrapper paths |
| Frontend endpoints already routed | 257 | Matched against current ThinkPHP route paths |
| Frontend missing read/selector candidates | 81 | Priority candidates for safe compatibility slices |
| Frontend deferred write/side-effect candidates | 207 | Do not implement without module-specific write plans |
| Frontend baseline files copied | 908 | Copied into `snowy-admin-web`; generated/cache files excluded |
| Current branch | `refactor/thinkphp-main` | Clean and synced with origin at last check |
| Latest frontend-slice commit | See latest Git log | Current dashboard records sale-project field change log read compatibility |

## Module Progress

| Area | Completion | Status | Done | Remaining |
| --- | ---: | --- | --- | --- |
| Project engineering setup | 95% | Green | Git, remote sync, worktree plan, docs, AGENTS rules, baseline commands | Keep docs updated after each slice |
| Database and Models | 85% | Green | 121-table SQL reference used; 67 passive Models; field/relation/index docs | Low-priority tables, model relation methods where needed, final migration review |
| Auth / Token / RBAC / Menu | 70% | Yellow | Login, password compatibility base, token middleware, RBAC/menu/session reads, protected routes | Refresh/session hardening, fine-grained permission enforcement, data-scope expansion |
| User / Org / Position | 70% | Yellow | Read-only org tree, position, user directory, user-center selectors, business-side directory aliases, camelCase display aliases for org/user/position pages, system user grant echo reads | User CRUD, grant writes, upload/avatar, import/export, encrypted profile fields |
| Workflow | 43% | Yellow | Runtime strategy, read-only task/process routes, process query aliases, file-list reads, runtime activity detail, variable normalization, copy/CC record reads, annual-leave balance detail read | Approval/reject/cancel/start, task SSE, vacation deductions, side effects, workflow write runtime |
| Dev/System/Mobile/Gen/Tenant reads | 65% | Yellow | Many read-only management endpoints added and routed | Write routes, provider actions, code generation, scheduler execution are intentionally deferred |
| Business read-only APIs | 88% | Yellow | Product, supplier, warehouse, inventory, delivery, purchase, settlement, payment, expenditure, collection, debit, file relation, history Excel, team project, return order, sale project including cost details, sale-project follow-up, sale-project field change log, sale-project product item relation list, sale-project invoice item page, sale-project draft detail, customer, customer follow-up, sale-project invoicing, invoice, reissue, project-rate, sale-project product info, biz-datareport sale-project summaries/unpaid/details/settlement/sale-profit/summary-statistics reads, leave-application reads, annual-leave balance detail, settlement-account-payment reads, payroll reads, workflow process/task reads | Remaining detail consumers |
| Business write APIs | 10% | Red | Mostly deferred by design | Add/edit/delete/audit/status/stock/payment/refund flows with transactions and side effects |
| Frontend adaptation | 61% | Yellow | Original Vue project copied into target repo; request prefix, Bearer token, upload/SSE token headers, local SM2 fallback, double-prefix fix, menu leaf handling adapted, API gap map generated, org/user display aliases added, sys-user grant echo reads added, copy-task CC record reads added, sale-project draft detail read added, annual-leave balance detail read added, sale-project invoice item page read added, sale-project field change log reads added, minimal SSE route added, short-lived SSE client fallback added; browser smoke reaches `/sys/org`, `/sys/user`, `/biz/bizdatareport/summaryStatistics`, `/biz/saleproject` with visible pagination, sale-project detail read tabs, and cost tab zero-revenue display is guarded | Broken API method cleanup, remaining read-only business routes |
| Testing / QA | 43% | Yellow | Composer, `php think`, route list, PHP lint, smoke tests per slice, backend/frontend browser smoke for summary-statistics, sale-project detail tab service smoke | Automated route/API test suite, regression matrix, broader frontend smoke, negative tests |
| Deployment | 15% | Red | Local MySQL/Redis startup method known; env is local | Production config, queue/runtime/log permissions, Nginx/PHP deployment checks |
| Final online data sync | 0% | Red | Requirement recorded as final-stage reminder | Must design and confirm after project completion; do not start early |

## Completed Compatibility Slices

| Slice | Current Capability | Write Behavior |
| --- | --- | --- |
| Auth | Login, logout, current user, token, RBAC/menu reads | Partial; password-safe endpoint only |
| User directory | Org/user/position selectors and read-only directory | Deferred |
| Workflow reads | Task count/list/page/history, process page/detail/variable reads | Deferred |
| System/dev/mobile/gen reads | Config, dict, file, email/sms records, job metadata, logs, messages, monitor, resource/menu/mobile resource, gen metadata, tenant reads | Deferred |
| Business reads | Product, supplier, settlement account, payment record, expenditure record, collection receipt, debit note, file relation, team project, task/comments, warehouses, inventory, delivery, purchase order, return order, sale project | Deferred |
| SSE compatibility | `/dev/message/createSseConnect` Java/frontend behavior mapped, minimal protected ThinkPHP route added, and frontend short-lived reconnect fallback added | Full realtime push deferred |
| Sale project reads | `/biz/saleproject/page`, `/case/page`, `/operation/page`, `/public/page`, `/list/detail`, `/detail`, `/product`, `/cost`, and `/cost/details` routed with aggregate read data, weighted-average purchase cost details, route precedence fixed for completed-project cost tabs, and admin-compatible list data scope for the copied sale-project page | Writes and workflow/finance side effects deferred |
| Sale project follow-up reads | `/biz/saleprojectfollowup/page` and `/detail` routed with project name, creator display fields, data-scope guarding, and unchanged `extJson` for frontend file-list parsing | Follow-up add/edit/delete and attachment writes deferred |
| Sale project product item relation reads | `/biz/saleprojectproductitemrelation/list` routed with combo-product child relation rows, product aliases, product display fields, and data-scope guarding through the owning sale project | Relation/product item mark edits and delivery/invoice writes deferred |
| Sale project detail browser smoke | `/biz/saleproject` detail modal read tabs browser-smoked for project information, follow-up records, case empty state, and pending-process empty state | Follow-up add/edit/delete, case upload/add, workflow actions, and sale-project writes deferred |
| Sale project cost frontend display | Completed-project cost tab guards zero-revenue gross profit rate and displays numeric zero instead of `NaN%` | Backend cost payloads and sale-project/finance writes unchanged |
| Sale project remaining tab smoke | Payment, return-order, invoice, and file-relation tab read services verified against one imported project | Browser visual confirmation and all write controls deferred |
| Customer reads | `/biz/customer/page`, `/detail`, `/detail/list`, `/biz/customerfollowup/page`, and `/detail` routed with customer owner/org/file and follow-up creator display fields | Customer and follow-up writes, owner reassignment, and SM4 plaintext search deferred |
| Sale project billing reads | `/biz/saleprojectinvoicing/page`, `/customer`, `/detail`, `/biz/saleprojectinvoice/page`, `/list`, `/biz/saleprojectreissueorder/list/query`, `/biz/projectrate/page`, and `/list` routed with nested invoice/reissue structures | Invoice, invoicing, reissue, rating, workflow, inventory, and finance writes deferred |
| Biz directory aliases | `/biz/org`, `/biz/user`, `/biz/position`, and `/biz/dict` read paths routed to existing system/dev read services | User/org/position/dict writes, role grants, password actions, import/export deferred |
| Workflow read aliases | `/biz/process/all/page`, `/query`, `/query/list`, `/project/runtime/query/list`, `/fileList`, and `/biz/task/runtime/activity/detail` routed through Camunda-table read services | Task approve/reject, task SSE, process starts/cancel, and Java delegate side effects deferred |
| Sale project product info reads | `/biz/saleprojectproductinfo/page`, `/list`, and `/detail` routed with software package/version rows and creator display names | Add/edit/delete package rows deferred |
| Biz datareport sale-project details | `/biz/bizdatareport/saleProjectList/details` routed with sale project rows, nested product items, package children, and return orders | Other report, profit, unpaid-payment, and summary-statistics endpoints deferred |
| Biz datareport sale-project summaries | `/biz/bizdatareport/saleproject`, `/saleproject/list`, `/saleproject/report`, and `/saleproject/UnpaidPayment` routed with amount totals, project amount rows, status/time rows, and unpaid amount | Summary-statistics endpoint deferred |
| Biz datareport settlement reads | `/biz/bizdatareport/settlement/income` and `/settlement/expenses` routed with payment/expenditure record rows, settlement categories, payer time, account, org, and amount fields | Summary-statistics, settlement mutations, and account balance updates deferred |
| Biz datareport sale-profit read | `/biz/bizdatareport/saleProfit` routed with raw `projectlist`, `orderList`, and `bizProducts` collections for frontend WebWorker calculation | Purchase/sale/return/inventory mutations and workflow side effects deferred |
| Biz datareport summary-statistics read | `/biz/bizdatareport/summary/statistics` routed with company-scoped `org`, `settlementAccounts`, `paymentRecords`, `bizExpenditureRecords`, `bizSaleProjects`, and `bizDebitNotes` collections for frontend WebWorker calculation; copied Vue page browser-smoked at `/biz/bizdatareport/summaryStatistics` | Settlement/account-balance mutations and workflow side effects deferred |
| Leave application reads | `/biz/bizleaveapplication/page`, `/my/page`, and `/detail` routed with applicant, organization, workflow process id, date, amount, and object id fields | Add/edit/delete and workflow start/approval side effects deferred |
| Settlement account payment reads | `/biz/settlementaccountpayment/page` and `/list` routed with account statement amount, settlement type/category, process id, account, and timestamp fields | Account payment/transfer/income/expense mutations and balance changes deferred |
| Payroll reads | `/biz/bizpayroll/page`, `/mypage`, and `/detail` routed with salary fields, employee display name, organization display name, salary month filters, and data-scope guards | Payroll import/export/generate/add/edit/batch edit/delete deferred |
| Sys user grant echo reads | `/sys/user/list/detail`, `/ownRole`, `/ownResource`, and `/ownPermission` routed for copied user grant dialogs, preserving `sys_relation.EXT_JSON` payloads and sanitizing user rows | Grant role/resource/permission writes, user CRUD, enable/disable, reset password, import/export deferred |
| Biz CC records reads | `/biz/ccrecords/page` and `/detail` routed for copied workflow copy-task page, filtered to the current token user and enriched with promoter/user display names | Delete, workflow copy delegate writes, and approval actions deferred |
| Biz draft detail read | `/biz/bizdraft/detail` routed for copied sale-project draft reloads, reading by `TARGET_ID` and preserving raw `extJson` | Draft save, sale-project writes, workflow start, and file upload side effects deferred |
| Biz user vacation detail read | `/biz/bizuservacation/detail` routed for copied leave-process annual-leave balance reads, defaulting to current user and `annualLeave` | Vacation generation/reduction, leave approval deductions, and write routes deferred |
| Biz history Excel reads | `/biz/bizhistoryexcel/page` and `/detail` routed for copied historical spreadsheet data browsing, preserving raw `extJson` and hiding logical deletes | Add/edit/delete, import/export, spreadsheet parsing changes, and storage writes deferred |
| Sale project invoice item page read | `/biz/saleprojectinvoiceItem/page` routed for copied sale-project delivery invoice item pagination, with product and warehouse display aliases | Invoice item writes, invoice/delivery/stock/project-state/finance side effects deferred |
| Sale project field change log reads | `/biz/salesprojectfieldchangelog/page` and `/detail` routed for copied sale-project change-log browsing, with project and creator display aliases | Add/edit/delete, sale-project change writes, workflow, finance, and audit side effects deferred |

## Remaining High-Level Plan

| Phase | Planned Dates | Target | Deliverables | Exit Criteria |
| --- | --- | --- | --- | --- |
| 1. Progress audit and gap map | 2026-06-01 to 2026-06-02 | Freeze current gap list | `docs/tasks/api-gap-map.md`, updated dashboard | Frontend API files mapped to implemented, missing read, and deferred write groups |
| 2. Remaining read-only business APIs | 2026-06-03 to 2026-06-07 | Finish safe GET/list/detail coverage | Sale project reads, customer strategy, invoice/invoicing/reissue/follow-up/rating reads | Main old frontend pages can load read-only data |
| 3. Data-scope and permission tightening | 2026-06-08 to 2026-06-10 | Align RBAC/data scope closer to Java | Data-scope org expansion, permission middleware checks where safe | Protected reads match expected user/org access |
| 4. Low-risk write endpoints | 2026-06-11 to 2026-06-15 | Add isolated CRUD without heavy side effects | Master-data writes for selected modules after review | Transactional writes smoke tested; no stock/payment/workflow side effects yet |
| 5. Business transactional writes | 2026-06-16 to 2026-06-22 | Implement side-effect-heavy writes | Purchase, inventory, delivery, settlement, payment/refund/status flows | Transactions, rollback tests, state updates, event replacement notes |
| 6. Workflow write runtime | 2026-06-23 to 2026-06-28 | Implement approval actions | Start/approve/reject/cancel and business side-effect hooks | Workflow smoke passes on imported data |
| 7. Frontend adaptation | Starts now as a parallel track; deeper work 2026-06-29 to 2026-07-03 | Make Vue frontend work against ThinkPHP API | Baseline import, token/request/menu/permission adaptation, missing API wrappers, joint backend/frontend smoke | Main workflows usable from browser |
| 8. Test hardening | 2026-07-04 to 2026-07-07 | Stabilize full system | Route/API regression suite, syntax/namespace checks, negative auth tests | `composer install`, `php think`, `route:list`, lint, smoke all pass |
| 9. Deployment rehearsal | 2026-07-08 to 2026-07-10 | Prepare runtime deployment | Env checklist, Nginx/PHP/runtime permission notes, backup plan | Staging deployment checklist passes |
| 10. Final online data sync plan | After phase 9, with user confirmation | Sync production/online realtime data into final project | Data backup, sync direction, downtime/rollback plan | User confirms sync plan before any production data operation |

## Time Forecast

| Delivery Level | Estimated Remaining Time | Meaning |
| --- | --- | --- |
| Backend read-only demo | 5 to 7 working days | Login plus most pages can load read-only data |
| Internal test version | 12 to 18 working days | Core writes and workflow begin to work; frontend smoke starts |
| Production-ready candidate | 22 to 30 working days | Full regression, deployment rehearsal, and data-sync plan ready |

The estimate assumes continued small commits and local MySQL/Redis availability. Heavy workflow side effects, encrypted customer fields, and frontend mismatches are the largest uncertainty.

## Next Immediate Actions

1. Add remaining read-only selectors and detail consumers needed by sale-project, finance, and customer pages.
2. Start the next safe read-only business slice before finance/workflow write behavior.
3. Browser-smoke `/sys/org`, `/sys/user`, customer/detail/report pages, and each newly routed visible business page after backend or frontend compatibility slices.
4. Create a focused test-agent task for realtime message/WebPush console noise after more read-only pages are covered.
5. Keep the customer encrypted-field strategy deferred until an approved SM4 compatibility plan.
6. Keep final production data sync deferred until the system is complete and the user confirms the sync plan.

## Frontend Joint Testing Rule

Frontend adaptation is now part of the active workflow, not a final-only task. Future backend slices should record whether they affect visible frontend pages. After the frontend baseline is imported, every completed route slice should be followed by a backend plus frontend smoke cycle:

1. Start MySQL/Redis.
2. Start ThinkPHP on port `82`.
3. Start Vue frontend on port `83`.
4. Browser-test login, menu loading, and the affected pages.
5. Record missing frontend calls in `docs/tasks/api-gap-map.md`.

Detailed workflow: `docs/tasks/frontend-joint-test-workflow.md`
