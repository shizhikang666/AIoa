# Test Agent Smoke Runbook

## Purpose

This runbook turns the repeated manual post-slice checks into a single test-agent command.

The script is intended for the integrated ThinkPHP project at:

`F:\AI\projects\testJava\OA-ThinkPHP`

It does not modify Java source, `.env`, database schema, Composer dependencies, or application behavior.

Future new Codex conversations should treat the main conversation as the merge/coordinator session and use real scoped worker Agents by default. `test-agent` owns smoke checks, syntax checks, route checks, namespace checks, Composer checks, and test documentation inside the explicit task scope. It should not take over frontend, API, docs, or merge/coordinator work unless the user assigns that scope.

## Script

`scripts/test-agent-smoke.ps1`

DB/Redis/export smoke script:

`scripts/test-agent-db-smoke.ps1`

## Baseline Command

```powershell
.\scripts\test-agent-smoke.ps1
```

The baseline command runs:

- `composer dump-autoload`
- `php think`
- `php think route:list`
- required route coverage checks for current frontend-visible personnel, message SSE, and biz directory aliases
- PHP syntax lint for `app`, `config`, and `route`
- frontend API method smoke for missing read-like imported API methods
- `git diff --check`

## DB Smoke Command

After the local runtime services are started and the ignored `.env` contains the local credentials, run:

```powershell
.\scripts\test-agent-db-smoke.ps1
```

The DB smoke command reads the ignored local `.env`, uses the bundled MySQL and Redis clients, and does not print passwords. It verifies:

- `phpoa20026` has application tables
- Redis responds to `PING`
- `UserDirectoryService::exportUsers(false, ...)` returns a valid CSV download descriptor
- `UserDirectoryService::exportUsers(true, ...)` returns a valid CSV download descriptor
- `UserDirectoryService::exportUserInfoFile(...)` returns a valid text download descriptor
- sampled export content does not include `PASSWORD`
- `DevFileService` local download, upload, tenant-scoped logical delete, and no physical delete behavior
- `DevEmailService` and `DevSmsService` tenant-scoped logical delete behavior
- `DevConfigService` `BIZ_DEFINE` add/edit/delete behavior, duplicate-key rejection, sensitive mask preservation, `SYS_BASE` delete rejection, and logical delete behavior
- `DevLogService` category physical delete behavior, tenant isolation, other-category preservation, and empty-category rejection
- `DevJobService` Java-style array delete behavior, malformed payload safety, logical delete behavior, and active-page hiding
- `GenConfigService` Java-style `editBatch` behavior, whitelist writes, optional-field nulling, deleted-row rejection, and failed-batch rollback safety
- `SaleProjectBillingService` invoicing complete behavior, tenant-scoped row lookup, idempotent state update, and cross-tenant rejection
- local file upload plus `BizFileRelationService` add/list/edit/delete behavior
- file-relation category validation, missing-file rejection, tenant spoofing rejection, and logical delete without deleting `dev_file`
- `ResourceService` module add/edit/delete behavior, duplicate-title rejection, built-in module delete rejection path coverage through service logic, child menu/button/field logical delete, and role-resource relation cleanup
- `ResourceService` button add/edit/delete behavior, duplicate-code rejection, logical delete, and role-resource `buttonInfo` cleanup
- `ResourceService` field add/edit/delete behavior, sibling duplicate-code rejection, menu-parent validation, logical delete tolerance for mixed missing ids, direct role-resource relation cleanup, and parent module/menu FIELD cascade coverage through the module/menu smoke steps
- `MobileResourceService` module add/edit/delete behavior, generated 10-character code, duplicate-title rejection, module/menu logical delete, mixed missing-id delete tolerance, and role mobile-menu relation cleanup
- `MobileResourceService` button add/edit/delete behavior, duplicate-code rejection, logical delete, mixed missing-id delete tolerance, and role mobile-menu `buttonInfo` cleanup
- `TeamProjectService` base add/edit/delete behavior, automatic current-user `LEADER` member creation, member edit audit refresh without role/permission mutation, project permission relation sync, version increment, and project/member logical delete

