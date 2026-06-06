# Java OA To ThinkPHP Refactor Progress Dashboard

Last updated: 2026-06-06 11:34 +08:00

Agent: merge-agent / main control agent

## Current Estimate

Overall production-ready completion: **63%**

Read-only API compatibility completion: **90%**

This estimate uses the final goal as the denominator: one complete runnable ThinkPHP OA system with login, RBAC, user/org, workflow, business APIs, frontend adaptation, tests, deployment, and final production data sync.

## Current Evidence

| Metric | Current Value | Notes |
| --- | ---: | --- |
| SQL tables analyzed/imported | 121 | From `F:\AI\projects\testJava\OA\oa2026.sql` |
| ThinkPHP Models | 67 | Database/model foundation is mostly in place |
| ThinkPHP Controllers | 69 | Includes auth, sys, dev, biz, mobile, gen, tenant, workflow read adapters |
| ThinkPHP Services | 60 | Includes auth/RBAC, user directory, workflow reads, and business read-only services |
| Registered route entries | 350 | Most are protected compatibility routes; auth session/token exit, dev-message delete, user-center self-service writes, index schedule add/delete, index all-message mark-read, customer head reassignment, sale-project product mark edits, product base add/edit/delete, product status/reconciliation, warehouse base add/edit/delete, supplier base add/edit/delete, customer base add/edit/delete, customer follow-up, sale-project follow-up, product-info, field-change-log, history-Excel, project-rate, CC-record delete, team-project member add/manage/delete, team-project comment/reply base writes, team-project task base/category maintenance, team-project task assignee sync, and task comment add/edit/delete are now open |
| API compatibility docs | 65 | Stored under `docs/api` |
| Database docs | 10 | Stored under `docs/database` |
| Java Controllers in original project | 84 | Read-only reference baseline |
| Frontend API files in copied project | 76 | Static scan source: `snowy-admin-web/src/api` |
| Unique frontend API endpoints | 545 | Normalized static wrapper paths |
| Frontend endpoints already routed | 326 | Matched against current ThinkPHP route paths |
| Explicit safe frontend read wrappers missing | 0 | Static scan of concrete page/list/detail/query/selector wrappers after `/auth/third/page` |
| Frontend missing read/selector candidates | 69 | Priority candidates for safe compatibility slices |
| Frontend deferred write/side-effect candidates | 153 | Do not implement without module-specific write plans |
| Frontend baseline files copied | 908 | Copied into `snowy-admin-web`; generated/cache files excluded |
| Current branch | `refactor/thinkphp-main` | Integration branch; local commits may be ahead until the user asks to push |
| Latest frontend-slice commit | See latest Git log | Current dashboard records index schedule self-service compatibility |

## Module Progress

