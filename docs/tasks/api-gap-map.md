# Frontend API Gap Map

Date: 2026-06-15

Agent: frontend-agent / main control agent

## Scope

This map compares the copied Vue API wrappers under `snowy-admin-web/src/api` with the current ThinkPHP route table.

It is a planning aid for the next api-agent, user-agent, workflow-agent, and frontend-agent slices. It does not mean every frontend wrapper should be implemented immediately. Write-heavy endpoints, workflow actions, finance/warehouse side effects, imports, exports, deletes, and production data sync remain deferred until each module has a confirmed design and test plan.

The Java source project at `F:\AI\projects\testJava\OA` remains read-only.

## Scan Summary

| Item | Count | Notes |
| --- | ---: | --- |
| Frontend API wrapper files | 76 | From `snowy-admin-web/src/api` |
| Frontend endpoint references | 547 | Raw wrapper calls found by static scan |
| Unique frontend endpoints | 545 | Normalized path strings |
| Current ThinkPHP routes | 481 | `php think route:list` concrete route count after public auth phone-code/WebPush deferred stubs, public third-party auth deferred stubs, public password-recovery deferred stubs, public password-recovery captcha, SMS provider-send deferred wrappers, project-rate edit, role grant-save routes, role add/edit/delete, system process-config edit, settlement-account base maintenance, collection-receipt mark-success, debit-note mark-success, payroll edit/batch-edit/delete, payroll import-template download, leave-application edit/delete, sale-project draft save, generator preview, and generator ZIP download |
| Endpoints already covered by route path | 453 | Includes read adapters, auth/system routes, public auth phone-code/WebPush controlled-deferred routes, public third-party auth controlled-deferred routes, public password-recovery controlled-deferred routes, protected SMS provider-send controlled-deferred routes, index schedule/message routes, sys process-config edit, settlement-account add/edit/status, collection-receipt mark-success, debit-note mark-success, payroll edit/batch-edit/delete, payroll import-template download, leave-application edit/delete, sale-project draft save, generator preview and ZIP download, user-center self-service routes, user and role grants, role add/edit/delete, status switches, reset-password/delete compatibility, organization and position write compatibility, system module/menu/button/field write compatibility, mobile module/menu/button write compatibility, system user import/export download compatibility, LOCAL/dynamic file upload/delete compatibility, dev config `BIZ_DEFINE` maintenance compatibility, dev log category delete compatibility, dev job metadata delete compatibility, gen config edit-batch metadata saves, sale-project invoicing complete, dev email/SMS metadata delete compatibility, business file-relation binding/delete compatibility, cloud upload unsupported stubs, team-project base maintenance, and selected low-risk writes |
| Missing read/selector/report candidates | 67 | Priority candidates for safe compatibility work |
| Deferred write/side-effect candidates | 92 | Add/edit/audit/import/export/workflow/finance/stock actions; sys process-config edit, settlement-account add/edit/status, collection-receipt mark-success, debit-note mark-success, payroll edit/batch-edit/delete, payroll import-template download, leave-application edit/delete, sale-project draft save, generator preview and ZIP download, sys role grant saves, sys role add/edit/delete, sys module/menu/button/field write compatibility, mobile module/menu/button write compatibility, dev config `BIZ_DEFINE` add/edit/delete, dev log category delete, dev job metadata delete, gen config editBatch, sale-project invoicing complete, project-rate edit, and team-project base maintenance moved out of deferred scope |

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
| `dev/config`, `dev/dict`, `dev/log` | Common management reads plus dev config `BIZ_DEFINE` add/edit/delete, dev log category delete, and BIZ dictionary maintenance writes |
| `dev/file`, `dev/email`, `dev/sms`, `dev/job`, `dev/monitor` | File metadata/list/detail, LOCAL/dynamic upload, file/email/SMS/job metadata logical delete, protected SMS send controlled-deferred wrappers, public local-file download compatibility, cloud upload unsupported stubs, and monitor reads; real cloud storage, scheduler runtime, real email/SMS provider sends, and provider actions remain deferred |
| `mobile/module`, `mobile/menu`, `mobile/button`, and `mobile/resource` | Mobile menu/resource read compatibility plus mobile module add/edit/delete, mobile menu add/edit/changeModule/delete, and mobile button add/edit/delete |
| `gen/basic`, `gen/config`, `tenant` | Generator metadata reads plus `gen/config/editBatch` field-configuration saves and tenant reads |
| `biz/product`, `biz/supplier`, `biz/settlementaccount` | Core master-data read adapters; product base add/edit/delete, kit relation maintenance, product status/reconciliation, supplier base add/edit/delete, and settlement-account add/edit/status are covered |
| `biz/bizpaymentrecord`, `biz/bizexpenditurerecord`, `biz/bizcollectionreceipt`, `biz/bizdebitnote` | Finance read adapters plus collection-receipt and debit-note mark-success |
| `biz/bizpurchaserequest`, `biz/bizpurchaseorder`, `biz/warehouses`, `biz/inventory`, `biz/delivery`, `biz/returnorder` | Purchase, warehouse, inventory, delivery, return read slices; warehouse base add/edit/delete is covered |
| `biz/saleprojectproductinfo` | Sale-project software package/version info reads and base add/edit/delete writes |
| `biz/bizdatareport` | Sale-project amount/list/report, unpaid-payment, settlement income/expenses, sale-profit, summary-statistics, and details reads |
| `biz/projectrate` | Project rating page, list, and detail reads |
| `biz/bizleaveapplication` | Leave/business-trip page, my-page, detail, edit, and logical delete |
| `biz/bizuservacation` | Annual-leave/vacation page and current-year balance detail reads |
| `biz/settlementaccountpayment` | Settlement account statement page/list reads |
| `biz/bizpayroll` | Payroll page, my-page, detail reads, import-template download, edit, batch edit, and logical delete |
| `biz/bizhistoryexcel` | Historical EXCEL page and detail reads |
| `biz/saleprojectinvoiceItem` | Sale-project delivery invoice item page reads |
| `biz/salesprojectfieldchangelog` | Sale-project field change log page and detail reads |
| `biz/teamproject`, `biz/task` | Team project add/edit/delete, member add/manage/edit/delete, task, task category, task user, project comment, project comment reply, task comment read/add/edit/delete slices, task base maintenance, task category maintenance, task assignee sync, and project comment/reply base write compatibility |
| `biz/process` | Basic workflow query/read slices |
| `biz/ccrecords` | Workflow copy/CC record page and detail reads |
| `biz/bizdraft` | Sale-project draft detail read and draft save |

