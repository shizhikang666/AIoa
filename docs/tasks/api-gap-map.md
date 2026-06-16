# Frontend API Gap Map

Date: 2026-06-16

Agent: frontend-agent / main control agent

## Scope

This map compares the copied Vue API wrappers under `snowy-admin-web/src/api` with the current ThinkPHP route table.

It is a planning aid for the next api-agent, user-agent, workflow-agent, and frontend-agent slices. It does not mean every frontend wrapper should be implemented immediately. Write-heavy endpoints, workflow actions, finance/warehouse side effects, imports, exports, deletes, and production data sync remain deferred until each module has a confirmed design and test plan.

The Java source project at `F:\AI\projects\testJava\OA` remains read-only.

## Scan Summary

| Item | Count | Notes |
| --- | ---: | --- |
| Frontend API wrapper files | 76 | From `snowy-admin-web/src/api` |
| Frontend endpoint references | 566 | Raw wrapper calls found by `scripts/frontend-api-route-gap-smoke.ps1`, including static ternary request branches and `moduleRequest` wrappers |
| Unique frontend endpoints | 560 | Normalized wrapper request paths after query-only template fragments are filtered |
| Current ThinkPHP routes | 578 | `php think route:list` concrete route count after public auth phone-code/WebPush deferred stubs, public third-party auth deferred stubs, public password-recovery deferred stubs, public password-recovery captcha, SMS provider-send deferred wrappers, project-rate edit, role grant-save routes, role add/edit/delete, system process-config edit, settlement-account base maintenance, collection-receipt mark-success, debit-note mark-success, payroll edit/batch-edit/delete, payroll import-template download, payroll CSV export download, leave-application edit/delete, vacation manual add/edit/delete, CC-record current-user add/edit/delete, dev-config editBatch value maintenance, dev-job metadata add/edit, gen-basic metadata add/edit/delete, gen-config metadata edit/delete, tenant metadata add/edit/delete, sale-project draft save, generator preview, generator ZIP download, payment-record payer-time edit, expenditure-record payer-time/category edit, and frontend controlled-deferred payment-record/expenditure-record/collection-receipt/debit-note/purchase-order/return-order/invoicing/inventory/delivery/settlement-account/HR-excluding-vacation/dev-excluding-config-editBatch-and-job-metadata/gen-excluding-basic-and-config-edit-delete/workflow/task/sale-project write wrappers |
| Endpoints already covered by route path | 560 | Current `frontend-api-route-gap-smoke` route-path scan; this is path coverage only and does not prove semantic parity |
| Missing read/selector/report candidates | 0 | `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing` passes |
| Deferred write/side-effect candidates | 0 | Static frontend wrapper paths are now all routed; real side-effect semantics still require module-specific plans before browser clicks or production use |

## Already Covered Route Groups

The current ThinkPHP project already covers these frontend-visible groups at least partially:

| Group | Current Coverage |
| --- | --- |
| `auth` | Login, logout, current user, token/session reads |
| `sys/index` and `sys/sysConfig` | User info, menu, permissions, dashboard basics, and process-config detail/edit |
| `sys/org` | Tree, selector, page/detail style reads plus base add/edit/delete |
| `sys/user` | User page/detail/list style reads, selectors, own-role, own-resource, own-permission, delete, grant saves, reset password, enable/disable status switches, and export/download blobs |
| `sys/position` | Position page/list/detail/selector reads plus base add/edit/delete |
| `sys/role` | Role page/list/detail/resource/menu relation reads, role add/edit/delete, and role resource/mobile-menu/permission/user grant saves |
| `sys/module`, `sys/menu`, `sys/button`, `sys/field`, and `sys/resource` | Module page/detail plus module add/edit/delete, menu/resource tree plus menu add/edit/changeModule/delete, button page/detail plus button add/edit/delete, field page/tree/detail plus field add/edit/delete, and selector reads |
| `dev/config`, `dev/dict`, `dev/log` | Common management reads plus dev config `BIZ_DEFINE` add/edit/delete, dev config editBatch existing-row value maintenance, dev log category delete, and BIZ dictionary maintenance writes |
| `dev/file`, `dev/email`, `dev/sms`, `dev/job`, `dev/monitor` | File metadata/list/detail, LOCAL/dynamic upload, file/email/SMS/job metadata logical delete, dev-job metadata add/edit, protected email/SMS send controlled-deferred wrappers, protected job run/stop/run-now controlled-deferred wrappers, public local-file download compatibility, cloud upload unsupported stubs, and monitor reads; real cloud storage, scheduler runtime, real email/SMS provider sends, and provider actions remain deferred |
| `mobile/module`, `mobile/menu`, `mobile/button`, and `mobile/resource` | Mobile menu/resource read compatibility plus mobile module add/edit/delete, mobile menu add/edit/changeModule/delete, and mobile button add/edit/delete |
| `gen/basic`, `gen/config`, `tenant` | Generator metadata reads, gen-basic metadata add/edit/delete with default config-row maintenance, `gen/config/edit`, `/delete`, and `/editBatch` field-configuration saves, generator config add/direct-project controlled-deferred writes, smoke-covered tenant reads, and narrow tenant add/edit/delete metadata maintenance |
| `biz/product`, `biz/supplier`, `biz/settlementaccount` | Core master-data read adapters; product base add/edit/delete, kit relation maintenance, product status/reconciliation, supplier base add/edit/delete, settlement-account add/edit/status, and settlement-account controlled-deferred side-effect wrappers are covered |
| `biz/bizpaymentrecord`, `biz/bizexpenditurerecord`, `biz/bizcollectionreceipt`, `biz/bizdebitnote` | Finance read adapters plus payment-record payer-time edit, expenditure-record payer-time/category edit, remaining payment-record/expenditure-record/collection-receipt/debit-note controlled-deferred wrappers, and collection-receipt/debit-note mark-success narrow status markers |
| `biz/bizpurchaserequest`, `biz/bizpurchaseorder`, `biz/warehouses`, `biz/inventory`, `biz/delivery`, `biz/returnorder` | Purchase, warehouse, inventory, delivery, return read slices; warehouse base add/edit/delete is covered; purchase-order add/edit/audit/cancel/delete/warehouse-add, inventory add/delete, delivery add, and return-order add/edit/delete now return controlled-deferred responses |
| `biz/saleprojectproductinfo` | Sale-project software package/version info reads are smoke-covered; base add/edit/delete writes are routed but excluded from read smoke |
| `biz/bizdatareport` | Sale-project amount/list/report, unpaid-payment, settlement income/expenses, sale-profit, summary-statistics, and details reads |
| `biz/projectrate` | Project rating page, list, and detail reads |
| `biz/bizleaveapplication` | Leave/business-trip page, my-page, detail, edit, logical delete, and add controlled-deferred wrapper |
| `biz/bizuservacation` | Annual-leave/vacation page and current-year balance detail reads plus manual add/edit/logical delete maintenance |
| `biz/settlementaccountpayment` | Settlement account statement page/list reads |
| `biz/bizpayroll` | Payroll page, my-page, detail reads, import-template download, CSV export download, edit, batch edit, logical delete, and add/import/generate controlled-deferred wrappers |
| `biz/bizhistoryexcel` | Historical EXCEL page and detail reads |
| `biz/saleprojectinvoiceItem` | Sale-project delivery invoice item page reads |
| `biz/salesprojectfieldchangelog` | Sale-project field change log page and detail reads |
| `biz/teamproject`, `biz/task` | Team project add/edit/delete, member add/manage/edit/delete, task, task category, task user, project comment, project comment reply, task comment read/add/edit/delete slices, task base maintenance, task category maintenance, task assignee sync, and project comment/reply base write compatibility |
| `biz/process` | Basic workflow query/read slices plus process start/edit/cancel controlled-deferred wrappers |
| `biz/ccrecords` | Workflow copy/CC record page/detail reads plus current-user add/edit/logical delete maintenance |
| `biz/bizdraft` | Sale-project draft detail read and draft save |

