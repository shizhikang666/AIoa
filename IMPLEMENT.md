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