## Priority 1: Visible Frontend Follow-Ups

These are the highest-priority follow-ups because they affect pages the user can already open after login.

| Area | Gap | Suggested Owner | Notes |
| --- | --- | --- | --- |
| Org/User visible tables | Verified by `scripts/user-display-http-smoke.ps1` | frontend-agent with api-agent support | Authenticated smoke confirms sys/biz user page/detail/list/detail/userSelector rows expose `orgName`, `positionName`, `genderName`, selector aliases, Java-style paging keys, and no `PASSWORD`; keep browser spot-check for future UI regressions |
| Message SSE | Frontend components call `/dev/message/createSseConnect` | api-agent or workflow/test support | Review Java behavior before adding a safe compatibility route |
| Upload compatibility | LOCAL/dynamic `dev/file/upload*`, `dev/file/delete`, and business attachment relation binding are covered | api-agent/test-agent | Real Aliyun/Tencent/Minio storage and physical file cleanup remain deferred |
| User profile center / homepage self-service | Current-user password, avatar, signature, profile, workbench, process-config edit, message detail mark-read, homepage all-mark-read, homepage schedule add/delete, and `/biz/user/center/edit` self-profile alias are covered | user-agent | Admin-side user CRUD/import/export and encrypted-field migration remain deferred |
| Sys/biz org, position, user, and role maintenance dialogs | `/sys/org/add`, `/sys/org/edit`, `/sys/org/delete`, `/biz/org/add`, `/biz/org/edit`, `/biz/org/delete`, `/sys/position/add`, `/sys/position/edit`, `/sys/position/delete`, `/biz/position/add`, `/biz/position/edit`, `/biz/position/delete`, `/sys/user/add`, `/sys/user/edit`, `/biz/user/add`, `/biz/user/edit`, `ownRole`, `ownResource`, `ownPermission`, `/sys/user/grantRole`, `/biz/user/grantRole`, `/sys/user/grantResource`, `/sys/user/grantPermission`, `/sys/role/add`, `/sys/role/edit`, `/sys/role/delete`, `/sys/role/grantResource`, `/sys/role/grantMobileMenu`, `/sys/role/grantPermission`, `/sys/role/grantUser`, `/sys/user/delete`, `/biz/user/delete`, `/sys/user/disableUser`, `/sys/user/enableUser`, `/biz/user/disableUser`, `/biz/user/enableUser`, `/sys/user/resetPassword`, `/biz/user/resetPassword`, `/sys/user/downloadImportUserTemplate`, `/sys/user/import`, `/sys/user/export`, `/sys/user/exportUserInfo`, `/biz/user/export`, and `/biz/user/exportUserInfo` are covered | user-agent/frontend-agent | Business user import and real `.docx` rendering remain deferred |