## Priority 1: Visible Frontend Follow-Ups

These are the highest-priority follow-ups because they affect pages the user can already open after login.

| Area | Gap | Suggested Owner | Notes |
| --- | --- | --- | --- |
| Org/User visible tables | Verified by `scripts/user-display-http-smoke.ps1` and `scripts/directory-alias-http-smoke.ps1` | frontend-agent with api-agent support | Authenticated smokes confirm sys/biz user page/detail/list/detail/userSelector rows expose `orgName`, `positionName`, `genderName`, selector aliases, Java-style paging keys, and no `PASSWORD`; directory alias smoke covers biz org/position/dict trees/pages/selectors and `size` pagination |
| Message SSE | Frontend components call `/dev/message/createSseConnect` | api-agent or workflow/test support | Review Java behavior before adding a safe compatibility route |
| Upload compatibility | LOCAL/dynamic `dev/file/upload*`, `dev/file/delete`, and business attachment relation binding are covered | api-agent/test-agent | Real Aliyun/Tencent/Minio storage and physical file cleanup remain deferred |
| User profile center / homepage self-service | Current-user password, avatar, signature, profile, workbench, process-config edit, message detail mark-read, homepage all-mark-read, homepage schedule add/delete, and `/biz/user/center/edit` self-profile alias are covered | user-agent | Admin-side user CRUD/import/export and encrypted-field migration remain deferred |
| Sys/biz org, position, user, and role maintenance dialogs | `/sys/org/add`, `/sys/org/edit`, `/sys/org/delete`, `/biz/org/add`, `/biz/org/edit`, `/biz/org/delete`, `/sys/position/add`, `/sys/position/edit`, `/sys/position/delete`, `/biz/position/add`, `/biz/position/edit`, `/biz/position/delete`, `/sys/user/add`, `/sys/user/edit`, `/biz/user/add`, `/biz/user/edit`, `ownRole`, `ownResource`, `ownPermission`, `/sys/user/grantRole`, `/biz/user/grantRole`, `/sys/user/grantResource`, `/sys/user/grantPermission`, `/sys/role/add`, `/sys/role/edit`, `/sys/role/delete`, `/sys/role/grantResource`, `/sys/role/grantMobileMenu`, `/sys/role/grantPermission`, `/sys/role/grantUser`, `/sys/user/delete`, `/biz/user/delete`, `/sys/user/disableUser`, `/sys/user/enableUser`, `/biz/user/disableUser`, `/biz/user/enableUser`, `/sys/user/resetPassword`, `/biz/user/resetPassword`, `/sys/user/downloadImportUserTemplate`, `/sys/user/import`, `/sys/user/export`, `/sys/user/exportUserInfo`, `/biz/user/export`, and `/biz/user/exportUserInfo` are covered | user-agent/frontend-agent | Business user import and real `.docx` rendering remain deferred |

## Priority 2: Safe Read-Only API Candidates

These groups should be handled before business writes, because they unlock more old frontend pages without creating side effects.

