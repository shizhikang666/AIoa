# PLANS.md

## Completed Plan: db-agent Phase 2 - Business Table Model Plan

Status: completed on 2026-05-28 after implementation and baseline checks.

### 1. Current Goal

Continue db-agent work after the foundation database mapping commit. The next db-agent slice should analyze and generate passive ThinkPHP Models for high-dependency OA business tables that later workflow-agent and api-agent will need.

This phase must not implement controller, service, route, auth, user, workflow, or frontend business logic.

### 2. Involved Modules

- db-agent only
- Java source analysis only under `F:\AI\projects\testJava\OA`
- ThinkPHP write target only under `F:\AI\projects\testJava\OA-db`

### 3. Involved Files

Expected Java analysis inputs:

- Primary SQL reference: `F:\AI\projects\testJava\OA\oa2026.sql`
- Historical SQL snapshot for comparison: `F:\AI\projects\testJava\OA\snowy-web-app\src\main\resources\_sql\2026\oa2026.sql`
- Java `biz` entity and mapper files under the Java OA project
- Existing db-agent docs under `F:\AI\projects\testJava\OA-db\docs\database`

Expected ThinkPHP outputs:

- `F:\AI\projects\testJava\OA-db\docs\database\biz-table-map.md`
- `F:\AI\projects\testJava\OA-db\docs\database\biz-model-plan.md`
- New passive model files under `F:\AI\projects\testJava\OA-db\app\model`

Candidate high-priority tables:

- `biz_cc_records`
- `biz_file_relation`
- `biz_leave_application`
- `biz_payment_record`
- `biz_expenditure_record`
- `biz_purchase_order`
- `biz_purchase_order_item`
- `biz_sale_project`
- `biz_sale_project_product_item`
- `biz_team_project`
- `biz_team_project_task`
- `customer`
- `supplier`
- `warehouses`
- `inventory`

### 4. Risks

- Business tables are numerous; this phase must stay small and prioritize dependency-heavy tables.
- Java entity names may not map one-to-one to table names.
- Some relations are stored in JSON or generic relation tables instead of SQL foreign keys.
- Generating too many models in one commit could violate the small-step rule.
- The updated root SQL may contain data needed for compatibility checks. Use it as read-only input only.
- Online realtime data sync is a final-stage requirement and must not be implemented until the system is complete and a confirmed migration/sync plan exists.

### 5. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }
## Completed Plan: auth-agent Phase 1 - Authentication Foundation Plan

Status: completed on 2026-05-28 for planning and public file request. Implementation is blocked until `route/app.php` change is confirmed.

### 1. Current Goal

Start auth-agent after db-agent completion. Analyze Java login, Token, RBAC, menu, and permission behavior, then prepare the smallest safe ThinkPHP implementation plan.

This phase must not implement auth business code until the required public file change is confirmed.

### 2. Involved Modules

- auth-agent only
- Java source analysis only under `F:\AI\projects\testJava\OA`
- ThinkPHP write target only under `F:\AI\projects\testJava\OA-auth`

### 3. Java Inputs

- `snowy-plugin-auth/src/main/java/vip/xiaonuo/auth/modular/login/controller/AuthController.java`
- `snowy-plugin-auth/src/main/java/vip/xiaonuo/auth/modular/login/service/impl/AuthServiceImpl.java`
- `snowy-plugin-auth/src/main/java/vip/xiaonuo/auth/core/config/AuthConfigure.java`
- `snowy-plugin-sys/src/main/java/vip/xiaonuo/sys/modular/user/provider/SysLoginUserApiProvider.java`
- `snowy-plugin-sys/src/main/java/vip/xiaonuo/sys/modular/user/service/impl/SysUserServiceImpl.java`

### 4. Expected ThinkPHP Outputs After Confirmation

- Auth controller for login, logout, and current user endpoints.
- Token service using Redis-compatible key conventions.
- RBAC service for roles, permission codes, button codes, and menu ids.
- Auth middleware for `Authorization: Bearer <token>`.
- Java auth behavior notes under `docs/tasks`.
- Status updates in `STATUS.md`.

### 5. Required Public File Change

`route/app.php` must be modified to expose Java-compatible auth routes:

- `GET /auth/b/getPicCaptcha`
- `POST /auth/b/doLogin`
- `GET /auth/b/doLogout`
- `GET /auth/b/getLoginUser`
- `POST /auth/b/safe/password`

The route file is locked by project rules, so implementation waits for confirmation. Details are written in `docs/tasks/public-file-change-request.md`.

### 6. Risks

