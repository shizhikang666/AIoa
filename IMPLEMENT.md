锘块敇鍧楁晣閸ф鏅ｉ柛褎顨嗛弲? IMPLEMENT.md

## db-agent Implementation Flow

Every db-agent phase must follow this order:

1. Read `AGENTS.md`, `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md`.
2. Check `git status --short --branch`.
3. Analyze Java SQL/entity/mapper files from `F:\AI\projects\testJava\OA` as read-only input.
4. Analyze existing ThinkPHP files in `F:\AI\projects\testJava\OA-db`.
## auth-agent Implementation Flow

Every auth-agent phase must follow this order:

1. Read `AGENTS.md`, `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md`.
2. Check `git status --short --branch`.
3. Analyze Java auth/sys source files from `F:\AI\projects\testJava\OA` as read-only input.
4. Analyze the current ThinkPHP files in `F:\AI\projects\testJava\OA-auth`.
5. Write the smallest safe change set.
6. Avoid public locked files unless a change request is written and confirmed.
7. Run required tests:
# workflow-agent Implementation Log

## Phase 1 Procedure

Date: 2026-05-28

### 1. Analyze Java Original Code

Read-only sources:

- `bpmn/*.bpmn`
- `bpmn/personnel/Process_ask_leave.bpmn`
- `snowy-plugin-biz/.../bizprocess/controller/BizProcessController.java`
- `snowy-plugin-biz/.../bizprocess/controller/BizProcessProjectController.java`
- `snowy-plugin-biz/.../bizprocess/controller/BizTaskController.java`
- `snowy-plugin-biz/.../bizprocess/service/impl/BizProcessServiceImpl.java`
- `snowy-plugin-biz/.../bizprocess/service/impl/BizProjectProcessServiceImpl.java`
- `snowy-plugin-biz/.../bizprocess/service/impl/BizTaskServiceImpl.java`
- `snowy-plugin-biz/.../bizprocess/service/impl/BizBaseProcessServiceImpl.java`
- `snowy-plugin-biz/.../bizprocess/provider/ProcessApiProvider.java`
- `snowy-plugin-biz/.../bizprocess/annotation/*`
- `snowy-plugin-biz/.../bizprocess/aspect/*`
- `snowy-plugin-biz/.../bizprocess/enums/*`
- `snowy-plugin-sys/.../userprocessconfig/*`
- `oa2026.sql`

### 2. Analyze Current ThinkPHP Project

This phase does not create PHP workflow classes. The current worktree is used only for docs and status tracking.

### 3. Minimal Change

Create the workflow analysis documents and phase status files only.

### 4. Test

Run baseline commands after document generation:

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

8. Run `git status --short --branch`.
9. Run `git add .`.
10. Run `git commit -m "db-agent: <clear summary>"`.
10. Run `git commit -m "auth-agent: <clear summary>"`.
11. Append completion status to `STATUS.md`.
12. Report completed content, modified files, test results, current issues, and next plan.

## Public File Change Request Rule

The following files are locked for db-agent by default:
The following files are locked for auth-agent by default:
# IMPLEMENT.md

## user-agent Implementation Flow

Every user-agent phase must follow:

1. Read `AGENTS.md`, `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md`.
2. Check `git status --short --branch`.
3. Analyze Java source under `F:\AI\projects\testJava\OA` as read-only input.
4. Analyze the current ThinkPHP worktree under `F:\AI\projects\testJava\OA-user`.
5. Write the smallest safe change set.
6. Avoid locked public files unless a change request is written and confirmed.
7. Run baseline checks.
8. Commit with a message containing `user-agent`.
# IMPLEMENT.md

## api-agent Implementation Flow

Every api-agent phase must follow:

1. Read `AGENTS.md`, `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md`.
2. Check `git status --short --branch`.
3. Analyze Java controller source under `F:\AI\projects\testJava\OA` as read-only input.
4. Analyze the current ThinkPHP worktree under `F:\AI\projects\testJava\OA-api`.
5. Write the smallest safe change set.
6. Avoid locked public files unless a change request is written and confirmed.
7. Run baseline checks.
8. Commit with a message containing `api-agent`.
9. Report modified files, tests, current issues, and next plan.

## Scope

user-agent owns:

- users
- departments and organizations
- positions
- user-center profile APIs
- user selectors and organization trees

user-agent must not own:

- login, token, RBAC session state, or menu permissions already handled by auth-agent
- workflow engine logic
- frontend adaptation
- database schema changes
api-agent owns:

- Java Controller inventory
- ThinkPHP Controller mapping plans
- API response standardization
- route grouping proposals
- request/response compatibility notes
- integration order for controller migration

api-agent must not own:

- database schema or model generation
- login, token, RBAC, menu, or permission service logic
- user/organization/position service logic
- workflow engine logic
- frontend component changes

## Public File Rule

The following files are locked:
# IMPLEMENT.md

## test-agent Implementation Workflow

test-agent must use the following workflow for every phase:

1. Read `AGENTS.md`, `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md`.
2. Confirm the current worktree is `F:\AI\projects\testJava\OA-test`.
3. Confirm the branch is `refactor/test`.
4. Check `git status --short --branch` before editing.
5. Modify only test-agent planning and task documentation unless the user explicitly approves more.
6. Run baseline test commands.
7. Record test results and risks in `STATUS.md` and `docs/tasks`.
8. Run `git status --short --branch`.
9. Stage only related files.
10. Commit with a message that starts with `test-agent:`.

## Phase 1 Scope

This phase creates a baseline test plan and risk list. It does not fix business code and does not change application behavior.

## Locked Files

The following files are locked for test-agent in this phase:
# IMPLEMENT.md

## docs-agent Workflow

docs-agent must follow:

1. Read local rules: `AGENTS.md`, `PLANS.md`, `IMPLEMENT.md`, `STATUS.md`.
2. Check current branch and worktree status.
3. Edit only documentation and status files in `F:\AI\projects\testJava\OA-docs`.
4. Do not modify Java source project files.
5. Do not modify ThinkPHP business code.
6. Run required checks.
7. Commit the completed documentation change.
8. Report changed files, test results, risks, and next steps.

## Phase 1 Implementation Notes

This phase records coordination documents only:

- current parallel Agent status
- final merge checklist
- post-launch realtime data sync reminder

The final deliverable remains one merged ThinkPHP OA project at:

`F:\AI\projects\testJava\OA-ThinkPHP`

The worktrees are temporary parallel workspaces, not separate final projects.

## Locked Files

docs-agent must not modify these files unless the main coordinator explicitly approves a public file change request:

- `composer.json`
- `composer.lock`
- `config/app.php`
- `config/database.php`
- `route/app.php`
- `.env`
- `.env.example`
- `app/common.php`

If a future db-agent phase needs one of these files, create:

`F:\AI\projects\testJava\OA-db\docs\tasks\public-file-change-request.md`

Then wait for confirmation before editing the locked file.

## Model Generation Rule

Foundation Models created by db-agent must:

- preserve physical table names
- preserve database column spelling and casing
- use comments to record Java entity/source table relations
- avoid controller/service logic
- avoid query behavior that belongs to auth-agent, user-agent, workflow-agent, or api-agent

If auth-agent needs one of these files, create:

`F:\AI\projects\testJava\OA-auth\docs\tasks\public-file-change-request.md`

Then wait for confirmation before editing the locked file.

## Auth Scope Rule

auth-agent may work on:

- login
- Token
- Redis-backed auth/session state
- RBAC
- menu and button permission lookup
- auth middleware

auth-agent must not implement:

- user CRUD or organization management
- workflow business logic
- frontend adaptation
- non-auth controllers and services
- database schema changes

## Token + Redis Rule

- Use `Authorization: Bearer <token>`.
- Store Token/session state with Redis-compatible key prefix `oa:auth:`.
- Do not commit Redis credentials, API keys, passwords, or plaintext secrets.
- Token payload must not contain plaintext password or sensitive identity data.
If a route or config change is needed, document it in `docs/tasks/public-file-change-request.md` and wait for confirmation.
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

### 5. Git

After tests:

```powershell
git status --short --branch
git add .
git commit -m "workflow-agent: add workflow analysis plan"
```

### 6. Report

Report:

- modified files
- Java modules analyzed
- SQL tables analyzed
- test results
- current problems
- next phase recommendation
If route registration is needed, document it in `docs/tasks/public-file-change-request.md` and wait for confirmation.
If a later test phase requires changing any locked file, create `docs/tasks/public-file-change-request.md` and wait for confirmation.

## 2026-06-05 Customer Base Maintenance Implementation

Agent: api-agent / frontend-agent

Execution summary:

1. Analyzed Java `CustomerController`, `CustomerServiceImpl`, customer add/edit/delete params, copied `customerApi.js`, and the ThinkPHP customer read service.
2. Added only base customer add, edit, and logical delete handlers.
3. Reused existing customer owner/org data-scope for write validation.
4. Registered only `/biz/customer/add`, `/edit`, and `/delete` in `route/app.php`.
5. Updated API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No `/biz/customer/head/edit`.
- No file upload/storage implementation.
- No SM4 migration.
- No Java source, schema, Composer, `.env`, or frontend source changes.

## 2026-06-05 Supplier Base Maintenance Implementation

Agent: api-agent / frontend-agent

Execution summary:

1. Analyzed Java `SupplierController`, `SupplierServiceImpl`, supplier add/edit/delete params, copied `supplierApi.js`, and the ThinkPHP supplier read service.
2. Added only base supplier add, edit, and logical delete handlers.
3. Preserved the imported lower-case physical `supplier.org` column.
4. Validated writes through admin-compatible roles, scoped organization ids, or matching `CREATE_USER`.
5. Registered only `/biz/supplier/add`, `/edit`, and `/delete` in `route/app.php`.
6. Updated API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No supplier import/export.
- No purchase, payment, procurement, inventory, or workflow side effects.
- No Java source, schema, Composer, `.env`, or frontend source changes.

## 2026-06-06 Warehouse Base Maintenance Implementation

Agent: api-agent / frontend-agent

Execution summary:

