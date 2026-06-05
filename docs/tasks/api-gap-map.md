# Frontend API Gap Map

Date: 2026-06-05

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
| Current ThinkPHP routes | 322 | From `php think route:list` after customer base maintenance route addition |
| Endpoints already covered by route path | 309 | Includes read adapters, auth/system routes, and selected low-risk writes |
| Missing read/selector/report candidates | 69 | Priority candidates for safe compatibility work |
| Deferred write/side-effect candidates | 167 | Add/edit/delete/audit/import/export/workflow/finance/stock actions |

## Already Covered Route Groups

The current ThinkPHP project already covers these frontend-visible groups at least partially:

| Group | Current Coverage |
| --- | --- |
| `auth` | Login, logout, current user, token/session reads |
| `sys/index` | User info, menu, permissions, dashboard basics |
| `sys/org` | Tree, selector, page/detail style reads |
| `sys/user` | User page/detail/list style reads, selectors, own-role, own-resource, and own-permission grant echo reads |
| `sys/position` | Position page/list/detail/selector reads |
| `sys/role` | Role page/list/detail/resource/menu relation reads |
| `sys/menu`, `sys/field`, and `sys/resource` | Menu/resource tree, field page/tree/detail, and selector reads |
| `dev/config`, `dev/dict`, `dev/log` | Common management reads |
| `dev/file`, `dev/email`, `dev/sms`, `dev/job`, `dev/monitor` | Metadata/list/detail and monitor reads; mutation routes remain deferred |
| `mobile/menu` and `mobile/resource` | Mobile menu/resource read compatibility |
| `gen/basic`, `gen/config`, `tenant` | Read-only compatibility routes |
| `biz/product`, `biz/supplier`, `biz/settlementaccount` | Core master-data read adapters |
| `biz/bizpaymentrecord`, `biz/bizexpenditurerecord`, `biz/bizdebitnote` | Finance read adapters |
| `biz/bizpurchaserequest`, `biz/bizpurchaseorder`, `biz/warehouses`, `biz/inventory`, `biz/delivery`, `biz/returnorder` | Purchase, warehouse, inventory, delivery, return read slices |
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
| `biz/teamproject`, `biz/task` | Team project, task, task category, task user, project comment, project comment reply, task comment read/add/edit/delete slices, task base maintenance, task category maintenance, task assignee sync, and project comment/reply base write compatibility |
| `biz/process` | Basic workflow query/read slices |
| `biz/ccrecords` | Workflow copy/CC record page and detail reads |
| `biz/bizdraft` | Sale-project draft detail read |

## Priority 1: Visible Frontend Follow-Ups

These are the highest-priority follow-ups because they affect pages the user can already open after login.

| Area | Gap | Suggested Owner | Notes |
| --- | --- | --- | --- |
| Org/User visible tables | Some rows show blank fields or missing dictionary labels | frontend-agent with api-agent support | First confirm response field names before changing backend or frontend |
| Message SSE | Frontend components call `/dev/message/createSseConnect` | api-agent or workflow/test support | Review Java behavior before adding a safe compatibility route |
| Upload compatibility | Frontend expects many `dev/file/upload*ReturnFile*` routes | api-agent | Do not implement storage writes until storage strategy is confirmed |
| User profile center | Several `sys/userCenter/*` profile and password helpers are missing | user-agent | Password/profile writes need stricter validation and audit notes |
| Sys user grant dialogs | `ownRole`, `ownResource`, and `ownPermission` read echoes are covered | user-agent/frontend-agent | Grant save endpoints remain deferred |

## Priority 2: Safe Read-Only API Candidates

These groups should be handled before business writes, because they unlock more old frontend pages without creating side effects.