| Area | Completion | Status | Done | Remaining |
| --- | ---: | --- | --- | --- |
| Project engineering setup | 95% | Green | Git, remote sync, worktree plan, docs, AGENTS rules, baseline commands | Keep docs updated after each slice |
| Database and Models | 85% | Green | 121-table SQL reference used; 67 passive Models; field/relation/index docs | Low-priority tables, model relation methods where needed, final migration review |
| Auth / Token / RBAC / Menu | 74% | Yellow | Login, password compatibility base, token middleware, token cache index, RBAC/menu/session reads, session/token exit compatibility, third-party binding page read, protected routes | Refresh/session hardening, fine-grained permission enforcement, data-scope expansion, C-side client auth, third-party OAuth render/callback deferred |
| User / Org / Position | 77% | Yellow | Read-only org tree, position, user directory, user-center selectors, business-side directory aliases, camelCase display aliases for org/user/position pages, system user grant echo reads, current-user profile/password/avatar/signature/workbench/process-config writes, user/index message detail mark-read, homepage all-message mark-read, and homepage schedule add/delete | User CRUD, grant writes, admin reset password, import/export, encrypted profile fields, message send/delete |
| Workflow | 44% | Yellow | Runtime strategy, read-only task/process routes, process query aliases, file-list reads, runtime activity detail, variable normalization, copy/CC record reads, annual-leave balance page/detail reads | Approval/reject/cancel/start, task SSE, vacation deductions, side effects, workflow write runtime |
| Dev/System/Mobile/Gen/Tenant reads | 68% | Yellow | Many read-only management endpoints added and routed, including server monitor, network monitor, sys-field resource reads, and gen basic metadata reads | Write routes, provider actions, code generation, scheduler execution are intentionally deferred |
| Business read-only APIs | 92% | Yellow | Product, supplier, warehouse, inventory, delivery, purchase, settlement, payment, expenditure, collection, debit, file relation, history Excel, team project, team-project task user, team-project comment/reply, return order, sale project including cost details, sale-project follow-up, sale-project field change log, sale-project product item relation list, sale-project invoice item page, sale-project draft detail, customer, customer follow-up, sale-project invoicing, invoice, reissue, project-rate page/list/detail, sale-project product info, biz-datareport sale-project summaries/unpaid/details/settlement/sale-profit/summary-statistics reads, leave-application reads, annual-leave balance page/detail, settlement-account-payment reads, payroll reads, workflow process/task reads | Remaining detail consumers |
| Business write APIs | 37% | Red | Customer head reassignment, sale-project product item/relation mark edits, product base add/edit/delete with kit relations, product status/reconciliation, warehouse base add/edit/delete, supplier base add/edit/delete, customer base add/edit/delete, customer follow-up, sale-project follow-up, sale-project product-info, sale-project field-change-log, history-Excel, project-rate, current-user CC-record delete, team-project member add/manage/delete, team-project comment/reply base writes, team-project task base/category maintenance, team-project task assignee sync, and team-project task comment add/edit/delete are now covered; most side-effect-heavy writes remain deferred by design | Sale-project state writes, audit/status/stock/payment/refund flows with transactions and side effects |
| Frontend adaptation | 90% | Yellow | Original Vue project copied into target repo; request prefix, Bearer token, upload/SSE token headers, local SM2 fallback, double-prefix fix, menu leaf handling adapted, API gap map generated, org/user display aliases added, sys-user grant echo reads, user-center self-service writes, message detail mark-read, homepage all-message mark-read, homepage schedule add/delete, auth monitor session/token exit compatibility, and dev-message delete added, sys-field reads added, gen basic metadata reads added, auth third user page read added, copy-task CC record reads/delete added, sale-project draft detail read added, annual-leave balance page/detail read added, sale-project invoice item page read added, sale-project product mark edits added, sale-project field change log reads/writes added, history-Excel reads/writes added, team-project member add/manage/delete added, team-project task base/category maintenance added, team-project task user reads plus task assignee sync added, team-project task comment add/edit/delete added, team-project comment/reply reads/add/edit/delete added, dev monitor network read added, sale-project rating detail/add/delete added, product base add/edit/delete and kit relations added, product status/reconciliation added, warehouse base add/edit/delete added, supplier base add/edit/delete added, customer base add/edit/delete and head reassignment added, customer follow-up writes added, sale-project follow-up writes added, sale-project product-info writes added, minimal SSE route added, short-lived SSE client fallback added; browser smoke reaches `/sys/org`, `/sys/user`, `/biz/bizdatareport/summaryStatistics`, `/biz/saleproject` with visible pagination, sale-project detail read tabs, and cost tab zero-revenue display is guarded | Broken API method cleanup, remaining side-effect-heavy business routes |
| Testing / QA | 44% | Yellow | Composer, `php think`, route list, PHP lint, smoke tests per slice, backend/frontend browser smoke for summary-statistics, sale-project detail tab service smoke, and recoverable message read-state service smokes | Automated route/API test suite, regression matrix, broader frontend smoke, negative tests |
| Deployment | 16% | Red | Local MySQL/Redis startup method and user-designated runtime target confirmed; env is local and ignored by Git | Production config, queue/runtime/log permissions, Nginx/PHP deployment checks |
| Final online data sync | 0% | Red | Requirement recorded as final-stage reminder | Must design and confirm after project completion; do not start early |

## Completed Compatibility Slices

