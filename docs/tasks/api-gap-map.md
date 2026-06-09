# Frontend API Gap Map

Date: 2026-06-09

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
| Current ThinkPHP routes | 433 | Existing route-entry scan count plus `/mobile/button/add`, `/mobile/button/edit`, and `/mobile/button/delete`; `php think route:list` also confirms the concrete routes |
| Endpoints already covered by route path | 404 | Includes read adapters, auth/system routes, index schedule/message routes, user-center self-service routes, user grants, status switches, reset-password/delete compatibility, organization and position write compatibility, system module and button add/edit/delete compatibility, mobile button add/edit/delete compatibility, system user import/export download compatibility, LOCAL/dynamic file upload/delete compatibility, dev config `BIZ_DEFINE` maintenance compatibility, dev log category delete compatibility, dev job metadata delete compatibility, gen config edit-batch metadata saves, sale-project invoicing complete, dev email/SMS metadata delete compatibility, business file-relation binding/delete compatibility, cloud upload unsupported stubs, team-project base maintenance, and selected low-risk writes |
| Missing read/selector/report candidates | 69 | Priority candidates for safe compatibility work |
| Deferred write/side-effect candidates | 129 | Add/edit/audit/import/export/workflow/finance/stock actions; sys module/button add/edit/delete, mobile button add/edit/delete, dev config `BIZ_DEFINE` add/edit/delete, dev log category delete, dev job metadata delete, gen config editBatch, sale-project invoicing complete, and team-project base maintenance moved out of deferred scope |

## Already Covered Route Groups

The current ThinkPHP project already covers these frontend-visible groups at least partially:

| Group | Current Coverage |
| --- | --- |
| `auth` | Login, logout, current user, token/session reads |
| `sys/index` | User info, menu, permissions, dashboard basics |
| `sys/org` | Tree, selector, page/detail style reads plus base add/edit/delete |
| `sys/user` | User page/detail/list style reads, selectors, own-role, own-resource, own-permission, delete, grant saves, reset password, enable/disable status switches, and export/download blobs |
| `sys/position` | Position page/list/detail/selector reads plus base add/edit/delete |
| `sys/role` | Role page/list/detail/resource/menu relation reads |
| `sys/module`, `sys/menu`, `sys/button`, `sys/field`, and `sys/resource` | Module page/detail plus module add/edit/delete, menu/resource tree, button page/detail plus button add/edit/delete, field page/tree/detail, and selector reads |
| `dev/config`, `dev/dict`, `dev/log` | Common management reads plus dev config `BIZ_DEFINE` add/edit/delete, dev log category delete, and BIZ dictionary maintenance writes |
| `dev/file`, `dev/email`, `dev/sms`, `dev/job`, `dev/monitor` | File metadata/list/detail, LOCAL/dynamic upload, file/email/SMS/job metadata logical delete, public local-file download compatibility, cloud upload unsupported stubs, and monitor reads; real cloud storage, scheduler runtime, and provider send actions remain deferred |
| `mobile/menu`, `mobile/button`, and `mobile/resource` | Mobile menu/resource read compatibility plus mobile button add/edit/delete |
| `gen/basic`, `gen/config`, `tenant` | Generator metadata reads plus `gen/config/editBatch` field-configuration saves and tenant reads |
| `biz/product`, `biz/supplier`, `biz/settlementaccount` | Core master-data read adapters; product base add/edit/delete, kit relation maintenance, product status/reconciliation, and supplier base add/edit/delete are covered |
| `biz/bizpaymentrecord`, `biz/bizexpenditurerecord`, `biz/bizdebitnote` | Finance read adapters |
| `biz/bizpurchaserequest`, `biz/bizpurchaseorder`, `biz/warehouses`, `biz/inventory`, `biz/delivery`, `biz/returnorder` | Purchase, warehouse, inventory, delivery, return read slices; warehouse base add/edit/delete is covered |
| `biz/saleprojectproductinfo` | Sale-project software package/version info reads and base add/edit/delete writes |
| `biz/bizdatareport` | Sale-project amount/list/report, unpaid-payment, settlement income/expenses, sale-profit, summary-statistics, and details reads |
| `biz/projectrate` | Project rating page, list, and detail reads |
| `biz/bizleaveapplication` | Leave/business-trip page, my-page, and detail reads |
| `biz/bizuservacation` | Annual-leave/vacation page and current-year balance detail reads |
| `biz/settlementaccountpayment` | Settlement account statement page/list reads |
| `biz/bizpayroll` | Payroll page, my-page, and detail reads |
| `biz/bizhistoryexcel` | Historical EXCEL page and detail reads |
| `biz/saleprojectinvoiceItem` | Sale-project delivery invoice item page reads |
| `biz/salesprojectfieldchangelog` | Sale-project field change log page and detail reads |
| `biz/teamproject`, `biz/task` | Team project add/edit/delete, member add/manage/edit/delete, task, task category, task user, project comment, project comment reply, task comment read/add/edit/delete slices, task base maintenance, task category maintenance, task assignee sync, and project comment/reply base write compatibility |
| `biz/process` | Basic workflow query/read slices |
| `biz/ccrecords` | Workflow copy/CC record page and detail reads |
| `biz/bizdraft` | Sale-project draft detail read |