## Priority 2: Safe Read-Only API Candidates

These groups should be handled before business writes, because they unlock more old frontend pages without creating side effects.

| Group | Missing Read/Selector/Report Candidates |
| --- | --- |
| `biz/saleproject` | Core read routes covered: `case/page`, `detail`, `list/detail`, `operation/page`, `page`, `product`, `public/page`, `cost`, `cost/details`; sale-project follow-up `page/detail/add/edit/delete` covered in `biz/saleprojectfollowup`; sale-project state/write routes remain deferred |
| `biz/salesprojectfieldchangelog` | `page`, `detail`, `add`, `edit`, and `delete` covered; sale-project change-generation side effects remain deferred |
| `biz/customer` | `add`, `edit`, `delete`, `detail`, `detail/list`, `page`, and `head/edit` covered |
| `biz/org` | `detail`, `list`, `orgTreeSelector`, `page`, `tree`, `userSelector`, `add`, `edit`, and `delete` |
| `biz/user` | `detail`, `list/detail`, `orgTreeSelector`, `ownRole`, `page`, `positionSelector`, `roleSelector`, `userSelector`, `disableUser`, `enableUser`, `export`, and `exportUserInfo` |
| `biz/position` | `detail`, `list`, `orgTreeSelector`, `page`, `positionSelector`, `add`, `edit`, and `delete` |
| `biz/dict` | `page`, `tree`, `treeAll`, and Java-compatible `edit` covered; business add/delete remain intentionally absent like Java |
| `biz/process` | Read aliases added for `all/page`, `fileList`, `project/runtime/query/list`, `query`, and `query/list`; write/start/cancel routes remain deferred |
| `biz/task` | `runtime/activity/detail` added; standalone `sse/stream`, `approve`, and `reject` remain deferred. Java `BizTaskController` does not currently expose `/biz/task/sse/stream`, and no active copied frontend caller was found; layout task refresh is covered through `/dev/message/createSseConnect` `FlushProcessNotice` |
| `biz/bizuservacation` | `page` and `detail` covered; `add`, `edit`, and `delete` remain deferred |
| `biz/ccrecords` | `page`, `detail`, and `delete` covered; add/edit and workflow copy-generation remain deferred |
| `biz/bizdraft` | `detail` and `saleproject/add` covered |
| `biz/bizhistoryexcel` | `page`, `detail`, `add`, `edit`, and `delete` covered; import/export parsing and row-table storage remain deferred |
| `biz/saleprojectinvoicing` | `customer`, `detail`, `page`, and `complete` covered; add/edit/delete remain deferred |
| `biz/saleprojectinvoiceItem` | `page` covered; invoice item writes remain deferred |
| `biz/projectrate` | `page`, `list`, `detail`, `add`, `edit`, and `delete` covered; file upload/storage remains deferred |
| `biz/saleprojectreissueorder` | `list/query` covered; reissue-order writes remain deferred |
| `biz/saleprojectproductitemrelation` | `list` and `mark/edit` covered |
| `biz/bizteamproject` | `page`, `detail`, `add`, `edit`, and `delete` covered; notification/data-change side effects remain deferred |
| `biz/bizteamprojectcomment` | `page`, `list`, `detail`, `add`, and `delete` covered; notification/data-change side effects remain deferred |
| `biz/bizteamprojectcommentreply` | `page`, `detail`, `add`, `edit`, and `delete` covered |
| `biz/bizteamprojecttask` | `page`, `list`, `detail`, `add`, `edit`, `delete`, and `user/edit` covered; task logs, notification, data-change events, and full drag ordering remain deferred |
| `biz/bizteamprojecttaskcategory` | `page`, `list`, `detail`, `add`, `edit`, `sort/edit`, and `delete` covered; task moves, notification, and data-change side effects remain deferred |
| `biz/bizteamprojecttaskcomment` | `page`, `list`, `detail`, `add`, `edit`, and `delete` covered for `COMMENT` rows; notification, data-change side effects, and generated `LOG` maintenance remain deferred |
| `biz/bizteamprojecttaskuser` | `page` and `detail` covered; task-detail assignment sync is covered through `/biz/bizteamprojecttask/user/edit`; standalone `add`, `edit`, and `delete` remain deferred |
| `dev/monitor` | `serverInfo` and `networkInfo` covered |
| `sys/field` | `page`, `tree`, `detail`, `MenuTreeSelector`, `add`, `edit`, and `delete` covered |
| `gen/basic` and `gen/config` | `gen/basic/page`, `detail`, `previewGen`, `execGenZip`, `tables`, `tableColumns`, `mobileModuleSelector`, `gen/config/list`, `detail`, and `editBatch` covered; generator add/edit/delete and direct project generation remain deferred |