| Group | Missing Read/Selector/Report Candidates |
| --- | --- |
| `biz/saleproject` | Core read routes covered: `case/page`, `detail`, `list/detail`, `operation/page`, `page`, `product`, `public/page`, `cost`, `cost/details`; sale-project follow-up `page/detail/add/edit/delete` covered in `biz/saleprojectfollowup`; sale-project state/write routes remain deferred |
| `biz/salesprojectfieldchangelog` | `page`, `detail`, `add`, `edit`, and `delete` covered; sale-project change-generation side effects remain deferred |
| `biz/customer` | `add`, `edit`, `delete`, `detail`, `detail/list`, `page`; `head/edit` remains deferred |
| `biz/org` | `detail`, `list`, `orgTreeSelector`, `page`, `tree`, `userSelector` |
| `biz/user` | `detail`, `list/detail`, `orgTreeSelector`, `ownRole`, `page`, `positionSelector`, `roleSelector`, `userSelector` |
| `biz/position` | `detail`, `list`, `orgTreeSelector`, `page`, `positionSelector` |
| `biz/dict` | `page`, `tree`, `treeAll` |
| `biz/process` | Read aliases added for `all/page`, `fileList`, `project/runtime/query/list`, `query`, and `query/list`; write/start/cancel routes remain deferred |
| `biz/task` | `runtime/activity/detail` added; `sse/stream`, `approve`, and `reject` remain deferred |
| `biz/bizuservacation` | `page` and `detail` covered; `add`, `edit`, and `delete` remain deferred |
| `biz/ccrecords` | `page`, `detail`, and `delete` covered; add/edit and workflow copy-generation remain deferred |
| `biz/bizdraft` | `detail` covered; `saleproject/add` remains deferred |
| `biz/bizhistoryexcel` | `page`, `detail`, `add`, `edit`, and `delete` covered; import/export parsing and row-table storage remain deferred |
| `biz/saleprojectinvoicing` | `customer`, `detail`, `page` |
| `biz/saleprojectinvoiceItem` | `page` covered; invoice item writes remain deferred |
| `biz/projectrate` | `page`, `list`, `detail`, `add`, and `delete` covered; edit and file upload/storage remain deferred |
| `biz/saleprojectreissueorder` | `list/query` |
| `biz/saleprojectproductitemrelation` | `list` covered; `mark/edit` remains deferred |
| `biz/bizteamprojectcomment` | `page`, `list`, `detail`, `add`, and `delete` covered; notification/data-change side effects remain deferred |
| `biz/bizteamprojectcommentreply` | `page`, `detail`, `add`, `edit`, and `delete` covered |
| `biz/bizteamprojecttask` | `page`, `list`, `detail`, `add`, `edit`, `delete`, and `user/edit` covered; task logs, notification, data-change events, and full drag ordering remain deferred |
| `biz/bizteamprojecttaskcategory` | `page`, `list`, `detail`, `add`, `edit`, `sort/edit`, and `delete` covered; task moves, notification, and data-change side effects remain deferred |
| `biz/bizteamprojecttaskcomment` | `page`, `list`, `detail`, `add`, `edit`, and `delete` covered for `COMMENT` rows; notification, data-change side effects, and generated `LOG` maintenance remain deferred |
| `biz/bizteamprojecttaskuser` | `page` and `detail` covered; task-detail assignment sync is covered through `/biz/bizteamprojecttask/user/edit`; standalone `add`, `edit`, and `delete` remain deferred |
| `dev/monitor` | `serverInfo` and `networkInfo` covered |
| `sys/field` | `page`, `tree`, `detail`, and `MenuTreeSelector` covered; add/edit/delete remain deferred |
| `gen/basic` | `page`, `detail`, `tables`, `tableColumns`, and `mobileModuleSelector` covered; add/edit/delete/previewGen/execGen routes remain deferred |

## Deferred Write And Side-Effect Groups

The frontend contains many wrappers that should stay deferred until their modules are explicitly opened for write work.