## Priority 1: Visible Frontend Follow-Ups

These are the highest-priority follow-ups because they affect pages the user can already open after login.

| Area | Gap | Suggested Owner | Notes |
| --- | --- | --- | --- |
| Org/User visible tables | Some rows show blank fields or missing dictionary labels | frontend-agent with api-agent support | First confirm response field names before changing backend or frontend |
| Message SSE | Frontend components call `/dev/message/createSseConnect` | api-agent or workflow/test support | Review Java behavior before adding a safe compatibility route |
| Upload compatibility | LOCAL/dynamic `dev/file/upload*`, `dev/file/delete`, and business attachment relation binding are covered | api-agent/test-agent | Real Aliyun/Tencent/Minio storage and physical file cleanup remain deferred |
| User profile center / homepage self-service | Current-user password, avatar, signature, profile, workbench, process-config edit, message detail mark-read, homepage all-mark-read, homepage schedule add/delete, and `/biz/user/center/edit` self-profile alias are covered | user-agent | Admin-side user CRUD/import/export and encrypted-field migration remain deferred |
| Sys/biz org, position, and user maintenance dialogs | `/sys/org/add`, `/sys/org/edit`, `/sys/org/delete`, `/biz/org/add`, `/biz/org/edit`, `/biz/org/delete`, `/sys/position/add`, `/sys/position/edit`, `/sys/position/delete`, `/biz/position/add`, `/biz/position/edit`, `/biz/position/delete`, `/sys/user/add`, `/sys/user/edit`, `/biz/user/add`, `/biz/user/edit`, `ownRole`, `ownResource`, `ownPermission`, `/sys/user/grantRole`, `/biz/user/grantRole`, `/sys/user/grantResource`, `/sys/user/grantPermission`, `/sys/user/delete`, `/biz/user/delete`, `/sys/user/disableUser`, `/sys/user/enableUser`, `/biz/user/disableUser`, `/biz/user/enableUser`, `/sys/user/resetPassword`, `/biz/user/resetPassword`, `/sys/user/downloadImportUserTemplate`, `/sys/user/import`, `/sys/user/export`, `/sys/user/exportUserInfo`, `/biz/user/export`, and `/biz/user/exportUserInfo` are covered | user-agent/frontend-agent | Business user import is absent in Java; real `.docx` rendering remains deferred |

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
| `biz/task` | `runtime/activity/detail` added; `sse/stream`, `approve`, and `reject` remain deferred |
| `biz/bizuservacation` | `page` and `detail` covered; `add`, `edit`, and `delete` remain deferred |
| `biz/ccrecords` | `page`, `detail`, and `delete` covered; add/edit and workflow copy-generation remain deferred |
| `biz/bizdraft` | `detail` covered; `saleproject/add` remains deferred |
| `biz/bizhistoryexcel` | `page`, `detail`, `add`, `edit`, and `delete` covered; import/export parsing and row-table storage remain deferred |
| `biz/saleprojectinvoicing` | `customer`, `detail`, `page`, and `complete` covered; add/edit/delete remain deferred |
| `biz/saleprojectinvoiceItem` | `page` covered; invoice item writes remain deferred |
| `biz/projectrate` | `page`, `list`, `detail`, `add`, and `delete` covered; edit and file upload/storage remain deferred |
| `biz/saleprojectreissueorder` | `list/query` |
| `biz/saleprojectproductitemrelation` | `list` and `mark/edit` covered |
| `biz/bizteamproject` | `page`, `detail`, `add`, `edit`, and `delete` covered; notification/data-change side effects remain deferred |
| `biz/bizteamprojectcomment` | `page`, `list`, `detail`, `add`, and `delete` covered; notification/data-change side effects remain deferred |
| `biz/bizteamprojectcommentreply` | `page`, `detail`, `add`, `edit`, and `delete` covered |
| `biz/bizteamprojecttask` | `page`, `list`, `detail`, `add`, `edit`, `delete`, and `user/edit` covered; task logs, notification, data-change events, and full drag ordering remain deferred |
| `biz/bizteamprojecttaskcategory` | `page`, `list`, `detail`, `add`, `edit`, `sort/edit`, and `delete` covered; task moves, notification, and data-change side effects remain deferred |
| `biz/bizteamprojecttaskcomment` | `page`, `list`, `detail`, `add`, `edit`, and `delete` covered for `COMMENT` rows; notification, data-change side effects, and generated `LOG` maintenance remain deferred |
| `biz/bizteamprojecttaskuser` | `page` and `detail` covered; task-detail assignment sync is covered through `/biz/bizteamprojecttask/user/edit`; standalone `add`, `edit`, and `delete` remain deferred |
| `dev/monitor` | `serverInfo` and `networkInfo` covered |
| `sys/field` | `page`, `tree`, `detail`, and `MenuTreeSelector` covered; add/edit/delete remain deferred |
| `gen/basic` and `gen/config` | `gen/basic/page`, `detail`, `tables`, `tableColumns`, `mobileModuleSelector`, `gen/config/list`, `detail`, and `editBatch` covered; generator add/edit/delete/previewGen/execGen routes remain deferred |