| Group | Missing Read/Selector/Report Candidates |
| --- | --- |
| `biz/saleproject` | Core read routes covered and smoke-verified: `case/page`, `detail`, `list/detail`, `operation/page`, `page`, `product`, `public/page`, `cost`, `cost/details`; sale-project follow-up `page/detail` read contracts are smoke-verified, while follow-up add/edit/delete remain covered but broader side effects are deferred; sale-project state/write routes now return controlled-deferred responses |
| `biz/salesprojectfieldchangelog` | `page`, `detail`, `add`, `edit`, and `delete` covered; sale-project change-generation side effects remain deferred |
| `biz/customer` | `detail`, `detail/list`, `page`, and customer follow-up `page/detail` read contracts are smoke-verified; `add`, `edit`, `delete`, and `head/edit` are covered as low-risk writes with broader side effects deferred |
| `biz/org` | `detail`, `list`, `orgTreeSelector`, `page`, `tree`, `userSelector`, `add`, `edit`, and `delete` |
| `biz/user` | `detail`, `list/detail`, `orgTreeSelector`, `ownRole`, `page`, `positionSelector`, `roleSelector`, `userSelector`, `disableUser`, `enableUser`, `export`, and `exportUserInfo` |
| `biz/position` | `detail`, `list`, `orgTreeSelector`, `page`, `positionSelector`, `add`, `edit`, and `delete` |
| `biz/dict` | `page`, `tree`, `treeAll`, and Java-compatible `edit` covered; business add/delete remain intentionally absent like Java |
| `biz/process` | Read aliases added and smoke-verified for `all/page`, `fileList`, `project/runtime/query/list`, `query`, and Java-compatible guarded `query/list`; write/start/cancel routes remain deferred |
| `biz/task` | `count`, `list`, `page`, `history/page`, and conditional `runtime/activity/detail` are smoke-covered; standalone `sse/stream`, `approve`, and `reject` remain deferred. Java `BizTaskController` does not currently expose `/biz/task/sse/stream`, and no active copied frontend caller was found; layout task refresh is covered through `/dev/message/createSseConnect` `FlushProcessNotice` |
| `biz/bizuservacation` | `page` and `detail` covered; `add`, `edit`, and `delete` now provide narrow manual maintenance with transaction, tenant/user, duplicate, amount, and logical-delete guards; generation/reduction and leave/payroll side effects remain deferred |
| `biz/ccrecords` | `page`, `detail`, `add`, `edit`, and `delete` covered with current-user scoping; workflow copy-generation remains deferred |
| `biz/bizdraft` | `detail` and `saleproject/add` covered |
| `biz/bizhistoryexcel` | `page`, `detail`, `add`, `edit`, and `delete` covered; import/export parsing and row-table storage remain deferred |
| `biz/saleprojectinvoicing` | `customer`, `detail`, and `page` are smoke-covered; `complete` is routed but intentionally excluded from read smoke; add/edit/delete now have controlled-deferred wrappers while real invoicing mutation remains deferred |
| `biz/saleprojectinvoiceItem` | `page` and `invoiceId` filtered page are smoke-covered; invoice item writes remain deferred |
| `biz/projectrate` | `page`, `list`, `detail`, `add`, `edit`, and `delete` covered; file upload/storage remains deferred |
| `biz/saleprojectreissueorder` | `list/query` nested `order` and `productItemList` structure is smoke-covered; reissue-order writes remain deferred |
| `biz/saleprojectproductitemrelation` | `list` is smoke-covered; relation and product-item `mark/edit` are routed but excluded from read smoke |
| `biz/bizteamproject` | `page`, `detail`, `add`, `edit`, and `delete` covered; notification/data-change side effects remain deferred |
| `biz/bizteamprojectcomment` | `page`, `list`, `detail`, `add`, and `delete` covered; notification/data-change side effects remain deferred |
| `biz/bizteamprojectcommentreply` | `page`, `detail`, `add`, `edit`, and `delete` covered |
| `biz/bizteamprojecttask` | `page`, `list`, `detail`, `add`, `edit`, `delete`, and `user/edit` covered; task logs, notification, data-change events, and full drag ordering remain deferred |
| `biz/bizteamprojecttaskcategory` | `page`, `list`, `detail`, `add`, `edit`, `sort/edit`, and `delete` covered; task moves, notification, and data-change side effects remain deferred |
| `biz/bizteamprojecttaskcomment` | `page`, `list`, `detail`, `add`, `edit`, and `delete` covered for `COMMENT` rows; notification, data-change side effects, and generated `LOG` maintenance remain deferred |
| `biz/bizteamprojecttaskuser` | `page` and `detail` covered; task-detail assignment sync is covered through `/biz/bizteamprojecttask/user/edit`; standalone `add`, `edit`, and `delete` remain deferred |
| `dev/monitor` | `serverInfo` and `networkInfo` covered |
| `sys/field` | `page`, `tree`, `detail`, `MenuTreeSelector`, `add`, `edit`, and `delete` covered |
| `gen/basic` and `gen/config` | `gen/basic/page`, `detail`, `previewGen`, `execGenZip`, `tables`, `tableColumns`, `mobileModuleSelector`, `add`, `edit`, `delete`, `gen/config/list`, `detail`, `edit`, `delete`, and `editBatch` covered; generator config add and direct project generation still return controlled-deferred responses |