- auth-agent branch does not include db-agent Model files until final merge. Auth code must be written to merge cleanly after `refactor/db`.
- Password compatibility depends on Java SM2/hash behavior; this phase should document and isolate password verification.
- Redis is required for Token/session state, but secrets and credentials must not be committed.
- Route changes are required for endpoint compatibility and must be approved.

### 7. Test Commands

```powershell
composer install --no-interaction --prefer-dist
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

`php think test` should be run only if a test command exists in the current ThinkPHP console.

### 6. Acceptance Criteria

- Java source project remains read-only.
- Only db-agent worktree is modified.
- No public locked files are modified.
- No database field is deleted, renamed, or altered.
- Added Models are passive database mapping classes only.
- New docs explain table purpose, fields, and relation notes for later agents.
- Tests above pass.
- Commit message includes `db-agent`.
- Single commit touches fewer than 30 files.

### 7. Forbidden Scope

- Do not modify `composer.json`, `composer.lock`, `config/app.php`, `config/database.php`, `route/app.php`, `.env`, `.env.example`, or `app/common.php`.
- Do not modify Java source files.
- Do not modify `F:\AI\projects\testJava\OA\oa2026.sql`; it is read-only reference input.
- Do not generate controllers, services, middleware, routes, API handlers, frontend code, or workflow runtime logic.
- Do not push remote branches unless explicitly requested.

## Final-Stage Reminder

Before project completion, remind the user that production/online realtime data must be synced into the final ThinkPHP OA project. Details are tracked in `docs/tasks/final-data-sync-reminder.md`.

## Completed Plan: db-agent Phase 3 - Sales Support Table Coverage

Status: completed on 2026-05-28 after implementation and baseline checks.

### Current Goal

Continue database coverage by analyzing sales project support tables that depend on the Phase 2 sales, customer, warehouse, and inventory foundations. Generate documentation and passive ThinkPHP Models only.

### Candidate Inputs

- Primary SQL reference: `F:\AI\projects\testJava\OA\oa2026.sql`
- Java entity and mapper files under `F:\AI\projects\testJava\OA`
- Current db-agent Models under `F:\AI\projects\testJava\OA-db\app\model`

### Candidate Scope

- Product master and product relation tables needed by sales order items.
- Sales project delivery, invoicing, reissue, follow-up, rating, field change, and return tables.
- Documentation updates under `docs/database`.
- Passive Model classes under `app/model` only.

Candidate tables:

- `biz_product`
- `product_relation`
- `biz_sale_project_invoice`
- `biz_sale_project_invoice_item`
- `biz_sale_project_invoicing`
- `biz_sale_project_product_info`
- `biz_sale_project_reissue_order`
- `sale_project_product_item_relation`
- `sale_project_follow_up`
- `customer_follow_up`
- `sale_project_rate`
- `sales_project_field_change_log`
- `return_order`
- `return_order_item`
- `delivery_record`

### Risks

- Java `ProductRelation` declares `PRODUCT_RELATION`, while the SQL dump contains `product_relation`; use the SQL physical table name for ThinkPHP.
- Some Java fields are translation-only with `@TableField(exist = false)` and must not become database columns.
- Follow-up, rating, and return tables reference users, organizations, customers, warehouses, and projects; only document those relations in this phase.
### 8. Acceptance Criteria

- Java source project remains read-only.
- Only auth-agent worktree is modified.
- No public locked file is changed without confirmation.
- No db/user/workflow/frontend business code is implemented.
- Auth plan and public file request are committed before route/business implementation.
- Commit message includes `auth-agent`.

### 9. Forbidden Scope

- Do not modify `F:\AI\projects\testJava\OA`.
- Do not modify `composer.json`, `composer.lock`, `config/app.php`, `config/database.php`, `route/app.php`, `.env`, `.env.example`, or `app/common.php` without the required change request and confirmation.
- Do not implement user management, workflow, frontend, or unrelated API modules.
- Do not push remote branches unless explicitly requested.

## Completed Plan: auth-agent Phase 2 - Login Token RBAC Skeleton

Status: completed on 2026-05-28 after user continued and route change request was treated as confirmed.

### Current Goal

Implement the first auth-agent code slice:

- Java-compatible B-side auth routes.
- Account/password login endpoint skeleton.
- Bearer Token generation, lookup, and revocation with `oa:auth:` cache key prefix.
- Current-login-user endpoint.
- RBAC payload assembly for roles, button codes, mobile button codes, permission codes, menu ids, and flat menu resources.
- Auth middleware for later protected routes.

### Involved Files

- `route/app.php`
- `app/controller/auth/AuthController.php`
- `app/service/auth/AuthService.php`
- `app/service/auth/TokenService.php`
- `app/service/auth/RbacService.php`
- `app/middleware/AuthMiddleware.php`
- `app/support/ApiResponse.php`
- `docs/tasks/auth-agent-phase2-notes.md`
- `STATUS.md`

### Risks

- Existing Java password compatibility uses SM2 decrypt plus SM3 hash. This first slice isolates password verification but does not claim full SM2 frontend compatibility yet.
- Redis credentials are not configured in this branch. Token state uses ThinkPHP Cache facade with Redis-compatible key names; final Redis store wiring remains a deployment/config task.
- Database runtime depends on final merge with db-agent table/model documentation and a valid database connection.

### Forbidden Scope

- Do not modify Java source files.
- Do not modify public locked files.
- Do not implement controller, service, route, auth, user, workflow, or frontend logic.
- Do not start production data synchronization.
- Do not modify database schema or seed data.
- Do not implement user CRUD, organization management, workflow, frontend, SMS sending, or web push behavior.
- Do not modify locked config files other than the confirmed `route/app.php`.

### Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

### Acceptance Criteria

- Java source project remains read-only.
- Only db-agent worktree is modified.
- Added Models are passive database mapping classes only.
- No public locked files are modified.
- New docs identify field groups and relation notes for later agents.
- Tests above pass.
- Commit message includes `db-agent`.

## Completed Plan: db-agent Phase 4 - Finance Settlement Table Coverage

Status: completed on 2026-05-28 after implementation and baseline checks.

### Current Goal

Continue database coverage for finance and settlement support tables that connect payment/expenditure records to settlement accounts and account statements.

### Candidate Scope

- Finance and settlement: `biz_collection_receipt`, `biz_debit_note`, `settlement_account`, `settlement_account_statement`.
- Documentation updates under `docs/database`.
- Passive Model classes under `app/model` only.

### Deferred Scope

- Collaboration around team projects: `biz_team_project_comment`, `biz_team_project_comment_reply`, `biz_team_project_task_comment`, `biz_team_project_task_category`, `biz_team_project_user`, `biz_team_project_task_user`.
- Other support records only if a later db-agent slice needs them.

### Risks

- `settlement_account.org` is lower-case in SQL and must remain unchanged.
- `biz_collection_receipt` and `biz_debit_note` depend on payment/expenditure record ids already modeled in Phase 2.
- Settlement account statements store process identifiers, but workflow behavior is out of scope for db-agent.
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

## Completed Plan: auth-agent Phase 3 - Password Compatibility

Status: completed on 2026-05-28 after Phase 2 commit.

### Current Goal

Add the smallest safe password compatibility slice based on Java and `oa2026.sql` analysis:

- Match Java's `CommonCryptogramUtil.doHashValue()` SM3 password storage format.
- Verify that imported `sys_user.PASSWORD` values such as the default password hash can be checked from ThinkPHP.
- Keep SM2 private-key decryption out of committed code and document the remaining compatibility boundary.
- Fix safe-password verification so it checks the current user's password before opening the short-lived safe window.

### Involved Files

- `app/service/auth/AuthService.php`
- `app/service/auth/PasswordService.php`
- `app/service/auth/Sm3Hasher.php`
- `docs/tasks/auth-agent-phase3-password-compat.md`
- `PLANS.md`
- `STATUS.md`

### Risks

- Full old-frontend compatibility still needs an SM2 decrypt adapter or frontend-agent must adapt password submission. No SM2 private key may be committed.
- Password handling must not allow direct pass-the-hash login.
- Runtime login still depends on a configured database and cache store.

### Forbidden Scope

- Do not modify Java source files.
- Do not modify public locked files.
- Do not implement controller, service, route, auth, user, workflow, or frontend logic.
- Do not start production data synchronization.
- Do not write SM2 private keys, API keys, passwords, or secrets.
- Do not modify locked public config files.
- Do not implement user CRUD, organization management, workflow, frontend, or unrelated API modules.

### Test Commands

```powershell
php -r "require 'vendor/autoload.php'; echo app\\service\\auth\\Sm3Hasher::hash('abc');"
php -r "require 'vendor/autoload.php'; echo app\\service\\auth\\Sm3Hasher::hash('123456');"
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