## Optional Backend No-Token Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -NoTokenSmoke
```

The optional smoke checks current protected download routes and expects an unauthenticated business response with `code = 401`.

## Optional Dev File HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevFileHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, uploads a temporary local file, calls `/dev/file/delete` with Java-style JSON array body, verifies `dev_file.DELETE_FLAG = DELETED`, verifies the physical uploaded file remains until cleanup, and then removes the temporary database and disk rows. It does not print tokens or local credentials.

## Optional Dev Email/SMS HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevEmailSmsHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, inserts temporary `dev_email` and `dev_sms` rows, verifies malformed Java-style array delete payloads do not partially delete valid ids, calls `/dev/email/delete` and `/dev/sms/delete`, verifies both rows reach `DELETE_FLAG = DELETED`, and then removes the temporary rows. It does not print tokens or local credentials.

## Optional Dev Config HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevConfigHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, calls `/dev/config/add`, `/dev/config/edit`, and `/dev/config/delete` for a temporary `BIZ_DEFINE` row, verifies add/edit/delete return `data = null`, verifies malformed mixed delete payloads do not partially delete valid ids, verifies temporary `SYS_BASE` delete is rejected, confirms `DELETE_FLAG = DELETED`, and then removes temporary rows. It does not print tokens or local credentials.

## Optional Dev Log HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevLogHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, inserts temporary `dev_log` rows, calls `/dev/log/delete` with `{ "category": "..." }`, verifies the response returns `data = null`, confirms only the target category row is physically deleted, confirms another temporary category remains until cleanup, and then removes temporary rows. It does not print tokens or local credentials.

## Optional Dev Job HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevJobHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, inserts temporary `dev_job` rows, verifies malformed Java-style array delete payloads do not partially delete valid ids, calls `/dev/job/delete`, verifies the response returns `data = null`, confirms only the target row reaches `DELETE_FLAG = DELETED`, and then removes temporary rows. It does not print tokens or local credentials.

## Optional Gen Config HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -GenConfigHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, inserts temporary `gen_config` rows, verifies a malformed mixed `editBatch` payload does not partially update valid rows, calls `/gen/config/editBatch`, verifies the response returns `data = null`, confirms only Java edit-parameter fields are updated, and then removes temporary rows. It does not print tokens or local credentials.

## Optional Sale Project Invoicing HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SaleProjectInvoicingHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, inserts temporary `biz_sale_project` and `biz_sale_project_invoicing` rows, verifies a cross-tenant complete request fails without updating the row, calls `/biz/saleprojectinvoicing/complete`, verifies the response returns `data = null`, confirms `INVOICING_STATE = INVOICING_STATE_COMPLETE`, and then removes temporary rows. It does not print tokens or local credentials.

## Sale Project Invoicing Write HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\sale-project-invoicing-write-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
```

This authenticated smoke creates a temporary active sale project, verifies no-token and validation guards for `/biz/saleprojectinvoicing/add`, verifies add/page/customer/detail readback, verifies invalid edit rollback, verifies valid edit, calls `/biz/saleprojectinvoicing/complete`, verifies mixed delete rollback, verifies logical delete, confirms the owning sale project is unchanged, and checks delivery invoice, payment, expenditure, statement, return, and rating side-effect table counts stay stable. It reads the local account from ignored `.env` and does not print tokens or credentials.

## Return Order Write HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\return-order-write-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
```

This authenticated smoke creates a temporary sale project, warehouse, settlement account, products, and shipped project product items, verifies no-token and validation guards for `/biz/returnorder/add`, verifies invalid-add rollback, verifies add/page/query/detail readback, verifies direct add creates one return IN delivery row and increments inventory, verifies edit/delete are blocked once delivery rows exist, verifies `ReturnAndRefund` expense settlement updates return-order state and sale-project return totals, verifies over-refund rollback, and confirms only the expected expenditure/statement rows are created. It reads the local account from ignored `.env` and does not print tokens or credentials.

## Workflow Project Return Approve HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\workflow-project-return-approve-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
```

This authenticated smoke creates temporary sale projects, warehouse, products, shipped project product items, and an active settlement account, verifies no-token and validation guards for `/biz/process/project/return/start`, verifies cancel/reject close without return-order, inventory, or finance side effects, verifies approval creates return-order/item rows plus Java-compatible return IN delivery rows, verifies inventory increment, verifies account-backed automatic `ReturnAndRefund` expenditure/statement rows and settlement-account decrement, verifies the return order is `AlreadySettled`, and verifies sale-project return/refund totals are recalculated. It reads the local account from ignored `.env` and does not print tokens or credentials.

## Sales Approval Reject Side-Effect Matrix

Start the ThinkPHP server separately, then run the approval-smoke set:

```powershell
.\scripts\workflow-project-init-approve-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
.\scripts\workflow-project-delivery-approve-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
.\scripts\workflow-project-play-approve-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
.\scripts\workflow-project-reissue-approve-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
.\scripts\workflow-project-return-approve-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
```

This matrix covers the visible sales approval reject/cancel paths that have bounded downstream side-effect maps. Project init cancel/reject must roll the sale project back to `FOLLOW`, keep `PROCESS_ID` empty, create no `SALE_PROJECT` file relations, and create no invoicing rows. Delivery cancel/reject must avoid delivery invoice rows, invoice item rows, delivery records, inventory decrement, sale-project delivery totals, and product-item delivery quantities. Play reject paths must avoid payment, statement, settlement-account, and project-collection side effects. Reissue reject must avoid reissue-order, reissue item, product-item relation, and downstream delivery/inventory side effects. Return reject must avoid return-order, return item, return IN delivery, inventory, finance, refund, and statement side effects. Each script reads the local account from ignored `.env` and does not print tokens or credentials.

## Sale Project Product Item Mutation HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\sale-project-product-item-mutation-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
```

This authenticated smoke creates a temporary customer, products, a kit-product child relation, and sale project, verifies no-token and invalid-product rollback for `/biz/saleproject/add`, verifies product-list add with direct and kit rows, verifies detail/product readback, verifies `/biz/saleproject/edit` update/insert/logical-delete behavior, verifies `productList = null` preserves active rows, blocks deletion when an active return-order item references a product item, verifies rollback, then clears unreferenced rows. It reads the local account from ignored `.env` and does not print tokens or credentials.

## Sale Project Product Item Standalone HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\sale-project-product-item-standalone-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
```

This authenticated smoke creates a temporary customer, `FOLLOW` sale project, products, and kit-product child relation, verifies no-token and missing-product guards for `/biz/saleprojectproductitem/add`, verifies standalone add/edit/delete with child relation preservation, blocks protected edits/deletes when a return-order item references the project product item, verifies the non-`FOLLOW` project guard, and checks delivery, inventory, invoice, finance, and workflow table counts stay stable. It reads the local account from ignored `.env` and does not print tokens or credentials.

## Sale Project Reissue Order Add HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\sale-project-reissue-order-add-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
```

This authenticated smoke creates temporary customer, product, sale-project, and workflow-history rows, verifies no-token and validation guards for `/biz/saleprojectreissueorder/add|edit|delete`, verifies workflow-owned `processId` rejection, verifies successful direct reissue order creation with one `REISSUE_ORDER`/`WAIT_DELIVER` project-product item and one child relation row, verifies duplicate `processId` rejection, verifies edit-time master/product-list replacement and old row logical delete, verifies mixed-delete rollback and final order/product/relation logical delete, checks sale-project total/status correction and `/biz/saleprojectreissueorder/list/query` readback before and after delete, and confirms delivery, inventory, invoice, invoicing, finance, settlement, and workflow row counts stay stable. It reads the local account from ignored `.env` and does not print tokens or credentials.

## Sale Project Invoice Add/Edit/Delete HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\sale-project-invoice-add-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
```

This authenticated smoke creates temporary customer, product, warehouse, inventory, and sale-project rows, verifies no-token and validation guards for `/biz/saleprojectinvoice/add`, `/edit`, and `/delete`, verifies successful direct delivery-invoice creation and logistics-field edit, verifies duplicate `processId` and mixed-delete rollback guards, checks project-product `DELIVERY`/`STATE` correction plus delete-time reverse correction, verifies sale-project shipment-state recalculation and `/biz/saleprojectinvoice/list` plus `/biz/saleprojectinvoiceItem/page` readback before and after delete, and confirms delivery-record, inventory, invoicing, finance, settlement, and workflow row counts stay stable. It reads the local account from ignored `.env` and does not print tokens or credentials.

## Optional File Relation HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -FileRelationHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, uploads a temporary local file, calls `/biz/bizfilerelation/add` with JSON, verifies the relation row, calls `/biz/bizfilerelation/projectCase/del`, and cleans up the temporary database and disk rows. It does not print tokens or local credentials.

## Optional Sys Button HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysButtonHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, calls `/sys/button/add`, verifies the created button appears in `/sys/button/page`, verifies duplicate `code` rejection, calls `/sys/button/edit`, prepares a temporary `SYS_ROLE_HAS_RESOURCE` relation, calls `/sys/button/delete`, verifies `DELETE_FLAG = DELETED` and `EXT_JSON.buttonInfo` cleanup, and removes temporary rows. It does not print tokens or local credentials.