## Deferred Write And Side-Effect Groups

The frontend contains many wrappers that should stay deferred until their modules are explicitly opened for write work.

| Group | Deferred Examples | Reason |
| --- | --- | --- |
| `biz/saleproject` | `add`, `edit`, `delete`, `amount/edit`, `deal/edit`, `cancel`, `history/add`, `special/add`, `visibility/edit` | Project state, finance, visibility, and history side effects |
| `biz/bizdraft` | `saleproject/add` | Draft save mutates sale-project draft state and needs validation/audit coverage |
| `biz/saleprojectfollowup` | File upload/storage cleanup, notifications | Add/edit/delete base record writes are covered; file and message side effects remain deferred |
| `biz/saleprojectproductitemrelation` | Delivery/invoice/stock side effects | Relation `mark/edit` is covered |
| `biz/saleprojectproductitem` | Add/edit/delete, delivery/invoice/stock side effects | Product item `mark/edit` is covered |
| `biz/customer` | SM4 plaintext search, file upload/storage, and related side effects | Customer base add/edit/delete and `head/edit` are covered |
| `biz/customerfollowup` | Attachment upload/storage cleanup, notifications | Add/edit/delete base record writes are covered; file side effects remain deferred |
| `biz/org`, `biz/user`, `biz/position`, `biz/dict` | business user import, real Office export rendering, resource/permission grants, business dictionary add/delete | `/biz/org/add`, `/biz/org/edit`, `/biz/org/delete`, `/biz/position/add`, `/biz/position/edit`, `/biz/position/delete`, `/biz/user/add`, `/biz/user/edit`, `/biz/user/center/edit`, `/biz/user/delete`, `/biz/user/grantRole`, `/biz/user/disableUser`, `/biz/user/enableUser`, `/biz/user/resetPassword`, `/biz/user/export`, `/biz/user/exportUserInfo`, and `/biz/dict/edit` are covered; `/dev/dict/add|edit|delete` cover BIZ maintenance; `/sys/user/import` covers Java system user import; business user import remains intentionally absent like Java; real `.docx` rendering and business dictionary add/delete remain deferred |
| `biz/process` | `leave/start`, `payment/start`, `procure/start`, project start actions, `cancel` | Workflow runtime and business hooks |
| `biz/task` | `approve`, `reject` | Workflow transitions and audit records |
| `dev/file` | cloud `upload*`, physical file cleanup | LOCAL/dynamic upload, public local download, metadata logical delete, and business relation binding are covered; cloud storage and optional physical cleanup still need dedicated plans |
| `dev/config` | `editBatch`, `SYS_BASE` writes, provider/system config cache mutation | `BIZ_DEFINE` add/edit/delete are covered with Java-style success envelopes, malformed delete payload rejection, sensitive-mask preservation, and logical delete |
| `dev/log` | Cross-tenant/global clear behavior | Category delete is covered with physical deletion and current-tenant protection when the token payload has a tenant id; Java clears globally by category |
| `dev/job` | `add`, `edit`, `stopJob`, `runJob`, `runJobNow`, scheduler lifecycle | Metadata delete is covered as logical delete with malformed-payload protection; scheduler stop/remove behavior remains deferred until a ThinkPHP scheduler exists |
| `dev/message` | Full realtime push | User-center/index detail mark-read, homepage all-mark-read, minimal SSE compatibility, message send, and message delete are covered; full SSE/WebPush parity remains deferred |
| `biz/bizpayroll`, `biz/bizleaveapplication`, `biz/saleprojectinvoicing` | payroll/leave `add`, `edit`, `delete`, import/generate actions; invoicing add/edit/delete | Business validation and transactional side effects; invoicing complete is covered as a single-field marker |
| `biz/saleprojectproductinfo` | Product master-data writes, sale-project product-item changes, import/export/report side effects | Add/edit/delete base package info writes are covered |
| `biz/bizproduct` | Inventory, purchase, sale-project, finance transaction, workflow, file upload/storage, and data-change/cache side effects | Product base add/edit/delete, kit relation maintenance, status toggle, and reconciliation edits are covered |
| `biz/salesprojectfieldchangelog` | Sale-project amount/change generation, workflow, finance, audit side effects | Add/edit/delete base log-row writes are covered |
| `biz/ccrecords` | `add`, `edit`, workflow copy-user delegate writes | Delete is covered as current-user logical delete; generation still belongs to workflow write runtime |
| `biz/teamproject comments and tasks` | notification push, data-change events, generated task logs, full task drag ordering, member role edit | Member add/manage-add/delete, comment/reply base writes, task add/edit/delete base rows, task comment add/edit/delete for `COMMENT` rows, task category add/edit/sort/delete, and task assignee sync are covered with member/resource-permission guards; push and data-change side effects remain deferred |
| `biz/bizuservacation` | `add`, `edit`, `delete`, generation/reduction helpers | Vacation balance writes affect leave workflow and payroll-facing data |
| `biz/bizhistoryexcel` | Import/export parsing, `biz_history_excel_row` writes | Base add/edit/delete writes are covered; parser/storage changes remain deferred |
| `biz/projectrate` | `edit`, image upload/storage cleanup | Add/delete base row writes are covered; Java controller does not expose edit in the current reference |
| `sys/user`, `sys/userCenter`, and `sys/index` | real `.docx` rendering and encrypted-field migration | Current-user profile/password/workbench/process-config writes, admin-side user add/edit/import, user delete, user role/resource/permission grant saves, user enable/disable, admin reset password, homepage schedule, message read-state writes, and user export/download blobs are covered |
| `gen/basic` and generator execution | `gen/basic/add`, `edit`, `delete`, `previewGen`, `execGenZip`, `execGenPro`, direct `/gen/config/edit`, `/gen/config/delete` | Generator basic-row writes, direct config single-row writes, delete, preview, and code generation output require a separate module plan; `/gen/config/editBatch` is covered for saved field metadata |