## Deferred Write And Side-Effect Groups

The frontend contains many wrappers that should stay deferred until their modules are explicitly opened for write work.

| Group | Deferred Examples | Reason |
| --- | --- | --- |
| `biz/saleproject` | `add`, `edit`, `delete`, `amount/edit`, `deal/edit`, `cancel`, `history/add`, `special/add`, `visibility/edit`, `repeal` | Wrappers return controlled `code=400`; real project state, finance, visibility, repeal, cancel, amount, and history side effects remain deferred |
| `biz/bizdraft` | Sale-project workflow submission and real project writes | Draft save is covered as isolated `biz_draft` persistence; formal sale-project add/edit and workflow side effects remain deferred |
| `biz/saleprojectfollowup` | File upload/storage cleanup, notifications | Add/edit/delete base record writes are covered; file and message side effects remain deferred |
| `biz/saleprojectproductitemrelation` | Delivery/invoice/stock side effects | Relation `mark/edit` is covered but excluded from read smoke |
| `biz/saleprojectproductitem` | Add/edit/delete, delivery/invoice/stock side effects | Product item `mark/edit` is covered |
| `biz/customer` | SM4 plaintext search, file upload/storage, and related side effects | Customer base add/edit/delete and `head/edit` are covered |
| `biz/customerfollowup` | Attachment upload/storage cleanup, notifications | Add/edit/delete base record writes are covered; file side effects remain deferred |
| `biz/org`, `biz/user`, `biz/position`, `biz/dict` | business user import, real Office export rendering, resource/permission grants, business dictionary add/delete | `/biz/org/add`, `/biz/org/edit`, `/biz/org/delete`, `/biz/position/add`, `/biz/position/edit`, `/biz/position/delete`, `/biz/user/add`, `/biz/user/edit`, `/biz/user/center/edit`, `/biz/user/delete`, `/biz/user/grantRole`, `/biz/user/disableUser`, `/biz/user/enableUser`, `/biz/user/resetPassword`, `/biz/user/export`, `/biz/user/exportUserInfo`, and `/biz/dict/edit` are covered; `/dev/dict/add|edit|delete` cover BIZ maintenance; `/sys/user/import` covers Java system user import; business user import remains intentionally absent like Java; real `.docx` rendering and business dictionary add/delete remain deferred |
| `biz/process` | `leave/start`, `payment/start`, `procure/start`, project start actions, `cancel` | Wrappers return controlled `code=400`; real workflow runtime and business hooks remain deferred |
| `biz/task` | `approve`, `reject`, standalone `sse/stream` | Wrappers return controlled `code=400`; real workflow transitions, audit records, and long-lived task SSE remain deferred; current layout task refresh uses `/dev/message/createSseConnect` |
| `dev/file` | cloud `upload*`, physical file cleanup | LOCAL/dynamic upload, public local download, metadata logical delete, and business relation binding are covered; cloud storage and optional physical cleanup still need dedicated plans |
| `biz/settlementaccount` | `delete`, `expenses/add`, `payment/add`, `transfer/add` controlled-deferred wrappers | Settlement-account base add/edit/status is covered; the wrappers return `code=400`, while real delete and quick income/expense/transfer remain deferred because they mutate account balance, statements, and payment/expenditure records |
| `dev/config` | provider/system config cache mutation, provider send/test behavior, unmasking secrets | `BIZ_DEFINE` add/edit/delete are covered with Java-style success envelopes, malformed delete payload rejection, sensitive-mask preservation, and logical delete; `editBatch` now updates only existing active `dev_config.CONFIG_VALUE` rows in a validated transaction and preserves sensitive values when `******` is submitted |
| `dev/log` | Cross-tenant/global clear behavior | Category delete is covered with physical deletion and current-tenant protection when the token payload has a tenant id; Java clears globally by category |
| `dev/email` and `dev/sms` | Real local/Aliyun/Tencent email sends and Aliyun/Tencent/Xiaonuo SMS provider calls | Routes are covered as protected controlled-deferred wrappers; provider credential reads, SMTP/SDK calls, external sends, and send-record writes remain deferred |
| `dev/job` | `stopJob`, `runJob`, `runJobNow`, scheduler lifecycle, task-class execution | Metadata add/edit/delete is covered with field validation, action-class allow-listing, duplicate guard, running-edit guard, and logical delete; real scheduler registration/removal, run, stop, run-now, and task execution remain deferred until a ThinkPHP scheduler exists |
| `dev/message` | Full realtime push | User-center/index detail mark-read, homepage all-mark-read, minimal SSE compatibility with initial message/process refresh notices, message send, and message delete are covered; full SSE/WebPush parity remains deferred |
| `biz/bizpaymentrecord`, `biz/bizexpenditurerecord`, `biz/bizcollectionreceipt`, `biz/bizdebitnote`, `biz/bizpurchaseorder`, `biz/inventory`, `biz/warehouses/delivery`, and `biz/returnorder` | payment-record add/edit-account/delete; payment-record edit covered as payer-time correction; expenditure-record add/edit-account/delete; expenditure-record edit covered as payer-time/category correction; collection-receipt add/edit/delete/batchExpenditure; debit-note add/edit/delete/batchRepayment/history add; purchase-order add/edit/audit/cancel/delete/warehouse add/one-warehouse add; inventory add/delete; delivery add; return-order add/edit/delete | Protected frontend compatibility wrappers return controlled `code=400` deferred responses except the narrow payment-record payer-time edit and expenditure-record payer-time/category edit; real payment creation/deletion, expenditure creation/deletion, account switch, receipt, repayment, purchase, return, delivery, order-state, stock, finance, workflow, and data-change side effects remain deferred |
| `biz/bizpayroll`, `biz/bizleaveapplication`, `biz/ccrecords`, `biz/saleprojectinvoicing` | payroll add/import/generate; leave add; workflow-copy generation; invoicing add/edit/delete | Payroll import-template download, CSV export download, edit/batch-edit/delete, payroll controlled-deferred add/import/generate actions, leave-application edit/delete/add controlled-deferred, CC current-user add/edit/delete, invoicing complete, and invoicing add/edit/delete controlled-deferred wrappers are covered; real generation, import parsing, EasyExcel-style xlsx export rendering, leave balance, workflow-copy delegate generation, and broader business side effects remain deferred |
| `biz/saleprojectproductinfo` | Product master-data writes, sale-project product-item changes, import/export/report side effects | Add/edit/delete base package info writes are covered; page/list/detail reads are smoke-covered |
| `biz/bizproduct` | Inventory, purchase, sale-project, finance transaction, workflow, file upload/storage, and data-change/cache side effects | Product base add/edit/delete, kit relation maintenance, status toggle, and reconciliation edits are covered |
| `biz/salesprojectfieldchangelog` | Sale-project amount/change generation, workflow, finance, audit side effects | Add/edit/delete base log-row writes are covered |
| `biz/ccrecords` | workflow copy-user delegate writes | Add/edit/delete are covered as current-user row maintenance; real generation still belongs to workflow write runtime |
| `biz/teamproject comments and tasks` | notification push, data-change events, generated task logs, full task drag ordering, member role edit | Member add/manage-add/delete, comment/reply base writes, task add/edit/delete base rows, task comment add/edit/delete for `COMMENT` rows, task category add/edit/sort/delete, and task assignee sync are covered with member/resource-permission guards; push and data-change side effects remain deferred |
| `biz/bizuservacation` | generation/reduction helpers, leave approval deductions, payroll-facing recalculation | Add/edit/logical delete manual maintenance is covered; generated accrual/reduction, workflow-owned deductions, payroll-facing recalculation, notification, and data-change behavior remain deferred |
| `biz/bizhistoryexcel` | Import/export parsing, `biz_history_excel_row` writes | Base add/edit/delete writes are covered; parser/storage changes remain deferred |
| `biz/projectrate` | image upload/storage cleanup | Add/edit/delete base row writes are covered; edit updates only the rating row and preserves submitted `imgList` in `EXT_JSON` |
| `sys/user`, `sys/userCenter`, and `sys/index` | real `.docx` rendering, encrypted-field migration, password-recovery SMS/email/reset flow | Current-user profile/password/workbench/process-config writes, public password-recovery captcha, admin-side user add/edit/import, user delete, user role/resource/permission grant saves, user enable/disable, admin reset password, homepage schedule, message read-state writes, and user export/download blobs are covered |
| `gen/basic`, `gen/config`, and generator execution | `gen/basic/execGenPro`, `/gen/config/add` | Generator basic metadata add/edit/delete, preview, ZIP download, and `/gen/config/edit`, `/delete`, and `/editBatch` are covered; config add and direct project output still return controlled `code=400` and require a separate module plan |
| `tenants/tenant` | default user/role/resource bootstrap, tenant cache/events | Tenant read routes and narrow add/edit/delete metadata maintenance are covered; Java default-data bootstrap, cache invalidation, and data-change behavior remain deferred |