| Group | Deferred Examples | Reason |
| --- | --- | --- |
| `biz/saleproject` | `add`, `edit`, `delete`, `amount/edit`, `deal/edit`, `cancel`, `history/add`, `special/add`, `visibility/edit` | Project state, finance, visibility, and history side effects |
| `biz/bizdraft` | `saleproject/add` | Draft save mutates sale-project draft state and needs validation/audit coverage |
| `biz/saleprojectfollowup` | File upload/storage cleanup, notifications | Add/edit/delete base record writes are covered; file and message side effects remain deferred |
| `biz/saleprojectproductitemrelation` | `mark/edit` | Relation mark mutation |
| `biz/saleprojectproductitem` | `mark/edit` | Product item mark mutation |
| `biz/customer` | `head/edit` | Customer ownership reassignment, SM4 plaintext search, file upload/storage, and related side effects |
| `biz/customerfollowup` | Attachment upload/storage cleanup, notifications | Add/edit/delete base record writes are covered; file side effects remain deferred |
| `biz/org`, `biz/user`, `biz/position` | `add`, `edit`, `delete`, grants, enable/disable, reset password | Permission and organization-state side effects |
| `biz/process` | `leave/start`, `payment/start`, `procure/start`, project start actions, `cancel` | Workflow runtime and business hooks |
| `biz/task` | `approve`, `reject` | Workflow transitions and audit records |
| `dev/file` | `upload*`, `delete` | Storage provider, file persistence, and cleanup strategy |
| `dev/message` | `send`, `delete`, `createSseConnect` | Messaging/SSE behavior must match Java expectations |
| `biz/bizpayroll`, `biz/bizleaveapplication`, `biz/saleprojectinvoicing` | `add`, `edit`, `delete`, import/generate/complete actions | Business validation and transactional side effects |
| `biz/saleprojectproductinfo` | Product master-data writes, sale-project product-item changes, import/export/report side effects | Add/edit/delete base package info writes are covered |
| `biz/salesprojectfieldchangelog` | Sale-project amount/change generation, workflow, finance, audit side effects | Add/edit/delete base log-row writes are covered |
| `biz/ccrecords` | `add`, `edit`, workflow copy-user delegate writes | Delete is covered as current-user logical delete; generation still belongs to workflow write runtime |
| `biz/teamproject comments and tasks` | notification push, data-change events, generated task logs, full task drag ordering, member role edit | Member add/manage-add/delete, comment/reply base writes, task add/edit/delete base rows, task comment add/edit/delete for `COMMENT` rows, task category add/edit/sort/delete, and task assignee sync are covered with member/resource-permission guards; push and data-change side effects remain deferred |
| `biz/bizuservacation` | `add`, `edit`, `delete`, generation/reduction helpers | Vacation balance writes affect leave workflow and payroll-facing data |
| `biz/bizhistoryexcel` | Import/export parsing, `biz_history_excel_row` writes | Base add/edit/delete writes are covered; parser/storage changes remain deferred |
| `biz/projectrate` | `edit`, image upload/storage cleanup | Add/delete base row writes are covered; Java controller does not expose edit in the current reference |
| `sys/user` and `sys/userCenter` | `import`, profile edits, password edits, grant actions | Security and audit requirements |
| `gen/basic` | `add`, `edit`, `delete`, `previewGen`, `execGenZip`, `execGenPro` | Generator writes or code generation output require a separate module plan |

## Authentication And Session Gaps

The frontend still references several auth monitoring and third-party routes:

| Endpoint | Current Recommendation |
| --- | --- |
| `auth/session/b/exit` | Defer unless session-monitor pages become active |
| `auth/session/c/exit` | Defer unless client session pages become active |
| `auth/token/b/exit` | Defer unless token-monitor pages become active |
| `auth/token/c/exit` | Defer unless client token pages become active |
| `auth/third/page` | Covered as protected read-only third-party user binding pagination |
| `auth/third/render`, `auth/third/callback` | Defer third-party login until provider config and security review |

## Next Execution Order

1. frontend-agent: document and fix visible org/user field-display and dictionary-label compatibility, with backend changes only if the route output shape is confirmed.
2. api-agent: add safe read-only `biz/saleproject` and `biz/customer` routes in small slices.
3. user-agent: add `biz/org`, `biz/user`, `biz/position`, and `biz/dict` selector/read aliases where they overlap with existing system data.
4. workflow-agent: review task SSE stream and workflow write actions only after the read-only workflow pages are stable.
5. api-agent: plan file upload compatibility after storage provider strategy is confirmed.
6. test-agent: turn the most-used frontend pages into repeatable backend plus frontend smoke checks.

## Guardrails

- Do not use this gap map as permission to add all missing routes at once.
- Do not add write routes without a module-specific plan, validation, transaction strategy, and test command list.
- Do not change database fields or delete compatibility fields.
- Do not modify `F:\AI\projects\testJava\OA`.
- Keep final online realtime data sync deferred until the full system is complete and the user confirms the data sync plan.