1. Analyzed Java `WarehousesController`, `WarehousesServiceImpl`, warehouse add/edit/delete params, copied `warehousesApi.js`, and the ThinkPHP warehouse read service.
2. Added only base warehouse add, edit, and logical delete handlers.
3. Defaulted new warehouse `USER` and `ORG` from the current token user.
4. Validated writes through admin-compatible roles, scoped organization ids, or direct warehouse ownership.
5. Registered only `/biz/warehouses/add`, `/edit`, and `/delete` in `route/app.php`.
6. Updated API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No inventory stock updates.
- No delivery record, purchase-order, sale-project invoice, or workflow writes.
- No Java source, schema, Composer, `.env`, or frontend source changes.

## 2026-06-06 Product Status And Reconciliation Implementation

Agent: api-agent / frontend-agent

Execution summary:

1. Analyzed Java `BizProductController`, `BizProductServiceImpl`, product status/reconciliation params, copied `bizProductApi.js`, and the ThinkPHP product read service.
2. Added only product status toggling and selected-product reconciliation edits.
3. Preserved lower-case physical `biz_product.status` and upper-case reconciliation columns.
4. Validated writes through admin-compatible roles, scoped organization ids, or matching product creator.
5. Registered only `/biz/bizproduct/edit/status` and `/biz/bizproduct/reconciliation/edit` in `route/app.php`.
6. Updated API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No product add, edit, or delete.
- No kit product relation writes.
- No inventory, purchase, sale-project, finance transaction, workflow, file upload/storage, or data-change/cache event behavior.
- No Java source, schema, Composer, `.env`, or frontend source changes.

## 2026-06-06 Product Base Maintenance Implementation

Agent: api-agent / frontend-agent

Execution summary:

1. Analyzed Java `BizProductController`, `BizProductServiceImpl`, product add/edit/delete params, copied `bizProductApi.js`, and the ThinkPHP product read/status service.
2. Added only product base add, edit, and logical delete handlers.
3. Preserved lower-case physical `biz_product.status`, uppercase base product columns, and Java-style `product_relation.CATEGORY = KIT_PRODUCT_DATA`.
4. Validated writes through admin-compatible roles, scoped organization ids, or matching product creator.
5. Registered only `/biz/bizproduct/add`, `/edit`, and `/delete` in `route/app.php`.
6. Updated API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No inventory stock updates.
- No purchase-order, sale-project item, finance transaction, workflow, file upload/storage, or Java data-change/cache side effects.
- No Java source, schema, Composer, `.env`, or frontend source changes.

## 2026-06-06 Sale Project Product Mark Implementation

Agent: api-agent / frontend-agent

Execution summary:

1. Analyzed Java `SaleProjectProductItemRelationController.editMark`, `SaleProjectProductItemRelationServiceImpl.editMark`, `BizSaleProjectProductItemController.editMark`, and `BizSaleProjectProductItemServiceImpl.editMark`.
2. Added only relation/product-item `MARK` update compatibility.
3. Added a tiny product-item controller/service for `/biz/saleprojectproductitem/mark/edit` without opening product-item CRUD.
4. Reused sale-project visibility checks through the owning active sale project before writes.
5. Registered only `/biz/saleprojectproductitemrelation/mark/edit` and `/biz/saleprojectproductitem/mark/edit` in `route/app.php`.
6. Updated API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No sale-project product item add/edit/delete.
- No delivery, invoice, return, inventory, workflow, finance, sale-project state, file upload/storage, or Java data-change/cache side effects.
- No Java source, schema, Composer, `.env`, or frontend source changes.

## 2026-06-06 Customer Head Reassignment Implementation

Agent: api-agent / frontend-agent

Execution summary:

1. Analyzed Java `CustomerController.editCustomerHead`, `CustomerServiceImpl.editCustomerHead`, `CustomerHeadEditParam`, and `BizUserServiceImpl.queryEntityByPermission`.
2. Added only customer owner reassignment compatibility for `/biz/customer/head/edit`.
3. Reused existing active-customer write-scope validation before changing ownership.
4. Validated target users through admin-compatible roles, data-scope organization ids, or current-user fallback.
5. Registered only `/biz/customer/head/edit` in `route/app.php`.
6. Updated API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No customer import/export, file upload/storage, SM4 plaintext search migration, notification, sale-project/customer side effect, or Java data-change event.
- No Java source, schema, Composer, `.env`, or frontend source changes.

## 2026-06-06 User Center Self-Service Write Implementation

Agent: user-agent / frontend-agent

Execution summary:

1. Analyzed Java `SysUserCenterController`, `SysUserServiceImpl`, `BizUserController.editUser`, and copied `userCenterApi.js` / `bizUserApi.js`.
2. Added `UserCenterWriteService` for current-user-only password, avatar, signature, profile, workbench, and process-config writes.
3. Reused the existing password transport decoder and SM3 hashing for password updates.
4. Registered only the copied frontend user-center routes and `/biz/user/center/edit`.
5. Updated API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No admin-side user CRUD, enable/disable, reset-password-by-admin, grants, import/export.
- No Java source, database schema, Composer, `.env`, or frontend source changes.
- No Java SM4 encrypted-field migration or full file-provider storage cleanup.

## 2026-06-06 User Message Detail Mark-Read Implementation

Agent: user-agent / frontend-agent

Execution summary:

1. Analyzed Java `SysUserCenterController.loginMessageDetail`, `SysUserServiceImpl.loginMessageDetail`, and `DevMessageServiceImpl.detail`.
2. Confirmed Java marks only the current login user's `dev_relation` receiver relation as read during message detail.
3. Confirmed `dev_relation` in `oa2026.sql` has only `ID`, `OBJECT_ID`, `TARGET_ID`, `CATEGORY`, and `EXT_JSON`.
4. Updated `UserDirectoryService.loginUnreadMessageDetail` to mark the current user's `MSG_TO_USER` relation `EXT_JSON.read = true` after ownership validation.
5. Kept existing route registration unchanged and updated API/frontend/gap/progress documentation.

Explicit non-goals:

- No message send/delete/all-mark-read implementation.
- No WebPush/full realtime push implementation.
- No Java source, database schema, route, Composer, `.env`, or frontend source changes.

## 2026-06-06 Index Message All-Mark-Read Implementation

Agent: user-agent / frontend-agent

Execution summary:

1. Analyzed Java `SysIndexController.allMessageMarkRead`, `SysIndexServiceImpl.allMessageMarkRead`, `DevMessageProvider.allMessageMarkRead`, and copied `indexApi.js` plus homepage message drawer components.
2. Added `IndexController.allMessageMarkRead` and `IndexService.allMessageMarkRead`.
3. Added `UserDirectoryService.markAllMessagesRead` to update only current-user `dev_relation` rows for `CATEGORY = MSG_TO_USER`.
4. Registered protected `POST /sys/index/message/allMessageMarkRead` in `route/app.php`.
5. Updated index API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No message send/delete implementation.
- No WebPush/full realtime push implementation.
- No schedule add/delete implementation.
- No Java source, database schema, Composer, `.env`, or frontend source changes.

## 2026-06-06 Index Schedule Self-Service Implementation

Agent: user-agent / frontend-agent

Execution summary:

1. Analyzed Java `SysIndexController.addSchedule`, `SysIndexController.deleteSchedule`, `SysIndexServiceImpl` schedule methods, schedule param/result classes, and copied homepage `schedule.vue`.
2. Added `IndexController.addSchedule` and `IndexController.deleteSchedule` with JSON/body parsing for form objects and Java-style delete arrays.
3. Added `IndexService.addSchedule` and `IndexService.deleteSchedule`.
4. Stored current-user schedules in `sys_relation` with `CATEGORY = SYS_USER_SCHEDULE_DATA`, matching Java relation storage.
5. Constrained deletion to current-token-user schedule rows before physical relation deletion.
6. Registered protected `POST /sys/index/schedule/add` and `POST /sys/index/schedule/deleteSchedule` in `route/app.php`.
7. Updated index API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No shared calendar behavior.
- No schedule editing or notifications.
- No cross-user schedule management.
- No Java source, database schema, Composer, `.env`, or frontend source changes.

## 2026-06-06 Auth Session And Token Exit Implementation

Agent: auth-agent / frontend-agent

Execution summary:

1. Analyzed Java `AuthSessionController`, `AuthSessionServiceImpl`, `AuthExitSessionParam`, `AuthExitTokenParam`, and copied auth monitor frontend API/views.
2. Added a cache-backed B-side token index in `TokenService` for tokens created after this slice.
3. Added Java-compatible session and token exit handlers to `SessionController` and `SessionMonitorService`.
4. Registered protected `/auth/session/b/exit`, `/auth/session/c/exit`, `/auth/token/b/exit`, and `/auth/token/c/exit` routes.
5. Kept C-side exit as success-compatible no-op behavior because C-side client auth is not implemented yet.
6. Updated auth session API notes, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No C-side login/client token storage.
- No third-party OAuth render/callback.
- No route permission middleware or UI permission rewrite.
- No Java source, database schema, Composer, `.env`, frontend source, user CRUD, workflow, or business module changes.

## 2026-06-06 Dev Message Delete Implementation

Agent: api-agent / frontend-agent

Execution summary:

1. Analyzed Java `DevMessageController.delete`, `DevMessageServiceImpl.delete`, `DevMessageIdParam`, and copied `dev/message` Vue list.
2. Added Java-compatible body parsing for `/dev/message/delete`.
3. Added `MessageService::delete` to remove `MSG_TO_USER` receiver relations before removing selected `dev_message` rows.
4. Added conservative delete scope: admin-compatible accounts/roles may delete tenant messages; ordinary users may delete only messages they created.
5. Registered protected `POST /dev/message/delete` in `route/app.php`.
6. Updated dev-message API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No message send implementation.
- No SSE/WebPush realtime push implementation.
- No file upload/storage cleanup.
- No Java source, database schema, Composer, `.env`, frontend source, user/workflow, or business module changes.

## 2026-06-06 Dev Message Send Implementation

Agent: api-agent / frontend-agent

Execution summary:

1. Analyzed Java `DevMessageController.send`, `DevMessageServiceImpl.send`, `DevMessageSendParam`, and copied `dev/message` Vue form.
2. Added Java-compatible body parsing for `/dev/message/send`.
3. Added `MessageService::send` to validate admin-compatible access, subject, category/defaults, receiver ids, and active tenant-scoped receivers.
4. Persisted one `dev_message` row with `EXT_JSON.href` and one `MSG_TO_USER` `dev_relation` row per receiver with `read = false`.
5. Registered protected `POST /dev/message/send` in `route/app.php`.
6. Updated dev-message API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No full SSE/WebPush realtime push implementation.
- No message templates.
- No file upload/storage cleanup.
- No Java source, database schema, Composer, `.env`, frontend source, user/workflow, or unrelated business module changes.

## 2026-06-06 Dev Message Detail Mark-Read Implementation

Agent: api-agent / frontend-agent

Execution summary:

1. Analyzed Java `DevMessageServiceImpl.detail` read-state behavior and the existing ThinkPHP `MessageService::detail`.
2. Passed the current auth payload from `MessageController::detail` into `MessageService::detail`.
3. Added current-user receiver relation mark-read behavior for `CATEGORY = MSG_TO_USER`.
4. Preserved existing relation `EXT_JSON` keys while setting `read = true`.
5. Kept the existing protected route and response shape unchanged.
6. Updated dev-message API docs, frontend adaptation notes, progress dashboard, and status tracking.

Explicit non-goals:

- No route changes.
- No full SSE/WebPush realtime push implementation.
- No Java source, database schema, Composer, `.env`, frontend source, user/workflow, or unrelated business module changes.

## 2026-06-06 User Role Grant Save Implementation

Agent: user-agent / frontend-agent

Execution summary:

1. Analyzed Java `SysUserController.grantRole`, `SysUserServiceImpl.grantRole`, `BizUserController.grantRole`, `BizUserServiceImpl.grantRole`, and copied `sys/userApi.js` / `biz/bizUserApi.js`.
2. Added `UserController.grantRole` and `UserController.bizGrantRole` for copied frontend role-grant save dialogs.
3. Added `UserDirectoryService::grantRole` to clear and rewrite only `sys_relation` rows where `CATEGORY = SYS_USER_HAS_ROLE`.
4. Validated active target users, active tenant-compatible role ids, admin-compatible payloads, route/button permission payloads, and conservative business data-scope.
5. Registered protected `/sys/user/grantRole` and `/biz/user/grantRole` routes in `route/app.php`.
6. Updated grant API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No resource grant or permission grant save implementation.
- No user add/edit/delete, enable/disable, reset-password-by-admin, import/export, or encrypted profile-field migration.
- No Java source, database schema, Composer, `.env`, frontend source, workflow, role CRUD, or unrelated business module changes.

## 2026-06-06 User Resource Grant Save Implementation

Agent: user-agent / frontend-agent

Execution summary:

1. Analyzed Java `SysUserController.grantResource`, `SysUserServiceImpl.grantResource`, `SysUserGrantResourceParam`, and copied `sys/userApi.js` / `sys/user/grantResourceForm.vue`.
2. Added `UserController.grantResource` for the copied system user menu/button grant dialog.
3. Added `UserDirectoryService::grantResource` to clear and rewrite only `sys_relation` rows where `CATEGORY = SYS_USER_HAS_RESOURCE`.
4. Preserved Java-compatible resource `EXT_JSON` with `menuId` and `buttonInfo`.
5. Validated active target users, active menu/button resource ids, admin-compatible payloads or route/button permission payloads, and Java's system-module/super-admin target safeguard.
6. Registered protected `/sys/user/grantResource` in `route/app.php`.
7. Updated grant API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No permission grant save implementation.
- No role resource grants, mobile resource grants, user add/edit/delete, enable/disable, reset-password-by-admin, import/export, or encrypted profile-field migration.
- No Java source, database schema, Composer, `.env`, frontend source, workflow, role CRUD, or unrelated business module changes.

## 2026-06-06 User Permission Grant Save Implementation

Agent: user-agent / frontend-agent

Execution summary:

1. Analyzed Java `SysUserController.grantPermission`, `SysUserServiceImpl.grantPermission`, `SysUserGrantPermissionParam`, and copied `sys/userApi.js` / `sys/user/grantPermissionForm.vue`.
2. Added `UserController.grantPermission` for the copied system user API/data-scope grant dialog.
3. Added `UserDirectoryService::grantPermission` to clear and rewrite only `sys_relation` rows where `CATEGORY = SYS_USER_HAS_PERMISSION`.
4. Preserved Java-compatible permission `EXT_JSON` with `apiUrl`, `scopeCategory`, and `scopeDefineOrgIdList`.
5. Validated active target users, supported Java/frontend scope categories, custom organization ids, and admin-compatible payloads or route/button permission payloads.
6. Registered protected `/sys/user/grantPermission` in `route/app.php`.
7. Updated grant API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No route-permission middleware implementation.
- No role permission grants, user add/edit/delete, enable/disable, reset-password-by-admin, import/export, or encrypted profile-field migration.
- No Java source, database schema, Composer, `.env`, frontend source, workflow, role CRUD, or unrelated business module changes.

## 2026-06-06 User Enable Disable Implementation

Agent: user-agent / frontend-agent

Execution summary:

1. Analyzed Java `SysUserController.disableUser/enableUser`, `BizUserController.disableUser/enableUser`, `SysUserServiceImpl`, `BizUserServiceImpl`, and copied `sys/userApi.js` / `biz/bizUserApi.js` table switches.
2. Added `UserController` handlers for system and business enable/disable routes.
3. Added `UserDirectoryService::setUserStatus` to update only `sys_user.USER_STATUS` between `ENABLE` and `DISABLED`.
4. Preserved Java's business data-scope behavior with conservative organization scope or current-user fallback.
5. Registered protected `/sys/user/disableUser`, `/sys/user/enableUser`, `/biz/user/disableUser`, and `/biz/user/enableUser` in `route/app.php`.
6. Updated status API docs, biz directory docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No user add/edit/delete implementation.
- No reset-password-by-admin, import/export, token/session invalidation on status change, or route-permission middleware implementation.
- No Java source, database schema, Composer, `.env`, frontend source, workflow, grants, or unrelated business module changes.

## 2026-06-06 User Reset Password Implementation

Agent: user-agent / frontend-agent

Execution summary:

1. Analyzed Java `SysUserController.resetPassword`, `SysUserServiceImpl.resetPassword`, `BizUserController.resetPassword`, `BizUserServiceImpl.resetPassword`, and copied `sys/userApi.js` / `biz/bizUserApi.js`.
2. Added `UserController.resetPassword` and `UserController.bizResetPassword` for copied system and business user row actions.
3. Added `UserDirectoryService::resetPassword` to update only `sys_user.PASSWORD`.
4. Read the configured system default password from `dev_config` and hashed it through existing SM3 compatibility without returning or printing the value.
5. Preserved Java's business data-scope behavior with conservative organization scope or current-user fallback.
6. Registered protected `/sys/user/resetPassword` and `/biz/user/resetPassword` routes in `route/app.php`.
7. Updated reset-password API docs, biz directory docs, user grant/status docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No user add/edit/delete implementation.
- No import/export, token/session invalidation after reset, route-permission middleware implementation, or encrypted profile-field migration.
- No Java source, database schema, Composer, `.env`, frontend source, workflow, grants, or unrelated business module changes.

## 2026-06-06 User Delete Implementation

Agent: user-agent / frontend-agent

Execution summary:

1. Analyzed Java `SysUserController.delete`, `SysUserServiceImpl.delete`, `BizUserController.delete`, `BizUserServiceImpl.delete`, and copied `sys/userApi.js` / `biz/bizUserApi.js`.
2. Added `UserController.delete` and `UserController.bizDelete` for copied system and business user row-delete and batch-delete actions.
3. Added `UserDirectoryService::deleteUsers` to logically delete `sys_user` rows by setting `DELETE_FLAG = DELETED`.
4. Accepted copied frontend array payloads such as `[{ id }]`, plus common `id`, `ids`, `idList`, and `userIds` forms.
5. Preserved Java-compatible cleanup by clearing affected direct user supervisor fields, extra-position `directorId` values in `POSITION_JSON`, and organization supervisor fields.
6. Preserved Java's business data-scope behavior with conservative organization scope or current-user fallback.
7. Protected built-in/admin-compatible accounts from deletion.
8. Registered protected `/sys/user/delete` and `/biz/user/delete` routes in `route/app.php`.
9. Updated user-delete API docs, biz directory docs, user grant/status docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No user add/edit implementation.
- No import/export, token/session invalidation after delete, Java data-change event publishing, route-permission middleware implementation, or encrypted profile-field migration.
- No Java source, database schema, Composer, `.env`, frontend source, workflow, grants, or unrelated business module changes.

## 2026-06-06 User Add Edit Implementation

Agent: user-agent / frontend-agent

Execution summary:

1. Analyzed Java `SysUserController.add/edit`, `SysUserServiceImpl.add/edit`, `BizUserController.add/edit`, `BizUserServiceImpl.add/edit`, and copied `sys/userApi.js` / `biz/bizUserApi.js` plus both user forms.
2. Added system and business add/edit controller handlers for the copied user management forms.
3. Added `UserDirectoryService::addUser` and `UserDirectoryService::editUser` for base `sys_user` profile writes only.
4. Preserved Java-compatible defaults on add: configured default password hash, enabled status, not-deleted flag, selected-organization tenant id, avatar fallback, bank defaults, and company employee id.
5. Preserved Java-compatible edit safeguards: required fields, unique account/phone/email, active organization/position/supervisor validation, built-in account protection, and no password/status/create-metadata updates.
6. Registered protected `/sys/user/add`, `/sys/user/edit`, `/biz/user/add`, and `/biz/user/edit` routes in `route/app.php`.
7. Updated user add/edit API docs, biz directory docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and status tracking.

Explicit non-goals:

- No import/export.
- No route-permission middleware.
- No token/session invalidation.
- No Java data-change event publishing.
- No full SM4 encrypted-field migration.
- No org/position CRUD, Java source, database schema, Composer, `.env`, frontend source, workflow, grants, or unrelated business module changes.

## 2026-06-06 Organization Add Edit Delete Implementation

Agent: user-agent / frontend-agent

Execution summary:

1. Analyzed Java `SysOrgController.add/edit/delete`, `BizOrgController.add/edit/delete`, `SysOrgServiceImpl`, `BizOrgServiceImpl`, copied `sys/orgApi.js`, `biz/bizOrgApi.js`, and both organization forms.
2. Added system and business organization write controller handlers for copied organization maintenance pages.
3. Added `OrgService::add`, `OrgService::edit`, and `OrgService::delete` for base `sys_org` writes only.
4. Preserved Java-compatible form payloads for `parentId`, `name`, `category`, `sortCode`, `directorId`, `extJson`, and copied frontend delete arrays such as `[{ id }]`.
5. Added validation for active parent organization, category values, same-level duplicate names, optional director, tenant compatibility, and parent cycle prevention.
6. Added dependency-protected delete that expands selected organizations to children and blocks active user, extra-position JSON, role, and position references.
7. Used logical `sys_org.DELETE_FLAG = DELETED` during the staged refactor instead of Java physical delete.
8. Preserved Java-compatible business data-scope checks for add/edit/delete.
9. Registered protected `/sys/org/add`, `/sys/org/edit`, `/sys/org/delete`, `/biz/org/add`, `/biz/org/edit`, and `/biz/org/delete` routes in `route/app.php`.
10. Updated organization API docs, biz directory docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

Explicit non-goals:

- No position add/edit/delete implementation.
- No user import/export.
- No route-permission middleware.
- No Java data-change event publishing.
- No Java physical delete behavior.
- No Java source, database schema, Composer, `.env`, frontend source, workflow, finance, stock, or unrelated business module changes.

## 2026-06-06 Position Add Edit Delete Implementation

Agent: user-agent / frontend-agent

Execution summary:

1. Analyzed Java `SysPositionController.add/edit/delete`, `BizPositionController.add/edit/delete`, `SysPositionServiceImpl`, `BizPositionServiceImpl`, copied `sys/positionApi.js`, `biz/bizPositionApi.js`, and both position forms.
2. Added system and business position write controller handlers for copied position maintenance pages.
3. Added `PositionService::add`, `PositionService::edit`, and `PositionService::delete` for base `sys_position` writes only.
4. Preserved Java-compatible form payloads for `orgId`, `name`, `category`, `sortCode`, `extJson`, and copied frontend delete arrays such as `[{ id }]`.
5. Added validation for active organization, category values, same-organization duplicate names, tenant compatibility, and route/button permission payloads.
6. Added dependency-protected delete that blocks active direct user `POSITION_ID` and user extra-position JSON references.
7. Used logical `sys_position.DELETE_FLAG = DELETED` during the staged refactor instead of Java physical delete.
8. Preserved Java-compatible business organization data-scope checks for add/edit/delete.
9. Registered protected `/sys/position/add`, `/sys/position/edit`, `/sys/position/delete`, `/biz/position/add`, `/biz/position/edit`, and `/biz/position/delete` routes in `route/app.php`.
10. Updated position API docs, biz directory docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

Explicit non-goals:

- No user import/export.
- No route-permission middleware.
- No Java data-change event publishing.
- No Java physical delete behavior.
- No Java source, database schema, Composer, `.env`, frontend source, workflow, finance, stock, or unrelated business module changes.

## 2026-06-06 User Export Download Implementation

Agent: user-agent / frontend-agent

Execution summary:

1. Analyzed Java `SysUserController.downloadImportUserTemplate/export/exportUserInfo`, `SysUserServiceImpl`, `BizUserController.export/exportUserInfo`, `BizUserServiceImpl`, and copied `sys/userApi.js` / `biz/bizUserApi.js`.
2. Added protected system and business user download routes for copied blob download buttons.
3. Added `UserDirectoryService::downloadImportUserTemplate`, `exportUsers`, and `exportUserInfoFile`.
4. Returned CSV/plain-text download blobs without adding Composer dependencies or changing frontend source.
5. Reused existing sanitized user rows so password/token fields are not exported.
6. Preserved conservative business organization data-scope or current-user fallback.
7. Updated export API docs, biz directory alias docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

Explicit non-goals:

- No `POST /sys/user/import`.
- No real `.xlsx` generation.
- No real `.docx` template rendering.
- No file upload/storage behavior.
- No route-permission middleware.
- No Java data-change events.
- No Java source, database schema, Composer, `.env`, frontend source, workflow, finance, stock, or unrelated business module changes.

## 2026-06-06 Test Agent Smoke Runbook Automation

Agent: test-agent

Execution summary:

1. Added `scripts/test-agent-smoke.ps1` to run the repeated post-slice baseline checks from one command.
2. Added required route coverage checks for current frontend-visible personnel download routes, message SSE compatibility, and biz directory aliases.
3. Added optional no-token backend smoke for the protected user download routes when a local backend server is already running.
4. Added `docs/tasks/test-agent-smoke-runbook.md` with baseline usage, optional backend smoke usage, and the current DB-backed export smoke blocker.
5. Kept DB credentials, `.env`, Composer dependencies, Java source, route files, controllers, services, frontend source, and database schema unchanged.

Explicit non-goals:

- No business behavior changes.
- No route, controller, service, frontend, database schema, Composer, `.env`, or Java source changes.
- No DB-backed export smoke until the local MySQL credentials are corrected.

## 2026-06-06 Test Agent DB Smoke Script Automation

Agent: test-agent

Execution summary:

1. Added `scripts/test-agent-db-smoke.ps1` to make DB/Redis/export smoke checks repeatable.
2. The script reads local credentials from ignored `.env`, uses `MYSQL_PWD` and `REDISCLI_AUTH`, and avoids printing passwords.
3. The script verifies `phpoa20026` has tables, Redis responds with `PONG`, and `UserDirectoryService` export methods return valid descriptors without `PASSWORD` content.
4. Updated `scripts/test-agent-smoke.ps1` so optional no-token HTTP smoke treats real HTTP 401 responses as expected unauthenticated responses.
5. Updated the smoke runbook to include the DB smoke command.

Explicit non-goals:

- No endpoint, controller, service, route, frontend, database schema, Composer, `.env`, or Java source changes.
- No data mutation beyond read-only verification.

## 2026-06-06 Business Dictionary Edit Implementation

Agent: api-agent

Execution summary:

1. Added `DictController::edit` for copied business dictionary edit requests.
2. Added `DictService::editBizDict` to update only active `dev_dict` rows where `CATEGORY = BIZ`.
3. Added validation for `id`, `dictLabel`, numeric `sortCode`, optional business parent, tenant compatibility, and same-parent duplicate labels.
4. Preserved existing `CATEGORY`, `DICT_VALUE`, `TENANT_ID`, `CREATE_TIME`, and `CREATE_USER`.
5. Registered protected `POST /biz/dict/edit` in `route/app.php`.
6. Updated business dictionary API docs, biz directory compatibility docs, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

Explicit non-goals:

- No `/biz/dict/add` or `/biz/dict/delete`.
- No system dictionary writes under `/dev/dict`.
- No dictionary cache invalidation parity with Java.
- No frontend source, Java source, database schema, Composer, `.env`, workflow, finance, stock, file storage, or unrelated business changes.

## 2026-06-12 Payroll Edit Batch-Edit Delete Implementation

Agent: merge-agent / api-agent

Execution summary:

1. Used the payroll explorer result for Java behavior: `/edit` copies Java `BizPayrollEditParam` into the row, `/bath/edit` validates all ids then batch-updates, and `/delete` removes rows through Java logical-delete behavior.
2. Added protected `POST /biz/bizpayroll/edit`, `POST /biz/bizpayroll/bath/edit`, and `POST /biz/bizpayroll/delete`.
3. Added controller body parsing for form POST, raw JSON, and request parameters.
4. Added `BizPayrollService::edit`, `bathEdit`, and `delete`.
5. Limited edit writes to Java edit fields only, preserving non-edit payroll fields such as `POST_WAGE`, `YEAR_END_BONUS`, `PUBLIC_ACCOUNT`, `PRIVATE_ACCOUNT`, `REMARK`, `USER`, `ORG`, and `SALARY_TIME`.
6. Added batch validation for missing and duplicate ids before any update.
7. Added logical delete via `DELETE_FLAG = DELETED`.
8. Added tenant-scoped write guards for admin-compatible users, data-scope organization rows, current-user rows, and creator-owned rows.
9. Updated payroll API docs, API gap map, progress dashboard, new-conversation bootstrap notes, implementation notes, and active plan status.

Explicit non-goals:

- No payroll add.
- No payroll generate/add.
- No payroll import/export.
- No payroll download template.
- No payroll calculation logic.
- No workflow, notification, finance, data-change event, Java source, database schema, Composer, `.env`, frontend source, or unrelated business module changes.

## 2026-06-12 Payroll Import Template Download Implementation

Agent: merge-agent / api-agent

Execution summary:

1. Used the payroll explorer result for Java behavior: `downloadImportTemplate` is a GET route that reads `userPayrollTemplate.xlsx` and returns a blob; it does not perform payroll writes.
2. Added the original Java template as a versioned ThinkPHP asset at `app/resources/biz/payroll/userPayrollTemplate.xlsx`.
3. Added `BizPayrollService::downloadImportTemplate` to read the template asset and return filename, content type, and bytes.
4. Added `BizPayrollController::downloadImportTemplate` with a local download response helper so the endpoint returns a file response rather than a JSON envelope.
5. Registered protected `GET /biz/bizpayroll/downloadImportTemplate`.
6. Updated payroll API docs, API gap map, progress dashboard, new-conversation bootstrap notes, implementation notes, and active plan status.

Explicit non-goals:

- No payroll import.
- No payroll export.
- No payroll generate/add.
- No payroll add.
- No Excel parser/renderer changes.
- No payroll calculation logic.
- No Java source, database schema, Composer, `.env`, frontend source, workflow, finance, data-change event, or unrelated business changes.

## 2026-06-12 Leave Application Edit Delete Implementation

Agent: merge-agent / api-agent

Execution summary:

1. Used the leave/vacation explorer result for Java behavior: Java exposes `edit` and `delete`, while `add` is commented out and workflow-owned.
2. Added protected `POST /biz/bizleaveapplication/edit` and `POST /biz/bizleaveapplication/delete`.
3. Added controller body parsing for form POST, raw JSON, and request parameters.
4. Added `BizLeaveApplicationService::edit` and `delete`.
5. Limited edit writes to Java `BizLeaveApplicationEditParam` fields only: `USER_ID`, `PROCESS_ID`, `category`, `AMOUNT`, `REMARK`, `START_TIME`, and `END_TIME`.
6. Preserved `OBJECT_ID`, `TENANT_ID`, `CREATE_TIME`, `CREATE_USER`, and delete state on edit.
7. Added logical delete via `DELETE_FLAG = DELETED` with `UPDATE_TIME` and `UPDATE_USER`.
8. Added full-batch validation before delete writes, including nested `{ ids: [{ id }] }` payload support.
9. Added tenant/data-scope write guards for admin-compatible users, applicant organization, current applicant, and creator-owned rows.
10. Updated leave API docs, API gap map, progress dashboard, new-conversation bootstrap notes, implementation notes, and active plan status.

Explicit non-goals:

- No leave add.
- No workflow start/approve/reject/cancel.
- No annual-leave or vacation deduction/generation.
- No payroll-facing leave recalculation.
- No Java source, database schema, Composer, `.env`, frontend source, notification, data-change event, or unrelated business changes.

## 2026-06-12 Sale Project Draft Save Implementation

Agent: merge-agent / api-agent

Execution summary:

1. Reviewed Java `BizDraftController` and `BizDraftServiceImpl.addOrEditSaleProjectDraft`: the route saves a draft by `targetId`, creates `CATEGORY = SALE_PROJECT_INIT` when missing, and otherwise updates `EXT_JSON`.
2. Added protected `POST /biz/bizdraft/saleproject/add`.
3. Added controller body parsing for form POST, raw JSON, and request parameters.
4. Added `BizDraftService::addOrEditSaleProjectDraft`.
5. Required `targetId` and `extJson`, preserving raw frontend JSON.
6. Created missing active drafts with current tenant, `DELETE_FLAG = NOT_DELETE`, `CREATE_TIME`, and `CREATE_USER`.
7. Updated existing active drafts in the current tenant with only `EXT_JSON`, `UPDATE_TIME`, and `UPDATE_USER`.
8. Updated draft API docs, API gap map, progress dashboard, new-conversation bootstrap notes, implementation notes, and active plan status.

Explicit non-goals:

- No real sale-project add or edit.
- No workflow start/approve/reject/cancel.
- No file upload or storage writes.
- No Java source, database schema, Composer, `.env`, frontend source, notification, data-change event, or unrelated business changes.

## 2026-06-12 Gen Basic Preview Implementation

Agent: merge-agent / api-agent / test-agent

Execution summary:

1. Used the scoped next-candidate explorer recommendation for `GET /gen/basic/previewGen`.
2. Reviewed Java `GenBasicController.previewGen`, `GenBasicServiceImpl.previewGen`, `GenBasicPreviewResult`, and the copied Vue preview modal.
3. Added protected `GET /gen/basic/previewGen`.
4. Added `BasicController::previewGen` with the existing shared guarded response handling.
5. Added `BasicService::previewGen` to load active `gen_basic` plus active `gen_config` rows and return Java-compatible preview bucket fields.
6. Rendered safe preview strings for SQL, frontend, backend, and optional mobile files without executing Java Beetl templates.
7. Preserved copied-modal behavior by returning `genBasicCodeMobileResultList = null` when no `mobileModule` is configured.
8. Updated generator API docs, API gap map, frontend adaptation notes, public route-change notes, progress dashboard, new-conversation bootstrap notes, implementation notes, and active plan status.

Explicit non-goals:

- No `/gen/basic/add`, `/edit`, or `/delete`.
- No `/gen/basic/execGenZip` or `/execGenPro`.
- No direct project file writes.
- No ZIP output.
- No full Java Beetl template parity.
- No Java source, database schema, Composer, `.env`, frontend source, scheduler, or unrelated generator writes.

## 2026-06-12 Lean Continuation Workflow Optimization

Agent: merge-agent / docs-agent fallback

Execution summary:

1. Reviewed current startup, Agent coordination, runtime, dashboard, API gap, and smoke-runbook docs.
2. Added `docs/tasks/lean-continuation-workflow.md` as the primary low-token continuation playbook.
3. Updated `docs/tasks/new-conversation-bootstrap.md` to use a fast startup packet and targeted module search before reading long logs.
4. Updated `AGENTS.md` so project-level rules point to the lean workflow and document the current coordinator-led small-slice mode.
5. Updated `docs/tasks/autonomous-execution-rules.md` to point normal continuations at the lean workflow while preserving autonomy boundaries.
6. Recorded quality-preserving rules for task triage, multi-Agent fallback, documentation scope, and risk-based checks.

Explicit non-goals:

- No business code changes.
- No route behavior changes.
- No frontend source changes.
- No database schema changes.
- No Composer or `.env` changes.
- No Java source changes.

## 2026-06-12 Message SSE Process Notice Compatibility

Agent: merge-agent / workflow-agent fallback

Execution summary:

1. Reviewed Java `BizTaskController` and confirmed it does not expose `/biz/task/sse/stream`.
2. Reviewed copied frontend task API and layout message panel; the active EventSource path is `/dev/message/createSseConnect`.
3. Confirmed the layout task refresh handler listens for `FlushProcessNotice`.
4. Updated `MessageSseService` so the existing short-lived compatibility stream emits both `FlushMessageNotice` and `FlushProcessNotice`.
5. Updated SSE/workflow/gap/progress docs to keep standalone `/biz/task/sse/stream` deferred unless a real caller appears.

Explicit non-goals:

- No `/biz/task/sse/stream` route.
- No task approve/reject.
- No workflow start/cancel.
- No long-lived SSE loop.
- No Redis pub/sub fanout.
- No frontend source changes.
- No database writes, Java source changes, Composer changes, or `.env` changes.

## 2026-06-15 Workflow Read-Only Row Compatibility And Handoff

Agent: merge-agent / workflow-agent sidecar

Execution summary:

1. Used real multi-Agent mode. Mencius reviewed the workflow diff as a read-only sidecar and confirmed the scope stayed within read-only workflow compatibility.
2. Updated `WorkflowQueryService` page/list shaping for copied workflow pages:
   - `/biz/task/page`
   - `/biz/task/history/page`
   - `/biz/process/page`
   - `/biz/process/all/page`
3. Added copied frontend pagination aliases: `current`, `size`, and `pages` alongside existing `page`, `limit`, `total`, and `records`.
4. Added task-row normalization so task pages preserve `id` as the task id while process instance ids are available as `instanceId` and `processInstanceId`.
5. Kept process-row normalization with `id` as the process instance id.
6. Ensured workflow rows expose `variable` as an object for copied Vue templates that read `record.variable.amount`.
7. Guarded `useProcessParam` against missing `SYS_CONFIG` or missing `processConfigMap`.
8. Added a copy-paste new-conversation starter prompt to `docs/tasks/new-conversation-bootstrap.md` so future conversations can continue from repository docs instead of long chat history.

Verification summary:

- `php -l app\service\workflow\WorkflowQueryService.php`: passed.
- `npm run build` in `snowy-admin-web`: passed before documentation-only follow-up edits, with existing warnings only.
- Authenticated API shape check returned HTTP 200 with `code=200` for workflow pending, history, started, all-process, and copy-record pages.
- Playwright browser smoke through `http://127.0.0.1:83` loaded `/biz/biztask`, `/biz/biztask/historyTask`, `/biz/biztask/mystarttask`, `/biz/biztask/allprocess`, and `/biz/biztask/copytask`; all rendered a table or empty state, had no blocking console errors, and triggered no workflow write requests.

Explicit non-goals:

- No workflow approve/reject.
- No workflow start/cancel/edit.
- No task SSE route.
- No vacation deduction or workflow business side effects.
- No Java source, database schema, Composer, `.env`, or unrelated frontend changes.

## 2026-06-15 Public Auth And Password-Recovery Deferred Wrapper Compatibility

Agent: merge-agent / auth-agent and user-agent fallback

Execution summary:

1. Used real multi-Agent mode for slice selection: explorer Agent Beauvoir reviewed remaining gap-map candidates while the main merge/coordinator inspected the current wrapper/route delta.
2. Confirmed the remaining copied wrapper gaps are mostly side-effect-heavy workflow, finance, inventory, provider, generator, and tenant actions.
3. Selected the smallest public frontend-visible compatibility slice: route existing login and password-recovery wrappers to controlled deferred responses instead of 404.
4. Added public routes and controller methods for:
   - `GET /auth/b/getPhoneValidCode`
   - `POST /auth/b/subscription`
   - `GET /sys/userCenter/findPasswordGetPhoneValidCode`
   - `GET /sys/userCenter/findPasswordGetEmailValidCode`
   - `POST /sys/userCenter/findPasswordByPhone`
   - `POST /sys/userCenter/findPasswordByEmail`
5. Kept all provider sends and password mutations deferred by returning standard `code = 400` API envelopes.
6. Updated API notes, gap map, progress dashboard, plan, implementation, and status logs.

Verification summary:

- `php -l app\controller\auth\AuthController.php`: passed.
- `php -l app\controller\sys\UserCenterController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed and lists all six new public wrapper routes.
- Public HTTP smoke against `http://127.0.0.1:82` without `/api` prefix returned HTTP 200 with `code = 400` for all six new routes.

Explicit non-goals:

- No SMS or email provider calls.
- No WebPush subscription persistence.
- No phone-code login.
- No password reset mutation.
- No Java source, frontend source, database schema, Composer, `.env`, workflow, finance, stock, or generator changes.

## 2026-06-15 Selector Pagination Shape Compatibility

Agent: merge-agent / user-agent sidecar

Execution summary:

1. Used Beauvoir's completed explorer recommendation to select a read-only selector response-shape slice.
2. Assigned Locke a bounded Java/frontend expectation check while the main merge/coordinator inspected current ThinkPHP selector services and copied frontend components.
3. Confirmed copied `XnPageSelect` and `XnUserSelector` expect `records`, `total`, `current`, and `size`.
4. Updated `UserDirectoryService::userSelector` to return paged selector payloads while preserving full sanitized user display fields.
5. Updated `PositionService::selector` to return paged selector payloads while preserving full position aliases such as `name`, `category`, and `sortCode`.
6. Accepted copied frontend `size` as an alias for existing `limit` and `pageSize`.
7. Updated selector API notes, dashboard, implementation log, plan, and status log.

Verification summary:

- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l app\service\user\PositionService.php`: passed.
- Authenticated HTTP smoke with ignored `.env` login values passed for `/sys/user/positionSelector`, `/biz/user/positionSelector`, `/sys/position/positionSelector`, `/biz/position/positionSelector`, `/sys/user/userSelector`, `/biz/user/userSelector`, `/sys/org/userSelector`, and `/biz/org/userSelector`; each returned `code=200` with `data.records`, `data.current`, and `data.total`.

Explicit non-goals:

- No selector route additions.
- No frontend source changes.
- No user, organization, or position writes.
- No role grant, import, export, password, workflow, finance, stock, Java source, database schema, Composer, or `.env` changes.
- No business data-scope redesign or child-organization selector rewrite.

## 2026-06-15 Project Progress Acceleration Helper

Agent: merge-agent / process optimization

Execution summary:

1. Audited the repository layout and confirmed the active integration work is centered in `F:\AI\projects\testJava\OA-ThinkPHP` on `refactor/thinkphp-main`.
2. Confirmed sibling module worktrees are mostly stale relative to `refactor/thinkphp-main`, so future continuations should not start by reading every old module `STATUS.md`.
3. Added `scripts/project-progress.ps1` as a read-only fast progress snapshot command.
4. The helper prints Git status, the progress dashboard head, the API gap map next execution order, the latest `STATUS.md` tail, optional sibling worktree summary, and the most useful follow-up commands.
5. Updated the new-conversation bootstrap and lean continuation workflow to prefer the helper before manual status/log reads.

Verification summary:

- `.\scripts\project-progress.ps1 -SkipStatusTail`: passed.
- `.\scripts\project-progress.ps1 -DashboardLines 20 -StatusTail 20 -IncludeWorktreeSummary`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No business logic, route, frontend behavior, Java source, database schema, Composer, `.env`, runtime service, production data, merge, push, or worktree cleanup changes.

## 2026-06-15 Problem Optimization Log

Agent: merge-agent / process optimization

Execution summary:

1. Added `docs/tasks/problem-optimization-log.md` as the living table for recurring project problems and mitigations.
2. Seeded the table with problems already encountered during the project-speed audit: parent workspace not being a Git repo, broad recursive scans, stale module worktrees, long continuation logs, and parent-relative patch path confusion.
3. Updated `scripts/project-progress.ps1` so future startup snapshots include the problem table.
4. Updated the new-conversation bootstrap and lean continuation workflow to require problem-log review and updates when recurring issues or better mitigations appear.
5. Added plan, implementation, and status entries for the process change.

Verification summary:

- `.\scripts\project-progress.ps1 -SkipStatusTail -ProblemLines 50`: passed and printed the problem table.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No business logic, route, frontend behavior, Java source, database schema, Composer, `.env`, runtime service, production data, merge, push, or worktree cleanup changes.

## 2026-06-15 Context Handoff Flow

Agent: merge-agent / process optimization

Execution summary:

1. Added `docs/tasks/context-handoff.md` with criteria for when to ask the user to open a new conversation.
2. Added a new conversation starter prompt that continues from repository docs and `scripts/project-progress.ps1` instead of old chat history.
3. Updated `scripts/project-progress.ps1` to point to the context handoff doc.
4. Updated bootstrap and lean workflow docs with long-context handoff rules.
5. Added problem-log row `P-006` for context overload risk and mitigation.

Verification summary:

- `.\scripts\project-progress.ps1 -SkipStatusTail -ProblemLines 60`: passed and printed the context handoff pointer plus problem table.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No business logic, route, frontend behavior, Java source, database schema, Composer, `.env`, runtime service, production data, merge, push, or worktree cleanup changes.

## 2026-06-15 Role Selector Pagination Shape Compatibility

Agent: merge-agent / user-agent sidecar

Execution summary:

1. Reviewed copied `roleSelectorPlus` and confirmed it reads `data.current`, `data.total`, and `data.records`.
2. Reviewed Java `SysUserServiceImpl::roleSelector` and `SysRoleServiceImpl::roleSelector`; both return Java `Page` payloads.
3. Updated `UserDirectoryService::roleSelector` so `/sys/user/roleSelector` and `/biz/user/roleSelector` return paged selector payloads.
4. Updated `RoleService::page` and `RoleService::roleSelector` so `/sys/role/page` keeps existing records while `/sys/role/roleSelector` exposes the same Java-style pagination aliases and selector aliases.
5. Added copied frontend `size` support to `RoleService` pagination.
6. Updated selector API docs and the progress dashboard.
7. Updated problem row `P-008` after starting the local runtime and rerunning the DB-backed smoke successfully.

Verification summary:

- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l app\service\auth\RoleService.php`: passed.
- `php think route:list | Select-String "roleSelector"`: passed and listed `/sys/user/roleSelector`, `/sys/role/roleSelector`, and `/biz/user/roleSelector`.
- DB-backed service smoke passed for `UserDirectoryService::roleSelector` with system and business filters plus `RoleService::roleSelector`, verifying `records`, `total`, `current`, `size`, and `pages`.

Explicit non-goals:

- No route changes.
- No frontend source changes.
- No role grant writes or role CRUD behavior changes.
- No Java source, database schema, Composer, `.env`, workflow, finance, stock, or production data changes.

## 2026-06-15 Runtime Readiness Check

Agent: merge-agent / test-agent support

Execution summary:

1. Added `scripts/runtime-ready.ps1` to check `127.0.0.1:3306`, `127.0.0.1:6379`, and `127.0.0.1:9000` with short TCP timeouts.
2. Wired the readiness check into `scripts/project-progress.ps1` through `-CheckRuntime`.
3. Updated local runtime and bootstrap docs to point future DB/HTTP smoke tests at the readiness helper.
4. Updated problem row `P-008` with the reusable mitigation.

Verification summary:

- `.\scripts\runtime-ready.ps1`: passed after local runtime services were started.
- `.\scripts\project-progress.ps1 -CheckRuntime -SkipStatusTail -ProblemLines 20`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No application behavior, service startup script, database config, Redis config, Java source, frontend source, Composer, `.env`, or production data changes.

## 2026-06-15 Web Readiness Check

Agent: merge-agent / test-agent support

Execution summary:

1. Added `scripts/web-ready.ps1` to check the local ThinkPHP backend and Vue frontend HTTP targets before browser or authenticated HTTP smoke tests.
2. Wired the readiness check into `scripts/project-progress.ps1` through `-CheckWeb`.
3. Updated the frontend joint-test workflow, runtime docs, bootstrap docs, lean workflow, context handoff doc, dashboard, and problem table.
4. Added problem row `P-009` for the repeated browser-smoke precondition where base runtime ports are ready but application ports `82` and `83` are not listening.

Verification summary:

- `.\scripts\web-ready.ps1`: passed after local ThinkPHP and Vite dev servers were started, verifying backend `82` and frontend `83` TCP plus HTTP readiness.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -SkipStatusTail -ProblemLines 35`: passed and printed both base runtime readiness and web readiness.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No application behavior, route registration, service startup script, frontend source, Java source, database config, Composer, `.env`, or production data changes.

## 2026-06-15 Role Selector HTTP Smoke

Agent: merge-agent / test-agent support

Execution summary:

1. Confirmed copied `/sys/user` and `/biz/user` grant-role dialogs use `roleSelectorPlus` and depend on `ownRole` plus role selector paged payloads.
2. Confirmed the copied frontend dependency set does not include Playwright, `@playwright/test`, or Puppeteer.
3. Added `scripts/role-selector-http-smoke.ps1` as a project-local authenticated HTTP fallback for the selector dialog APIs.
4. Wired the script into `scripts/project-progress.ps1` fast commands and the new-conversation bootstrap.
5. Updated selector API docs, dashboard, plan, status, and problem rows `P-010` and `P-011`.

Verification summary:

- `.\scripts\role-selector-http-smoke.ps1`: passed.
- `.\scripts\web-ready.ps1`: passed before the HTTP smoke.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No browser UI automation, frontend source edits, role grant writes, route changes, schema changes, dependency additions, Java source changes, `.env` edits, or production data operations.

## 2026-06-15 Case-Safe JSON Smoke Helper

Agent: merge-agent / test-agent support

Execution summary:

1. Confirmed the local shell is Windows PowerShell 5.1 and does not support `ConvertFrom-Json -AsHashtable`.
2. Added `scripts/json-read.js`, a dependency-free Node helper that reads JSON from stdin and returns a requested dot-path value.
3. Updated `scripts/project-progress.ps1` fast commands and bootstrap docs with the helper usage.
4. Updated problem row `P-011` so future HTTP smoke scripts have a concrete case-sensitive parsing option.

Verification summary:

- `'{\"data\":{\"records\":[{\"ID\":\"upper\",\"id\":\"lower\"}]}}' | node .\scripts\json-read.js data.records.0.id`: returned `lower`.
- `'{\"data\":{\"records\":[{\"ID\":\"upper\",\"id\":\"lower\"}]}}' | node .\scripts\json-read.js data.records.0.ID`: returned `upper`.
- `.\scripts\role-selector-http-smoke.ps1`: passed after adding the helper.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No broad smoke-script rewrite, npm dependency change, frontend source edit, application behavior change, route change, Java source change, `.env` edit, or production data operation.

## 2026-06-15 Project Preflight Bundle

Agent: merge-agent / test-agent support

Execution summary:

1. Added `scripts/project-preflight.ps1` to run the common local preflight sequence.
2. The default sequence runs Git status, runtime readiness, web readiness, role-selector HTTP smoke, and `git diff --check`.
3. Added skip switches for unavailable layers: `-SkipRuntime`, `-SkipWeb`, `-SkipRoleSelector`, and `-SkipDiffCheck`.
4. Wired the command into `scripts/project-progress.ps1`, bootstrap docs, lean continuation workflow, runtime docs, dashboard, plan, status, and problem row `P-012`.

Verification summary:

- `.\scripts\project-preflight.ps1`: passed.
- `.\scripts\project-preflight.ps1 -SkipWeb -SkipRoleSelector -SkipDiffCheck`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No full regression-suite expansion, application behavior change, frontend source edit, route change, schema change, dependency change, Java source change, `.env` edit, or production data operation.

## 2026-06-15 New Conversation Fast Handoff

Agent: merge-agent / process support

Execution summary:

1. Updated `docs/tasks/context-handoff.md` so the exact new-conversation starter begins from `F:\AI\projects\testJava\OA-ThinkPHP`, runs `.\scripts\project-progress.ps1 -SkipStatusTail`, and then runs `.\scripts\project-preflight.ps1` when local services are expected.
2. Updated `docs/tasks/new-conversation-bootstrap.md` so future conversations start from script-produced context instead of manually reading long docs first.
3. Updated `docs/tasks/lean-continuation-workflow.md` so its fast startup and handoff rules match the new preflight workflow.

Verification summary:

- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No application behavior, frontend source, route, schema, dependency, Java source, `.env`, or production data changes.

## 2026-06-15 Explicit Commit Guardrail

Agent: merge-agent / process support

Execution summary:

1. Updated `docs/tasks/context-handoff.md` so the exact new-conversation starter says not to commit unless the user explicitly asks or the main merge/coordinator explicitly approves committing the completed slice.
2. Updated `docs/tasks/new-conversation-bootstrap.md` so multi-Agent coordination focuses on review, verification, and doc updates, with commits gated by explicit approval.
3. Updated `docs/tasks/lean-continuation-workflow.md` so the implementation loop no longer implies automatic per-slice commits.
4. Added problem-log row `P-013` for the recurring commit-workflow ambiguity.

Verification summary:

- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No Git commit command, application behavior change, frontend source edit, route change, schema change, dependency change, Java source change, `.env` edit, or production data operation.

## 2026-06-15 Progress Snapshot Commit Guardrail

Agent: merge-agent / process support

Execution summary:

1. Added a `Commit Guardrail` section to `scripts/project-progress.ps1`.
2. The fast startup snapshot now states that commits require an explicit user request or explicit main merge/coordinator approval.
3. Updated problem row `P-013` so the mitigation includes both active docs and the startup snapshot.
4. Updated the problem-log review checklist to use `.\scripts\project-progress.ps1 -SkipStatusTail`.

Verification summary:

- `.\scripts\project-progress.ps1 -SkipStatusTail -ProblemLines 20`: passed and printed the new commit guardrail.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No application behavior, frontend source, route, schema, dependency, Java source, `.env`, production data, or Git commit changes.

## 2026-06-15 Recent Problem Row Visibility

Agent: merge-agent / process support

Execution summary:

1. Updated `scripts/project-progress.ps1` so it still prints the problem-log head, then also prints a `Recent Problem Rows` section.
2. The recent section extracts the latest appended `P-###` rows from `docs/tasks/problem-optimization-log.md`.
3. Added problem-log row `P-014` for the risk that short startup output can hide the newest mitigations.

Verification summary:

- `.\scripts\project-progress.ps1 -SkipStatusTail -ProblemLines 20`: passed and printed `Recent Problem Rows` including `P-014`.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No problem-table schema redesign, dependency change, application behavior change, frontend source edit, route change, Java source change, `.env` edit, production data operation, or Git commit command.

## 2026-06-15 Lean Progress Snapshot Mode

Agent: merge-agent / process support

Execution summary:

1. Added `-Lean` to `scripts/project-progress.ps1`.
2. Lean mode defaults `DashboardLines` to `35`, `ProblemLines` to `20`, and skips the `STATUS.md` tail.
3. Lean mode still prints recent problem rows, context handoff, commit guardrail, and fast commands.
4. Updated fast command examples to prefer `-Lean` for runtime and web readiness snapshots.
5. Updated context handoff, new conversation bootstrap, and lean continuation docs to use `.\scripts\project-progress.ps1 -Lean` as the first startup command.
6. Added problem-log row `P-015` for startup context size.

Verification summary:

- `.\scripts\project-progress.ps1 -Lean`: passed.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No preflight behavior change, dependency change, application behavior change, frontend source edit, route change, Java source change, `.env` edit, production data operation, or Git commit command.

## 2026-06-15 Lean Dashboard Summary

Agent: merge-agent / process support

Execution summary:

1. Added `Show-DashboardLean` to `scripts/project-progress.ps1`.
2. Lean mode now prints `Progress Dashboard Summary` instead of raw dashboard head lines.
3. The summary extracts selected stable facts from `docs/tasks/refactor-progress-dashboard.md`: update time, completion estimates, compact key frontend route metrics, current branch, and truncated recent verification notes.
4. Added problem-log row `P-016` for wide dashboard rows consuming startup context.

Verification summary:

- `.\scripts\project-progress.ps1 -Lean`: passed and printed compact `Progress Dashboard Summary` metrics without raw wide dashboard table notes.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No dashboard source rewrite, preflight behavior change, dependency change, application behavior change, frontend source edit, route change, Java source change, `.env` edit, production data operation, or Git commit command.

## 2026-06-15 Lean Problem Summary

Agent: merge-agent / process support

Execution summary:

1. Added `Convert-ProblemLine` and `Show-ProblemsLean` to `scripts/project-progress.ps1`.
2. Lean mode now prints the problem-log path, open problem count, and compact recent rows in `ID | Area | Status | Problem` format.
3. Non-lean mode still prints the configured problem-log head plus full recent problem rows.
4. Added problem-log row `P-017` for noisy full problem rows in startup output.

Verification summary:

- `.\scripts\project-progress.ps1 -Lean`: passed and printed compact problem summaries including `P-017`.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No problem-log schema change, preflight behavior change, dependency change, application behavior change, frontend source edit, route change, Java source change, `.env` edit, production data operation, or Git commit command.

## 2026-06-15 Problem Table Pipe Guard

Agent: merge-agent / process support

Execution summary:

1. Fixed problem row `P-017` so it no longer contains raw vertical bars inside a Markdown table cell.
2. Updated `Convert-ProblemLine` to trim non-empty columns and read status from the final column.
3. Added problem-log row `P-018` for raw vertical bars breaking table/script parsing.

Verification summary:

- `.\scripts\project-progress.ps1 -Lean`: passed and showed `P-017` and `P-018` with `Mitigated` status.
- `.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No problem-log table schema change, Markdown parser dependency, preflight behavior change, application behavior change, frontend source edit, route change, Java source change, `.env` edit, production data operation, or Git commit command.

## 2026-06-15 Third-Party Auth Deferred Wrappers

Agent: merge-agent / auth-agent fallback

Execution summary:

1. Reviewed copied frontend `auth/third` wrappers and Java `AuthThirdController` render/callback routes.
2. Added `ThirdController::render` and `ThirdController::callback` as controlled deferred public wrappers.
3. Registered `GET /auth/third/render` and `GET /auth/third/callback` without changing the protected `/auth/third/page` route.
4. Updated auth-third compatibility docs, API gap map, progress dashboard, public route-change notes, active plan log, implementation log, and status log.

Verification summary:

- `php -l app\controller\auth\ThirdController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list | Select-String "auth/third"`: passed and listed `render`, `callback`, and protected `page`.
- Public HTTP smoke for `/auth/third/render` and `/auth/third/callback`: passed; both returned business `code = 400`.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No real OAuth redirect, provider credential read, callback code exchange, token issuance, user binding write, database schema change, Java source change, frontend source edit, Composer change, `.env` edit, production data operation, or Git commit command.

## 2026-06-15 SMS Provider Deferred Wrappers

Agent: merge-agent / dev-sms-agent fallback

Execution summary:

1. Reviewed copied frontend SMS provider-send wrappers and Java `DevSmsController` send routes.
2. Added `SmsController::sendAliyun`, `SmsController::sendTencent`, and `SmsController::sendXiaonuo` as controlled deferred protected wrappers.
3. Registered `POST /dev/sms/sendAliyun`, `/dev/sms/sendTencent`, and `/dev/sms/sendXiaonuo` inside the existing protected `dev/sms` route group.
4. Updated dev email/SMS compatibility docs, API gap map, progress dashboard, public route-change notes, active plan log, implementation log, and status log.

Verification summary:

- `php -l app\controller\dev\SmsController.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list | Select-String "dev/sms"`: passed and listed `sendAliyun`, `sendTencent`, and `sendXiaonuo`.
- Authenticated HTTP smoke for all three provider-send routes: passed; each returned business `code = 400`.
- No-token HTTP smoke for all three provider-send routes: passed; each returned business `code = 401`.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No real SMS sending, provider credential read, SMS SDK integration, external provider call, send-record write, database schema change, Java source change, frontend source edit, Composer change, `.env` edit, production data operation, or Git commit command.

## 2026-06-15 User Display Smoke Coverage

Agent: merge-agent / frontend-agent fallback

Execution summary:

1. Reviewed the existing sys/biz user display alias implementation and compatibility docs.
2. Added `scripts/user-display-http-smoke.ps1` to verify authenticated user page, detail, list-detail, and selector payloads used by copied frontend pages.
3. Used `scripts/json-read.js` for JSON path checks because Windows PowerShell 5.1 cannot parse payloads that intentionally include case-only duplicate keys such as `ID` and `id`.
4. Added the user-display smoke to `scripts/project-preflight.ps1` with a `-SkipUserDisplay` switch.
5. Updated selector/display docs, API gap map next order, progress dashboard, plan log, implementation log, and status log.

Verification summary:

- `.\scripts\user-display-http-smoke.ps1`: passed.
- The smoke verified `/sys/user/page`, `/biz/user/page`, `/sys/user/detail`, `/sys/user/list/detail`, `/sys/user/userSelector`, and `/biz/user/userSelector`.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No application route change, service behavior change, frontend source edit, Java source edit, schema change, `.env` edit, production data operation, real email/SMS/provider behavior, or Git push.

## 2026-06-15 Business Read Smoke Coverage

Agent: merge-agent / api-agent fallback

Execution summary:

1. Reviewed existing customer and sale-project read routes, frontend wrappers, and compatibility docs.
2. Added `scripts/business-read-http-smoke.ps1` to verify authenticated read payloads using existing active local customer and sale-project rows.
3. Covered customer page/detail/detail-list and sale-project page/case/operation/public/detail/list-detail/product/cost/cost-details.
4. Added the business-read smoke to `scripts/project-preflight.ps1` with a `-SkipBizRead` switch.
5. Updated customer/sale-project docs, API gap map next order, progress dashboard, plan log, implementation log, and status log.

Verification summary:

- `php -l app\controller\biz\CustomerController.php`: passed.
- `php -l app\controller\biz\SaleProjectController.php`: passed.
- `php -l app\service\biz\CustomerService.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `.\scripts\business-read-http-smoke.ps1`: passed.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No customer write call, sale-project state/write call, workflow action, finance effect, stock effect, provider send, frontend source edit, Java source edit, schema change, `.env` edit, production data operation, or Git push.