## Optional Sys Field HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysFieldHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, creates temporary system module/menu fixtures through the service layer, calls `/sys/field/add`, verifies the created field appears in `/sys/field/page` and `/sys/field/detail`, verifies duplicate sibling `code` rejection, calls `/sys/field/edit`, prepares a temporary direct `SYS_ROLE_HAS_RESOURCE` relation, calls `/sys/field/delete` with a mixed existing and missing id payload, verifies `DELETE_FLAG = DELETED`, verifies the sibling field remains active, verifies the direct relation row is removed, and removes temporary rows. It does not print tokens or local credentials.

## Optional Sys Module HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysModuleHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, calls `/sys/module/add`, verifies the created module appears in `/sys/module/page`, verifies duplicate `title` rejection, calls `/sys/module/edit`, prepares a temporary child menu and `SYS_ROLE_HAS_RESOURCE` relation, calls `/sys/module/delete`, verifies module and child menu reach `DELETE_FLAG = DELETED`, verifies the relation is removed, and removes temporary rows. DB smoke additionally covers module delete FIELD cascade and direct field relation cleanup. It does not print tokens or local credentials.

## Optional Sys Menu HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysMenuHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, creates temporary system modules through the service layer, calls `/sys/menu/add`, verifies the created menu appears in `/sys/menu/tree`, verifies duplicate sibling-title rejection, validates child parent/module mismatch rejection, calls `/sys/menu/edit`, verifies `IFRAME` normalization, verifies self/descendant parent rejection, verifies child `changeModule` rejection, calls root `/sys/menu/changeModule`, prepares a temporary system button and `SYS_ROLE_HAS_RESOURCE` relation, calls `/sys/menu/delete` with a mixed existing and missing id payload, verifies the menu/button tree is logically deleted, verifies the role-resource relation row is removed, and removes temporary rows. DB smoke additionally covers menu delete FIELD cascade and direct field relation cleanup. It does not print tokens or local credentials.

## Optional Mobile Button HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -MobileButtonHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, calls `/mobile/button/add`, verifies the created button appears in `/mobile/button/page`, verifies duplicate `code` rejection, calls `/mobile/button/edit`, prepares a temporary `SYS_ROLE_HAS_MOBILE_MENU` relation, calls `/mobile/button/delete` with a mixed existing and missing id payload, verifies `DELETE_FLAG = DELETED` and `EXT_JSON.buttonInfo` cleanup, and removes temporary rows. It does not print tokens or local credentials.

## Optional Mobile Module HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -MobileModuleHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, calls `/mobile/module/add`, verifies the created module appears in `/mobile/module/page`, verifies duplicate `title` rejection, calls `/mobile/module/edit`, prepares temporary child mobile menu rows and a `SYS_ROLE_HAS_MOBILE_MENU` relation, calls `/mobile/module/delete` with a mixed existing and missing id payload, verifies module/menu rows reach `DELETE_FLAG = DELETED`, verifies the mobile-menu relation row is removed, and removes temporary rows. It does not print tokens or local credentials.

## Optional Mobile Menu HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -MobileMenuHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, creates temporary mobile modules through the service layer, calls `/mobile/menu/add`, verifies the created menu appears in `/mobile/menu/tree`, verifies duplicate sibling-title rejection, validates child parent/module mismatch rejection, calls `/mobile/menu/edit`, verifies child `changeModule` rejection, calls root `/mobile/menu/changeModule`, prepares a temporary mobile button and `SYS_ROLE_HAS_MOBILE_MENU` relation, calls `/mobile/menu/delete` with a mixed existing and missing id payload, verifies the menu tree is logically deleted, verifies the mobile-menu relation row is removed, verifies the button row is preserved, and removes temporary rows. It does not print tokens or local credentials.

## Optional Team Project HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -TeamProjectHttpSmoke
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, calls `/biz/bizteamproject/add`, verifies the created project, current-user `LEADER` member, and `TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION` relation, calls `/biz/bizteamprojectuser/edit` and verifies audit refresh without role/permission mutation, calls `/biz/bizteamproject/edit`, verifies base field updates and version increment, calls `/biz/bizteamproject/delete`, verifies project/member logical delete, and cleans up temporary rows. It does not print tokens or local credentials.

## Optional Team Project Task Write HTTP Smoke

Start the ThinkPHP server separately, then run:

```powershell
.\scripts\team-project-task-write-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
```