## Completed Plan: auth-agent Phase 4 - Login Menu Compatibility

Status: completed on 2026-05-28 after the user allowed the main agent to decide the parallel plan.

### Current Goal

Implement the next auth-agent slice for authorized menu tree compatibility without crossing into user-agent ownership:

- Add `GET /sys/userCenter/loginMenu` compatibility route.
- Build the authorized menu tree from user/role resource relations.
- Keep the implementation limited to auth/RBAC menu data.
- Avoid user profile, organization, position, workbench, and message APIs.

### Involved Files If Confirmed

- `app/service/auth/RbacService.php`
- `app/service/auth/MenuService.php`
- `app/controller/auth/UserCenterAuthController.php`
- `docs/tasks/auth-agent-phase4-menu-compat.md`
- `STATUS.md`
- `route/app.php`

### Risk

The Java/old frontend path for menu loading is `/sys/userCenter/loginMenu`. Although the data comes from RBAC menu permissions, the path belongs to user center. auth-agent must implement only this menu compatibility route and leave the rest of user center to user-agent.

### Proposed Ownership Options

- Do not implement user center profile, organization, position, workbench, process config, or message APIs.
- Do not modify Java source files.
- Do not modify database schema or seed data.
- Do not modify locked public files other than the confirmed `route/app.php` route addition.

### Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

## Completed Plan: db-agent Phase 5 - Team Collaboration Support Tables

Status: completed on 2026-05-28 after implementation and baseline checks.

### Current Goal

Add passive database coverage for team project comments, task comments, task categories, and team/task user relation tables.

### Candidate Scope

- `biz_team_project_comment`
- `biz_team_project_comment_reply`
- `biz_team_project_task_comment`
- `biz_team_project_task_category`
- `biz_team_project_user`
- `biz_team_project_task_user`

### Forbidden Scope

- Do not modify Java source files.
- Do not modify public locked files.
- Do not implement team collaboration controller, service, route, workflow, or frontend behavior.

### Risks

- Several Java display fields are marked `@TableField(exist = false)` and must remain documentation-only.
- `biz_team_project_task_comment` can represent both logs and comments through `CATEGORY`; behavior is out of db-agent scope.
- Role and permission interpretation for team members belongs to later business agents.

### Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

## Completed Plan: db-agent Phase 6 - Remaining Support Tables Audit

Status: completed on 2026-05-28 after audit and baseline checks.

### Current Goal

Audit remaining Java entity to SQL table mappings and decide whether db-agent should add more passive Models or stop and hand over to auth-agent.

### Candidate Scope

- `biz_draft`
- `biz_history_excel`
- `biz_payroll`
- `biz_user_vacation`
- `BIZ_RELATION`
- Shared dev tables only if later agents require them directly.

### Decision Gate

If the remaining tables are not required for auth-agent/user-agent/api-agent startup, stop db-agent and move to auth-agent according to the original staged order.

### Decision

db-agent can stop after Phase 6. Remaining low-priority support/shared tables do not block auth-agent startup. They can be handled later by the relevant module agent or by db-agent if a concrete dependency appears.

## Current Plan: db-agent Phase 7 - Workflow Engine Table Models
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

## Current Plan: auth-agent Phase 5 - Frontend Response Compatibility

Status: in progress on 2026-05-29.

### Current Goal

Add passive ThinkPHP Model coverage for Camunda-style workflow `act_*` tables required by workflow-agent read-only query services.

### Candidate Scope

- `act_ge_bytearray`
- `act_re_deployment`
- `act_re_procdef`
- `act_ru_execution`
- `act_ru_task`
- `act_ru_variable`
- `act_ru_identitylink`
- `act_hi_procinst`
- `act_hi_taskinst`
- `act_hi_varinst`
- `act_hi_actinst`
- `act_hi_comment`
- `act_hi_identitylink`
Add Java frontend-compatible `msg` response field while preserving the ThinkPHP target `message` field.

### Involved Files

- `app/support/ApiResponse.php`
- `PLANS.md`
- `STATUS.md`

### Risk

The old Vue frontend reads `data.msg` in the request interceptor, while project docs standardize on `message`. Returning both avoids breaking frontend behavior without changing service logic.

### Forbidden Scope

- Do not modify Java source files.
- Do not modify database schema or SQL seed data.
- Do not implement workflow runtime, controllers, services, routes, or side effects.
- Do not modify public locked files.

### Risks

- Camunda tables use `ID_` primary keys and underscore-suffixed columns, so model naming must preserve SQL compatibility.
- These models are only passive table access foundations; workflow semantics belong to workflow-agent.
- Do not modify route or config files.
- Do not implement frontend code in auth-agent.
- Do not change token, RBAC, menu, or password behavior.

### Test Commands

```powershell
Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }
composer dump-autoload
php think
php think route:list
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```