## Authentication And Session Gaps

The frontend still references several auth monitoring and third-party routes:

| Endpoint | Current Recommendation |
| --- | --- |
| `auth/session/b/exit` | Covered by auth-agent session/token exit compatibility with cache-backed B-side token index |
| `auth/session/c/exit` | Covered as success-compatible C-side no-op until client auth is implemented |
| `auth/token/b/exit` | Covered by auth-agent token exit compatibility for indexed B-side bearer tokens |
| `auth/token/c/exit` | Covered as success-compatible C-side no-op until client auth is implemented |
| `auth/b/getPhoneValidCode` | Covered as a controlled deferred route; SMS sending remains deferred |
| `auth/b/subscription` | Covered as a controlled deferred route; WebPush subscription persistence remains deferred |
| `sys/userCenter/findPasswordGetPhoneValidCode` | Covered as a controlled deferred route; SMS sending remains deferred |
| `sys/userCenter/findPasswordGetEmailValidCode` | Covered as a controlled deferred route; email sending remains deferred |
| `sys/userCenter/findPasswordByPhone` | Covered as a controlled deferred route; password reset mutation remains deferred |
| `sys/userCenter/findPasswordByEmail` | Covered as a controlled deferred route; password reset mutation remains deferred |
| `auth/third/page` | Covered as protected read-only third-party user binding pagination |
| `auth/third/render`, `auth/third/callback` | Covered as controlled deferred routes; OAuth provider config, redirects, callback token issuance, and user binding remain deferred |

## Next Execution Order

1. merge-agent/api-agent: choose the next controlled-deferred wrapper group and write the module-specific transaction, permission, rollback, side-effect, and smoke-test plan before replacing it with real behavior.
2. test-agent/frontend-agent: keep using `docs/tasks/upload-provider-deferred-plan.md` for any additional render-only browser smoke on pages with upload, provider-send, or file-cleanup controls.
3. api-agent: plan cloud storage and optional physical-file cleanup separately through `docs/tasks/upload-provider-deferred-plan.md`; keep Aliyun/Tencent/Minio deferred until provider config is confirmed.
4. provider-agent: keep real Email before real SMS in the final provider phase.

## Guardrails

- Do not use this gap map as permission to add all missing routes at once.
- Do not add write routes without a module-specific plan, validation, transaction strategy, and test command list.
- Do not change database fields or delete compatibility fields.
- Do not modify `F:\AI\projects\testJava\OA`.
- Keep final online realtime data sync deferred until the full system is complete and the user confirms the data sync plan.