This optional authenticated smoke creates a short-lived local token from `LOCAL_SUPER_ADMIN_ACCOUNT` in the ignored `.env`, creates a temporary project and temporary member user, calls task category add/edit/sort/delete, task add/edit/delete, task assignee remove/re-add, and task-comment add/edit/delete endpoints, verifies validation and missing-row guards, verifies final logical-delete/version/task-user database state, and cleans up temporary rows. It does not print tokens or local credentials.

## Local Runtime Services

Use the user-provided local service bundle before DB-backed smoke tests:

```powershell
Set-Location E:\project\socket\AI\testPhp\files
.\startServer1.bat
```

Detailed connection notes for future conversations are kept in:

`docs/tasks/local-runtime-services.md`

Expected local services:

- MySQL listens on `127.0.0.1:3306`
- Redis listens on `127.0.0.1:6379`
- PHP FastCGI listens on `127.0.0.1:9000`

The project local `.env` is ignored by Git and should hold the user-provided MySQL and Redis credentials. Do not print or commit database or Redis passwords in test logs.

Local login smoke credentials must also come from the ignored project `.env`:

- `LOCAL_SUPER_ADMIN_ACCOUNT`
- `LOCAL_SUPER_ADMIN_PASSWORD`

Never write plaintext local login credentials, tokens, database passwords, Redis passwords, or other secrets into tracked files, smoke output excerpts, commits, or final reports.

## DB-Backed Export Smoke Status

Resolved on 2026-06-06 after starting the user-provided local service bundle.

Verified:

- `phpoa20026` exists.
- The database has application tables.
- Redis responds after authentication.
- `UserDirectoryService::exportUsers(false, ...)` returns a CSV download descriptor.
- `UserDirectoryService::exportUsers(true, ...)` returns a CSV download descriptor.
- `UserDirectoryService::exportUserInfoFile(...)` returns a text profile download descriptor.
- Export smoke output did not include password headers or password text.

## Deferred Checks

Add these only when a backend and frontend browser session are already available:

- optional no-token HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -NoTokenSmoke`
- optional dev-file HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevFileHttpSmoke`
- optional dev-email/SMS HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevEmailSmsHttpSmoke`
- optional dev-config HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevConfigHttpSmoke`
- optional dev-log HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevLogHttpSmoke`
- optional dev-job HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -DevJobHttpSmoke`
- optional gen-config HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -GenConfigHttpSmoke`
- optional sale-project invoicing HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SaleProjectInvoicingHttpSmoke`
- sale-project invoicing write HTTP smoke through `.\scripts\sale-project-invoicing-write-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`
- workflow project return approve HTTP smoke through `.\scripts\workflow-project-return-approve-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`
- sales approval reject side-effect matrix through `.\scripts\workflow-project-init-approve-http-smoke.ps1`, `.\scripts\workflow-project-delivery-approve-http-smoke.ps1`, `.\scripts\workflow-project-play-approve-http-smoke.ps1`, `.\scripts\workflow-project-reissue-approve-http-smoke.ps1`, and `.\scripts\workflow-project-return-approve-http-smoke.ps1`
- sale-project product-item mutation HTTP smoke through `.\scripts\sale-project-product-item-mutation-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`
- sale-project product-item standalone HTTP smoke through `.\scripts\sale-project-product-item-standalone-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`
- optional file-relation HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -FileRelationHttpSmoke`
- optional sys-module HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysModuleHttpSmoke`
- optional sys-menu HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysMenuHttpSmoke`
- optional sys-button HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysButtonHttpSmoke`
- optional mobile-module HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -MobileModuleHttpSmoke`
- optional mobile-menu HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -MobileMenuHttpSmoke`
- optional mobile-button HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -MobileButtonHttpSmoke`
- optional team-project base HTTP smoke through `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -TeamProjectHttpSmoke`
- browser smoke through the copied Vue frontend for affected visible pages, including dev config "other config" maintenance when `/dev/config/add|edit|delete` changes
- system resource browser smoke should use the real dynamic routes `/sys/module` and `/sys/menu`; if the current local admin menu lacks these routes, insert only temporary `sys_relation` user-resource rows, run the browser check, and delete those rows before final verification
- mobile resource browser smoke should use `/mobile/module` and `/mobile/menu`; if imported data lacks dynamic menu rows, insert temporary marked `sys_resource`, `sys_relation`, and `mobile_resource` rows, run the browser check, and delete those rows before final verification