| Slice | Current Capability | Write Behavior |
| --- | --- | --- |
| Auth | Login, logout, current user, token, RBAC/menu reads, session/token monitor reads and exit compatibility | Partial; password-safe endpoint only, C-side auth and OAuth deferred |
| User directory | Org/user/position selectors, read-only directory, current-user self-service profile/password/workbench/process-config writes, message detail mark-read, homepage all-message mark-read, and homepage schedule add/delete | Admin-side CRUD and grants deferred |
| Workflow reads | Task count/list/page/history, process page/detail/variable reads | Deferred |
| System/dev/mobile/gen reads | Config, dict, file, email/sms records, job metadata, logs, messages, monitor, resource/menu/mobile resource, gen metadata, gen basic database metadata, tenant reads | Deferred |
| Business reads/writes | Product, supplier, settlement account, payment record, expenditure record, collection receipt, debit note, file relation, team project, task/comments, warehouses, inventory, delivery, purchase order, return order, sale project reads; product status/reconciliation, warehouse base add/edit/delete, supplier base add/edit/delete, customer base add/edit/delete, customer follow-up, sale-project follow-up, product-info, field-change-log, history-Excel, project-rate, CC-record delete, team-project member add/manage/delete, and team-project comment/reply base writes | Most side-effect-heavy business writes deferred |
| SSE compatibility | `/dev/message/createSseConnect` Java/frontend behavior mapped, minimal protected ThinkPHP route added, and frontend short-lived reconnect fallback added | Full realtime push deferred |
| Sys field reads | `/sys/field/page`, `/tree`, `/detail`, and `/MenuTreeSelector` routed for copied system field resource drawers, reading `sys_resource.CATEGORY = FIELD` | Field add/edit/delete and resource writes deferred |
| Gen basic metadata reads | `/gen/basic/tables` and `/tableColumns` routed for copied generator form database metadata, reading MySQL `information_schema` and excluding `ACT_` workflow engine tables | Generator add/edit/delete, preview, execution, templates, and generated code output deferred |
| Auth third user page read | `/auth/third/page` routed for copied third-party user binding pagination, reading `auth_third_user` with category/search filters | OAuth render/callback, third-party binding writes, user creation, and token issuing deferred |
| Sale project reads | `/biz/saleproject/page`, `/case/page`, `/operation/page`, `/public/page`, `/list/detail`, `/detail`, `/product`, `/cost`, and `/cost/details` routed with aggregate read data, weighted-average purchase cost details, route precedence fixed for completed-project cost tabs, and admin-compatible list data scope for the copied sale-project page | Writes and workflow/finance side effects deferred |
| Sale project follow-up reads and writes | `/biz/saleprojectfollowup/page`, `/detail`, `/add`, `/edit`, and `/delete` routed with project name, creator display fields, data-scope guarding, `fileList` to `extJson` preservation, and logical delete | File upload/storage cleanup, notifications, sale-project state, workflow, finance, and inventory side effects deferred |
| Sale project product item relation reads and mark edits | `/biz/saleprojectproductitemrelation/list`, `/biz/saleprojectproductitemrelation/mark/edit`, and `/biz/saleprojectproductitem/mark/edit` routed with combo-product child relation rows, product aliases, product display fields, data-scope guarding through the owning sale project, and single-field `MARK` writes | Product item add/edit/delete, delivery, invoice, inventory, workflow, finance, and sale-project state writes deferred |
| Sale project detail browser smoke | `/biz/saleproject` detail modal read tabs browser-smoked for project information, follow-up records, case empty state, and pending-process empty state; follow-up add backend write is now available | Case upload/add, workflow actions, and sale-project writes deferred |
| Sale project cost frontend display | Completed-project cost tab guards zero-revenue gross profit rate and displays numeric zero instead of `NaN%` | Backend cost payloads and sale-project/finance writes unchanged |
| Sale project remaining tab smoke | Payment, return-order, invoice, and file-relation tab read services verified against one imported project | Browser visual confirmation and all write controls deferred |
| Product reads and base writes | `/biz/bizproduct/page`, `/list`, `/detail`, `/children`, `/add`, `/edit`, `/delete`, `/edit/status`, and `/reconciliation/edit` routed with product/org display aliases, base product maintenance, kit `KIT_PRODUCT_DATA` relation maintenance, status toggling, selected-product reconciliation fields, write-scope guarding, audit fields, and logical delete | Inventory, purchase, sale-project, finance transaction, workflow, file upload/storage implementation, and data-change/cache events deferred |
| Warehouse reads and base writes | `/biz/warehouses/page`, `/list`, `/detail`, `/add`, `/edit`, and `/delete` routed with owner/org display aliases, token owner/org defaults, write-scope guarding, and logical delete | Inventory stock updates, delivery records, purchase-order writes, sale-project invoice writes, and workflow side effects deferred |
| Supplier reads and base writes | `/biz/supplier/page`, `/list`, `/list/query/name`, `/detail`, `/add`, `/edit`, and `/delete` routed with supplier display aliases, lower-case physical `org` preservation, write-scope guarding, and logical delete | Supplier import/export and purchase/payment/procurement/inventory/workflow side effects deferred |
| Customer reads and base/follow-up/head writes | `/biz/customer/page`, `/add`, `/edit`, `/delete`, `/head/edit`, `/detail`, `/detail/list`, `/biz/customerfollowup/page`, `/detail`, `/add`, `/edit`, and `/delete` routed with customer owner/org/file, customer base write guarding, head reassignment, follow-up creator display fields, data-scope write guarding, and logical delete | Attachment upload/storage cleanup, notifications, data-change events, sale-project/customer side effects, and SM4 plaintext search deferred |
| Sale project billing reads/writes | `/biz/saleprojectinvoicing/page`, `/customer`, `/detail`, `/biz/saleprojectinvoice/page`, `/list`, `/biz/saleprojectreissueorder/list/query`, `/biz/projectrate/page`, `/list`, `/detail`, `/add`, and `/delete` routed with nested invoice/reissue structures, rating detail reads, and base rating logical delete | Invoice, invoicing, reissue, rating edit, image storage, workflow, inventory, and finance writes deferred |
| Biz directory aliases | `/biz/org`, `/biz/user`, `/biz/position`, and `/biz/dict` read paths routed to existing system/dev read services; `/biz/user/center/edit` routed for current-user self-profile writes | User/org/position/dict admin writes, role grants, admin password actions, import/export deferred |
| Workflow read aliases | `/biz/process/all/page`, `/query`, `/query/list`, `/project/runtime/query/list`, `/fileList`, and `/biz/task/runtime/activity/detail` routed through Camunda-table read services | Task approve/reject, task SSE, process starts/cancel, and Java delegate side effects deferred |
| Sale project product info reads and writes | `/biz/saleprojectproductinfo/page`, `/list`, `/detail`, `/add`, `/edit`, and `/delete` routed with software package/version rows, creator display names, audit writes, and logical delete | Product master-data, product-item, import/export, report, workflow, finance, inventory, and delivery side effects deferred |
| Biz datareport sale-project details | `/biz/bizdatareport/saleProjectList/details` routed with sale project rows, nested product items, package children, and return orders | Other report, profit, unpaid-payment, and summary-statistics endpoints deferred |
| Biz datareport sale-project summaries | `/biz/bizdatareport/saleproject`, `/saleproject/list`, `/saleproject/report`, and `/saleproject/UnpaidPayment` routed with amount totals, project amount rows, status/time rows, and unpaid amount | Summary-statistics endpoint deferred |
| Biz datareport settlement reads | `/biz/bizdatareport/settlement/income` and `/settlement/expenses` routed with payment/expenditure record rows, settlement categories, payer time, account, org, and amount fields | Summary-statistics, settlement mutations, and account balance updates deferred |
| Biz datareport sale-profit read | `/biz/bizdatareport/saleProfit` routed with raw `projectlist`, `orderList`, and `bizProducts` collections for frontend WebWorker calculation | Purchase/sale/return/inventory mutations and workflow side effects deferred |
| Biz datareport summary-statistics read | `/biz/bizdatareport/summary/statistics` routed with company-scoped `org`, `settlementAccounts`, `paymentRecords`, `bizExpenditureRecords`, `bizSaleProjects`, and `bizDebitNotes` collections for frontend WebWorker calculation; copied Vue page browser-smoked at `/biz/bizdatareport/summaryStatistics` | Settlement/account-balance mutations and workflow side effects deferred |
| Leave application reads | `/biz/bizleaveapplication/page`, `/my/page`, and `/detail` routed with applicant, organization, workflow process id, date, amount, and object id fields | Add/edit/delete and workflow start/approval side effects deferred |
| Settlement account payment reads | `/biz/settlementaccountpayment/page` and `/list` routed with account statement amount, settlement type/category, process id, account, and timestamp fields | Account payment/transfer/income/expense mutations and balance changes deferred |
| Payroll reads | `/biz/bizpayroll/page`, `/mypage`, and `/detail` routed with salary fields, employee display name, organization display name, salary month filters, and data-scope guards | Payroll import/export/generate/add/edit/batch edit/delete deferred |
| Sys user grant echo reads and user-center/index writes | `/sys/user/list/detail`, `/ownRole`, `/ownResource`, and `/ownPermission` routed for copied user grant dialogs; `/sys/userCenter/updatePassword`, `/updateAvatar`, `/updateSignature`, `/updateUserInfo`, `/updateUserWorkbench`, `/process/config/edit`, detail-time message mark-read, `/sys/index/message/allMessageMarkRead`, `/sys/index/schedule/add`, and `/sys/index/schedule/deleteSchedule` routed for current-user self-service flows | Grant role/resource/permission writes, user CRUD, enable/disable, admin reset password, import/export, and message send deferred |
| Dev message management | `/dev/message/page`, `/detail`, `/createSseConnect`, and `/delete` routed for copied station-message management page; delete removes receiver relations and selected message rows with conservative owner/admin guard | Message send, SSE/WebPush realtime push, and attachment/storage cleanup deferred |
| Biz CC records reads and delete | `/biz/ccrecords/page`, `/detail`, and `/delete` routed for copied workflow copy-task page, filtered to the current token user, enriched with promoter/user display names, and logically deleted by current user only | Add/edit, workflow copy delegate writes, and approval actions deferred |
| Biz draft detail read | `/biz/bizdraft/detail` routed for copied sale-project draft reloads, reading by `TARGET_ID` and preserving raw `extJson` | Draft save, sale-project writes, workflow start, and file upload side effects deferred |
| Biz user vacation reads | `/biz/bizuservacation/page` and `/detail` routed for copied leave-process annual-leave balance reads, defaulting detail to current user and `annualLeave` | Vacation generation/reduction, leave approval deductions, and write routes deferred |
| Biz history Excel reads and writes | `/biz/bizhistoryexcel/page`, `/detail`, `/add`, `/edit`, and `/delete` routed for copied historical spreadsheet data browsing and base row persistence, preserving raw `extJson` and using logical delete | Import/export, spreadsheet parser changes, `biz_history_excel_row` writes, and storage changes deferred |
| Sale project invoice item page read | `/biz/saleprojectinvoiceItem/page` routed for copied sale-project delivery invoice item pagination, with product and warehouse display aliases | Invoice item writes, invoice/delivery/stock/project-state/finance side effects deferred |
| Sale project field change log reads and writes | `/biz/salesprojectfieldchangelog/page`, `/detail`, `/add`, `/edit`, and `/delete` routed for copied sale-project change-log browsing and base log-row maintenance, with project and creator display aliases plus logical delete | Sale-project generated change writes, workflow, finance, and audit side effects deferred |
| Team project member maintenance | `/biz/bizteamprojectuser/add`, `/manage/add`, and `/delete` routed for copied team-project member consumers, requiring imported project resource permissions, restoring previously deleted rows, syncing member relation permission JSON, and logically deleting members | Member role edit, notification push, data-change events, and team-project base writes deferred |
| Team project task user reads and assignee sync | `/biz/bizteamprojecttaskuser/page` and `/detail` routed for copied team-task member browsing, and `/biz/bizteamprojecttask/user/edit` routed for task detail assignee sync with project-member, imported `addUser`, and task `MANAGE` guards | Standalone task-user add/edit/delete, task status/progress/content writes, notifications, and data-change events deferred |
| Team project task category maintenance | `/biz/bizteamprojecttaskcategory/add`, `/edit`, `/sort/edit`, and `/delete` routed for copied kanban category consumers, requiring project maintainer permission, defaulting new category `SORT_CODE` to `99`, and rejecting non-empty category deletion | Task add/edit/delete, task drag/move, notifications, and data-change events deferred |
| Team project task base maintenance | `/biz/bizteamprojecttask/add`, `/edit`, and `/delete` routed for copied kanban task consumers, creating current-user `MANAGE` task membership, validating selected task users as project members, validating task status, and logically deleting tasks plus active task-user rows | Generated task logs, notifications, data-change events, workflow actions, and full drag ordering deferred |
| Team project task comment maintenance | `/biz/bizteamprojecttaskcomment/add`, `/edit`, and `/delete` routed for copied task comment consumers, deriving the team project from the task, preserving `EXT_JSON.file` attachment metadata, and protecting generated `LOG` rows as read-only | Notifications, data-change events, and generated task-log maintenance deferred |
| Team project comment/reply reads and writes | `/biz/bizteamprojectcomment/detail`, `/add`, `/delete`, `/biz/bizteamprojectcommentreply/page`, `/detail`, `/add`, `/edit`, and `/delete` routed for copied project timeline comment/reply consumers, preserving project-member visibility guard, imported `delComment` permission for maintenance, and storing mentioned users under `EXT_JSON` | Notifications and data-change events deferred |
| Dev monitor network read | `/dev/monitor/networkInfo` routed for copied monitor pages, returning `upLinkRate` and `downLinkRate` with safe zero fallback | Monitor writes, server control, and metric persistence deferred |
| Sale project rating reads and writes | `/biz/projectrate/page`, `/list`, `/detail`, `/add`, and `/delete` routed for copied sale-project rating consumers, returning `projectName`, `customerName`, raw `extJson`, preserving `imgList`, and using logical delete | Rating edit, image upload/storage, sale-project writes, workflow, finance, and notifications deferred |
| Biz user vacation page read | `/biz/bizuservacation/page` routed for copied vacation-balance management wrappers, returning user display names and balance fields | Vacation writes, generation/reduction, and leave approval deductions deferred |

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