## Authentication And Session Gaps

The frontend still references several auth monitoring and third-party routes:

| Endpoint | Current Recommendation |
| --- | --- |
| `auth/session/b/exit` | Covered by auth-agent session/token exit compatibility with cache-backed B-side token index |
| `auth/session/c/exit` | Covered as success-compatible C-side no-op until client auth is implemented |
| `auth/token/b/exit` | Covered by auth-agent token exit compatibility for indexed B-side bearer tokens |
| `auth/token/c/exit` | Covered as success-compatible C-side no-op until client auth is implemented |
| `auth/third/page` | Covered as protected read-only third-party user binding pagination |
| `auth/third/render`, `auth/third/callback` | Defer third-party login until provider config and security review |

## Next Execution Order

1. frontend-agent: document and fix visible org/user field-display and dictionary-label compatibility, with backend changes only if the route output shape is confirmed.
2. api-agent: add safe read-only `biz/saleproject` and `biz/customer` routes in small slices.
3. user-agent: add `biz/org`, `biz/user`, `biz/position`, and `biz/dict` selector/read aliases where they overlap with existing system data.
4. workflow-agent: review task SSE stream and workflow write actions only after the read-only workflow pages are stable.
5. test-agent/frontend-agent: browser-smoke copied upload controls now that `/dev/file/upload*` and `/biz/bizfilerelation/add` are both covered.
6. api-agent: plan cloud storage and optional physical-file cleanup separately; keep Aliyun/Tencent/Minio deferred until provider config is confirmed.

## Guardrails

- Do not use this gap map as permission to add all missing routes at once.
- Do not add write routes without a module-specific plan, validation, transaction strategy, and test command list.
- Do not change database fields or delete compatibility fields.
- Do not modify `F:\AI\projects\testJava\OA`.
- Keep final online realtime data sync deferred until the full system is complete and the user confirms the data sync plan.