## 2026-06-15 Parallel Coordination And Directory Alias Smoke

Agent: merge-agent / multi-agent coordinator

Execution summary:

1. Spawned three read-only explorer agents for directory alias smoke scouting, remaining read-only/detail-consumer scouting, and workflow read/write boundary reconnaissance.
2. Added `docs/tasks/parallel-execution-plan.md` to define safe parallel tracks, serial shared files, deferred high-risk modules, worker prompt templates, and the current recommended queue.
3. Linked the parallel plan from context handoff, new conversation bootstrap, and lean continuation workflow docs.
4. Fixed `OrgService::pagination()` to accept copied frontend `size` pagination for `/biz/org/page`.
5. Added `scripts/directory-alias-http-smoke.ps1` for authenticated read-only checks of biz org/position/dict alias pages, trees, and selectors.
6. Added the directory alias smoke to `scripts/project-preflight.ps1` with a `-SkipDirectoryAlias` switch.
7. Updated directory alias docs, selector docs, API gap map, progress dashboard, plan log, implementation log, and status log.

Verification summary:

- `php -l app\service\user\OrgService.php`: passed.
- `.\scripts\directory-alias-http-smoke.ps1`: passed.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No organization, position, user, or dictionary write call; no workflow write; no finance/inventory side effect; no job execution; no provider send; no cloud storage; no Java source edit; no schema change; no `.env` edit; no production data operation; no Git push.

## 2026-06-15 Follow-Up Read Smoke Coverage

Agent: merge-agent / api-agent fallback

Execution summary:

1. Reviewed customer follow-up and sale-project follow-up controllers, services, routes, frontend wrappers, and existing docs.
2. Extended `scripts/business-read-http-smoke.ps1` to verify customer follow-up page/detail and sale-project follow-up page/detail payloads using existing active local rows when available.
3. Kept detail checks conditional so the smoke remains stable when local sample follow-up rows are absent.
4. Updated `scripts/json-read.js` to strip a leading BOM before strict JSON parsing, after local PHP sample output produced BOM-prefixed JSON.
5. Updated customer and sale-project follow-up docs, API gap map next order, progress dashboard, problem log, bootstrap docs, plan log, implementation log, and status log.

Verification summary:

- `php -l app\controller\biz\CustomerFollowUpController.php`: passed.
- `php -l app\controller\biz\SaleProjectFollowUpController.php`: passed.
- `php -l app\service\biz\CustomerFollowUpService.php`: passed.
- `php -l app\service\biz\SaleProjectFollowUpService.php`: passed.
- `.\scripts\business-read-http-smoke.ps1`: passed.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No follow-up add/edit/delete call, attachment cleanup, notification side effect, sale-project state write, workflow action, finance effect, inventory effect, provider send, frontend source edit, Java source edit, schema change, `.env` edit, production data operation, or Git push.

## 2026-06-15 Workflow Read Smoke Coverage

Agent: merge-agent / workflow-agent fallback

Execution summary:

1. Reviewed workflow task, process, and CC read controllers/services plus existing compatibility docs.
2. Added `scripts/workflow-read-http-smoke.ps1` for authenticated read-only HTTP checks.
3. Covered task count/list/page/history, process page/all-page/query/query-list/detail/variable/file-list, project runtime query when local sample data exists, and CC page/detail when current-user sample data exists.
4. Kept query-list bounded with a missing `processKeys` filter after empty filters exhausted the local PHP memory limit on the large historic workflow dataset.
5. Added the workflow-read smoke to `scripts/project-preflight.ps1` with a `-SkipWorkflowRead` switch.
6. Updated workflow docs, API gap map next order, parallel plan, progress dashboard, bootstrap docs, problem log, plan log, implementation log, and status log.

Verification summary:

- `php -l app\controller\biz\TaskController.php`: passed.
- `php -l app\controller\biz\ProcessController.php`: passed.
- `php -l app\controller\biz\CcRecordsController.php`: passed.
- `php -l app\service\workflow\WorkflowQueryService.php`: passed.
- `php -l app\service\workflow\WorkflowVariableService.php`: passed.
- `php -l app\service\biz\CcRecordsService.php`: passed.
- `.\scripts\workflow-read-http-smoke.ps1`: passed.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No workflow approve/reject/start/cancel call, no task SSE stream, no CC delete, no process write, no finance/inventory side effect, no job execution, no provider send, no frontend source edit, no Java source edit, no schema change, no `.env` edit, no production data operation, and no Git push.

## 2026-06-15 Sale-Project Billing Nested Read Smoke Coverage

Agent: merge-agent / api-agent fallback

Execution summary:

1. Reviewed sale-project billing routes, controllers, service methods, and compatibility docs.
2. Extended `scripts/business-read-http-smoke.ps1` to load local invoicing, invoice, invoice-item, and reissue-order sample ids.
3. Added authenticated read checks for `/biz/saleprojectinvoicing/page`, `/detail`, and `/customer`.
4. Added authenticated read checks for `/biz/saleprojectinvoice/page`, `/list`, `/biz/saleprojectinvoiceItem/page`, invoice-filtered invoice-item page, and `/biz/saleprojectreissueorder/list/query`.
5. Verified nested structures: `bizSaleProjectInvoice` plus `invoiceItems`, and `order` plus `productItemList`.
6. Updated billing/read docs, API gap map next order, parallel plan, progress dashboard, plan log, implementation log, and status log.

Verification summary:

- `php -l app\controller\biz\SaleProjectInvoicingController.php`: passed.
- `php -l app\controller\biz\SaleProjectInvoiceController.php`: passed.
- `php -l app\controller\biz\SaleProjectInvoiceItemController.php`: passed.
- `php -l app\controller\biz\SaleProjectReissueOrderController.php`: passed.
- `php -l app\service\biz\SaleProjectBillingService.php`: passed.
- `.\scripts\business-read-http-smoke.ps1`: passed.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No `/biz/saleprojectinvoicing/complete` call, no invoicing add/edit/delete, no delivery invoice write, no reissue-order write, no stock, no settlement, no finance, no workflow, no sale-project state write, no file cleanup, no provider send, no frontend source edit, no Java source edit, no schema change, no `.env` edit, no production data operation, and no Git push.

## 2026-06-15 Sale-Project Product Relation Read Smoke Coverage

Agent: merge-agent / api-agent fallback

Execution summary:

1. Reviewed product-info and product-item relation read controllers, services, routes, and compatibility docs.
2. Extended `scripts/business-read-http-smoke.ps1` to load local product-info and product-item relation sample ids.
3. Added authenticated read checks for `/biz/saleprojectproductinfo/page`, `/detail`, and bounded `/list?targetIds=...`.
4. Added authenticated read checks for `/biz/saleprojectproductitemrelation/list` using a Java-style JSON row body.
5. Verified product-info fields and relation fields including `productId`, `extJson`, product display aliases, project aliases, and relation child product fields.
6. Updated product-info/relation docs, API gap map next order, parallel plan, progress dashboard, plan log, implementation log, and status log.

Verification summary:

- `php -l app\controller\biz\SaleProjectProductInfoController.php`: passed.
- `php -l app\controller\biz\SaleProjectProductItemRelationController.php`: passed.
- `php -l app\service\biz\SaleProjectProductInfoService.php`: passed.
- `php -l app\service\biz\SaleProjectProductItemRelationService.php`: passed.
- `.\scripts\business-read-http-smoke.ps1`: passed.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No product-info add/edit/delete, no relation mark edit, no product-item mark edit, no product-item add/edit/delete, no delivery, no invoice write, no inventory, no finance, no workflow, no sale-project state write, no file cleanup, no provider send, no frontend source edit, no Java source edit, no schema change, no `.env` edit, no production data operation, and no Git push.

## 2026-06-15 Workflow Query List Guard

Agent: workflow-agent / merge-agent fallback

Execution summary:

1. Compared ThinkPHP `/biz/process/query/list` with Java `BizBaseProcessQueryParam`, which requires non-empty `processKeyList` and `attribute`.
2. Updated `ProcessController` to parse JSON request bodies for `query/list`, `variable`, and `fileList`, while retaining form/query fallbacks.
3. Updated `WorkflowQueryService::queryProcessList()` to reject missing process keys or attributes with controlled `400` responses.
4. Updated `scripts/workflow-read-http-smoke.ps1` to send JSON bodies via a temporary file, avoiding PowerShell/curl quote loss.
5. Added smoke coverage for the Java-style filtered query-list success path and the empty-filter guard.
6. Updated workflow docs, problem log, API gap map, parallel plan, progress dashboard, plan log, implementation log, and status log.

Verification summary:

- `php -l app\controller\biz\ProcessController.php`: passed.
- `php -l app\service\workflow\WorkflowQueryService.php`: passed.
- `.\scripts\workflow-read-http-smoke.ps1`: passed.
- `.\scripts\project-preflight.ps1`: passed.
- `git diff --check`: passed with existing line-ending warnings only.

Explicit non-goals:

- No workflow approve/reject/start/cancel, no task SSE stream, no process write, no Java delegate side effect, no finance/inventory side effect, no provider send, no frontend source edit, no Java source edit, no schema change, no `.env` edit, no production data operation, and no Git push.