## Deferred Write And Side-Effect Groups

The frontend contains many wrappers that should stay deferred until their modules are explicitly opened for write work.

| Group | Deferred Examples | Reason |
| --- | --- | --- |
| `biz/saleproject` | `add`, `edit`, `delete`, `amount/edit`, `deal/edit`, `cancel`, `history/add`, `special/add`, `visibility/edit` | Project state, finance, visibility, and history side effects |
| `biz/bizdraft` | Sale-project workflow submission and real project writes | Draft save is covered as isolated `biz_draft` persistence; formal sale-project add/edit and workflow side effects remain deferred |
| `biz/saleprojectfollowup` | File upload/storage cleanup, notifications | Add/edit/delete base record writes are covered; file and message side effects remain deferred |
| `biz/saleprojectproductitemrelation` | Delivery/invoice/stock side effects | Relation `mark/edit` is covered |
| `biz/saleprojectproductitem` | Add/edit/delete, delivery/invoice/stock side effects | Product item `mark/edit` is covered |
| `biz/customer` | SM4 plaintext search, file upload/storage, and related side effects | Customer base add/edit/delete and `head/edit` are covered |
| `biz/customerfollowup` | Attachment upload/storage cleanup, notifications | Add/edit/delete base record writes are covered; file side effects remain deferred |
| `biz/org`, `biz/user`, `biz/position`, `biz/dict` | business user import, real Office export rendering, resource/permission grants, business dictionary add/delete | `/biz/org/add`, `/biz/org/edit`, `/biz/org/delete`, `/biz/position/add`, `/biz/position/edit`, `/biz/position/delete`, `/biz/user/add`, `/biz/user/edit`, `/biz/user/center/edit`, `/biz/user/delete`, `/biz/user/grantRole`, `/biz/user/disableUser`, `/biz/user/enableUser`, `/biz/user/resetPassword`, `/biz/user/export`, `/biz/user/exportUserInfo`, and `/biz/dict/edit` are covered; `/dev/dict/add|edit|delete` cover BIZ maintenance; `/sys/user/import` covers Java system user import; business user import remains intentionally absent like Java; real `.docx` rendering and business dictionary add/delete remain deferred |
| `biz/process` | `leave/start`, `payment/start`, `procure/start`, project start actions, `cancel` | Workflow runtime and business hooks |
| `biz/task` | `approve`, `reject`, standalone `sse/stream` if a real caller appears | Workflow transitions and audit records; current layout task refresh uses `/dev/message/createSseConnect` |
| `dev/file` | cloud `upload*`, physical file cleanup | LOCAL/dynamic upload, public local download, metadata logical delete, and business relation binding are covered; cloud storage and optional physical cleanup still need dedicated plans |
| `biz/settlementaccount` | `delete`, `expenses/add`, `payment/add`, `transfer/add` | Settlement-account base add/edit/status is covered; delete is absent in Java controller and quick income/expense/transfer mutate account balance, statements, and payment/expenditure records |
| `dev/config` | `editBatch`, `SYS_BASE` writes, provider/system config cache mutation | `BIZ_DEFINE` add/edit/delete are covered with Java-style success envelopes, malformed delete payload rejection, sensitive-mask preservation, and logical delete |
| `dev/log` | Cross-tenant/global clear behavior | Category delete is covered with physical deletion and current-tenant protection when the token payload has a tenant id; Java clears globally by category |
| `dev/sms` | Real `sendAliyun`, `sendTencent`, and `sendXiaonuo` provider calls | Routes are covered as protected controlled-deferred wrappers; provider credential reads, SDK calls, external sends, and send-record writes remain deferred |
| `dev/job` | `add`, `edit`, `stopJob`, `runJob`, `runJobNow`, scheduler lifecycle | Metadata delete is covered as logical delete with malformed-payload protection; scheduler stop/remove behavior remains deferred until a ThinkPHP scheduler exists |
| `dev/message` | Full realtime push | User-center/index detail mark-read, homepage all-mark-read, minimal SSE compatibility with initial message/process refresh notices, message send, and message delete are covered; full SSE/WebPush parity remains deferred |
| `biz/bizpayroll`, `biz/bizleaveapplication`, `biz/saleprojectinvoicing` | payroll add, import/export, and generate actions; leave add; invoicing add/edit/delete | Payroll import-template download, edit/batch-edit/delete, leave-application edit/delete, and invoicing complete are covered; generation, import/export, leave balance, workflow, and broader business side effects remain deferred |
| `biz/saleprojectproductinfo` | Product master-data writes, sale-project product-item changes, import/export/report side effects | Add/edit/delete base package info writes are covered |
| `biz/bizproduct` | Inventory, purchase, sale-project, finance transaction, workflow, file upload/storage, and data-change/cache side effects | Product base add/edit/delete, kit relation maintenance, status toggle, and reconciliation edits are covered |
| `biz/salesprojectfieldchangelog` | Sale-project amount/change generation, workflow, finance, audit side effects | Add/edit/delete base log-row writes are covered |
| `biz/ccrecords` | `add`, `edit`, workflow copy-user delegate writes | Delete is covered as current-user logical delete; generation still belongs to workflow write runtime |
| `biz/teamproject comments and tasks` | notification push, data-change events, generated task logs, full task drag ordering, member role edit | Member add/manage-add/delete, comment/reply base writes, task add/edit/delete base rows, task comment add/edit/delete for `COMMENT` rows, task category add/edit/sort/delete, and task assignee sync are covered with member/resource-permission guards; push and data-change side effects remain deferred |
| `biz/bizuservacation` | `add`, `edit`, `delete`, generation/reduction helpers | Vacation balance writes affect leave workflow and payroll-facing data |
| `biz/bizhistoryexcel` | Import/export parsing, `biz_history_excel_row` writes | Base add/edit/delete writes are covered; parser/storage changes remain deferred |
| `biz/projectrate` | image upload/storage cleanup | Add/edit/delete base row writes are covered; edit updates only the rating row and preserves submitted `imgList` in `EXT_JSON` |
| `sys/user`, `sys/userCenter`, and `sys/index` | real `.docx` rendering, encrypted-field migration, password-recovery SMS/email/reset flow | Current-user profile/password/workbench/process-config writes, public password-recovery captcha, admin-side user add/edit/import, user delete, user role/resource/permission grant saves, user enable/disable, admin reset password, homepage schedule, message read-state writes, and user export/download blobs are covered |
| `gen/basic` and generator execution | `gen/basic/add`, `edit`, `delete`, `execGenPro`, direct `/gen/config/edit`, `/gen/config/delete` | Generator preview, ZIP download, and `/gen/config/editBatch` are covered; basic-row writes, direct config single-row writes, delete, and direct project output require a separate module plan |

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

1. api-agent: add safe read-only `biz/saleproject` and `biz/customer` routes in small slices.
2. user-agent: keep `biz/org`, `biz/user`, `biz/position`, and `biz/dict` selector/read aliases under smoke coverage where they overlap with existing system data.
3. workflow-agent: review approve/reject and workflow write actions only after the read-only workflow pages are stable; revisit standalone task SSE only if a real Java route or active frontend caller appears.
4. test-agent/frontend-agent: browser-smoke copied upload controls now that `/dev/file/upload*` and `/biz/bizfilerelation/add` are both covered.
5. api-agent: plan cloud storage and optional physical-file cleanup separately; keep Aliyun/Tencent/Minio deferred until provider config is confirmed.

## Guardrails

- Do not use this gap map as permission to add all missing routes at once.
- Do not add write routes without a module-specific plan, validation, transaction strategy, and test command list.
- Do not change database fields or delete compatibility fields.
- Do not modify `F:\AI\projects\testJava\OA`.
- Keep final online realtime data sync deferred until the full system is complete and the user confirms the data sync plan.
