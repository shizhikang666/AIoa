# Frontend API Gap Map

Date: 2026-06-03

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
| Current ThinkPHP routes | 259 | From `php think route:list` after CC-record read-only route additions |
| Endpoints already covered by route path | 253 | Includes read adapters and auth/system routes |
| Missing read/selector/report candidates | 85 | Priority candidates for safe compatibility work |
| Deferred write/side-effect candidates | 207 | Add/edit/delete/audit/import/export/workflow/finance/stock actions |

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
| `sys/menu` and `sys/resource` | Menu/resource tree and selector reads |
| `dev/config`, `dev/dict`, `dev/log` | Common management reads |
| `dev/file`, `dev/email`, `dev/sms`, `dev/job` | Metadata/list/detail reads; mutation routes remain deferred |
| `mobile/menu` and `mobile/resource` | Mobile menu/resource read compatibility |
| `gen/basic`, `gen/config`, `tenant` | Read-only compatibility routes |
| `biz/product`, `biz/supplier`, `biz/settlementaccount` | Core master-data read adapters |
| `biz/bizpaymentrecord`, `biz/bizexpenditurerecord`, `biz/bizdebitnote` | Finance read adapters |
| `biz/bizpurchaserequest`, `biz/bizpurchaseorder`, `biz/warehouses`, `biz/inventory`, `biz/delivery`, `biz/returnorder` | Purchase, warehouse, inventory, delivery, return read slices |
| `biz/saleprojectproductinfo` | Sale-project software package/version info reads |
| `biz/bizdatareport` | Sale-project amount/list/report, unpaid-payment, settlement income/expenses, sale-profit, summary-statistics, and details reads |
| `biz/bizleaveapplication` | Leave/business-trip page, my-page, and detail reads |
| `biz/settlementaccountpayment` | Settlement account statement page/list reads |
| `biz/bizpayroll` | Payroll page, my-page, and detail reads |
| `biz/teamproject`, `biz/task` | Team project and task read slices |
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
| `biz/saleproject` | Core read routes covered: `case/page`, `detail`, `list/detail`, `operation/page`, `page`, `product`, `public/page`, `cost`, `cost/details`; sale-project follow-up `page/detail` covered in `biz/saleprojectfollowup`; write routes remain deferred |
| `biz/customer` | `detail`, `detail/list`, `page` |
| `biz/org` | `detail`, `list`, `orgTreeSelector`, `page`, `tree`, `userSelector` |
| `biz/user` | `detail`, `list/detail`, `orgTreeSelector`, `ownRole`, `page`, `positionSelector`, `roleSelector`, `userSelector` |
| `biz/position` | `detail`, `list`, `orgTreeSelector`, `page`, `positionSelector` |
| `biz/dict` | `page`, `tree`, `treeAll` |
| `biz/process` | Read aliases added for `all/page`, `fileList`, `project/runtime/query/list`, `query`, and `query/list`; write/start/cancel routes remain deferred |
| `biz/task` | `runtime/activity/detail` added; `sse/stream`, `approve`, and `reject` remain deferred |
| `biz/ccrecords` | `page` and `detail` covered; `delete` remains deferred |
| `biz/bizdraft` | `detail` covered; `saleproject/add` remains deferred |
| `biz/saleprojectinvoicing` | `customer`, `detail`, `page` |
| `biz/saleprojectreissueorder` | `list/query` |
| `biz/saleprojectproductitemrelation` | `list` covered; `mark/edit` remains deferred |

## Deferred Write And Side-Effect Groups

The frontend contains many wrappers that should stay deferred until their modules are explicitly opened for write work.

| Group | Deferred Examples | Reason |
| --- | --- | --- |
| `biz/saleproject` | `add`, `edit`, `delete`, `amount/edit`, `deal/edit`, `cancel`, `history/add`, `special/add`, `visibility/edit` | Project state, finance, visibility, and history side effects |
| `biz/bizdraft` | `saleproject/add` | Draft save mutates sale-project draft state and needs validation/audit coverage |
| `biz/saleprojectfollowup` | `add`, `edit`, `delete` | Follow-up record and attachment metadata writes |
| `biz/saleprojectproductitemrelation` | `mark/edit` | Relation mark mutation |
| `biz/saleprojectproductitem` | `mark/edit` | Product item mark mutation |
| `biz/customer` | `add`, `edit`, `delete`, `head/edit` | Customer ownership and possibly encrypted fields |
| `biz/org`, `biz/user`, `biz/position` | `add`, `edit`, `delete`, grants, enable/disable, reset password | Permission and organization-state side effects |
| `biz/process` | `leave/start`, `payment/start`, `procure/start`, project start actions, `cancel` | Workflow runtime and business hooks |
| `biz/task` | `approve`, `reject` | Workflow transitions and audit records |
| `dev/file` | `upload*`, `delete` | Storage provider, file persistence, and cleanup strategy |
| `dev/message` | `send`, `delete`, `createSseConnect` | Messaging/SSE behavior must match Java expectations |
| `biz/bizpayroll`, `biz/bizleaveapplication`, `biz/saleprojectinvoicing`, `biz/saleprojectproductinfo` | `add`, `edit`, `delete`, import/generate/complete actions | Business validation and transactional side effects |
| `sys/user` and `sys/userCenter` | `import`, profile edits, password edits, grant actions | Security and audit requirements |

## Authentication And Session Gaps

The frontend still references several auth monitoring and third-party routes:

| Endpoint | Current Recommendation |
| --- | --- |
| `auth/session/b/exit` | Defer unless session-monitor pages become active |
| `auth/session/c/exit` | Defer unless client session pages become active |
| `auth/token/b/exit` | Defer unless token-monitor pages become active |
| `auth/token/c/exit` | Defer unless client token pages become active |
| `auth/third/page`, `auth/third/render`, `auth/third/callback` | Defer third-party login until provider config and security review |

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
