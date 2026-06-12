锘块敇鍧楁晣閸ф鏅ｉ柛褎顨嗛弲? PLANS.md

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
# workflow-agent Plans

## Active Plan: Phase 1 - Workflow Analysis And Planning

Date: 2026-05-28

### 1. Current Goal

Start workflow-agent first phase for the Java OA to ThinkPHP refactor.

This phase only analyzes and plans the workflow module. It does not implement business Controller, Service, route, Model, or database changes.

### 2. Module Scope

- Approval flows
- Process definitions
- Process instances
- Approval task records
- Copy-to records
- User workflow configuration

### 3. Files To Modify

Only workflow-agent worktree files:
# PLANS.md

## Active Plan: test-agent Phase 1 - Baseline Test Plan

Status: completed on 2026-05-28.

### 1. Current Goal

Start test-agent baseline work for the Java OA to ThinkPHP refactor. This phase only checks the current ThinkPHP worktree health and records a reusable test plan for later multi-branch merges.

### 2. Modules In Scope

- test-agent baseline checks
- multi-worktree merge test planning
- syntax, route, namespace, Composer, and ThinkPHP console checks

### 3. Files In Scope
# PLANS.md

## Active Plan: docs-agent Phase 1 - Parallel Refactor Documentation

Status: completed locally, pending commit.

Date: 2026-05-28

## Current Goal

Start docs-agent phase 1 and document the current multi-agent parallel refactor status, final merge rules, and post-launch realtime data sync reminder.

## Module

docs-agent only.

## Files In Scope

- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`
- `docs/tasks/workflow-agent-java-map.md`
- `docs/tasks/workflow-agent-phase1-notes.md`
- `docs/tasks/workflow-table-map.md`

### 4. Java Read-Only Inputs

- `F:\AI\projects\testJava\OA\bpmn`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-biz\src\main\java\vip\xiaonuo\biz\modular\bizprocess`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-sys\src\main\java\vip\xiaonuo\sys\modular\userprocessconfig`
- `F:\AI\projects\testJava\OA\oa2026.sql`

### 5. Risks

- Java project uses Camunda engine APIs and `act_*` tables; ThinkPHP needs a later design decision before runtime workflow implementation.
- BPMN files contain Java delegate class names; direct execution in PHP is not possible without replacing delegate behavior.
- Workflow tables share business side effects with finance, sale project, warehouse, procurement, and leave modules.
- Some Java source comments are mojibake, so analysis should rely on class names, route paths, enum values, BPMN IDs, SQL comments, and method behavior.

### 6. Test Commands
- `docs/tasks/parallel-agent-status.md`
- `docs/tasks/final-merge-checklist.md`
- `docs/tasks/post-launch-data-sync-reminder.md`

## Risks

- Worktrees may drift if Agents do not commit and report status before merge.
- Public files such as `route/app.php` may create conflicts if multiple Agents change them without a request record.
- The final project can become fragmented if worktrees are treated as final deliverables.
- Post-launch realtime data sync can be forgotten if it is not kept as an explicit release task.

## Test Commands

```powershell
git status --short --branch
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

If the ThinkPHP console later exposes a test command:

```powershell
php think test
```

### 7. Acceptance Criteria

- Workflow Java routes, services, delegates, BPMN files, and SQL tables are mapped in docs.
- No Java source files are modified.
- No locked public files are modified.
- No ThinkPHP business code is generated.
- Git commit is created with message `workflow-agent: add workflow analysis plan`.

### 8. Forbidden Scope

- Do not modify `F:\AI\projects\testJava\OA`.
- Do not modify `composer.json`, `composer.lock`, `config/app.php`, `config/database.php`, `route/app.php`, `.env`, `.env.example`, or `app/common.php`.
- Do not modify auth-agent or user-agent paths.
- Do not implement workflow Controller, Service, Model, or route in this phase.

## Active Plan: Phase 2 - Workflow Runtime Strategy

Date: 2026-05-29

### 1. Current Goal

Choose and document the safest ThinkPHP workflow runtime strategy before implementing workflow business code.

### 2. Module Scope

- Workflow runtime migration strategy
- Workflow API migration order
- Approval side-effect replacement map
- Existing `act_*` table compatibility

### 3. Files To Modify

Only workflow-agent worktree files:

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/workflow-runtime-design.md`
- `docs/tasks/workflow-api-map.md`
- `docs/tasks/workflow-side-effect-map.md`

### 4. Risks

- Direct Camunda execution is Java-specific and cannot be run inside ThinkPHP.
- Full workflow runtime implementation is high risk without merged auth/user/db foundations.
- Delegate side effects touch sales, finance, warehouse, procurement, and personnel modules.
- `docs/tasks/test-agent-baseline.md`
- `docs/tasks/test-agent-risk-list.md`

### 4. Risks

- Module branches may diverge and produce route, namespace, or model conflicts during final merge.
- `php think route:list` may fail after route files are changed by later agents.
- Composer dependency drift can appear if multiple branches edit dependency files.
- Database/model changes from db-agent may be required before auth/user/workflow tests are meaningful.

### 5. Test Commands
```

## Acceptance Criteria

- docs-agent rules files exist in `F:\AI\projects\testJava\OA-docs`.
- Parallel Agent status is documented.
- Final merge checklist is documented.
- Post-launch realtime data sync reminder is documented.
- Java source project remains read-only.
- No business code or locked public files are modified.
- Changes are committed with message `docs-agent: update parallel refactor docs`.

## Forbidden Scope

- Do not modify `F:\AI\projects\testJava\OA`.
- Do not modify business code.
- Do not modify locked public files.
- Do not push to remote in this phase.

## Active Plan: docs-agent Phase 2 - Autonomous Execution Rules

Status: in progress.

Date: 2026-05-29

## Current Goal

Document how the main control Agent can keep moving autonomously while preserving safety boundaries for a long-running multi-agent refactor.

## Files In Scope

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/autonomous-execution-rules.md`
- `docs/tasks/parallel-agent-status.md`

## Risks

- Over-broad authorization could allow unsafe edits to Java source, production data, locked config files, or database schema.
- Under-specified authorization will keep causing avoidable pauses for safe recurring commands.
- Merge and route registration still need explicit scope rules because they touch integration behavior.

## Test Commands

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
# PLANS.md

## Completed Plan: user-agent Phase 1 - User Organization Analysis
# PLANS.md

## Completed Plan: api-agent Phase 1 - Controller Inventory And Route Boundary

Status: completed on 2026-05-28.

### Current Goal

Start user-agent without modifying business code. Analyze Java user, organization, position, and user-center modules, then prepare the smallest safe implementation path for later phases.

### Involved Modules

- user-agent worktree: `F:\AI\projects\testJava\OA-user`
- Java source, read-only: `F:\AI\projects\testJava\OA`
- SQL source, read-only: `F:\AI\projects\testJava\OA\oa2026.sql`

### Java Inputs

- `snowy-plugin-sys/.../user/controller/SysUserController.java`
- `snowy-plugin-sys/.../user/controller/SysUserCenterController.java`
- `snowy-plugin-sys/.../org/controller/SysOrgController.java`
- `snowy-plugin-sys/.../position/controller/SysPositionController.java`
- `snowy-plugin-sys/.../user/service/impl/SysUserServiceImpl.java`
- `snowy-plugin-sys/.../org/service/impl/SysOrgServiceImpl.java`
- `snowy-plugin-sys/.../position/service/impl/SysPositionServiceImpl.java`

### Database Inputs

- `sys_user`
- `sys_org`
- `sys_position`
- `sys_relation`
- `sys_user_process_config`

### Risks

- `GET /sys/userCenter/loginMenu` is already handled by auth-agent for menu compatibility; user-agent must not add a duplicate route.
- User management overlaps RBAC grants through `sys_relation`; user-agent must coordinate with auth-agent for role/resource/permission grants.
- `PHONE`, identity, and some profile fields may be encrypted in Java through common cryptogram utilities.
- User import/export and file upload should be deferred until API and storage conventions are stable.
Start api-agent without modifying business code. Inventory Java controllers, define API ownership boundaries, and prepare a safe route integration path for later phases.

### Involved Modules

- api-agent worktree: `F:\AI\projects\testJava\OA-api`
- Java source, read-only: `F:\AI\projects\testJava\OA`
- Final ThinkPHP target: `F:\AI\projects\testJava\OA-ThinkPHP`

### Java Inputs

- Java controller files discovered with `rg --files -g "*Controller.java" F:\AI\projects\testJava\OA`
- Auth controller group under `snowy-plugin-auth`
- System controller group under `snowy-plugin-sys`
- Business controller group under `snowy-plugin-biz`
- Development support controller group under `snowy-plugin-dev`
- Mobile controller group under `snowy-plugin-mobile`
- Generator, tenant, and client plugin controllers

### Risks

- `route/app.php` is a locked public file and must not be changed by api-agent without an approved change request.
- auth-agent already owns login, token, RBAC, permissions, and login menu compatibility routes.
- user-agent owns user, organization, and position service behavior; api-agent must not reimplement those services.
- workflow-agent owns approval/process behavior; api-agent must not reimplement process engines.
- Some Java endpoints perform import, export, file upload, SSE, scheduled jobs, and code generation; these need deferred integration decisions.

### Forbidden Scope

- Do not modify Java source files.
- Do not modify public locked files.
- Do not implement controller, service, route, auth, user, workflow, or frontend logic.
- Do not start production data synchronization.
- Do not modify database schema or seed data.
- Do not implement user CRUD, organization management, workflow, frontend, SMS sending, or web push behavior.
- Do not modify locked config files other than the confirmed `route/app.php`.
- Do not modify locked public files.
- Do not implement auth, workflow, frontend, or unrelated business modules.
- Do not delete database fields or seed data.
- Do not add routes in Phase 1.
- Do not modify locked public files in Phase 1.
- Do not create ThinkPHP Controller implementations in Phase 1.
- Do not implement auth, user, workflow, frontend, or database business logic.
- Do not delete fields, tables, seed data, or routes.

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
## Current Plan: user-agent Phase 2 - Read-Only Directory Services
## Current Plan: api-agent Phase 3 - User Directory Controller Adapters

## Current Plan: merge-agent Phase - Frontend Read-Only Selector Compatibility

Status: in progress on 2026-05-29.

### Current Goal

Close the next frontend compatibility gap after runtime auth smoke tests by adding only low-risk read-only selector and list aliases used by the existing Vue API modules.

### Involved Files

- `app/service/user/UserDirectoryService.php`
- `app/service/user/OrgService.php`
- `app/service/user/PositionService.php`
- `app/controller/sys/UserController.php`
- `app/controller/sys/OrgController.php`
- `app/controller/sys/PositionController.php`
- `app/controller/sys/UserCenterController.php`
- `route/app.php`
- `docs/api/frontend-readonly-selector-compat.md`
- `docs/tasks/public-file-change-request.md`
- `STATUS.md`

### Risks

- `route/app.php` is a locked public file, so the route additions must stay documented and limited to read-only frontend compatibility endpoints.
- User, organization, position, and role selectors overlap user-agent and auth-agent ownership. This phase must only expose already available read-only data and must not add grants, writes, imports, exports, uploads, or workflow mutations.
- User read APIs must not leak password hashes.

### Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

Runtime smoke checks should use a bearer token and cover the newly added selector/list endpoints.

## Current Plan: merge-agent Phase - Protect System Directory Routes

Status: in progress on 2026-05-29.

### Current Goal

Attach `AuthMiddleware` to the read-only system directory route groups so organization, position, and user directory data is only available to authenticated requests.

### Involved Files

- `route/app.php`
- `docs/tasks/public-file-change-request.md`
- `STATUS.md`
- `PLANS.md`

### Risks

- This changes unauthenticated access behavior for existing read-only system routes from public to `401`.
- Old frontend requests should still pass because authenticated pages send the bearer token after login.

### Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

Runtime smoke must verify token requests return `200` and no-token requests return `401` for `/sys/org/tree`, `/sys/position/page`, and `/sys/user/page`.

## Current Plan: merge-agent Phase - RBAC Role Read-Only Compatibility

Status: in progress on 2026-05-29.

### Current Goal

Add read-only `/sys/role/*` compatibility endpoints used by the existing Vue role management page, without implementing role writes or grant mutations.

### Involved Files

- `app/service/auth/RoleService.php`
- `app/controller/sys/RoleController.php`
- `route/app.php`
- `docs/api/rbac-role-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `STATUS.md`

### Risks

- Role grant viewing reads relation data, but grant mutation must remain deferred.
- Resource and mobile menu tree payloads are compatibility approximations based on current database rows.
- Permission tree data is derived from existing `sys_relation` permission targets until route-level permission metadata is fully modeled.

### Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

Runtime smoke should cover role page/detail/selectors/own* routes with a bearer token and confirm no-token requests return `401`.

## Current Plan: merge-agent Phase - Auth SM2 Transport Compatibility

Status: in progress on 2026-05-29.

### Current Goal

Add a safe optional SM2 decrypt adapter for legacy Vue login password transport without committing private key material.

### Involved Files

- `app/service/auth/Sm2Decryptor.php`
- `app/service/auth/PasswordService.php`
- `app/service/auth/AuthService.php`
- `docs/api/auth-sm2-compatibility.md`
- `docs/tasks/runtime-verification-plan.md`
- `PLANS.md`
- `STATUS.md`

### Risks

- Password transport compatibility needs private key material at runtime. The key must be supplied only through local/secure environment configuration and must never be committed.
- Pure PHP SM2 math relies on `bcmath`; runtime checks must confirm the extension is loaded.
- Existing plaintext local smoke tests must continue to pass.

### Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

Runtime smoke must confirm plaintext login still succeeds. SM2 encrypted login should be tested only when `AUTH_SM2_PRIVATE_KEY` is configured locally.

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
Add a minimal read-only service layer for organization, position, and user directory queries. Do not add routes or controllers in this phase.

### Involved Modules

- user-agent worktree: `F:\AI\projects\testJava\OA-user`
- Java source, read-only: `F:\AI\projects\testJava\OA`
- SQL source, read-only: `F:\AI\projects\testJava\OA\oa2026.sql`
- db-agent model dependency after final merge: `SysUser`, `SysOrg`, `SysPosition`, `SysRelation`

### Involved Files

- `app/service/user/TreeBuilder.php`
- `app/service/user/OrgService.php`
- `app/service/user/PositionService.php`
- `app/service/user/UserDirectoryService.php`
- `docs/tasks/user-agent-phase2-services.md`
- `STATUS.md`

### Risks

- The current `refactor/user` branch does not contain db-agent model files yet; these services are intended for the final merged branch after `refactor/db` lands first.
- Route registration requires `route/app.php`, which is locked. This phase intentionally does not add routes.
- Write operations, permission grants, import/export, uploads, and password/profile mutation remain deferred.
Add thin read-only Controller adapters for user, organization, position, and user-center directory endpoints without registering routes.

### Involved Files

- `app/controller/sys/BaseSysController.php`
- `app/controller/sys/OrgController.php`
- `app/controller/sys/PositionController.php`
- `app/controller/sys/UserController.php`
- `app/controller/sys/UserCenterController.php`
- `docs/api/user-directory-controller-adapters.md`
- `PLANS.md`
- `STATUS.md`

### Risks

- These adapters reference user-agent services and auth-agent `ApiResponse`; runtime validation must happen after final merge order brings db/auth/user before api.
- `route/app.php` remains locked and is not modified in this phase.

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
- Do not modify locked public files.
- Do not create ThinkPHP controllers or route entries in this phase.
- Do not implement auth, RBAC, menu, workflow, or frontend behavior.
- Do not change database fields or seed data.
- Do not modify `route/app.php`.
- Do not implement user service logic inside controllers.
- Do not implement write endpoints, grants, import/export, upload, or workflow endpoints.

### Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

## Current Plan: api-agent Phase 4 - Workflow Read-Only Controller Adapters

Status: in progress on 2026-05-29.

### Current Goal

Add thin read-only Controller adapters for workflow task/process query endpoints without registering routes.

### Involved Files

- `app/controller/biz/BaseWorkflowController.php`
- `app/controller/biz/TaskController.php`
- `app/controller/biz/ProcessController.php`
- `docs/api/workflow-readonly-controller-adapters.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `STATUS.md`

### Risks

- These adapters reference workflow-agent services and auth-agent `ApiResponse`; runtime validation must happen after final merge order brings db/auth/workflow before api.
- `route/app.php` remains locked and is not modified in this phase.

### Forbidden Scope

- Do not modify Java source files.
- Do not modify `route/app.php`.
- Do not implement workflow service logic inside controllers.
- Do not implement approve, reject, cancel, process start, delegate side effects, SSE, upload, or business mutations.

### Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

## Current Plan: api-agent Phase 2 - User Directory Route Request

Status: in progress on 2026-05-29.

### Current Goal

Prepare the route and controller integration request for read-only user, organization, and position APIs. Do not edit `route/app.php` in this phase.

### Involved Modules

- api-agent worktree: `F:\AI\projects\testJava\OA-api`
- user-agent service dependency after merge: `OrgService`, `PositionService`, `UserDirectoryService`
- auth-agent dependency after merge: auth middleware and response helper conventions
- final integration branch: `refactor/thinkphp-main`

### Involved Files

- `docs/api/user-directory-route-map.md`
- `docs/tasks/public-file-change-request.md`
- `STATUS.md`

### Risks

- `route/app.php` is locked and cannot be modified without confirmation.
- API controller implementation should wait until user-agent services are merged before api-agent.
- Response helper should align with auth-agent's API response conventions to avoid duplicate helpers.

### Forbidden Scope

- Do not modify Java source files.
- Do not edit `route/app.php`.
- Do not implement controllers in this phase.
- Do not implement user, auth, workflow, or database service logic.

### Test Commands

```powershell
Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }
composer dump-autoload
php think
php think route:list
### 6. Acceptance Criteria

- A workflow runtime strategy is documented.
- API migration batches are documented.
- Java delegate side effects are mapped to future PHP services.
- No Java source files are modified.
- No ThinkPHP workflow code, routes, models, or database changes are added.

## Current Plan: Phase 3 - Read-Only Workflow Query Services

Date: 2026-05-29

### 1. Current Goal

Add read-only workflow query service classes for pending tasks, history tasks, started processes, process detail, and variable normalization.

### 2. Module Scope

- Runtime task reads.
- Historic task reads.
- Historic process instance reads.
- Historic activity and comment reads.
- Runtime/history variable normalization.

### 3. Files To Modify

Only workflow-agent worktree files:

- `PLANS.md`
- `STATUS.md`
- `app/service/workflow/WorkflowVariableService.php`
- `app/service/workflow/WorkflowQueryService.php`
- `docs/tasks/workflow-query-services.md`

### 4. Risks

- The current workflow branch does not yet contain db-agent `Act*` models; final merged branch must merge `refactor/db` first.
- Query services are read-only and intentionally do not mutate workflow state.
- Process variable value compatibility needs real data tests after database configuration exists.

### 5. Forbidden Scope

- Do not modify Java source files.
- Do not modify locked public files.
- Do not add routes or controllers.
- Do not implement approve, reject, cancel, process start, or side effects.

### 6. Test Commands
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

Optional when available:

```powershell
php think test
```

### 6. Acceptance Criteria

- The test-agent worktree stays on `refactor/test`.
- No locked public files are modified.
- No Controller, Service, Model, or business files are modified.
- Baseline test results are recorded.
- Future merge test scope for db/auth/user/workflow/api/frontend/docs is documented.
- Work is committed with a test-agent commit message.

### 7. Not Allowed

- Do not modify `F:\AI\projects\testJava\OA`.
- Do not modify locked public files:
  - `composer.json`
  - `composer.lock`
  - `config/app.php`
  - `config/database.php`
  - `route/app.php`
  - `.env`
  - `.env.example`
  - `app/common.php`
- Do not modify business Controller, Service, Model, route implementation, or database schema.

## Active Plan: test-agent Phase 2 - Integration Test Matrix

Status: in progress on 2026-05-29.

### 1. Current Goal

Document the merge-time integration test matrix for the currently prepared db/auth/user/workflow/api/frontend contracts.

### 2. Modules In Scope

- merge-time Composer checks
- ThinkPHP console checks
- route list checks
- PHP lint checks
- auth response contract checks
- read-only user directory route checks
- read-only workflow route checks

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/integration-test-matrix.md`

### 4. Risks

- Current test-agent branch does not contain module code until final merge, so this phase is a test matrix rather than executable route tests.
- Runtime endpoint checks need database/cache configuration after merge.
- Route registrations are still pending approval in `route/app.php`.

### 5. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```
```

### 6. Acceptance Criteria

- Integration test matrix is documented.
- Baseline ThinkPHP checks still pass in test-agent worktree.
- No locked public files or business code are modified.
git status --short --branch
```

## Acceptance Criteria

- Autonomous execution rules include allowed actions, stop conditions, and a copyable user authorization statement.
- Branch push/sync status is documented.
- No business code or locked public files are modified.

## Active Plan: merge-agent - User Center Read-Only Compatibility

Status: in progress on 2026-05-29.

### 1. Current Goal

Add old-frontend-compatible read-only user-center endpoints for login workbench, process config, and login user messages.

### 2. Modules In Scope

- user-center read-only API compatibility
- system relation workbench lookup
- user process config read lookup
- dev message read lookup

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/user/UserDirectoryService.php`
- `app/controller/sys/UserCenterController.php`
- `route/app.php`
- `docs/api/user-center-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java message detail marks messages as read, but this phase is read-only, so the ThinkPHP compatibility endpoint must not update `dev_relation`.
- `route/app.php` is a locked public file; the route change is documented as a merge-agent public file request.
- Process config defaults are derived from existing SQL process names when the user has no saved config.

### 5. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only user-center endpoints are added.
- No update profile, update workbench, process config edit, message mark-read, or other write routes are added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks pass.

## Active Plan: merge-agent - Index Read-Only Compatibility

Status: in progress on 2026-05-29.

### 1. Current Goal

Add old-frontend-compatible read-only `/sys/index/*` endpoints for homepage schedule, message panel, and current user logs.

### 2. Modules In Scope

- homepage read-only API compatibility
- schedule list lookup from `sys_relation`
- message list/page/detail lookup from `dev_message` and `dev_relation`
- current user visit/operation logs from `dev_log`

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/user/UserDirectoryService.php`
- `app/service/sys/IndexService.php`
- `app/controller/sys/IndexController.php`
- `route/app.php`
- `docs/api/index-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java message detail and all-message-mark-read can update read status; this phase must keep all index message endpoints read-only.
- `route/app.php` is a locked public file, so the route change is documented as a merge-agent public file request.
- SSE and schedule write endpoints are deferred to avoid adding long-running connections or mutations in this slice.

### 5. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/sys/index/*` routes are added.
- No schedule add/delete, all-message-mark-read, or SSE routes are added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Biz Expenditure Record Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only expenditure-record endpoints for expense list and detail views.

### 2. Modules In Scope

- expenditure record read-only API compatibility
- `/biz/bizexpenditurerecord/page`
- `/biz/bizexpenditurerecord/listDetails`
- `/biz/bizexpenditurerecord/list`
- `/biz/bizexpenditurerecord/detail`
- settlement-account display enrichment
- organization-name display enrichment

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/ExpenditureRecordService.php`
- `app/controller/biz/ExpenditureRecordController.php`
- `route/app.php`
- `docs/api/biz-expenditure-record-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java expenditure-record edits can update settlement statements and switch account balances, so edit routes must remain deferred.
- Frontend has add/delete wrappers, but the analyzed Java controller does not expose add/delete; those remain excluded from this read-only slice.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\biz\ExpenditureRecordService.php
php -l app\controller\biz\ExpenditureRecordController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/biz/bizexpenditurerecord/page`, `/biz/bizexpenditurerecord/listDetails`, `/biz/bizexpenditurerecord/list`, and `/biz/bizexpenditurerecord/detail` routes are added.
- No expenditure-record add/edit/delete/account-switch route is added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Biz Collection Receipt Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only collection-receipt endpoints for received-on-behalf list views and optional detail lookup.

### 2. Modules In Scope

- collection receipt read-only API compatibility
- `/biz/bizcollectionreceipt/page`
- `/biz/bizcollectionreceipt/list`
- `/biz/bizcollectionreceipt/detail`
- payment-record display enrichment
- settlement-account display enrichment
- organization-name display enrichment through the linked payment record

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/CollectionReceiptService.php`
- `app/controller/biz/CollectionReceiptController.php`
- `route/app.php`
- `docs/api/biz-collection-receipt-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java mark-success is now covered as a single-table status update; batch-expenditure mutates expenditure records and settlement-account side effects, so it remains deferred.
- Java add/edit/delete/detail mappings are commented out in the analyzed controller, while the old frontend still has a detail wrapper; this slice exposes detail only as a read-only compatibility endpoint.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\biz\CollectionReceiptService.php
php -l app\controller\biz\CollectionReceiptController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Read-only `/biz/bizcollectionreceipt/page`, `/biz/bizcollectionreceipt/list`, and `/biz/bizcollectionreceipt/detail` routes are available.
- `POST /biz/bizcollectionreceipt/mark/success/edit` is now available as a single-table status update.
- No collection-receipt add/edit/delete/batch-expenditure route is added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Biz Debit Note Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only debit-note endpoints for loan/payment-on-behalf list and detail views.

### 2. Modules In Scope

- debit note read-only API compatibility
- `/biz/bizdebitnote/page`
- `/biz/bizdebitnote/list`
- `/biz/bizdebitnote/detail`
- expenditure-record display enrichment
- settlement-account display enrichment
- organization-name display enrichment

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/DebitNoteService.php`
- `app/controller/biz/DebitNoteController.php`
- `route/app.php`
- `docs/api/biz-debit-note-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java debit-note mark success is now covered as a single-table status update; history add and batch repayment still mutate payment records and settlement accounts, so they remain deferred.
- Java list/page joins expenditure records only when account/category filters are present. This slice always enriches read rows by the linked expenditure record but keeps mutation routes excluded.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\biz\DebitNoteService.php
php -l app\controller\biz\DebitNoteController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Read-only `/biz/bizdebitnote/page`, `/biz/bizdebitnote/list`, and `/biz/bizdebitnote/detail` routes are available.
- `POST /biz/bizdebitnote/mark/success/edit` is now available as a single-table status update.
- No debit-note history add, batch-repayment, add, edit, or delete route is added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Active Plan: merge-agent - System Resource Read-Only Compatibility

Status: in progress on 2026-05-29.

### 1. Current Goal

Add old-frontend-compatible read-only system resource endpoints for modules, menus, and buttons so RBAC/menu management pages can load existing data.

### 2. Modules In Scope

- system resource read-only API compatibility
- module pagination/detail
- menu pagination/tree/detail/selectors
- button pagination/detail

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/sys/ResourceService.php`
- `app/controller/sys/ModuleController.php`
- `app/controller/sys/MenuController.php`
- `app/controller/sys/ButtonController.php`
- `route/app.php`
- `docs/api/resource-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- `route/app.php` is a locked public file, so this route change must stay documented and limited to read-only endpoints.
- Resource write routes can affect role grants, menu permissions, and frontend routing; they remain deferred.
- Tree payloads must stay compatible enough for old Vue selectors while preserving original database field meanings.

### 5. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/sys/module/*`, `/sys/menu/*`, and `/sys/button/*` routes are added.
- No module/menu/button add, edit, delete, change-module, or grant mutation routes are added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Active Plan: merge-agent - Mobile Resource Read-Only Compatibility

Status: in progress on 2026-05-29.

### 1. Current Goal

Add old-frontend-compatible read-only mobile resource endpoints for mobile modules, menus, and buttons.

### 2. Modules In Scope

- mobile resource read-only API compatibility
- mobile module pagination/detail
- mobile menu tree/detail/selectors
- mobile button pagination/detail

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/mobile/MobileResourceService.php`
- `app/controller/mobile/ModuleController.php`
- `app/controller/mobile/MenuController.php`
- `app/controller/mobile/ButtonController.php`
- `route/app.php`
- `docs/api/mobile-resource-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Mobile resource write routes clean role/mobile-menu grant relations, so they remain deferred.
- `route/app.php` is locked and must only receive documented protected read-only routes.
- Mobile menu tree ordering differs from system menu tree in Java; this slice preserves descending sort for `tree`.

### 5. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/mobile/module/*`, `/mobile/menu/*`, and `/mobile/button/*` routes are added.
- No mobile module/menu/button add, edit, delete, change-module, or grant mutation routes are added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Active Plan: merge-agent - Dev Dict Read-Only Compatibility

Status: in progress on 2026-05-29.

### 1. Current Goal

Add old-frontend-compatible read-only dictionary endpoints because the Vue app loads `/dev/dict/tree` after login and many forms depend on cached dictionary data.

### 2. Modules In Scope

- dev dictionary read-only API compatibility
- dictionary page/list/tree/detail
- current tenant dictionary visibility for `FRM` plus current tenant rows

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/DictService.php`
- `app/controller/dev/DictController.php`
- `route/app.php`
- `docs/api/dev-dict-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Dictionary writes refresh Java translation cache and can affect many forms, so add/edit/delete remain deferred.
- `route/app.php` is locked and must only receive documented protected read-only routes.
- Tree payload must expose frontend-friendly `name`, `dictLabel`, and `dictValue` fields.

### 5. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/dev/dict/page`, `/dev/dict/list`, `/dev/dict/tree`, and `/dev/dict/detail` routes are added.
- No dictionary add, edit, delete, or cache mutation endpoints are added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Active Plan: merge-agent - Dev Log Read-Only Compatibility

Status: in progress on 2026-05-29.

### 1. Current Goal

Add old-frontend-compatible read-only log endpoints for visit and operation log pages and their chart panels.

### 2. Modules In Scope

- dev log read-only API compatibility
- log page/detail
- visit log line/pie chart data
- operation log bar/pie chart data

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/LogService.php`
- `app/controller/dev/LogController.php`
- `route/app.php`
- `docs/api/dev-log-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Log detail may contain request/response payloads from historical Java operations, so routes must stay authenticated.
- Log delete is destructive and must remain deferred.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/dev/log/page`, `/dev/log/detail`, and chart data routes are added.
- No log delete/clear route is added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Dev Message Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only station-message endpoints for message management pages.

### 2. Modules In Scope

- dev message read-only API compatibility
- message page/detail
- receive user read-status list

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/MessageService.php`
- `app/controller/dev/MessageController.php`
- `route/app.php`
- `docs/api/dev-message-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java detail marks the current user's message as read, but this slice must stay read-only and must not update `dev_relation`.
- Message send/delete are mutations and remain deferred.
- Message content may include business details, so routes must stay authenticated.

### 5. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/dev/message/page` and `/dev/message/detail` routes are added.
- No message send/delete/SSE/read-status mutation routes are added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Dev Config Safe Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only configuration endpoints while preventing accidental exposure of sensitive config values.

### 2. Modules In Scope

- dev config read-only API compatibility
- public system base config list for login page
- protected config page/list/detail
- sensitive config value masking

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/ConfigService.php`
- `app/controller/dev/ConfigController.php`
- `route/app.php`
- `docs/api/dev-config-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- `dev_config` stores default passwords, cloud SecretKey values, email credentials, SMS credentials, and file-storage keys.
- Java returns full values for several reads, but this PHP slice should mask sensitive values until a write-endpoint and permission review exists.
- `sysBaseList` is public in the Java project and must exclude default password data.
- `route/app.php` is locked and must only receive documented protected/public read-only routes.

### 5. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/dev/config/sysBaseList`, `/dev/config/page`, `/dev/config/list`, and `/dev/config/detail` routes are added.
- No config add/edit/delete/editBatch routes are added.
- `sysBaseList` excludes `SNOWY_SYS_DEFAULT_PASSWORD`.
- Sensitive config values are masked in read responses.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Dev File Metadata Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only file metadata endpoints for the file management page and file detail drawer.

### 2. Modules In Scope

- dev file metadata read-only API compatibility
- file page/list/detail
- tenant-scoped page/list reads

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/FileService.php`
- `app/controller/dev/FileController.php`
- `route/app.php`
- `docs/api/dev-file-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java file upload and delete mutate file metadata and storage, so they remain deferred.
- Java file download streams local files from `STORAGE_PATH`; this slice must not expose file content or read from disk.
- `dev_file.THUMBNAIL` can contain large base64 data; it is included for frontend compatibility but only as stored metadata.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/dev/file/page`, `/dev/file/list`, and `/dev/file/detail` routes are added.
- No file upload, delete, or download file-stream route is added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Dev Email And Sms Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only metadata/query endpoints for email and SMS send records.

### 2. Modules In Scope

- dev email record page/detail
- dev SMS record page/detail
- Java `DevEmailController` and `DevSmsController` compatibility for read-only routes only

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/EmailService.php`
- `app/service/dev/SmsService.php`
- `app/controller/dev/EmailController.php`
- `app/controller/dev/SmsController.php`
- `route/app.php`
- `docs/api/dev-email-sms-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java send endpoints call local/cloud providers and write `dev_email` / `dev_sms`; they must remain deferred.
- Delete endpoints remove historical send records and must remain deferred.
- `CONTENT`, `RECEIPT_INFO`, `TEMPLATE_PARAM`, and phone/email fields may contain sensitive operational data, so routes must stay authenticated.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\dev\EmailService.php
php -l app\service\dev\SmsService.php
php -l app\controller\dev\EmailController.php
php -l app\controller\dev\SmsController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/dev/email/page`, `/dev/email/detail`, `/dev/sms/page`, and `/dev/sms/detail` routes are added.
- No email/SMS send or delete routes are added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Dev Job Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only scheduled-job endpoints so the job management page can load current job records without enabling scheduler mutations.

### 2. Modules In Scope

- dev job page/list/detail
- dev job action-class lookup as a read-only compatibility helper
- Java `DevJobController` compatibility for GET routes only

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/JobService.php`
- `app/controller/dev/JobController.php`
- `route/app.php`
- `docs/api/dev-job-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java add/edit/delete/run/stop routes mutate the scheduler and database; they must remain deferred.
- `runJobNow` can execute arbitrary registered job classes and must not be exposed in this slice.
- `getActionClass` in Java scans Spring beans; ThinkPHP cannot execute Java beans, so this slice returns stored action class names from `dev_job` as read-only compatibility data.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\dev\JobService.php
php -l app\controller\dev\JobController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/dev/job/page`, `/dev/job/list`, `/dev/job/detail`, and `/dev/job/getActionClass` routes are added.
- No job add/edit/delete/stop/run/run-now routes are added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Sys Config Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only system configuration detail endpoint for workflow process settings loaded after login.

### 2. Modules In Scope

- system configuration detail read
- `sys_config.CONFIG_JSON` decode
- Java `SysConfigController.detail` compatibility

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/sys/SysConfigService.php`
- `app/controller/sys/SysConfigController.php`
- `route/app.php`
- `docs/api/sys-config-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java `detail` can generate a default config when missing; this slice must stay read-only and must not insert or update `sys_config`.
- Java `edit` writes workflow process configuration and updates tenant cache; it must remain deferred.
- Java `generateConfig` is a GET endpoint but mutates data; it must remain deferred.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\sys\SysConfigService.php
php -l app\controller\sys\SysConfigController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `GET /sys/sysConfig/detail` is added.
- No `/sys/sysConfig/edit` or `/sys/sysConfig/generateConfig` route is added.
- Route is protected by `AuthMiddleware`.
- Missing or invalid config returns a runtime default object without writing to the database.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Dev Monitor Server Info Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only server monitor information endpoint without executing external system commands.

### 2. Modules In Scope

- dev monitor server info
- Java `DevMonitorController.serverInfo` compatibility
- safe PHP runtime and disk information

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/MonitorService.php`
- `app/controller/dev/MonitorController.php`
- `route/app.php`
- `docs/api/dev-monitor-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java `networkInfo` runs OS commands and waits during sampling; it must remain deferred.
- Server monitor data can expose host/runtime information, so the route must stay authenticated.
- PHP runtime cannot provide all Java/JVM/OSHI metrics without extensions or system commands; unavailable values must be safe placeholders.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\dev\MonitorService.php
php -l app\controller\dev\MonitorController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `GET /dev/monitor/serverInfo` is added.
- No `/dev/monitor/networkInfo` route is added.
- No external system commands are executed.
- Route is protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Gen Metadata Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only generator metadata endpoints for saved generator definitions and field configurations, without enabling code generation or database schema scanning.

### 2. Modules In Scope

- code-generator metadata reads
- `gen_basic` page/detail
- `gen_config` list/detail
- mobile module selector passthrough for generator forms

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/gen/BasicService.php`
- `app/service/gen/ConfigService.php`
- `app/controller/gen/BasicController.php`
- `app/controller/gen/ConfigController.php`
- `route/app.php`
- `docs/api/gen-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java generator execution can create files or modify the Java project; all execution and preview routes must remain deferred.
- Table and column scanning exposes database schema information and should wait for a separate approval and allow-list design.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\gen\BasicService.php
php -l app\service\gen\ConfigService.php
php -l app\controller\gen\BasicController.php
php -l app\controller\gen\ConfigController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/gen/basic/page`, `/gen/basic/detail`, `/gen/basic/mobileModuleSelector`, `/gen/config/list`, and `/gen/config/detail` routes are added.
- No `/gen/basic/tables`, `/gen/basic/tableColumns`, `/gen/basic/execGenZip`, `/gen/basic/execGenPro`, `/gen/basic/previewGen`, or generator write route is added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Auth Session Current Token Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only session monitor endpoints for the currently authenticated B-side token, without adding token/session exit behavior or a new global token index.

### 2. Modules In Scope

- auth session monitor reads
- current bearer token session analysis
- B-side session page for the current token
- C-side session page as an empty compatibility response until client auth exists

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/auth/SessionMonitorService.php`
- `app/controller/auth/SessionController.php`
- `route/app.php`
- `docs/api/auth-session-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- The current ThinkPHP token service stores token payloads by hashed token key and does not keep a searchable session index.
- Java Sa-Token can enumerate all online sessions; this slice can only report the current request token without changing token write behavior.
- Session and token exit routes are mutations and must remain deferred.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\auth\SessionMonitorService.php
php -l app\controller\auth\SessionController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/auth/session/analysis`, `/auth/session/b/page`, and `/auth/session/c/page` routes are added.
- No `/auth/session/b/exit`, `/auth/session/c/exit`, `/auth/token/b/exit`, or `/auth/token/c/exit` route is added.
- Routes are protected by `AuthMiddleware`.
- Token values are not written to docs or committed files.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Tenants Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only tenant page and detail endpoints so the tenant management page can load imported tenant data without enabling tenant creation, edit, deletion, or default-data generation.

### 2. Modules In Scope

- tenant table read-only API compatibility
- `/tenants/tenant/page`
- `/tenants/tenant/detail`
- `tenants` table mixed-case physical columns

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/tenant/TenantsService.php`
- `app/controller/tenant/TenantsController.php`
- `route/app.php`
- `docs/api/tenants-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java tenant add/edit/delete routes mutate tenant data and can generate default users, roles, and permissions.
- The SQL table uses mixed-case physical columns `Tenant_ID` and `Tenant_Name`; ThinkPHP queries must preserve those names.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\tenant\TenantsService.php
php -l app\controller\tenant\TenantsController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/tenants/tenant/page` and `/tenants/tenant/detail` routes are added.
- No `/tenants/tenant/add`, `/tenants/tenant/edit`, or `/tenants/tenant/delete` route is added.
- No tenant default system data generation is performed.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Biz Product Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only product master endpoints for product pages, selectors, details, and kit-product child lookup.

### 2. Modules In Scope

- product master read-only API compatibility
- `biz_product` page/list/detail
- `product_relation` kit-product child reads

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/ProductService.php`
- `app/controller/biz/ProductController.php`
- `route/app.php`
- `docs/api/biz-product-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java product writes update kit-product relations and trigger data-change events; all writes must remain deferred.
- Java data-scope logic is richer than the current token payload; this slice only applies tenant filtering and token data-scope org ids when present.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\biz\ProductService.php
php -l app\controller\biz\ProductController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/biz/bizproduct/page`, `/biz/bizproduct/list`, `/biz/bizproduct/detail`, and `/biz/bizproduct/children` routes are added.
- No product add/edit/delete/status/reconciliation mutation route is added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Biz Supplier Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only supplier master endpoints for supplier pages, selectors, name lookup, and details.

### 2. Modules In Scope

- supplier master read-only API compatibility
- `supplier` page/list/detail
- enabled supplier name lookup

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/SupplierService.php`
- `app/controller/biz/SupplierController.php`
- `route/app.php`
- `docs/api/biz-supplier-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java supplier writes are used by purchase/settlement flows and must stay deferred.
- Java data-scope logic is richer than the current token payload; this slice only applies tenant filtering and token data-scope org ids when present.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\biz\SupplierService.php
php -l app\controller\biz\SupplierController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/biz/supplier/page`, `/biz/supplier/list`, `/biz/supplier/list/query/name`, and `/biz/supplier/detail` routes are added.
- No supplier add/edit/delete mutation route is added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Biz Warehouses Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only warehouse endpoints for warehouse pages, selectors, and details.

### 2. Modules In Scope

- warehouse master read-only API compatibility
- `warehouses` page/list/detail
- owner and organization display-name lookup

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/WarehousesService.php`
- `app/controller/biz/WarehousesController.php`
- `route/app.php`
- `docs/api/biz-warehouses-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java warehouse reads use login-user data scope through the warehouse owner user; the current ThinkPHP token has a simplified payload, so this slice applies tenant filtering and optional token data-scope org ids only when present.
- Inventory, purchase, and sale flows depend on warehouse ids, so write endpoints must remain deferred until validation and downstream effects are implemented.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\biz\WarehousesService.php
php -l app\controller\biz\WarehousesController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/biz/warehouses/page`, `/biz/warehouses/list`, and `/biz/warehouses/detail` routes are added.
- No warehouse add/edit/delete mutation route is added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Biz Inventory Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only inventory endpoints for warehouse inventory page, export-list reads, and detail lookup.

### 2. Modules In Scope

- warehouse inventory read-only API compatibility
- `inventory` page/list/detail
- joined enabled `biz_product` display fields

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/InventoryService.php`
- `app/controller/biz/InventoryController.php`
- `route/app.php`
- `docs/api/biz-inventory-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java `page` and `list` require a warehouse id and validate that the warehouse exists before reading inventory.
- Inventory write operations can change stock quantities and trigger data-change events, so add/out/in/batch behavior must remain deferred.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\biz\InventoryService.php
php -l app\controller\biz\InventoryController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/biz/inventory/page`, `/biz/inventory/list`, and `/biz/inventory/detail` routes are added.
- No inventory add/delete/stock-changing route is added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Biz Delivery Record Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only warehouse delivery-record endpoints for product inventory history and export-list reads.

### 2. Modules In Scope

- warehouse delivery record read-only API compatibility
- `/biz/warehouses/delivery/page`
- `/biz/warehouses/delivery/exportOtherCompanyRecordsList`
- `/biz/warehouses/delivery/detail` as frontend-wrapper compatibility
- display-name enrichment for warehouse, product, and operator

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/DeliveryRecordService.php`
- `app/controller/biz/DeliveryRecordController.php`
- `route/app.php`
- `docs/api/biz-delivery-record-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java delivery record add adjusts inventory and publishes data-change events, so the add route must remain deferred.
- The old frontend export form sends a `completionTime` range while the Java param uses `deliveryStartTime` and `deliveryEndTime`; this slice supports both shapes without changing frontend code.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\biz\DeliveryRecordService.php
php -l app\controller\biz\DeliveryRecordController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/biz/warehouses/delivery/page`, `/biz/warehouses/delivery/exportOtherCompanyRecordsList`, and `/biz/warehouses/delivery/detail` routes are added.
- No `/biz/warehouses/delivery/add` mutation route is added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Biz Purchase Order Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only purchase-order endpoints for procurement list/detail pages.

### 2. Modules In Scope

- purchase order read-only API compatibility
- `/biz/bizpurchaseorder/page`
- `/biz/bizpurchaseorder/detail/list`
- `/biz/bizpurchaseorder/list`
- `/biz/bizpurchaseorder/detail`
- purchase-order item enrichment with product display fields
- purchase-order detail wrapper with related goods expenditure records

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/PurchaseOrderService.php`
- `app/controller/biz/PurchaseOrderController.php`
- `route/app.php`
- `docs/api/biz-purchase-order-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java purchase-order write operations can trigger audit, workflow, payment, warehouse stock-in, and inventory side effects, so add/edit/audit/cancel/warehouse routes must remain deferred.
- Supplier display data in the imported SQL is primarily stored in `EXT_JSON.supplier`, so read filters must preserve JSON compatibility without changing the schema.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\biz\PurchaseOrderService.php
php -l app\controller\biz\PurchaseOrderController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/biz/bizpurchaseorder/page`, `/biz/bizpurchaseorder/detail/list`, `/biz/bizpurchaseorder/list`, and `/biz/bizpurchaseorder/detail` routes are added.
- No purchase-order mutation or warehouse stock-in route is added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Biz Settlement Account Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only settlement-account endpoints for account master data and account-name selectors.

### 2. Modules In Scope

- settlement account read-only API compatibility
- `/biz/settlementaccount/page`
- `/biz/settlementaccount/list`
- `/biz/settlementaccount/detail`
- `/biz/settlementaccount/queryName`
- organization-name enrichment for the lower-case SQL `org` field

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/SettlementAccountService.php`
- `app/controller/biz/SettlementAccountController.php`
- `route/app.php`
- `docs/api/biz-settlement-account-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java settlement-account write operations can mutate account balances, settlement statements, income records, expenditure records, and transfer state, so those routes must remain deferred.
- The SQL table uses lower-case `org`; the ThinkPHP query must preserve that column name while still returning frontend-friendly `org` and `orgName`.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\biz\SettlementAccountService.php
php -l app\controller\biz\SettlementAccountController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/biz/settlementaccount/page`, `/biz/settlementaccount/list`, `/biz/settlementaccount/detail`, and `/biz/settlementaccount/queryName` routes are added.
- No settlement account add/edit/delete/status/expenses/payment/transfer route is added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Completed Plan: merge-agent - Biz Payment Record Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only payment-record endpoints for income/payment list and detail views.

### 2. Modules In Scope

- payment record read-only API compatibility
- `/biz/bizpaymentrecord/page`
- `/biz/bizpaymentrecord/listdetails`
- `/biz/bizpaymentrecord/list`
- `/biz/bizpaymentrecord/detail`
- settlement-account display enrichment
- organization-name display enrichment

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/PaymentRecordService.php`
- `app/controller/biz/PaymentRecordController.php`
- `route/app.php`
- `docs/api/biz-payment-record-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java payment-record edits can update settlement statements and switch account balances, so edit routes must remain deferred.
- The old frontend exposes a detail wrapper even though the analyzed Java controller only exposes page/listdetails/list; this slice adds detail as read-only compatibility only.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\biz\PaymentRecordService.php
php -l app\controller\biz\PaymentRecordController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/biz/bizpaymentrecord/page`, `/biz/bizpaymentrecord/listdetails`, `/biz/bizpaymentrecord/list`, and `/biz/bizpaymentrecord/detail` routes are added.
- No payment-record edit or account-switch route is added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Plan: merge-agent - Biz File Relation Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, baseline checks, runtime HTTP smoke tests, and secret scan.

### 1. Current Goal

Add old-frontend-compatible read-only file-relation endpoints for business attachment lists and detail lookups.

### 2. Modules In Scope

- file relation read-only API compatibility
- `/biz/bizfilerelation/page`
- `/biz/bizfilerelation/list`
- `/biz/bizfilerelation/detail`
- dev-file display enrichment
- create-user display enrichment

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/FileRelationService.php`
- `app/controller/biz/FileRelationController.php`
- `route/app.php`
- `docs/api/biz-file-relation-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java file-relation add/delete flows change attachment links and derive file names from `dev_file`, so write routes must remain deferred.
- `biz_file_relation.FILE_NAME` is often empty in the imported SQL, so read responses should also expose the linked `dev_file.NAME`.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\biz\FileRelationService.php
php -l app\controller\biz\FileRelationController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/biz/bizfilerelation/page`, `/biz/bizfilerelation/list`, and `/biz/bizfilerelation/detail` routes are added.
- No file-relation add/edit/delete or project-case delete route is added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Plan: merge-agent - Biz Team Project Read-Only Foundation

Status: completed on 2026-05-29 after implementation, HTTP smoke, lint, route, and secret-scan checks.

### 1. Current Goal

Add old-frontend-compatible read-only endpoints for team project cards, project details, and project member lists.

### 2. Modules In Scope

- team project read-only API compatibility
- `/biz/bizteamproject/page`
- `/biz/bizteamproject/detail`
- `/biz/bizteamprojectuser/page`
- `/biz/bizteamprojectuser/list`
- `/biz/bizteamprojectuser/detail`
- current-user membership filtering
- user/avatar and role-permission enrichment

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/TeamProjectService.php`
- `app/controller/biz/TeamProjectController.php`
- `app/controller/biz/TeamProjectUserController.php`
- `route/app.php`
- `docs/api/biz-team-project-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java team-project detail requires the current login user to be a project member; ThinkPHP must preserve that membership gate.
- The team project detail page immediately requests member lists, so project and member read APIs should land together.
- Team project add/edit/delete and member mutations emit data-change events and role permissions, so write routes must remain deferred.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\biz\TeamProjectService.php
php -l app\controller\biz\TeamProjectController.php
php -l app\controller\biz\TeamProjectUserController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only team-project and team-project-user routes are added.
- No team project add/edit/delete route is added.
- No team member add/manage/edit/delete route is added.
- Routes are protected by `AuthMiddleware`.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Plan: merge-agent - Biz Team Project Task Read-Only Compatibility

Status: completed on 2026-05-29 after implementation, route registration, lint, baseline checks, HTTP smoke, and secret-scan checks.

### 1. Current Goal

Add the remaining old-frontend-compatible read-only endpoints required by the team project detail page: task categories, tasks, project timeline comments, and task comments.

### 2. Modules In Scope

- `/biz/bizteamprojecttaskcategory/page`
- `/biz/bizteamprojecttaskcategory/list`
- `/biz/bizteamprojecttaskcategory/detail`
- `/biz/bizteamprojecttask/page`
- `/biz/bizteamprojecttask/list`
- `/biz/bizteamprojecttask/detail`
- `/biz/bizteamprojectcomment/page`
- `/biz/bizteamprojectcomment/list`
- `/biz/bizteamprojecttaskcomment/page`
- `/biz/bizteamprojecttaskcomment/list`
- `/biz/bizteamprojecttaskcomment/detail`
- nested project-comment replies returned from project-comment list
- current-user team-project membership gating
- creator/avatar and task-user enrichment

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/TeamProjectTaskReadService.php`
- `app/controller/biz/TeamProjectTaskCategoryController.php`
- `app/controller/biz/TeamProjectTaskController.php`
- `app/controller/biz/TeamProjectCommentController.php`
- `app/controller/biz/TeamProjectTaskCommentController.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Task detail opens by task id from a query string, so ThinkPHP must resolve the project id from the task before checking membership.
- Project comment list embeds reply rows; a standalone reply read route is not part of the Java controller and should remain deferred.
- Task add/edit/delete, category add/edit/sort/delete, comment add/delete, reply add/edit/delete, and task-user edit are write flows and must remain deferred.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\biz\TeamProjectTaskReadService.php
php -l app\controller\biz\TeamProjectTaskCategoryController.php
php -l app\controller\biz\TeamProjectTaskController.php
php -l app\controller\biz\TeamProjectCommentController.php
php -l app\controller\biz\TeamProjectTaskCommentController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only the listed read-only team-project task/category/comment routes are added.
- No task, task-category, comment, reply, or task-user write route is added.
- Routes are protected by `AuthMiddleware`.
- Current user must be a member of the target project for project-scoped reads.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.

## Plan: merge-agent - Biz Return Order Read-Only Compatibility

Status: completed on 2026-06-01 after implementation, route registration, lint, baseline checks, HTTP smoke, and secret-scan checks.

Date: 2026-06-01

### 1. Current Goal

Add the old-frontend-compatible read-only return-order endpoints for sale-project return/refund views.

### 2. Modules In Scope

- `/biz/returnorder/page`
- `/biz/returnorder/query`
- `/biz/returnorder/detail`
- return-order item enrichment for `productList`
- project, warehouse, user, and organization display-name enrichment

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/ReturnOrderService.php`
- `app/controller/biz/ReturnOrderController.php`
- `route/app.php`
- `docs/api/biz-return-order-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### 4. Risks

- Java return-order write behavior creates warehouse delivery-in records, updates settlement status, and emits data-change events. Those mutation routes must remain deferred.
- Java data scope uses login-user organization scope when available and falls back to the current user. The current ThinkPHP token payload may not always include expanded data-scope org ids, so this slice preserves the same fallback shape as closely as the current auth payload allows.
- `route/app.php` is locked and must only receive documented protected read-only routes.

### 5. Test Commands

```powershell
php -l app\service\biz\ReturnOrderService.php
php -l app\controller\biz\ReturnOrderController.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

### 6. Acceptance Criteria

- Only read-only `/biz/returnorder/page`, `/biz/returnorder/query`, and `/biz/returnorder/detail` routes are added.
- No return-order add/edit/status/warehouse/inventory/refund mutation route is added.
- Routes are protected by `AuthMiddleware`.
- `query` returns `productList` rows for each return order.
- Baseline ThinkPHP checks and representative HTTP smoke tests pass.
## Completed Plan: frontend-agent - Frontend API Gap Map

Status: completed on 2026-06-01 after static frontend API scan, gap-map documentation, dashboard update, and baseline ThinkPHP checks.

Date: 2026-06-01

### 1. Current Goal

Generate a frontend API gap map after the Vue baseline import and first login smoke test.

This phase documents which copied frontend API wrappers already match ThinkPHP routes, which wrappers are still missing, and which endpoints are intentionally deferred because they are write-heavy or require workflow/finance/warehouse side effects.

### 2. Modules In Scope

- frontend-agent API compatibility analysis
- api-agent follow-up planning
- docs-agent status tracking

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`

### 4. Inputs

- Copied frontend API files under `snowy-admin-web/src/api`
- Current ThinkPHP route output from `php think route:list`
- Existing API docs under `docs/api`

### 5. Risks

- Some frontend API wrappers build endpoint names dynamically, so the gap map is a planning aid rather than a perfect compiler.
- Write endpoints must not be implemented just because the frontend contains wrappers for them.
- Missing field and dictionary display problems may require backend response-shape work even when the route exists.

### 6. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
git status --short --branch
```

### 7. Acceptance Criteria

- Java source project remains read-only.
- No business Controller, Service, Model, or route implementation is changed in this phase.
- `docs/tasks/api-gap-map.md` lists implemented, missing, and deferred frontend endpoint groups.
- `STATUS.md` records test results and next priorities.
- Commit message contains `frontend-agent`.

## Completed Plan: user-agent - Sys Org/User Display Field Compatibility

Status: completed on 2026-06-01 after service alias implementation, API response probes, backend checks, frontend build, and browser smoke.

Date: 2026-06-01

### 1. Current Goal

Fix the first visible post-login compatibility issue on `/sys/org` and `/sys/user`: table rows and trees should expose the camelCase fields that the copied Vue frontend already expects.

This phase keeps the change small. It only adds response aliases/enrichment to existing read services. It does not add user/org/position write endpoints.

### 2. Modules In Scope

- System organization reads
- System user reads
- System position reads used by user pages/selectors
- Documentation and status tracking

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `app/service/user/OrgService.php`
- `app/service/user/UserDirectoryService.php`
- `app/service/user/PositionService.php`
- `docs/api/sys-user-org-display-compat.md`
- `docs/tasks/refactor-progress-dashboard.md`

### 4. Risks

- Frontend pages still use dictionary values from local storage. This phase should ensure the source field exists, but it should not rewrite frontend dictionary behavior.
- User rows need `orgName` and `positionName`; this must be resolved in batch to avoid N+1 queries.
- Existing consumers may still use uppercase SQL fields. Keep uppercase fields in the response and add camelCase aliases rather than replacing fields.
- Public locked files are not in scope.

### 5. Test Commands

```powershell
php -l app\service\user\OrgService.php
php -l app\service\user\UserDirectoryService.php
php -l app\service\user\PositionService.php
composer dump-autoload
php think
php think route:list
npm run build
git diff --check
```

### 6. Acceptance Criteria

- `/sys/org/page`, `/sys/org/tree`, and `/sys/org/detail` responses include `id`, `parentId`, `name`, `category`, and `sortCode`.
- `/sys/user/page` and `/sys/user/detail` responses include `id`, `account`, `name`, `gender`, `genderName`, `phone`, `orgId`, `orgName`, `positionId`, `positionName`, `userStatus`, and `sortCode`.
- `/sys/position/page`, `/sys/position/list`, and `/sys/position/detail` responses include `id`, `orgId`, `name`, `category`, and `sortCode`.
- Existing uppercase SQL fields remain present for compatibility.
- No route, Controller, database, Java source, or write endpoint is changed.

## Completed Plan: api-agent/frontend-agent - Dev Message SSE Compatibility Review

Status: completed on 2026-06-01 as a planning-only slice. Route implementation is pending public-file approval or merge-agent handling.

Date: 2026-06-01

### 1. Current Goal

Review the missing `/dev/message/createSseConnect` browser-console 404 and define the smallest safe ThinkPHP compatibility path without touching locked route files in this slice.

### 2. Modules In Scope

- Dev message SSE route compatibility analysis
- Frontend EventSource caller analysis
- Public route-change request documentation
- Status and progress tracking

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `docs/api/dev-message-sse-compat-plan.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### 4. Risks

- `route/app.php` is a locked public file, so route registration must not be changed without an explicit request record.
- Java uses Spring `SseEmitter`; ThinkPHP needs a PHP stream response and long-running connection behavior.
- Full push/broadcast behavior touches workflow and message mutation side effects, so first implementation must stay connection/heartbeat-only.
- EventSource browser behavior should be tested from the copied frontend after the route is implemented.

### 5. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
git diff --check
```

### 6. Acceptance Criteria

- Java source remains read-only.
- No `route/app.php` edit is made in this slice.
- The Java controller/service/SSE-provider behavior is summarized.
- The copied frontend EventSource callers are identified.
- A public-file change request records the proposed protected route.

## Completed Plan: api-agent - Minimal Dev Message SSE Compatibility

Status: completed on 2026-06-01 after route registration, minimal SSE service implementation, direct HTTP probe, browser smoke, baseline ThinkPHP checks, and syntax checks.

Date: 2026-06-01

### 1. Current Goal

Implement the approved minimal compatibility route for `/dev/message/createSseConnect` so the copied Vue layout no longer receives a 404 after login.

This phase does not implement full realtime push, Redis pub/sub, message mutations, or workflow side-effect notifications.

### 2. Modules In Scope

- Dev message SSE compatibility route
- Dev message controller adapter
- Minimal SSE response helper/service
- Documentation and status tracking

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/dev/MessageController.php`
- `app/service/dev/MessageSseService.php`
- `docs/api/dev-message-sse-compat-plan.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### 4. Risks

- Long-running SSE would block local `php think run` development requests, so the first slice must be a short compatibility stream.
- EventSource callers may reconnect after a short stream closes; this is acceptable for this compatibility slice and safer than holding the built-in PHP server.
- Full live push behavior needs later queue/Redis/pub-sub design and workflow/message mutation integration.
- `route/app.php` is locked, but the user continued after the public-file request; keep the route change scoped to the requested `dev/message` group.

### 5. Test Commands

```powershell
php -l app\controller\dev\MessageController.php
php -l app\service\dev\MessageSseService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
git diff --check
```

### 6. Acceptance Criteria

- `GET /dev/message/createSseConnect` appears in `php think route:list`.
- The route is protected by the existing `AuthMiddleware`.
- The response content type is `text/event-stream`.
- The initial event includes Java-compatible `code = 0` with a client id.
- No Java source, database schema, frontend file, Composer file, `.env`, or message mutation endpoint is changed.

## Active Plan: api-agent - Sale Project Read API Compatibility

Status: completed on 2026-06-02.

### 1. Current Goal

Add the first read-only ThinkPHP compatibility slice for Java `BizSaleProjectController`, focused on frontend browse/test flows after login.

This phase does not implement sale project creation, editing, deletion, visibility changes, deal-state changes, cancellation, special/history project creation, or weighted-average cost calculation.

### 2. Modules In Scope

- `biz/saleproject` read-only Controller mapping
- Sale project list/page/detail/product item read queries
- Product child relation compatibility from `sale_project_product_item_relation`
- API and public route-change documentation

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/SaleProjectController.php`
- `app/service/biz/SaleProjectService.php`
- `docs/api/biz-saleproject-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### 4. Risks

- `route/app.php` is a locked public file, so the route registration must stay narrow and be recorded in the public-file change request document.
- Java sale project detail aggregates invoices, invoicing records, payment records, return orders, follow-ups, change logs, and product children. This first slice returns those related read lists where table structure is already mapped and keeps mutation side effects deferred.
- Cost endpoints depend on inventory weighted-average logic and are intentionally deferred to avoid inaccurate financial behavior.
- Customer detail endpoints are still a separate api-agent slice, so a sale-project detail page may still request missing customer APIs after this phase.

### 5. Test Commands

```powershell
php -l app\controller\biz\SaleProjectController.php
php -l app\service\biz\SaleProjectService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/saleproject/page`, `/case/page`, `/operation/page`, `/public/page`, `/list/detail`, `/detail`, and `/product` appear in `php think route:list`.
- All new sale project routes are protected by the existing `AuthMiddleware`.
- Page responses include Java/frontend-compatible sale project display fields such as `customerName`, `headName`, `headPhone`, `orgName`, and `accountName`.
- Detail responses include `bizSaleProject`, `productItems`, `invoicingList`, `invoiceList`, `paymentRecords`, `saleProjectFollowUps`, `changeLogs`, and `returnOrders`.
- Product item responses include `children` arrays with relation `extJson` preserved for frontend parsing.
- Java source, database schema, frontend files, Composer files, `.env`, and write endpoints remain unchanged.

### 7. Completion Notes

- Implemented the read-only Controller and Service slice for the seven Java-compatible sale-project read routes.
- Registered the four nested saleproject routes as explicit full paths to avoid route-cache/runtime ambiguity during local smoke tests.
- Kept all sale-project write, inventory cost, workflow, Java source, database schema, frontend, Composer, and `.env` changes out of scope.
- Verified the local MySQL/Redis helper script path is `F:\project\socket\AI\testPhp\files\startServer1.bat`.

## Active Plan: api-agent - Customer Read API Compatibility

Status: completed on 2026-06-02.

### 1. Current Goal

Add the next read-only ThinkPHP compatibility slice for Java `CustomerController` and `CustomerFollowUpController`, focused on customer list/detail/export and customer follow-up tabs used by the copied Vue frontend after login.

This phase does not implement customer creation, editing, deletion, head-owner reassignment, follow-up creation, follow-up editing, or follow-up deletion.

### 2. Modules In Scope

- `biz/customer` read-only Controller mapping
- Customer list/page/detail/detail-list queries
- Customer follow-up page/detail queries
- Java/frontend-compatible display fields for customer owner, creator, organization, file download path, and follow-up creator organization
- API and public route-change documentation

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/CustomerController.php`
- `app/controller/biz/CustomerFollowUpController.php`
- `app/service/biz/CustomerService.php`
- `app/service/biz/CustomerFollowUpService.php`
- `docs/api/biz-customer-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### 4. Risks

- `route/app.php` is a locked public file, so the route registration must stay narrow and be recorded in the public-file change request document.
- Java customer phone and detail-address fields use SM4 type handlers. This slice preserves stored values and documents that plaintext decrypt/search behavior is deferred until a dedicated cryptography compatibility plan is approved.
- Customer data scope depends on Java login context. This slice uses the existing token payload org scope when present and falls back to current user ownership to avoid broadening visibility.
- Customer write endpoints remain deferred to avoid accidental mutation behavior before validation, permission, and encryption rules are complete.

### 5. Test Commands

```powershell
php -l app\controller\biz\CustomerController.php
php -l app\controller\biz\CustomerFollowUpController.php
php -l app\service\biz\CustomerService.php
php -l app\service\biz\CustomerFollowUpService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/customer/page`, `GET /biz/customer/detail`, and `POST /biz/customer/detail/list` appear in `php think route:list`.
- `GET /biz/customerfollowup/page` and `GET /biz/customerfollowup/detail` appear in `php think route:list`.
- All new routes are protected by the existing `AuthMiddleware`.
- Customer responses include Java/frontend-compatible fields such as `headName`, `orgName`, `createUserName`, and `downloadPath`.
- Customer follow-up responses include `customerName`, `createUserName`, `avatar`, `createUserOrgId`, and `createUserOrgName`.
- Java source, database schema, frontend files, Composer files, `.env`, and customer/follow-up write endpoints remain unchanged.

### 7. Completion Notes

- Implemented read-only Controller and Service adapters for the five customer and customer-follow-up routes.
- Preserved Java/frontend field compatibility for owner, organization, creator, file download, and follow-up creator organization display fields.
- Kept SM4 phone/detail-address plaintext search deferred and documented the limitation.
- Kept all customer/follow-up write, owner reassignment, Java source, database schema, frontend, Composer, and `.env` changes out of scope.

## Active Plan: api-agent - Sale Project Billing Read API Compatibility

Status: completed on 2026-06-02.

### 1. Current Goal

Add the next read-only ThinkPHP compatibility slice for Java sales-project billing-adjacent APIs used by the copied Vue frontend after the sale-project and customer pages are available.

This phase covers invoicing applications, delivery invoices, reissue-order detail lists, and project rating reads. It does not implement add, edit, delete, complete, workflow, inventory, finance, or stock side effects.

### 2. Modules In Scope

- `biz/saleprojectinvoicing` read-only page/customer/detail mapping
- `biz/saleprojectinvoice` read-only page/list mapping
- `biz/saleprojectreissueorder` read-only list/query mapping
- `biz/projectrate` read-only page/list mapping
- Java/frontend-compatible nested invoice item and reissue product-item response structures
- API and public route-change documentation

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/SaleProjectInvoicingController.php`
- `app/controller/biz/SaleProjectInvoiceController.php`
- `app/controller/biz/SaleProjectReissueOrderController.php`
- `app/controller/biz/SaleProjectRateController.php`
- `app/service/biz/SaleProjectBillingService.php`
- `docs/api/biz-saleproject-billing-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### 4. Risks

- `route/app.php` is a locked public file, so the route registration must stay narrow and be recorded in the public-file change request document.
- Java invoicing page filters projects to invoiceable states only: `PARTIALLY_SHIPPED`, `SHIPPED`, and `COMPLETED`. This slice preserves that filter.
- Reissue-order product item children must keep `extJson` compatible because the frontend parses nested product data from that JSON value.
- Write endpoints remain deferred to avoid accidental workflow, inventory, finance, or billing mutations before those modules are planned.

### 5. Test Commands

```powershell
php -l app\controller\biz\SaleProjectInvoicingController.php
php -l app\controller\biz\SaleProjectInvoiceController.php
php -l app\controller\biz\SaleProjectReissueOrderController.php
php -l app\controller\biz\SaleProjectRateController.php
php -l app\service\biz\SaleProjectBillingService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/saleprojectinvoicing/page`, `/customer`, and `/detail` appear in `php think route:list`.
- `GET /biz/saleprojectinvoice/page` and `/list` appear in `php think route:list`.
- `GET /biz/saleprojectreissueorder/list/query` appears in `php think route:list`.
- `GET /biz/projectrate/page` and `/list` appear in `php think route:list`.
- All new routes are protected by the existing `AuthMiddleware`.
- Invoice list responses include `bizSaleProjectInvoice` and `invoiceItems`.
- Reissue list responses include `order` and `productItemList`, and product items include `children` with relation `extJson` preserved.
- Java source, database schema, frontend files, Composer files, `.env`, and all billing write endpoints remain unchanged.

### 7. Completion Notes

- Implemented read-only Controller and Service adapters for the eight sales-project billing-adjacent routes.
- Preserved Java's invoiceable-project-state filter for invoice application pages.
- Returned nested Java/frontend-compatible invoice and reissue structures.
- Preserved relation `extJson` for reissue children and synthesized minimal product JSON only when the relation row has no `extJson`.
- Kept all invoice, invoicing, reissue, project-rate, workflow, inventory, finance, Java source, database schema, frontend, Composer, and `.env` mutations out of scope.

## Active Plan: user-agent - Biz Directory Alias Read API Compatibility

Status: completed on 2026-06-02.

### 1. Current Goal

Add safe read-only compatibility aliases for legacy frontend `/biz/org`, `/biz/user`, `/biz/position`, and `/biz/dict` requests by reusing the existing ThinkPHP organization, user, position, and dictionary read services.

This phase does not implement add, edit, delete, grant, enable, disable, reset-password, export, import, upload, or profile-write behavior.

### 2. Modules In Scope

- `biz/org` page/list/tree/detail/selectors
- `biz/user` page/detail/list-detail/selectors/own-role reads
- `biz/position` page/list/detail/selectors
- `biz/dict` page/tree/treeAll reads
- API and public route-change documentation

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/sys/UserController.php`
- `app/controller/dev/DictController.php`
- `app/service/user/UserDirectoryService.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### 4. Risks

- `route/app.php` is a locked public file, so added routes must stay limited to safe GET reads and be recorded in the public-file change request document.
- `/biz/user/ownRole` reads role relations from `sys_relation` with category `SYS_USER_HAS_ROLE`; `/biz/user/grantRole` remains deferred because it writes permissions.
- `biz/dict/treeAll` is mapped to an unscoped dictionary tree for frontend compatibility, while write/edit routes remain deferred.

### 5. Test Commands

```powershell
php -l app\controller\sys\UserController.php
php -l app\controller\dev\DictController.php
php -l app\service\user\UserDirectoryService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- Legacy `/biz/org` GET read routes appear in `php think route:list`.
- Legacy `/biz/user` GET read routes appear in `php think route:list`, including `list/detail` and `ownRole`.
- Legacy `/biz/position` GET read routes appear in `php think route:list`.
- Legacy `/biz/dict/page`, `/tree`, and `/treeAll` appear in `php think route:list`.
- All new routes are protected by `AuthMiddleware`.
- Password fields are never returned by user list/detail reads.
- Java source, database schema, frontend files, Composer files, `.env`, and all write endpoints remain unchanged.

### 7. Completion Notes

- Added twenty-two protected GET aliases for legacy `/biz/org`, `/biz/user`, `/biz/position`, and `/biz/dict` frontend wrappers.
- Reused existing system/dev read services for organization, user, position, role selector, and dictionary reads.
- Added user `listDetail` and `ownRole` read helpers without adding grant/write behavior.
- Added dictionary `treeAll` read helper without adding dictionary edit behavior.
- Kept all Java source, database schema, frontend, Composer, `.env`, role grant, password, import/export, and write-route changes out of scope.

## Active Plan: workflow-agent/api-agent - Workflow Read Alias Compatibility

Status: completed on 2026-06-02.

### 1. Current Goal

Add the next safe read-only compatibility slice for workflow pages after login. The slice maps Java and copied Vue workflow query endpoints to the existing ThinkPHP Camunda-table read layer.

This phase does not implement task approve, task reject, process start, process cancel, workflow writes, business side effects, or long-lived task SSE.

### 2. Modules In Scope

- `/biz/process/all/page`
- `/biz/process/query`
- `/biz/process/query/list`
- `/biz/process/project/runtime/query/list`
- `/biz/process/fileList`
- `/biz/task/runtime/activity/detail`
- Workflow query documentation and status tracking

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/ProcessController.php`
- `app/controller/biz/TaskController.php`
- `app/service/workflow/WorkflowQueryService.php`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/api-gap-map.md`

### 4. Risks

- `route/app.php` is a locked public file, so route changes must stay limited to protected read-only compatibility endpoints and be recorded in the public-file change request document.
- Java workflow uses Camunda runtime APIs; ThinkPHP can read imported `act_*` tables but cannot execute Java delegates.
- `fileList` relies on `biz_file_relation` and `dev_file` data shape; upload/delete behavior remains deferred.
- `/biz/task/sse/stream`, `/biz/task/approve`, `/biz/task/reject`, process starts, and process cancel remain deferred because they create runtime side effects.

### 5. Test Commands

```powershell
php -l app\controller\biz\ProcessController.php
php -l app\controller\biz\TaskController.php
php -l app\service\workflow\WorkflowQueryService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- All six new workflow read routes appear in `php think route:list`.
- All new routes are protected by the existing `AuthMiddleware`.
- Process page/query responses include frontend-friendly fields such as `id`, `instanceId`, `category`, `title`, `status`, and `variable`.
- Runtime task detail returns `category`, `variables`, `taskId`, `processKey`, and process identifiers.
- Java source, database schema, frontend files, Composer files, `.env`, and workflow write endpoints remain unchanged.

### 7. Completion Notes

- Added six protected workflow read aliases used by the copied Vue workflow pages.
- Added Java/frontend-compatible process row aliases and `id` fallback compatibility for process detail/variable reads.
- Added workflow detail response shape with `userProcess`, `startUser`, `startOrgTree`, `userActivityList`, and `ccUser`.
- Added runtime activity detail reads from `act_ru_task` and normalized runtime variables.
- Kept task approve/reject, process start/cancel, task SSE, Java delegates, database schema, frontend, Composer, and `.env` changes out of scope.

## Active Plan: api-agent - Sale Project Product Info Read API Compatibility

Status: completed on 2026-06-03.

### 1. Current Goal

Add a small read-only compatibility slice for Java `BizSaleProjectProductInfoController` and the copied Vue sale-project-product-info page.

This phase does not implement add, edit, delete, import, export, workflow, inventory, finance, or frontend changes.

### 2. Modules In Scope

- `/biz/saleprojectproductinfo/page`
- `/biz/saleprojectproductinfo/list`
- `/biz/saleprojectproductinfo/detail`
- Java/frontend-compatible response fields for product package/version information
- API, public route-change, status, dashboard, and gap-map documentation

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/SaleProjectProductInfoController.php`
- `app/service/biz/SaleProjectProductInfoService.php`
- `docs/api/biz-saleproject-product-info-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/api-gap-map.md`

### 4. Risks

- `route/app.php` is a locked public file, so added routes must stay limited to the three protected read-only endpoints and be recorded in the public-file change request document.
- Java `list` accepts `targetIds`; the frontend sends comma-separated ids, while Java param type is a list. The ThinkPHP service must normalize both forms.
- The copied Vue page still calls `add`, `edit`, and `delete` from modal actions. Those write routes remain intentionally deferred until validation, audit, and transaction behavior are planned.

### 5. Test Commands

```powershell
php -l app\controller\biz\SaleProjectProductInfoController.php
php -l app\service\biz\SaleProjectProductInfoService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/saleprojectproductinfo/page`, `/list`, and `/detail` appear in `php think route:list`.
- All new routes are protected by the existing `AuthMiddleware`.
- `list` supports comma-separated and array `targetIds`.
- Responses include frontend-compatible fields such as `targetId`, `productId`, `createUserName`, `contentText`, `oldCode`, `alias`, `abbreviation`, `versionType`, `hardware`, `remark`, and `versionRemark`.
- Java source, database schema, frontend files, Composer files, `.env`, and all write endpoints remain unchanged.

### 7. Completion Notes

- Added three protected read-only routes for sale-project product package/version info.
- Added a thin Controller and read-only Service that preserves Java `page`, `list`, and `detail` behavior.
- Normalized comma-separated and array `targetIds` values for frontend compatibility.
- Added creator/updater and product display aliases for expanded frontend rows.
- Kept add/edit/delete, Java source, database schema, frontend, Composer, `.env`, workflow, inventory, and finance mutations out of scope.

## Active Plan: api-agent - Biz Data Report Sale Project Details Read API Compatibility

Status: completed on 2026-06-03.

### 1. Current Goal

Add the smallest read-only `bizdatareport` slice needed by the copied Vue `saleprojectproductinfo` page.

This phase only implements the Java-compatible `POST /biz/bizdatareport/saleProjectList/details` endpoint. It does not implement the full reporting module.

### 2. Modules In Scope

- `POST /biz/bizdatareport/saleProjectList/details`
- Sale project rows filtered by completion date, organization, token data scope, and deal-state list
- Nested `productList` with sale-project product items and kit children
- Nested `returnOrders`
- API, public route-change, status, dashboard, and gap-map documentation

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `docs/api/biz-datareport-saleproject-details-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/api-gap-map.md`

### 4. Risks

- `route/app.php` is a locked public file, so route changes must stay limited to this one protected POST endpoint and be recorded in the public-file change request document.
- The Java reporting service also exposes amount, list, unpaid-payment, income, expenses, sale-profit, and summary-statistics endpoints; those remain deferred because they have separate financial semantics.
- The endpoint can return a large list when a wide date range is requested. This slice follows Java behavior and keeps pagination out of scope.

### 5. Test Commands

```powershell
php -l app\controller\biz\BizDataReportController.php
php -l app\service\biz\BizDataReportService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/bizdatareport/saleProjectList/details` appears in `php think route:list`.
- The new route is protected by the existing `AuthMiddleware`.
- The response is an array of sale project rows with `productList` and `returnOrders`.
- Product rows include `children` arrays with `extJson` preserved or synthesized for frontend parsing.
- Java source, database schema, frontend files, Composer files, `.env`, and other report endpoints remain unchanged.

### 7. Completion Notes

- Added one protected read-only route for `POST /biz/bizdatareport/saleProjectList/details`.
- Added a thin Controller and read-only Service that preserve the Java sale-project details report shape.
- Applied Java-compatible filters for completion date, organization subtree, token data scope, and sale-project deal states.
- Returned sale-project rows with nested `productList`, product item `children`, and `returnOrders`.
- Preserved long ID values as strings while normalizing known amount and quantity fields.
- Kept all other `bizdatareport` routes, Java source, database schema, frontend files, Composer files, `.env`, workflow, inventory, finance, and business write behavior out of scope.

## Active Plan: api-agent - Biz Leave Application Read API Compatibility

Status: completed on 2026-06-03.

### 1. Current Goal

Add a small read-only compatibility slice for Java `BizLeaveApplicationController` and the copied Vue leave-application pages.

This phase only implements list/detail reads. It does not implement leave creation, edit, delete, workflow start, approval, or process side effects.

### 2. Modules In Scope

- `GET /biz/bizleaveapplication/page`
- `GET /biz/bizleaveapplication/my/page`
- `GET /biz/bizleaveapplication/detail`
- Java/frontend-compatible response fields for leave/business-trip records
- Data-scope and current-user filtering compatible with the Java service
- API, public route-change, status, dashboard, and gap-map documentation

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizLeaveApplicationController.php`
- `app/service/biz/BizLeaveApplicationService.php`
- `docs/api/biz-leave-application-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/api-gap-map.md`

### 4. Risks

- `route/app.php` is a locked public file, so route changes must stay limited to three protected read-only endpoints and be recorded in the public-file change request document.
- Java `page` uses data scope when available and falls back to the current login user; this service must preserve that behavior without exposing unrelated records.
- Java `my/page` must always restrict to the current login user.
- Leave records can link to workflow process details through `processId` and sale project details through `objectId`, but this phase must not start or mutate workflows.

### 5. Test Commands

```powershell
php -l app\controller\biz\BizLeaveApplicationController.php
php -l app\service\biz\BizLeaveApplicationService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/bizleaveapplication/page`, `/my/page`, and `/detail` appear in `php think route:list`.
- All new routes are protected by the existing `AuthMiddleware`.
- `page` supports filters for `name`, `category`, `amount`, `remark`, `orgId`, `startStartTime/endStartTime`, and `startEndTime/endEndTime`.
- `my/page` restricts records to the current user from the bearer token payload.
- Responses include frontend-compatible fields such as `id`, `userId`, `name`, `orgId`, `orgName`, `processId`, `category`, `amount`, `remark`, `startTime`, `endTime`, and `objectId`.
- Java source, database schema, frontend files, Composer files, `.env`, add/edit/delete, and workflow write endpoints remain unchanged.

### 7. Completion Notes

- Added three protected read-only routes for leave/business-trip records.
- Added a thin Controller and read-only Service compatible with Java `page`, `my/page`, and `detail`.
- Preserved Java data-scope behavior for `page` and current-login-user filtering for `my/page`.
- Returned frontend-compatible applicant, organization, process, date, amount, and object-id fields.
- Kept add/edit/delete, workflow start/approval/cancel, Java source, database schema, frontend files, Composer files, and `.env` changes out of scope.

## Active Plan: api-agent - Settlement Account Payment Read API Compatibility

Status: completed on 2026-06-03.

### 1. Current Goal

Add a small read-only compatibility slice for Java `SettlementAccountStatementController` and the copied Vue settlement-account detail statement tab.

This phase only implements account statement page/list reads. It does not implement settlement account payment creation, transfer, income, expenses, edit, delete, or balance mutation.

### 2. Modules In Scope

- `GET /biz/settlementaccountpayment/page`
- `GET /biz/settlementaccountpayment/list`
- Java/frontend-compatible settlement account statement fields
- Filters for account id, payer time, create time, and sorting
- API, public route-change, status, dashboard, and gap-map documentation

### 3. Files In Scope

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/SettlementAccountPaymentController.php`
- `app/service/biz/SettlementAccountPaymentService.php`
- `docs/api/biz-settlement-account-payment-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/api-gap-map.md`

### 4. Risks

- `route/app.php` is a locked public file, so route changes must stay limited to two protected read-only endpoints and be recorded in the public-file change request document.
- Java names this module `SettlementAccountStatement`, while the frontend route path is `settlementaccountpayment`; implementation must preserve the old route path.
- The frontend detail tab recalculates `beforeAmount` and `afterAmount` client-side, so backend rows must keep amount and settlement type/category values compatible.
- Settlement account balance mutations and transfer/payment creation remain out of scope.

### 5. Test Commands

```powershell
php -l app\controller\biz\SettlementAccountPaymentController.php
php -l app\service\biz\SettlementAccountPaymentService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/settlementaccountpayment/page` and `/list` appear in `php think route:list`.
- Both routes are protected by the existing `AuthMiddleware`.
- `page` returns a ThinkPHP-compatible pagination object with `records`, `total`, `current`, `size`, and `pages`.
- `list` returns rows for an `accountId`, sorted by `payerTime` when requested by the frontend.
- Responses include frontend-compatible fields such as `id`, `accountId`, `processId`, `beforeAmount`, `amount`, `afterAmount`, `settlementType`, `settlementCategory`, `processCategory`, `payerTime`, `createTime`, and `extJson`.
- Java source, database schema, frontend files, Composer files, `.env`, and balance mutation endpoints remain unchanged.

### 7. Completion Notes

- Added two protected read-only routes for settlement account statement rows.
- Added a thin Controller and read-only Service compatible with Java `page` and `list`.
- Supported Java `startPlayTime/endPlayTime` filters and frontend `startPayerTime/endPayerTime` aliases.
- Returned frontend-compatible amount, settlement type/category, process id, timestamp, account, and organization display fields.
- Kept settlement account payment creation, transfer, income/expense mutations, balance changes, Java source, database schema, frontend files, Composer files, and `.env` changes out of scope.
## Active Plan: api-agent - Biz Payroll Read API Compatibility

Date: 2026-06-03

### 1. Current Goal

Add the next small read-only compatibility slice for the Java payroll module so the copied frontend payroll pages can load salary rows and details through ThinkPHP.

### 2. Involved Modules

- api-agent
- Java read-only inputs under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/BizPayrollController.php`
- `app/service/biz/BizPayrollService.php`
- `route/app.php`
- `docs/api/biz-payroll-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/public-file-change-request.md`
- `STATUS.md`

### 4. Risks

- Payroll data is sensitive, so ordinary page reads must keep Java-style data-scope filtering and `mypage` must stay limited to the current user.
- The Java table uses the `USER` column name, so SQL aliases must be explicit.
- Write endpoints such as import, generate, add, edit, batch edit, export, and delete remain deferred.

### 5. Test Commands

```powershell
php -l app/controller/biz/BizPayrollController.php
php -l app/service/biz/BizPayrollService.php
php -l route/app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/bizpayroll/page`, `GET /biz/bizpayroll/mypage`, and `GET /biz/bizpayroll/detail` are registered behind token middleware.
- Page responses include Java/frontend-compatible salary fields plus `headName` and `orgName`.
- No Java source, database schema, frontend, Composer, `.env`, or write-side business behavior is modified.

### 7. Forbidden Scope

- Do not add payroll import, export, generate, add, edit, batch edit, or delete behavior.
- Do not modify locked public config files other than the documented `route/app.php` route addition.
- Do not modify `F:\AI\projects\testJava\OA`.

## Active Plan: api-agent - Biz Datareport Sale Project Summary Reads

Date: 2026-06-03

### 1. Current Goal

Add the next small read-only compatibility slice for Java `BizDataReportController` sale-project summary endpoints used by the copied dashboard page.

### 2. Involved Modules

- api-agent
- Java read-only inputs under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `route/app.php`
- `docs/api/biz-datareport-saleproject-summary-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/public-file-change-request.md`
- `STATUS.md`

### 4. Risks

- The dashboard uses multiple report endpoints; this slice must not try to implement all reports at once.
- Java `saleproject` and `saleproject/list` filter by completion date and成交 states, while `saleproject/report` uses create time OR completion date and does not apply the same成交-state filter.
- Data scope must stay aligned with existing sale-project report reads.

### 5. Test Commands

```powershell
php -l app/controller/biz/BizDataReportController.php
php -l app/service/biz/BizDataReportService.php
php -l route/app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/bizdatareport/saleproject`, `/saleproject/list`, and `/saleproject/report` are registered behind token middleware.
- `saleproject` returns an object with `amount`.
- `saleproject/list` returns project amount rows with Java/frontend-compatible fields.
- `saleproject/report` returns an object with `list` containing `playState`, `projectState`, `createTime`, and `completionDate`.
- Java source, database schema, frontend, Composer, `.env`, and write-side business behavior remain unchanged.

### 7. Forbidden Scope

- Do not implement `saleProfit`, `saleproject/UnpaidPayment`, `settlement/income`, `settlement/expenses`, or `summary/statistics` in this slice.
- Do not modify locked public config files other than the documented `route/app.php` route addition.
- Do not modify `F:\AI\projects\testJava\OA`.

## Active Plan: api-agent - Biz Datareport Sale Project Unpaid Payment Read

Date: 2026-06-03

### 1. Current Goal

Add a focused read-only compatibility slice for Java `POST /biz/bizdatareport/saleproject/UnpaidPayment`, used by the copied data-report dashboard's current-month unpaid amount card.

### 2. Involved Modules

- api-agent
- Java read-only inputs under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `route/app.php`
- `docs/api/biz-datareport-saleproject-unpaid-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/public-file-change-request.md`
- `STATUS.md`

### 4. Risks

- The endpoint is a financial read, so it must preserve Java data-scope and org subtree filters.
- Java calculates unpaid amount as `totalPrice - amountCollected + totalReturnAmount`; this should not be replaced with a different finance formula.
- More complex profit, settlement, and summary-statistics reports remain out of scope.

### 5. Test Commands

```powershell
php -l app/controller/biz/BizDataReportController.php
php -l app/service/biz/BizDataReportService.php
php -l route/app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/bizdatareport/saleproject/UnpaidPayment` is registered behind token middleware.
- The response returns an object with `amount`.
- The calculation uses成交 project states plus `UNPAID` and `PARTIALLY_PAID` play states.
- Java source, database schema, frontend, Composer, `.env`, and write-side business behavior remain unchanged.

### 7. Forbidden Scope

- Do not implement `saleProfit`, `settlement/income`, `settlement/expenses`, or `summary/statistics` in this slice.
- Do not modify locked public config files other than the documented `route/app.php` route addition.
- Do not modify `F:\AI\projects\testJava\OA`.

## Active Plan: api-agent - Biz Datareport Settlement Income And Expenses Reads

Date: 2026-06-03

### 1. Current Goal

Add a focused read-only compatibility slice for Java `POST /biz/bizdatareport/settlement/income` and `POST /biz/bizdatareport/settlement/expenses`, used by the copied data-report settlement statistics page.

### 2. Involved Modules

- api-agent
- Java read-only inputs under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `route/app.php`
- `docs/api/biz-datareport-settlement-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/public-file-change-request.md`
- `STATUS.md`

### 4. Risks

- The endpoint parameters use `startCreateTime/endCreateTime`, but Java applies them to `PAYER_TIME`; the ThinkPHP implementation must preserve that behavior.
- Settlement report reads touch financial payment and expenditure records, so data-scope organization filters and current-user fallback must be preserved.
- These endpoints return raw record lists for frontend aggregation; they must not mutate account balances or create settlement statements.

### 5. Test Commands

```powershell
php -l app\controller\biz\BizDataReportController.php
php -l app\service\biz\BizDataReportService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/bizdatareport/settlement/income` and `/settlement/expenses` are registered behind token middleware.
- Income reads return payment record rows with Java/frontend-compatible fields such as `settlementCategory`, `amount`, `payerTime`, `payer`, `remark`, `accountName`, and `orgName`.
- Expenses reads return expenditure record rows with the same frontend-compatible settlement fields.
- The Java-compatible org subtree, token data-scope, current-user fallback, category, and payer-time filters are preserved.
- Java source, database schema, frontend, Composer, `.env`, account-balance mutation, and write-side business behavior remain unchanged.

### 7. Forbidden Scope

- Do not implement `saleProfit` or `summary/statistics` in this slice.
- Do not add settlement account income, expense, payment, transfer, or balance mutation routes.
- Do not modify locked public config files other than the documented `route/app.php` route addition.
- Do not modify `F:\AI\projects\testJava\OA`.

## Active Plan: api-agent - Biz Datareport Sale Profit Read

Date: 2026-06-03

### 1. Current Goal

Add a focused read-only compatibility slice for Java `POST /biz/bizdatareport/saleProfit`, used by the copied sale-profit dashboard page.

### 2. Involved Modules

- api-agent
- Java read-only inputs under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `route/app.php`
- `docs/api/biz-datareport-sale-profit-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/public-file-change-request.md`
- `STATUS.md`

### 4. Risks

- The frontend computes profit in a WebWorker and expects raw Java-compatible `projectlist`, `orderList`, and `bizProducts` collections rather than precomputed summary numbers.
- Java purchase-order scope ignores the selected `orgId` and only uses login data-scope or current-user fallback; the ThinkPHP implementation should preserve that behavior.
- Product item `children` compatibility matters because the frontend treats any truthy `children` field as a kit product. Empty child arrays must not be sent for single products in this endpoint.
- Purchase and sales records are financial inputs, so this slice must stay strictly read-only.

### 5. Test Commands

```powershell
php -l app\controller\biz\BizDataReportController.php
php -l app\service\biz\BizDataReportService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/bizdatareport/saleProfit` is registered behind token middleware.
- The response returns `projectlist`, `orderList`, and `bizProducts`.
- `projectlist` includes project `productList` and return-order `productList` rows needed by the frontend worker.
- `orderList` includes completed purchase orders with nested `orderItems`.
- `bizProducts` includes product rows needed for worker product lookup.
- Java source, database schema, frontend, Composer, `.env`, finance mutation, workflow, and write-side business behavior remain unchanged.

### 7. Forbidden Scope

- Do not implement `summary/statistics` in this slice.
- Do not add purchase, sale, inventory, settlement, payment, or workflow mutation routes.
- Do not modify locked public config files other than the documented `route/app.php` route addition.
- Do not modify `F:\AI\projects\testJava\OA`.

## Active Plan: api-agent - Biz Datareport Summary Statistics Read

Date: 2026-06-03

### 1. Current Goal

Add a focused read-only compatibility slice for Java `POST /biz/bizdatareport/summary/statistics`, used by the copied annual summary statistics page and its WebWorker.

### 2. Involved Modules

- api-agent
- Java read-only inputs under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `route/app.php`
- `docs/api/biz-datareport-summary-statistics-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/public-file-change-request.md`
- `STATUS.md`

### 4. Risks

- The frontend WebWorker computes annual and monthly finance totals from raw Java-compatible collections, so backend response field names must match Java/frontend expectations.
- Java groups summary data by accessible company organizations and then expands each company to its child organizations; data-scope handling must preserve this shape.
- The endpoint returns potentially large yearly financial collections, so queries must stay bounded by data-scope, company subtree, tenant, and selected year.
- This slice reads finance data only. Account balance changes, settlement corrections, workflow starts, and repayment writes remain forbidden.

### 5. Test Commands

```powershell
php -l app\controller\biz\BizDataReportController.php
php -l app\service\biz\BizDataReportService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/bizdatareport/summary/statistics` is registered behind token middleware.
- The response returns one item per accessible company organization.
- Each item includes `org`, `settlementAccounts`, `paymentRecords`, `bizExpenditureRecords`, `bizSaleProjects`, and `bizDebitNotes`.
- Returned records use camelCase fields expected by the copied frontend worker, including `completionDate`, `totalPrice`, `historyAmount`, `totalReturnAmount`, `payerTime`, `settlementCategory`, `targetId`, `initialAmount`, `playStatus`, and `settlementAmount`.
- Java source, database schema, frontend, Composer, `.env`, finance mutation, workflow, and write-side business behavior remain unchanged.

### 7. Forbidden Scope

- Do not add settlement account income, expense, payment, transfer, or balance mutation routes.
- Do not add or modify workflow start/approve/reject/cancel behavior.
- Do not modify locked public config files other than the documented `route/app.php` route addition.
- Do not modify `F:\AI\projects\testJava\OA`.
## 2026-06-03 - test-agent - Summary Statistics Browser Smoke

### Current Goal

Verify the completed read-only summary-statistics route through the copied Vue frontend and local ThinkPHP backend.

### Modules

- Frontend joint testing
- Biz datareport summary statistics

### Files Involved

- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### Risks

- The page runs frontend WebWorker calculations over imported finance data, so rendering may be slower than simple list pages.
- Layout-level realtime message/WebPush behavior may still produce console noise unrelated to the summary-statistics table.
- Screenshot capture in the in-app browser may time out on heavy table pages; visible DOM text is the primary verification signal for this smoke.

### Test Commands

- Start ThinkPHP dev server on port `82`.
- Start Vue dev server on port `83`.
- Browser login smoke.
- Browser route smoke for `/biz/bizdatareport/summaryStatistics`.
- `git diff --check`

### Acceptance Criteria

- Login reaches the authenticated layout.
- `/biz/bizdatareport/summaryStatistics` loads with title `汇总统计 - 福地科技`.
- The page renders `汇总统计表` and `未回款统计表`.
- No ThinkPHP runtime exception is introduced by the smoke.

### Do Not Modify

- Java source project.
- Business Controller, Service, Model, Mapper, or database schema.
- Public locked config files.
## Active Plan: api-agent - Sale Project Cost Read

Date: 2026-06-03

### 1. Current Goal

Add a focused read-only compatibility slice for Java sale-project cost endpoints used by the copied frontend sale-project API wrapper.

### 2. Involved Modules

- api-agent
- Java read-only inputs under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/SaleProjectController.php`
- `app/service/biz/SaleProjectService.php`
- `route/app.php`
- `docs/api/biz-saleproject-cost-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### 4. Risks

- Java cost details combine sale project product items, combo-product child rows, return orders, and completed purchase order item unit amounts.
- The endpoint must stay read-only and must not update inventory, settlement, purchase order, sale project, workflow, or account-balance state.
- The copied frontend currently calls `cost/details`; `cost` is added only for Java route compatibility.
- The Java `cost` aggregate uses the detail result as its source. The ThinkPHP route must remain deterministic and documented because the original aggregate expression is easy to misread.

### 5. Test Commands

```powershell
php -l app\controller\biz\SaleProjectController.php
php -l app\service\biz\SaleProjectService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/saleproject/cost/details` is registered behind token middleware.
- `POST /biz/saleproject/cost` is registered behind token middleware.
- `cost/details` returns `items`, `productItems`, and `returnOrders`.
- Cost items include `productId`, `productName`, `amount`, and `avgUnitAmount`.
- Product amount calculation reads sale project items, combo child rows, and return-order product rows without mutating data.
- Average unit amount reads completed purchase-order items only.
- Java source, database schema, frontend files, Composer files, `.env`, purchase/inventory/finance/workflow writes, and account-balance behavior remain unchanged.

### 7. Forbidden Scope

- Do not add sale project add/edit/delete/cancel/repeal/visibility/history/special routes.
- Do not add purchase, inventory, delivery, settlement, payment, return-order, workflow, or account-balance mutation behavior.
- Do not modify locked public config files other than the documented `route/app.php` route addition.
- Do not modify `F:\AI\projects\testJava\OA`.

## Active Plan: api-agent - Sale Project Follow-Up Read

Date: 2026-06-03

### 1. Current Goal

Add a focused read-only compatibility slice for Java sale-project follow-up endpoints used by the copied sale-project follow-up list and sale-project detail tabs.

### 2. Involved Modules

- api-agent
- Java read-only inputs under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/SaleProjectFollowUpController.php`
- `app/service/biz/SaleProjectFollowUpService.php`
- `route/app.php`
- `docs/api/biz-saleproject-followup-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### 4. Risks

- The copied sale-project detail tab parses `extJson.fileList`; the backend must return the original `extJson` unchanged.
- Java page queries join sale projects for data-scope filtering, so the ThinkPHP query must not expose follow-up records outside the visible sale-project scope.
- The frontend also contains add/edit/delete calls, but those write paths update records and attachments and remain deferred in this read-only slice.
- Browser-level testing may depend on a sale project being visible in the frontend table, which currently has a known display/query mismatch.

### 5. Test Commands

```powershell
php -l app\controller\biz\SaleProjectFollowUpController.php
php -l app\service\biz\SaleProjectFollowUpService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/saleprojectfollowup/page` is registered behind token middleware.
- `GET /biz/saleprojectfollowup/detail` is registered behind token middleware.
- Page records include `projectId`, `projectName`, `followUpTime`, `category`, `content`, `createUserName`, `avatar`, `createUserOrgId`, `createUserOrgName`, and `extJson`.
- Filters support `projectId`, `startFollowUpTime`, `endFollowUpTime`, `category`, `content`, and `searchKey`.
- Sort supports Java/frontend camelCase fields and defaults to ascending follow-up id.
- Java source, database schema, frontend files, Composer files, `.env`, sale-project writes, attachment writes, workflow, and finance behavior remain unchanged.

### 7. Forbidden Scope

- Do not add sale-project follow-up add/edit/delete routes in this slice.
- Do not modify sale-project, workflow, finance, file upload, or attachment write behavior.
- Do not modify locked public config files other than the documented `route/app.php` route addition.
- Do not modify `F:\AI\projects\testJava\OA`.

## Active Plan: api-agent - Sale Project Product Item Relation List Read

Date: 2026-06-03

### 1. Current Goal

Add a focused read-only compatibility slice for Java `POST /biz/saleprojectproductitemrelation/list`, used by copied sale-project delivery/invoice UI helpers to read combo-product child rows.

### 2. Involved Modules

- api-agent
- Java read-only inputs under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/SaleProjectProductItemRelationController.php`
- `app/service/biz/SaleProjectProductItemRelationService.php`
- `route/app.php`
- `docs/api/biz-saleproject-product-item-relation-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `STATUS.md`

### 4. Risks

- Java returns raw relation rows, while existing ThinkPHP sale-project detail already embeds child rows; the standalone list route should preserve raw relation compatibility and product display fields.
- The copied frontend exposes `mark/edit`, but that route mutates relation marks and must stay deferred in this read-only slice.
- Relation rows are addressed by sale-project product item ids, so ThinkPHP should data-scope through `biz_sale_project_product_item -> biz_sale_project`.
- Some rows rely on `EXT_JSON.product`; when missing, the response should provide a minimal product JSON fallback like the existing sale-project detail service.

### 5. Test Commands

```powershell
php -l app\controller\biz\SaleProjectProductItemRelationController.php
php -l app\service\biz\SaleProjectProductItemRelationService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/saleprojectproductitemrelation/list` is registered behind token middleware.
- Requests can pass Java-style body rows like `[{ "id": "<productItemId>" }]`.
- Response rows include `id`, `objectId`, `targetId`, `productId`, `mark`, `number`, `extJson`, product display fields, audit fields, and tenant id.
- The route reads only rows under visible sale projects.
- Java source, database schema, frontend files, Composer files, `.env`, relation mark writes, sale-project writes, delivery/invoice writes, workflow, inventory, and finance behavior remain unchanged.

### 7. Forbidden Scope

- Do not add `POST /biz/saleprojectproductitemrelation/mark/edit`.
- Do not add `POST /biz/saleprojectproductitem/mark/edit`.
- Do not modify sale-project product items, invoices, delivery, inventory, workflow, finance, file upload, or account-balance behavior.
- Do not modify locked public config files other than the documented `route/app.php` route addition.
- Do not modify `F:\AI\projects\testJava\OA`.

## Active Plan: api-agent/frontend-agent - Sale Project Page Data Scope Smoke Fix

Date: 2026-06-03

### 1. Current Goal

Fix the smallest backend compatibility issue causing the copied Vue `/biz/saleproject` page to show an empty table for the local admin smoke account while the imported database contains `FOLLOW` sale projects.

### 2. Involved Modules

- api-agent
- frontend-agent smoke only
- Java read-only inputs under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/service/biz/SaleProjectService.php`
- `docs/api/biz-saleproject-readonly.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- The copied sale-project page forces `projectState=FOLLOW`; strict fallback-to-current-user data scope can hide all imported rows for admin smoke testing.
- The fix must not relax ordinary user data scope, org filters, or tenant filters.
- Browser logs still contain unrelated realtime message and `docx-templates` warnings.

### 5. Test Commands

```powershell
php -l app\service\biz\SaleProjectService.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- Admin-compatible accounts such as `bizAdmin`, `superadmin`, or roles `bizadmin/superadmin/tenantadmin` can read sale-project lists without fallback user-only filtering.
- Ordinary data-scope behavior remains unchanged for non-admin accounts.
- Authenticated frontend-shaped `/biz/saleproject/page?projectState=FOLLOW&showDiscard=false` returns records.
- `/biz/process/query` still returns compatible rows for the sale-project page's secondary workflow lookup.
- Browser smoke on `/biz/saleproject` shows pagination instead of `暂无数据`.

### 7. Forbidden Scope

- Do not change frontend source in this slice.
- Do not change routes, Composer files, `.env`, database schema, Java source, sale-project writes, workflow writes, finance behavior, inventory behavior, or account-balance behavior.

## Completed Plan: test-agent/frontend-agent - Sale Project Detail Tab Smoke

Date: 2026-06-03

### 1. Current Goal

Verify the copied Vue sale-project detail modal after the sale-project list visibility fix, focusing only on read-only tab rendering and avoiding all write buttons.

### 2. Involved Modules

- test-agent browser smoke
- frontend-agent compatibility observation
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`

### 4. Risks

- The detail modal contains write controls such as follow-up add, case upload, edit, discard, and batch discard. This smoke must not click those controls.
- Browser console still has known realtime-message disconnect noise and upstream `docx-templates` warnings that are unrelated to sale-project detail reads.
- Some tabs can legitimately show empty states depending on the sampled project data.

### 5. Test Commands

```powershell
git status --short --branch
git diff --check
```

Browser smoke:

- Open `/biz/saleproject`.
- Open a visible sale-project detail modal.
- Click `项目跟进记录`.
- Click `项目案例`.
- Click `审核中的流程`.

### 6. Acceptance Criteria

- The sale-project detail modal opens from a visible table row.
- The information tab renders project and customer details.
- The follow-up tab renders existing follow-up read data.
- The case tab renders its empty/read state without backend runtime failure.
- The pending-process tab renders its empty/read state without backend runtime failure.
- Java source, database schema, frontend files, routes, services, controllers, models, Composer files, `.env`, sale-project writes, workflow writes, finance behavior, inventory behavior, file upload, and account-balance behavior remain unchanged.

### 7. Forbidden Scope

- Do not click or implement add, edit, delete, discard, upload, workflow action, delivery, invoice, inventory, finance, or account-balance write behavior.
- Do not modify `F:\AI\projects\testJava\OA`.

## Completed Plan: api-agent/frontend-agent - Sale Project Cost Route Precedence Fix

Date: 2026-06-03

### 1. Current Goal

Fix the completed sale-project detail cost tab smoke failure where the copied Vue frontend calls `POST /biz/saleproject/cost/details` but ThinkPHP route matching returns the aggregate `/cost` response first.

### 2. Involved Modules

- api-agent route compatibility
- frontend-agent browser smoke
- Java read-only inputs under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `route/app.php`
- `docs/api/biz-saleproject-cost-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- `route/app.php` is a locked public file; this change must be documented as a public-file change request.
- ThinkPHP route matching can prefer the shorter `cost` route if it is registered before `cost/details`.
- The fix must preserve both Java-compatible endpoints and keep both routes protected by `AuthMiddleware`.

### 5. Test Commands

```powershell
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

HTTP/browser smoke:

- Authenticated `POST /biz/saleproject/cost/details` returns `items`, `productItems`, and `returnOrders`.
- Authenticated `POST /biz/saleproject/cost` still returns the numeric aggregate.
- Browser cost tab in `/biz/saleproject/dealProjectList` no longer renders the 500 result.

### 6. Acceptance Criteria

- `cost/details` is registered before `cost` in the sale-project route group.
- No new route, controller, service, model, frontend source, database schema, Java source, Composer, `.env`, write-side sale-project behavior, workflow behavior, inventory behavior, finance behavior, file upload, or account-balance behavior is changed.

### 7. Forbidden Scope

- Do not implement sale-project writes.
- Do not implement delivery, invoice, return, workflow, inventory, finance, or account-balance mutations.
- Do not modify `F:\AI\projects\testJava\OA`.

## Completed Plan: frontend-agent - Sale Project Cost Zero-Revenue Display Fix

Status: completed on 2026-06-03 after frontend build and source verification. Browser automation for the already-open local page was blocked by the browser URL policy, so visual confirmation remains a user/manual smoke item.

Date: 2026-06-03

### 1. Current Goal

Fix the copied Vue sale-project cost tab display where imported historical projects with `totalPrice = 0` render gross profit rate as `NaN%`.

### 2. Involved Modules

- frontend-agent
- ThinkPHP target frontend copy under `F:\AI\projects\testJava\OA-ThinkPHP\snowy-admin-web`

### 3. Involved Files

- `snowy-admin-web/src/views/biz/saleproject/saleProjectTab/cost/index.vue`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- The cost tab uses Decimal.js; division by zero should be avoided without changing business totals.
- The fix must not change backend cost data, database records, project state, finance behavior, or write-side routes.

### 5. Test Commands

```powershell
npm run build
git diff --check
git status --short --branch
```

Browser smoke:

- Open `/biz/saleproject/dealProjectList`.
- Open a historical completed project detail.
- Open `成本核算`.
- Confirm gross profit rate shows a numeric zero-value display instead of `NaN%`.

### 6. Acceptance Criteria

- Zero or empty sales revenue returns gross profit rate `0`.
- Non-zero sales revenue continues using the existing Decimal gross-profit-rate formula.
- No backend, route, Java source, database schema, Composer file, `.env`, sale-project write behavior, workflow behavior, inventory behavior, finance behavior, file upload, or account-balance behavior is changed.

### 7. Forbidden Scope

- Do not change cost calculation data returned by ThinkPHP.
- Do not implement sale-project writes or finance/workflow side effects.
- Do not modify `F:\AI\projects\testJava\OA`.

## Completed Plan: test-agent/frontend-agent - Sale Project Detail Remaining Tab API Smoke

Status: completed on 2026-06-03 with direct authenticated service smoke. No business source file changes were needed.

Date: 2026-06-03

### 1. Current Goal

Verify the remaining read-only sale-project detail tab APIs used by the copied Vue frontend after the cost tab compatibility fixes.

### 2. Involved Modules

- test-agent
- frontend-agent compatibility observation
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`

No backend or frontend business source file is planned for this smoke unless a small, clearly scoped read-only compatibility bug is found.

### 4. APIs To Smoke

- `GET /biz/bizpaymentrecord/page`
- `GET /biz/returnorder/page`
- `GET /biz/saleprojectinvoice/list`
- `GET /biz/bizfilerelation/list`

### 5. Risks

- Some sampled historical projects may legitimately have empty payment, return, invoice, or file rows.
- Browser automation against the local page was blocked by URL policy in the previous slice, so this smoke uses authenticated PHP/HTTP or direct service checks unless the browser becomes available again.
- This smoke must not click upload, edit, delete, workflow, finance, inventory, or account-balance write controls.

### 6. Test Commands

```powershell
php think route:list
git diff --check
git status --short --branch
```

Smoke checks:

- Authenticate with the local test user.
- Select a visible completed sale project from the imported database.
- Call the four tab APIs with that project id.
- Confirm each route returns `code = 200` or a valid read-only service result.

### 7. Acceptance Criteria

- Payment, return-order, invoice, and file-relation tab reads execute without ThinkPHP runtime errors.
- Empty datasets are accepted as valid for imported historical records.
- No Java source, database schema, backend business source, frontend component source, Composer file, `.env`, sale-project write behavior, workflow behavior, inventory behavior, finance behavior, file upload write behavior, or account-balance behavior is changed.

### 8. Forbidden Scope

- Do not add or test sale-project writes.
- Do not add or test file upload writes.
- Do not add or test finance, inventory, workflow, return, delivery, or account-balance mutations.
- Do not modify `F:\AI\projects\testJava\OA`.

## Completed Plan: frontend-agent - Message SSE Noise Fallback

Status: completed on 2026-06-03 after frontend build, route-list check, and authenticated browser observation.

Date: 2026-06-03

### 1. Current Goal

Reduce repeated frontend console noise from the copied layout message panel while ThinkPHP only provides the current short-lived `/dev/message/createSseConnect` compatibility stream.

### 2. Involved Modules

- frontend-agent
- test-agent smoke
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `snowy-admin-web/src/layout/components/panel-message/index.vue`
- `PLANS.md`
- `STATUS.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`

### 4. Risks

- The current ThinkPHP SSE service is intentionally short-lived to avoid blocking the local PHP built-in server.
- The frontend currently assumes a long-lived EventSource and logs/reconnects every time the short stream closes.
- This slice must not implement Redis pub/sub, workflow push, message send/delete, read-status writes, or production realtime behavior.

### 5. Test Commands

```powershell
npm run build
git diff --check
git status --short --branch
```

Optional browser smoke:

- Start backend on port `82`.
- Start frontend on port `83`.
- Open the login page and authenticated layout.
- Confirm the layout can load without repeated message-SSE error spam.

### 6. Acceptance Criteria

- The panel message component closes existing SSE/timer resources on unmount.
- Short-lived SSE disconnects no longer produce repeated `console.error` noise every few seconds.
- The component still loads task and message counts on initial connection and when the message panel opens.
- Java source, database schema, backend SSE service, route files, message writes, workflow writes, Redis/queue behavior, Composer files, `.env`, and production data sync behavior remain unchanged.

### 7. Forbidden Scope

- Do not implement full realtime push in this slice.
- Do not add message send/delete/read-state write routes.
- Do not modify `F:\AI\projects\testJava\OA`.

## Completed Plan: user-agent/frontend-agent - Sys User Grant Echo Read-Only Compatibility

Status: completed on 2026-06-03 after route/service implementation and verification, pending commit.

### 1. Current Goal

Support the copied `/sys/user` page grant dialogs with read-only echo endpoints for existing user role, resource, and permission grants.

### 2. Involved Modules

- user-agent read-only directory/RBAC echo
- frontend-agent visible system user page compatibility
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/service/user/UserDirectoryService.php`
- `app/controller/sys/UserController.php`
- `route/app.php`
- `docs/api/sys-user-grant-readonly.md`
- `docs/api/frontend-readonly-selector-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Grant dialogs include save buttons, but this slice must only add read echo endpoints.
- `route/app.php` is a locked public file, so the route change is recorded in `docs/tasks/public-file-change-request.md`.
- `sys_relation.EXT_JSON` can be empty or malformed; the read response falls back to `TARGET_ID` for compatibility.

### 5. Test Commands

```powershell
php -l app\service\user\UserDirectoryService.php
php -l app\controller\sys\UserController.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /sys/user/list/detail` is registered behind token middleware.
- `GET /sys/user/ownRole` is registered behind token middleware.
- `GET /sys/user/ownResource` is registered behind token middleware.
- `GET /sys/user/ownPermission` is registered behind token middleware.
- Grant echo responses read only existing `sys_relation` records.
- User rows continue to omit `PASSWORD`.

### 7. Forbidden Scope

- Do not add `/sys/user/grantRole`, `/sys/user/grantResource`, or `/sys/user/grantPermission`.
- Do not implement user add/edit/delete, enable/disable, reset password, import/export, upload/avatar, workflow, finance, or business writes.
- Do not modify Java source or database schema.

## Active Plan: api-agent/workflow-agent - Biz CC Records Read-Only Compatibility

Status: completed on 2026-06-04 after route, syntax, service-smoke, and baseline checks.

Date: 2026-06-04

### 1. Current Goal

Add Java-compatible read-only endpoints for workflow copy/CC records used by the copied Vue copy-task page.

### 2. Involved Modules

- api-agent route/controller compatibility
- workflow-agent read-only copy/CC record context
- frontend-agent visible copied page compatibility
- Java read-only input under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/CcRecordsController.php`
- `app/service/biz/CcRecordsService.php`
- `route/app.php`
- `docs/api/biz-cc-records-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- The copied page still exposes delete controls, but this slice must only implement reads.
- Java page queries restrict rows to the current login user; ThinkPHP must preserve that behavior.
- The process-detail drawer relies on `instanceId`, so the read response must preserve it.

### 5. Test Commands

```powershell
php -l app\controller\biz\CcRecordsController.php
php -l app\service\biz\CcRecordsService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/ccrecords/page` is registered behind token middleware.
- `GET /biz/ccrecords/detail` is registered behind token middleware.
- Page reads filter by the current bearer token user id.
- Rows include `id`, `title`, `processId`, `promoterId`, `promoterName`, `instanceId`, `category`, `user`, `userName`, and audit fields.
- Java source, database schema, workflow writes, delete behavior, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not add `/biz/ccrecords/add`, `/edit`, or `/delete`.
- Do not implement workflow copy delegate writes, approval/reject/start/cancel actions, task SSE, or business side effects.
- Do not modify `F:\AI\projects\testJava\OA`.

## Active Plan: api-agent - Biz Draft Read-Only Detail Compatibility

Status: completed on 2026-06-04 after route, syntax, service-smoke, and baseline checks.

Date: 2026-06-04

### 1. Current Goal

Add Java-compatible read-only detail support for sale-project draft data used by the copied Vue sale-project draft flow.

### 2. Involved Modules

- api-agent business read adapter
- frontend-agent copied sale-project draft compatibility
- Java read-only input under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/BizDraftController.php`
- `app/service/biz/BizDraftService.php`
- `route/app.php`
- `docs/api/biz-draft-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java `detail` queries by `TARGET_ID`, not by draft row `ID`.
- The frontend expects the raw `EXT_JSON` draft payload to remain parseable.
- `/biz/bizdraft/saleproject/add` is a write endpoint and must remain deferred.
- `route/app.php` is a locked public file, so the route change must be recorded.

### 5. Test Commands

```powershell
php -l app\controller\biz\BizDraftController.php
php -l app\service\biz\BizDraftService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/bizdraft/detail` is registered behind token middleware.
- Detail reads by `TARGET_ID` and returns the matching non-deleted draft row.
- Rows include `id`, `targetId`, `category`, `extJson`, audit fields, and tenant id.
- Java source, database schema, sale-project draft writes, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not add `/biz/bizdraft/saleproject/add`.
- Do not modify sale-project add/edit, workflow start, file upload, or draft save behavior.
- Do not modify `F:\AI\projects\testJava\OA`.

## Active Plan: workflow-agent/api-agent - Biz User Vacation Detail Read-Only Compatibility

Status: completed on 2026-06-04 after route, syntax, service-smoke, and baseline checks.

Date: 2026-06-04

### 1. Current Goal

Add Java-compatible read-only annual-leave balance detail support for copied leave-process pages.

### 2. Involved Modules

- workflow-agent leave-process read context
- api-agent business read adapter
- frontend-agent copied leave form/detail compatibility
- Java read-only input under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/BizUserVacationController.php`
- `app/service/biz/BizUserVacationService.php`
- `route/app.php`
- `docs/api/biz-user-vacation-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java `detail` defaults to the current login user when `userId` is omitted.
- Java `detail` defaults to `annualLeave` when `category` is omitted.
- Java filters records by the current year using `CREATE_TIME`.
- Leave approval later deducts vacation through separate write behavior; this slice must not mutate balances.
- `route/app.php` is a locked public file, so the route change must be recorded.

### 5. Test Commands

```powershell
php -l app\controller\biz\BizUserVacationController.php
php -l app\service\biz\BizUserVacationService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/bizuservacation/detail` is registered behind token middleware.
- Detail reads by requested `userId` or current token user id.
- Detail defaults to `annualLeave` and current-year records.
- Missing records return a zero-balance annual-leave object compatible with the copied frontend.
- Java source, database schema, vacation writes, leave approval deductions, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not add `/biz/bizuservacation/page`, `/add`, `/edit`, or `/delete`.
- Do not implement vacation generation, reduction, leave approval deductions, workflow writes, or payroll writes.
- Do not modify `F:\AI\projects\testJava\OA`.

## Active Plan: api-agent/frontend-agent - Biz History Excel Read-Only Compatibility

Status: completed on 2026-06-04 after route, syntax, service-smoke, and baseline checks.

Date: 2026-06-04

### 1. Current Goal

Add Java-compatible read-only page and detail endpoints for the copied historical EXCEL data page.

### 2. Involved Modules

- api-agent business read adapter
- frontend-agent copied page compatibility
- Java read-only input under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/BizHistoryExcelController.php`
- `app/service/biz/BizHistoryExcelService.php`
- `route/app.php`
- `docs/api/biz-history-excel-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- `biz_history_excel.EXT_JSON` can be large because it stores serialized spreadsheet data.
- The copied frontend still exposes add, edit, and delete controls, but this slice must not add write routes.
- Java default sorting is by `ID` ascending; custom sorting must use a whitelist.
- `route/app.php` is a locked public file, so the route change must be recorded.

### 5. Test Commands

```powershell
php -l app\controller\biz\BizHistoryExcelController.php
php -l app\service\biz\BizHistoryExcelService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/bizhistoryexcel/page` is registered behind token middleware.
- `GET /biz/bizhistoryexcel/detail` is registered behind token middleware.
- Page reads preserve Java-compatible pagination and safe sorting.
- Detail returns the matching row by `id`.
- Rows include `id`, `name`, `remark`, `extJson`, audit fields, `deleteFlag`, and `tenantId`.
- Java source, database schema, history Excel writes, file import/export behavior, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not add `/biz/bizhistoryexcel/add`, `/edit`, or `/delete`.
- Do not implement Excel import/export, spreadsheet parsing changes, storage writes, or data mutation.
- Do not modify `F:\AI\projects\testJava\OA`.

## Active Plan: api-agent/frontend-agent - Sale Project Invoice Item Page Read-Only Compatibility

Status: completed on 2026-06-04 after route, syntax, service-smoke, and baseline checks.

Date: 2026-06-04

### 1. Current Goal

Add Java-compatible read-only page support for sale-project delivery invoice item rows.

### 2. Involved Modules

- api-agent sales-project billing read adapter
- frontend-agent copied invoice/detail page compatibility
- Java read-only input under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/SaleProjectInvoiceItemController.php`
- `app/service/biz/SaleProjectBillingService.php`
- `route/app.php`
- `docs/api/sale-project-invoice-item-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java exposes only `/biz/saleprojectinvoiceItem/page` for the invoice item controller; no write routes should be opened.
- Java page filters by `invoiceId` and `warehousesId` and defaults sorting by `projectProductItemId`.
- The route path contains an uppercase `I` in `invoiceItem`, so the ThinkPHP route must preserve that compatibility path.
- `route/app.php` is a locked public file, so the route change must be recorded.

### 5. Test Commands

```powershell
php -l app\controller\biz\SaleProjectInvoiceItemController.php
php -l app\service\biz\SaleProjectBillingService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/saleprojectinvoiceItem/page` is registered behind token middleware.
- Page reads existing non-deleted invoice item rows.
- Filters support `invoiceId` and `warehousesId`.
- Sorting uses a whitelist and defaults to `PROJECT_PRODUCT_ITEM_ID` ascending.
- Rows preserve Java item fields and include compatible product/warehouse display fields already used by sale-project invoice details.
- Java source, database schema, invoice writes, delivery writes, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not add invoice item add/edit/delete routes.
- Do not modify invoice creation, delivery shipment, stock, project state, or finance write behavior.
- Do not modify `F:\AI\projects\testJava\OA`.

## Active Plan: api-agent/frontend-agent - Sales Project Field Change Log Read-Only Compatibility

Status: completed on 2026-06-04 after route, syntax, service-smoke, and baseline checks.

Date: 2026-06-04

### 1. Current Goal

Add Java-compatible read-only page and detail support for sale-project field change log records.

### 2. Involved Modules

- api-agent sale-project read adapter
- frontend-agent copied sale-project detail/history compatibility
- Java read-only input under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/SalesProjectFieldChangeLogController.php`
- `app/service/biz/SalesProjectFieldChangeLogService.php`
- `route/app.php`
- `docs/api/sales-project-field-change-log-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java `page` defaults to `ID` ascending and has no business write side effects.
- The table stores project change reasons and before/after values; values should be returned without mutation.
- The route path is `salesprojectfieldchangelog`, not `saleprojectfieldchangelog`.
- `route/app.php` is a locked public file, so the route change must be recorded.

### 5. Test Commands

```powershell
php -l app\controller\biz\SalesProjectFieldChangeLogController.php
php -l app\service\biz\SalesProjectFieldChangeLogService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/salesprojectfieldchangelog/page` is registered behind token middleware.
- `GET /biz/salesprojectfieldchangelog/detail` is registered behind token middleware.
- Page reads existing non-deleted change-log rows with Java-compatible pagination and safe sorting.
- Detail returns the matching row by `id`.
- Rows include `id`, `objectId`, `fieldName`, `fieldLabel`, `beforeValue`, `afterValue`, `changeReason`, audit fields, and tenant id.
- Java source, database schema, change-log writes, sale-project writes, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not add `/biz/salesprojectfieldchangelog/add`, `/edit`, or `/delete`.
- Do not modify sale-project amount/change writes, project history generation, workflow, finance, or audit side effects.
- Do not modify `F:\AI\projects\testJava\OA`.

## Active Plan: api-agent/frontend-agent - Team Project Task User Read-Only Compatibility

Status: completed on 2026-06-04 after route, syntax, service-smoke, and baseline checks.

Date: 2026-06-04

### 1. Current Goal

Add Java-compatible read-only page and detail support for team-project task user rows.

### 2. Involved Modules

- api-agent team-project task read adapter
- frontend-agent copied team-project/task compatibility
- Java read-only input under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/TeamProjectTaskUserController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/team-project-task-user-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java `page` defaults to `ID` ascending and exposes add/edit/delete separately; this slice must not open writes.
- Existing ThinkPHP team-project reads guard by current user's project membership; task-user page/detail should keep that boundary.
- Rows should keep Java task-user fields plus translated user display fields `headName` and `avatar`.
- `route/app.php` is a locked public file, so the route change must be recorded.

### 5. Test Commands

```powershell
php -l app\controller\biz\TeamProjectTaskUserController.php
php -l app\service\biz\TeamProjectTaskReadService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/bizteamprojecttaskuser/page` is registered behind token middleware.
- `GET /biz/bizteamprojecttaskuser/detail` is registered behind token middleware.
- Page reads existing non-deleted task-user rows from team projects visible to the current user.
- Detail returns the matching task-user row by `id`.
- Rows include `id`, `userId`, `headName`, `avatar`, `teamProjectId`, `teamProjectTaskId`, `roleType`, `extJson`, audit fields, and tenant id.
- Java source, database schema, task-user writes, team task writes, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not add `/biz/bizteamprojecttaskuser/add`, `/edit`, or `/delete`.
- Do not modify team task assignment writes, task status/progress writes, notifications, or workflow side effects.
- Do not modify `F:\AI\projects\testJava\OA`.

## Completed Plan: api-agent/frontend-agent - Dev Monitor Network Info Read-Only Compatibility

Status: completed on 2026-06-04 after route, syntax, service-smoke, and baseline checks.

Date: 2026-06-04

### 1. Current Goal

Add Java-compatible read-only network monitor info support for the copied dev monitor page.

### 2. Involved Modules

- api-agent dev monitor read adapter
- frontend-agent copied dev monitor compatibility
- Java read-only input under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/dev/MonitorController.php`
- `app/service/dev/MonitorService.php`
- `route/app.php`
- `docs/api/dev-monitor-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java returns only `devMonitorNetworkInfo` with `upLinkRate` and `downLinkRate`.
- Windows and Linux expose network counters differently; the ThinkPHP implementation should degrade to `0 B/s` instead of failing.
- The endpoint is operational monitoring only and must not mutate runtime, database, or config.
- `route/app.php` is a locked public file, so the route change must be recorded.

### 5. Test Commands

```powershell
php -l app\controller\dev\MonitorController.php
php -l app\service\dev\MonitorService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /dev/monitor/networkInfo` is registered behind token middleware.
- The service returns `devMonitorNetworkInfo.upLinkRate` and `devMonitorNetworkInfo.downLinkRate`.
- Missing or unsupported OS counters return safe zero rates instead of an exception.
- Java source, database schema, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not add monitor write routes.
- Do not change serverInfo shape.
- Do not modify OS services, database, config, or `F:\AI\projects\testJava\OA`.

## Completed Plan: api-agent/frontend-agent - Sale Project Rate Detail Read-Only Compatibility

Status: completed on 2026-06-05 after route, syntax, database smoke, and baseline checks.

Date: 2026-06-05

### 1. Current Goal

Add read-only detail compatibility for the copied sale-project customer rating API wrapper.

### 2. Involved Modules

- api-agent sale-project rating read adapter
- frontend-agent copied sale-project/project-case rating compatibility
- Java read-only input under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/SaleProjectRateController.php`
- `app/service/biz/SaleProjectBillingService.php`
- `route/app.php`
- `docs/api/biz-saleproject-billing-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java controller exposes `page` and `list`; Java service still has `detail/queryEntity`. The copied frontend wrapper includes `detail`, so this slice only exposes a protected read-only detail endpoint.
- Project rating rows include raw `extJson`; this slice must return it without mutation so the copied frontend can parse image lists.
- `route/app.php` is a locked public file, so the route change must be recorded.

### 5. Test Commands

```powershell
php -l app\controller\biz\SaleProjectRateController.php
php -l app\service\biz\SaleProjectBillingService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/projectrate/detail` is registered behind token middleware.
- Detail reads one non-deleted rating row by `id`.
- Detail returns the same normalized rating shape used by `page` and `list`, including `projectName`, `customerName`, and raw `extJson`.
- Java source, database schema, rating writes, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not add `/biz/projectrate/add`, `/edit`, or `/delete`.
- Do not modify rating image upload, project state, sale-project writes, or file storage behavior.
- Do not modify `F:\AI\projects\testJava\OA`.

## Completed Plan: workflow-agent/api-agent/frontend-agent - Biz User Vacation Page Read-Only Compatibility

Status: completed on 2026-06-05 after route, syntax, database smoke, and baseline checks.

Date: 2026-06-05

### 1. Current Goal

Add read-only page compatibility for copied annual-leave/vacation balance management wrappers.

### 2. Involved Modules

- workflow-agent annual-leave balance read support
- api-agent controller/route compatibility
- frontend-agent copied `bizUserVacationApi` compatibility
- Java read-only input under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/BizUserVacationController.php`
- `app/service/biz/BizUserVacationService.php`
- `route/app.php`
- `docs/api/biz-user-vacation-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java controller currently wires `detail`, while Java service still exposes `page`; the copied frontend wrapper includes `page`, so this slice adds protected read-only frontend compatibility.
- Vacation writes affect annual-leave generation/reduction and leave approval deductions; this slice must not open any write route.
- `route/app.php` is a locked public file, so the route change must be recorded.

### 5. Test Commands

```powershell
php -l app\controller\biz\BizUserVacationController.php
php -l app\service\biz\BizUserVacationService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/bizuservacation/page` is registered behind token middleware.
- Page reads existing non-deleted vacation-balance rows with pagination.
- Rows include `id`, `userId`, `userName`, `amount`, `usedAmount`, `category`, audit fields, tenant id, and version.
- Java source, database schema, vacation generation/reduction, leave approval deductions, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not add `/biz/bizuservacation/add`, `/edit`, or `/delete`.
- Do not implement vacation generation, vacation reduction, leave approval deductions, or workflow write side effects.
- Do not modify `F:\AI\projects\testJava\OA`.

## Completed Plan: api-agent/frontend-agent - Team Project Comment Reply Read-Only Compatibility

Status: completed on 2026-06-05 after route, syntax, database smoke, and baseline checks.

Date: 2026-06-05

### 1. Current Goal

Close the next real frontend API gap by adding only read-only routes for copied team-project comment detail and standalone comment-reply page/detail consumers.

### 2. Involved Modules

- api-agent team-project read adapter
- frontend-agent copied team-project comment/reply API compatibility
- Java read-only input under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/TeamProjectCommentController.php`
- `app/controller/biz/TeamProjectCommentReplyController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/team-project-comment-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java `BizTeamProjectCommentController` exposes page/list but not detail, while Java service and copied frontend wrapper include detail.
- Java `BizTeamProjectCommentReplyController` exposes only write routes, while Java service and copied frontend wrapper include page/detail.
- Reply reads must not bypass the existing team-project membership boundary.
- `route/app.php` is a locked public file, so the route change must be recorded.

### 5. Test Commands

```powershell
php -l app\controller\biz\TeamProjectCommentController.php
php -l app\controller\biz\TeamProjectCommentReplyController.php
php -l app\service\biz\TeamProjectTaskReadService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /biz/bizteamprojectcomment/detail` is registered behind token middleware.
- `GET /biz/bizteamprojectcommentreply/page` and `/detail` are registered behind token middleware.
- Reads preserve the current team-project membership visibility boundary.
- Comment detail includes nested `bizTeamProjectCommentReplies`.
- Reply rows include `id`, `targetId`, `contentText`, `extJson`, creator display fields, audit fields, and tenant id.
- Java source, database schema, comment/reply writes, notifications, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not add `/biz/bizteamprojectcomment/add` or `/delete`.
- Do not add `/biz/bizteamprojectcommentreply/add`, `/edit`, or `/delete`.
- Do not modify task comments, team-project writes, notifications, file upload behavior, or `F:\AI\projects\testJava\OA`.

## Completed Plan: user-agent/api-agent/frontend-agent - Sys Field Read-Only Compatibility

Status: completed on 2026-06-05 after route, syntax, database smoke, and baseline checks.

Date: 2026-06-05

### 1. Current Goal

Close the copied frontend system-resource field page read gap by adding read-only `sys/field` routes backed by `sys_resource.CATEGORY = FIELD`.

### 2. Involved Modules

- user-agent system resource read compatibility
- api-agent route/controller mapping
- frontend-agent copied `sys/resource/fieldApi.js` compatibility
- Java/read-only frontend input under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/sys/FieldController.php`
- `app/service/sys/ResourceService.php`
- `route/app.php`
- `docs/api/sys-field-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- The copied frontend has `sys/field` API wrappers and views, but the current imported database has no `FIELD` rows in `sys_resource`.
- Java backend field controller was not found in the current source scan; behavior is inferred from the copied frontend and `sys_resource` category convention.
- `MenuTreeSelector` uses uppercase `M` in the frontend wrapper, so route registration must preserve that compatibility path.
- `route/app.php` is a locked public file, so the route change must be recorded.

### 5. Test Commands

```powershell
php -l app\controller\sys\FieldController.php
php -l app\service\sys\ResourceService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /sys/field/page`, `/tree`, `/detail`, and `/MenuTreeSelector` are registered behind token middleware.
- Field page and tree read only `sys_resource` rows with category `FIELD`.
- Empty `FIELD` data returns stable page/tree structures rather than errors.
- `MenuTreeSelector` delegates to existing menu tree selector data for the field form.
- Java source, database schema, field writes, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not add `/sys/field/add`, `/edit`, or `/delete`.
- Do not modify menu/button/module write behavior.
- Do not modify `F:\AI\projects\testJava\OA`.

## Completed Plan: api-agent/frontend-agent - Gen Basic Database Metadata Reads

Status: completed on 2026-06-05 after route, syntax, database smoke, and baseline checks.

Date: 2026-06-05

### 1. Current Goal

Close the copied generator basic form read gap by adding the two Java-compatible, read-only database metadata endpoints used when opening the generator form.

### 2. Involved Modules

- api-agent route/controller mapping
- frontend-agent copied `gen/basic` API compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/gen/BasicController.php`
- `app/service/gen/BasicService.php`
- `route/app.php`
- `docs/api/gen-basic-metadata-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Metadata reads depend on the configured MySQL database and `information_schema` availability.
- Java returns upper-case column names from JDBC metadata; ThinkPHP must preserve that shape for copied frontend primary-key selectors.
- `route/app.php` is a locked public file, so the route change must be recorded.
- Code generation preview and execution routes are side-effect/generation flows and remain out of scope.

### 5. Test Commands

```powershell
php -l app\controller\gen\BasicController.php
php -l app\service\gen\BasicService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /gen/basic/tables` is registered behind token middleware.
- `GET /gen/basic/tableColumns` is registered behind token middleware.
- `tables` returns Java-compatible `tableName` and `tableRemark` values and excludes `ACT_` workflow engine tables.
- `tableColumns` requires `tableName` and returns Java-compatible `columnName`, `typeName`, and `columnRemark` values.
- Java source, database schema, generator writes, code generation preview/execution, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not add `/gen/basic/add`, `/edit`, `/delete`, `/previewGen`, `/execGenZip`, or `/execGenPro`.
- Do not generate or write business code.
- Do not modify generator templates, Java source, database schema, Composer files, or `.env`.

## Completed Plan: auth-agent/frontend-agent - Third-Party User Page Read-Only Compatibility

Status: completed on 2026-06-05 after route, syntax, database smoke, frontend-wrapper gap scan, and baseline checks.

Date: 2026-06-05

### 1. Current Goal

Close the final explicit frontend read-only wrapper gap by adding Java-compatible `GET /auth/third/page` for third-party user binding pagination.

### 2. Involved Modules

- auth-agent third-party user binding read compatibility
- frontend-agent copied `auth/third` API compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/auth/ThirdController.php`
- `app/service/auth/ThirdService.php`
- `route/app.php`
- `docs/api/auth-third-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Third-party OAuth render/callback flows need provider configuration and security review; this slice must not implement them.
- `auth_third_user` may be empty in the imported local database, so smoke tests should accept a stable empty page.
- `route/app.php` is a locked public file, so the route change must be recorded.

### 5. Test Commands

```powershell
php -l app\controller\auth\ThirdController.php
php -l app\service\auth\ThirdService.php
php -l route\app.php
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /auth/third/page` is registered behind token middleware.
- Page reads only `auth_third_user` rows and supports `category`, `searchKey`, pagination, and safe sort fields.
- Rows return Java-compatible camelCase fields for third-party user bindings.
- `render` and `callback` remain unimplemented/deferred.
- Java source, database schema, OAuth provider configuration, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not add `/auth/third/render` or `/auth/third/callback`.
- Do not add third-party login, OAuth binding, user creation, or token issuance behavior.
- Do not modify Java source, database schema, Composer files, `.env`, or frontend source.

## Completed Plan: api-agent/frontend-agent - Customer Follow-Up Write Compatibility

Status: completed on 2026-06-05 after route, syntax, add/edit/logical-delete service smoke, backend/frontend reachability, and baseline checks.

Date: 2026-06-05

### 1. Current Goal

Open the first low-risk business write slice by adding Java-compatible customer follow-up `add`, `edit`, and `delete` endpoints for the copied frontend wrapper.

### 2. Involved Modules

- api-agent customer follow-up write compatibility
- frontend-agent copied `customerFollowUpApi.js` wrapper compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/CustomerFollowUpController.php`
- `app/service/biz/CustomerFollowUpService.php`
- `route/app.php`
- `docs/api/biz-customer-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- This is the first visible business write slice; validation and rollback checks must be tighter than read-only endpoints.
- Java physically removes rows through MyBatis, while the ThinkPHP project uses `DELETE_FLAG` visibility conventions; this slice uses logical delete to avoid unsafe data loss.
- Customer data-scope must be checked from the owning customer row before every write.
- Attachment file upload/storage remains deferred; this slice only preserves `extJson` when the frontend submits it.

### 5. Test Commands

```powershell
php -l app\controller\biz\CustomerFollowUpController.php
php -l app\service\biz\CustomerFollowUpService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/customerfollowup/add`, `/edit`, and `/delete` are registered behind token middleware.
- Add requires `customerId`, `followUpTime`, and `content`.
- Edit requires `id` and only updates submitted mutable fields.
- Delete accepts Java-style `[{id: ...}]`, `idList`, `ids`, or single `id` input and performs logical deletion.
- Each write validates customer visibility using admin role/account, data-scope org IDs, or customer owner fallback.
- Java source, database schema, customer writes, file upload/storage, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not implement `/biz/customer/add`, `/edit`, `/delete`, or `/head/edit`.
- Do not implement attachment upload, physical file cleanup, customer owner reassignment, workflow, finance, stock, or notification side effects.
- Do not modify Java source, database schema, Composer files, `.env`, or frontend source.

## Completed Plan: api-agent/frontend-agent - Sale Project Follow-Up Write Compatibility

Status: completed on 2026-06-05 after route, syntax, add/edit/logical-delete service smoke with `fileList`, backend/frontend reachability, and baseline checks.

Date: 2026-06-05

### 1. Current Goal

Add Java-compatible sale-project follow-up `add`, `edit`, and `delete` endpoints so the copied sale-project detail follow-up tab and standalone follow-up page can save records.

### 2. Involved Modules

- api-agent sale-project follow-up write compatibility
- frontend-agent copied `saleProjectFollowUpApi.js` wrapper compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/SaleProjectFollowUpController.php`
- `app/service/biz/SaleProjectFollowUpService.php`
- `route/app.php`
- `docs/api/biz-saleproject-followup-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Sale-project follow-up forms may include `fileList`; this slice may preserve the submitted metadata in `EXT_JSON` but must not implement upload/storage behavior.
- Java physically removes rows, while this ThinkPHP project hides deleted rows through `DELETE_FLAG`; this slice should use logical delete.
- Write permission must be checked from the owning `biz_sale_project` row before add, edit, or delete.
- This slice must not trigger project-state, workflow, inventory, finance, notification, or file cleanup side effects.

### 5. Test Commands

```powershell
php -l app\controller\biz\SaleProjectFollowUpController.php
php -l app\service\biz\SaleProjectFollowUpService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/saleprojectfollowup/add`, `/edit`, and `/delete` are registered behind token middleware.
- Add requires `projectId`, `followUpTime`, `category`, and `content`.
- Add stores submitted `fileList` as `{"fileList":[...]}` in `EXT_JSON`, matching the Java service behavior.
- Edit requires `id`, `projectId`, `followUpTime`, `category`, and `content`.
- Delete accepts Java-style `[{id: ...}]`, `idList`, `ids`, or single `id` input and performs logical deletion.
- Each write validates sale-project visibility using admin role/account, data-scope org IDs, or project owner fallback.
- Java source, database schema, sale-project writes, file upload/storage cleanup, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not implement `/biz/saleproject/add`, `/edit`, `/delete`, status edits, amount edits, workflow starts, or finance/inventory side effects.
- Do not implement file upload, physical file cleanup, notification pushes, or attachment storage changes.
- Do not modify Java source, database schema, Composer files, `.env`, or frontend source.

## Completed Plan: api-agent/frontend-agent - Sale Project Product Info Write Compatibility

Status: completed on 2026-06-05 after route, syntax, add/edit/logical-delete service smoke, strict full lint rerun, backend/frontend reachability, and baseline checks.

Date: 2026-06-05

### 1. Current Goal

Add Java-compatible software package/version info `add`, `edit`, and `delete` endpoints for the copied `/biz/saleprojectproductinfo` page.

### 2. Involved Modules

- api-agent sale-project product info CRUD compatibility
- frontend-agent copied `bizSaleProjectProductInfoApi.js` wrapper compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/SaleProjectProductInfoController.php`
- `app/service/biz/SaleProjectProductInfoService.php`
- `route/app.php`
- `docs/api/biz-saleproject-product-info-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- This module is a low-risk standalone package/version info table, but writes should remain transactional and avoid sale-project state side effects.
- Java physically removes rows through MyBatis, while this ThinkPHP project hides deleted rows through `DELETE_FLAG`; this slice should use logical delete.
- Add requires `productId`, `targetId`, and `contentText`; edit should preserve Java's loose optional-field behavior and only require `id`.

### 5. Test Commands

```powershell
php -l app\controller\biz\SaleProjectProductInfoController.php
php -l app\service\biz\SaleProjectProductInfoService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/saleprojectproductinfo/add`, `/edit`, and `/delete` are registered behind token middleware.
- Add requires `productId`, `targetId`, and `contentText`.
- Edit requires `id` and updates only submitted mutable fields.
- Delete accepts Java-style `[{id: ...}]`, `idList`, `ids`, or single `id` input and performs logical deletion.
- Java source, database schema, sale-project state, workflow, finance, inventory, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not modify sale-project orders, product items, inventory, delivery, workflow, finance, or report generation logic.
- Do not implement import/export changes or product master-data writes.
- Do not modify Java source, database schema, Composer files, `.env`, or frontend source.

## Completed Plan: api-agent/frontend-agent - Sale Project Field Change Log Write Compatibility

Status: completed on 2026-06-05 after route, syntax, add/edit/logical-delete service smoke, backend/frontend reachability, and baseline checks.

Date: 2026-06-05

### 1. Current Goal

Add Java-compatible sale-project field change log `add`, `edit`, and `delete` endpoints for the copied frontend and Java route compatibility, while keeping this as a narrow log-table CRUD slice.

### 2. Involved Modules

- api-agent sale-project field change log write compatibility
- frontend-agent copied field-change-log wrapper compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/SalesProjectFieldChangeLogController.php`
- `app/service/biz/SalesProjectFieldChangeLogService.php`
- `route/app.php`
- `docs/api/sales-project-field-change-log-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java physically removes rows through MyBatis, while this ThinkPHP project hides deleted rows with `DELETE_FLAG`; this slice should use logical delete.
- Change logs are usually generated by sale-project amount/change writes, but this slice must not implement those larger sale-project side effects.
- Imported SQL uses mixed collations between the log and sale-project tables; existing read joins must remain unchanged.

### 5. Test Commands

```powershell
php -l app\controller\biz\SalesProjectFieldChangeLogController.php
php -l app\service\biz\SalesProjectFieldChangeLogService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/salesprojectfieldchangelog/add`, `/edit`, and `/delete` are registered behind token middleware.
- Add requires `objectId`, `fieldName`, `fieldLabel`, `beforeValue`, `afterValue`, and `changeReason`.
- Edit requires the same fields plus `id`.
- Delete accepts Java-style `[{id: ...}]`, `idList`, `ids`, or single `id` input and performs logical deletion.
- Java source, database schema, sale-project writes, workflow, finance, audit side effects, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not implement `/biz/saleproject/history/add`, amount edit, deal edit, visibility edit, or any sale-project state transition.
- Do not implement workflow, finance, inventory, file storage, or notification side effects.
- Do not modify Java source, database schema, Composer files, `.env`, or frontend source.

## Completed Plan: api-agent/frontend-agent - Biz History Excel Write Compatibility

Status: completed on 2026-06-05 after route, syntax, add/edit/logical-delete service smoke, backend/frontend reachability, and baseline checks.

Date: 2026-06-05

### 1. Current Goal

Add Java-compatible historical Excel data `add`, `edit`, and `delete` endpoints for the copied `/biz/bizhistoryexcel` page.

### 2. Involved Modules

- api-agent historical Excel CRUD compatibility
- frontend-agent copied `bizHistoryExcelApi.js` wrapper compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/BizHistoryExcelController.php`
- `app/service/biz/BizHistoryExcelService.php`
- `route/app.php`
- `docs/api/biz-history-excel-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- The frontend parses Excel locally and submits a large JSON payload; this slice should preserve submitted `extJson` rather than changing parser behavior.
- Java physically removes rows through MyBatis, while this ThinkPHP project hides deleted rows with `DELETE_FLAG`; this slice should use logical delete.
- `biz_history_excel_row` exists in SQL, but Java `BizHistoryExcelController` writes only `biz_history_excel`; this slice must not invent row-table parsing or storage.

### 5. Test Commands

```powershell
php -l app\controller\biz\BizHistoryExcelController.php
php -l app\service\biz\BizHistoryExcelService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/bizhistoryexcel/add`, `/edit`, and `/delete` are registered behind token middleware.
- Add requires `name` and stores submitted `extJson`.
- Edit requires `id` and updates submitted `extJson` only, matching the Java edit parameter.
- Delete accepts Java-style `[{id: ...}]`, `idList`, `ids`, or single `id` input and performs logical deletion.
- Java source, database schema, row-table parsing, file upload/storage, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not modify Excel parsing in `snowy-admin-web`.
- Do not write `biz_history_excel_row` rows or implement import/export/storage changes.
- Do not modify Java source, database schema, Composer files, `.env`, or frontend source.

## Completed Plan: api-agent/frontend-agent - Sale Project Rate Write Compatibility

Status: completed on 2026-06-05 after route, syntax, add/logical-delete service smoke, backend/frontend reachability, and baseline checks.

Date: 2026-06-05

### 1. Current Goal

Add Java-compatible sale-project customer rating `add` and `delete` endpoints for the copied sale-project case/rating tab.

### 2. Involved Modules

- api-agent sale-project rating write compatibility
- frontend-agent copied `saleProjectRateApi.js` wrapper compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/SaleProjectRateController.php`
- `app/service/biz/SaleProjectBillingService.php`
- `route/app.php`
- `docs/api/biz-saleproject-billing-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java exposes `add` and `delete` for `/biz/projectrate`; `edit` exists in the service layer/front-end wrapper but is not exposed by the Java controller, so this slice should not add edit.
- Rating image paths are submitted as `imgList`; this slice should store them in `EXT_JSON` as `{ "imgList": [...] }` without implementing file upload/storage.
- Java physical delete is converted to the project-standard logical delete so imported rows remain recoverable during refactor testing.

### 5. Test Commands

```powershell
php -l app\controller\biz\SaleProjectRateController.php
php -l app\service\biz\SaleProjectBillingService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/projectrate/add` and `/delete` are registered behind token middleware.
- Add requires `projectId` and `subject`, defaults missing `rateAmount` to `0.00`, defaults missing `content` to an empty string, and stores `imgList` in `EXT_JSON`.
- Delete accepts Java-style `[{id: ...}]`, `idList`, `ids`, or single `id` input and performs logical deletion.
- Java source, database schema, file upload/storage, sale-project state, workflow, finance, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not implement `/biz/projectrate/edit` in this slice.
- Do not implement file upload/storage, sale-project state changes, workflow, finance, inventory, or notification side effects.
- Do not modify Java source, database schema, Composer files, `.env`, or frontend source.

## Completed Plan: api-agent/frontend-agent - CC Records Delete Compatibility

Status: completed on 2026-06-05 after route, syntax, current-user logical-delete service smoke, backend/frontend reachability, and baseline checks.

Date: 2026-06-05

### 1. Current Goal

Add Java-compatible workflow copy/CC record `delete` endpoint for the copied copy-task page.

### 2. Involved Modules

- api-agent CC records delete compatibility
- frontend-agent copied `bizCcRecordsApi.js` delete wrapper compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/CcRecordsController.php`
- `app/service/biz/CcRecordsService.php`
- `route/app.php`
- `docs/api/biz-cc-records-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java physically removes rows through MyBatis, while this ThinkPHP project should use logical delete for imported-data safety.
- Java filters delete by `USER = StpUtil.getLoginId()`; this slice must preserve the current-user guard so one user cannot delete another user's copy record.
- Workflow copy-user generation and approval actions must remain untouched.

### 5. Test Commands

```powershell
php -l app\controller\biz\CcRecordsController.php
php -l app\service\biz\CcRecordsService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/ccrecords/delete` is registered behind token middleware.
- Delete accepts Java-style `[{id: ...}]`, `idList`, `ids`, or single `id` input.
- Delete only affects rows where `USER` equals the current token user id and optional `TENANT_ID` matches the token tenant id.
- Delete performs logical deletion through `DELETE_FLAG = DELETED`.
- Java source, database schema, add/edit CC writes, workflow copy delegate writes, approval/reject/start/cancel flows, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not implement `/biz/ccrecords/add` or `/edit` in this slice.
- Do not implement workflow copy-user delegate writes or approval/reject/start/cancel side effects.
- Do not modify Java source, database schema, Composer files, `.env`, or frontend source.

## Completed Plan: api-agent/frontend-agent - Team Project Comment Add Compatibility

Status: completed on 2026-06-05.

Date: 2026-06-05

### 1. Current Goal

Add Java-compatible team-project timeline comment `add` and comment-reply `add` endpoints used by the copied team-project detail page.

### 2. Involved Modules

- api-agent team-project comment write compatibility
- frontend-agent copied team-project detail timeline/comment wrappers
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/TeamProjectCommentController.php`
- `app/controller/biz/TeamProjectCommentReplyController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/api/team-project-comment-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java comment add can trigger notification strategies through mentioned users; this slice must store mention metadata only and keep notifications deferred.
- Java uses resource permission checks for `teamProjectId`; this slice should preserve the existing member-visibility guard through `biz_team_project_user`.
- Delete/edit routes remain more sensitive because they affect existing timeline history, so this slice should not add them.

### 5. Test Commands

```powershell
php -l app\controller\biz\TeamProjectCommentController.php
php -l app\controller\biz\TeamProjectCommentReplyController.php
php -l app\service\biz\TeamProjectTaskReadService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/bizteamprojectcomment/add` and `/biz/bizteamprojectcommentreply/add` are registered behind token middleware.
- Comment add requires `teamProjectId`, `status`, `statusColor`, `contentText`, and `mentionableUsers`.
- Comment add stores `mentionableUsers` in `EXT_JSON` as `{"mentionableUsers":[...]}`.
- Reply add requires `targetId` and `contentText`.
- Both writes only allow a current token user who is a non-deleted member of the owning team project.
- Java source, database schema, comment delete, reply edit/delete, notification push, task state/progress writes, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not implement `/biz/bizteamprojectcomment/delete`.
- Do not implement `/biz/bizteamprojectcommentreply/edit` or `/delete`.
- Do not implement notification push, data-change events, task state/progress writes, Java source changes, database schema changes, Composer changes, `.env` changes, or frontend source changes.

## Completed Plan: api-agent/frontend-agent - Team Project Comment Maintenance Compatibility

Status: completed on 2026-06-05.

Date: 2026-06-05

### 1. Current Goal

Add Java-compatible team-project timeline comment `delete`, comment-reply `edit`, and comment-reply `delete` endpoints as a narrow maintenance slice.

### 2. Involved Modules

- api-agent team-project comment maintenance compatibility
- frontend-agent copied team-project comment/reply wrappers
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/TeamProjectCommentController.php`
- `app/controller/biz/TeamProjectCommentReplyController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/api/team-project-comment-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java physically deletes comment/reply rows; this project should use logical deletion to preserve imported data during refactor testing.
- Reply edit can theoretically move a reply to another target comment, so this slice must validate both the existing reply and requested target comment through the same team-project membership boundary.
- Notification push and data-change events remain side-effect-heavy and must stay deferred.

### 5. Test Commands

```powershell
php -l app\controller\biz\TeamProjectCommentController.php
php -l app\controller\biz\TeamProjectCommentReplyController.php
php -l app\service\biz\TeamProjectTaskReadService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/bizteamprojectcomment/delete`, `/biz/bizteamprojectcommentreply/edit`, and `/biz/bizteamprojectcommentreply/delete` are registered behind token middleware.
- Comment delete accepts Java-style `[{id: ...}]`, `idList`, `ids`, or single `id` input and logically deletes only visible non-deleted comments.
- Reply edit requires `id`, `targetId`, and `contentText`, validates project membership for both existing reply and target comment, and updates only the reply target/content/audit fields.
- Reply delete accepts Java-style `[{id: ...}]`, `idList`, `ids`, or single `id` input and logically deletes only visible non-deleted replies.
- Java source, database schema, team-project mutations, task/category/task-user writes, notification push, data-change events, Composer files, `.env`, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not implement team-project add/edit/delete.
- Do not implement task/category/task-user add/edit/delete or task state/progress writes.
- Do not implement notification push, data-change events, Java source changes, database schema changes, Composer changes, `.env` changes, or frontend source changes.

## Completed Plan: api-agent/frontend-agent - Team Project Task User Edit Compatibility

Status: completed on 2026-06-05.

Date: 2026-06-05

### 1. Current Goal

Add the Java-compatible task assignee synchronization endpoint used by the copied team-project task detail drawer:

- `POST /biz/bizteamprojecttask/user/edit`

This slice only syncs task-user assignment rows. It does not implement task add/edit/delete, task category writes, task comments, status/progress changes, notifications, or data-change side effects.

### 2. Involved Modules

- api-agent team-project task assignment compatibility
- frontend-agent copied task-detail API compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/TeamProjectTaskController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- The frontend selector can submit either user id strings or user objects, so input normalization must accept both shapes.
- Java physically deletes removed task-user rows; this ThinkPHP refactor keeps imported data safer by using logical deletion for removed assignments.
- Task assignment affects collaboration visibility, so the write must validate project membership and imported project permission before syncing rows.

### 5. Test Commands

```powershell
php -l app\controller\biz\TeamProjectTaskController.php
php -l app\service\biz\TeamProjectTaskReadService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/bizteamprojecttask/user/edit` is registered behind token middleware.
- Request body accepts `id` plus `user` values as ids, comma-separated ids, or user objects containing `id`/`userId`/`value`.
- The current token user must be a non-deleted member of the owning team project and must have imported `addUser` project permission or task-level `MANAGE` role.
- Submitted assignees must already be non-deleted members of the same team project.
- Missing assignees are logically deleted; new assignees are inserted as `MEMBER` rows with audit fields.
- Java source, database schema, frontend source, task add/edit/delete, category writes, comments, notifications, data-change events, Composer files, and `.env` remain unchanged.

### 7. Forbidden Scope

- Do not implement `/biz/bizteamprojecttask/add`, `/edit`, or `/delete`.
- Do not implement `/biz/bizteamprojecttaskcategory/add`, `/edit`, `/sort/edit`, or `/delete`.
- Do not implement task comment writes, status/progress/content writes, notification push, data-change events, Java source changes, database schema changes, Composer changes, `.env` changes, or frontend source changes.

## Completed Plan: api-agent/frontend-agent - Team Project Task Comment Add Compatibility

Status: completed on 2026-06-05.

Date: 2026-06-05

### 1. Current Goal

Add the Java-compatible task comment submit endpoint used by the copied team-project task detail drawer:

- `POST /biz/bizteamprojecttaskcomment/add`

This slice only adds user comments for an existing team-project task. It does not implement task comment edit/delete, task status/progress/content changes, task/category mutations, notifications, or Java data-change events.

### 2. Involved Modules

- api-agent team-project task comment compatibility
- frontend-agent copied task-detail API compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/TeamProjectTaskCommentController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- The frontend stores uploaded attachment metadata under `files`, while existing task comments expose raw `extJson`; this slice must preserve the Java-compatible `{"file":[...]}` shape for the copied parser.
- Java emits data-change events after task-comment add; this refactor intentionally defers those push/notification side effects.
- Comment writes must be limited to users who can see the owning task through team-project membership.

### 5. Test Commands

```powershell
php -l app\controller\biz\TeamProjectTaskCommentController.php
php -l app\service\biz\TeamProjectTaskReadService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/bizteamprojecttaskcomment/add` is registered behind token middleware.
- Request body requires `teamProjectTaskId`.
- The current token user must be a non-deleted member of the owning team project.
- The inserted row uses `CATEGORY = COMMENT`, `DELETE_FLAG = NOT_DELETE`, task-derived `TEAM_PROJECT_ID`, current-user audit fields, and tenant id.
- Submitted `files` are stored in `EXT_JSON` as `{"file":[...]}` so the copied task detail drawer can parse them.
- Java source, database schema, frontend source, task comment edit/delete, task/category writes, task status/progress/content writes, notifications, data-change events, Composer files, and `.env` remain unchanged.

### 7. Forbidden Scope

- Do not implement `/biz/bizteamprojecttaskcomment/edit` or `/delete`.
- Do not implement `/biz/bizteamprojecttask/add`, `/edit`, or `/delete`.
- Do not implement task-category writes, task-user writes, task status/progress/content writes, notification push, data-change events, Java source changes, database schema changes, Composer changes, `.env` changes, or frontend source changes.

## Completed Plan: api-agent/frontend-agent - Team Project Task Comment Maintenance Compatibility

Status: completed on 2026-06-05.

Date: 2026-06-05

### 1. Current Goal

Add Java-compatible protected task-comment maintenance endpoints for copied API wrapper completeness:

- `POST /biz/bizteamprojecttaskcomment/edit`
- `POST /biz/bizteamprojecttaskcomment/delete`

This slice only maintains user comments with `CATEGORY = COMMENT`. Generated task logs with `CATEGORY = LOG` remain read-only to avoid corrupting task state/audit history.

### 2. Involved Modules

- api-agent team-project task comment maintenance compatibility
- frontend-agent copied task-comment API compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/TeamProjectTaskCommentController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/api/team-project-comment-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java physically deletes task comments; this ThinkPHP refactor should preserve imported data with logical deletion.
- Task logs are stored in the same table as user comments, so this slice must avoid editing or deleting `LOG` rows.
- Maintenance writes need a narrow permission boundary: comment creator, imported project `delComment`, or task-level `MANAGE`.

### 5. Test Commands

```powershell
php -l app\controller\biz\TeamProjectTaskCommentController.php
php -l app\service\biz\TeamProjectTaskReadService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/bizteamprojecttaskcomment/edit` and `/delete` are registered behind token middleware.
- Edit requires `id`, validates project membership, rejects `LOG` rows, and updates only `CONTENT_TEXT`, `EXT_JSON`, and audit fields.
- Edit accepts `files`/`file`/`fileList` and stores them as `{"file":[...]}`; if raw `extJson` is provided and no file list is provided, it may be preserved as-is.
- Delete accepts Java-style array bodies, `idList`, `ids`, or single `id`, validates project membership, rejects `LOG` rows, and logically deletes rows with `DELETE_FLAG = DELETED`.
- Maintenance is allowed for the comment creator, a project user with imported `delComment`, or a task-level `MANAGE` user.
- Java source, database schema, frontend source, task add/edit/delete, category writes, task status/progress/content writes, notifications, data-change events, Composer files, and `.env` remain unchanged.

### 7. Forbidden Scope

- Do not edit or delete `CATEGORY = LOG` rows.
- Do not implement `/biz/bizteamprojecttask/add`, `/edit`, or `/delete`.
- Do not implement task-category writes, task-user writes, task status/progress/content writes, notification push, data-change events, Java source changes, database schema changes, Composer changes, `.env` changes, or frontend source changes.

## Active Plan: api-agent/frontend-agent - Team Project Task Category Maintenance Compatibility

Status: completed on 2026-06-05.

Date: 2026-06-05

### 1. Current Goal

Add Java-compatible protected team-project task category maintenance endpoints used by the copied kanban view:

- `POST /biz/bizteamprojecttaskcategory/add`
- `POST /biz/bizteamprojecttaskcategory/edit`
- `POST /biz/bizteamprojecttaskcategory/sort/edit`
- `POST /biz/bizteamprojecttaskcategory/delete`

This slice only maintains task category columns. It does not implement task add/edit/delete, task drag-to-category writes, task status/progress/content changes, notifications, or Java data-change events.

### 2. Involved Modules

- api-agent team-project task category compatibility
- frontend-agent copied kanban category API compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/TeamProjectTaskCategoryController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java physically deletes categories; this refactor should preserve imported data with logical deletion.
- Deleting a category that still has tasks can orphan task cards, so this slice must reject non-empty category deletion.
- Category writes affect the kanban layout, so they should require a project maintainer boundary instead of plain read membership.

### 5. Test Commands

```powershell
php -l app\controller\biz\TeamProjectTaskCategoryController.php
php -l app\service\biz\TeamProjectTaskReadService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- Category `add`, `edit`, `sort/edit`, and `delete` routes are registered behind token middleware.
- Add requires `teamProjectId` and `title`, validates project maintainer permission, inserts `SORT_CODE = 99` by default, and returns the created category row.
- Edit requires `id` and `title`, validates the owning project, and updates only title/extJson/sortCode when submitted plus audit fields.
- Sort accepts Java-style ordered `[{id: ...}]` payloads, validates all categories belong to the same active project, and updates `SORT_CODE` by submitted order.
- Delete accepts Java-style array bodies, `idList`, `ids`, or single `id`, validates project maintainer permission, rejects categories that still contain active tasks, and logically deletes categories with `DELETE_FLAG = DELETED`.
- Java source, database schema, frontend source, task writes, notification push, data-change events, Composer files, and `.env` remain unchanged.

### 7. Forbidden Scope

- Do not implement `/biz/bizteamprojecttask/add`, `/edit`, or `/delete`.
- Do not implement task drag-to-category writes, task-user standalone writes, task status/progress/content writes, notification push, data-change events, Java source changes, database schema changes, Composer changes, `.env` changes, or frontend source changes.

## Active Plan: api-agent/frontend-agent - Team Project Task Base Maintenance Compatibility

Status: completed on 2026-06-05.

Date: 2026-06-05

### 1. Current Goal

Add Java-compatible protected team-project task base maintenance endpoints used by the copied kanban view and task detail drawer:

- `POST /biz/bizteamprojecttask/add`
- `POST /biz/bizteamprojecttask/edit`
- `POST /biz/bizteamprojecttask/delete`

This slice only maintains base task rows and task-user rows needed by task creation. It does not implement notification push, Java data-change events, generated `LOG` task comments, workflow actions, or standalone task-user CRUD routes.

### 2. Involved Modules

- api-agent team-project task base compatibility
- frontend-agent copied kanban task API compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/TeamProjectTaskController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java edit emits data-change events that create task `LOG` comments; this slice intentionally leaves those side effects deferred.
- Java delete physically deletes tasks; this refactor should preserve imported data with logical deletion.
- Task add creates task-user rows, so submitted users must already be non-deleted members of the same team project.
- Frontend drag-to-category uses the same edit endpoint; this slice supports category id changes but does not add full drag ordering or event broadcast behavior.

### 5. Test Commands

```powershell
php -l app\controller\biz\TeamProjectTaskController.php
php -l app\service\biz\TeamProjectTaskReadService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- Task `add`, `edit`, and `delete` routes are registered behind token middleware.
- Add requires `teamProjectId` and `teamProjectTaskCategoryId`, validates category/project match, validates current-user project membership, inserts task with `STATUS = TODO`, `PROGRESS = 0`, `DELETE_FLAG = NOT_DELETE`, and creates current-user `MANAGE` task-user row.
- Add accepts optional `users`, validates each selected user is a project member, and creates those task-user rows as `MEMBER`.
- Edit requires `id`, validates task visibility and maintainer permission, updates only submitted base task fields: `TITLE`, `STATUS`, `CONTENT_TEXT`, `PROGRESS`, `TEAM_PROJECT_TASK_CATEGORY_ID`, `SORT_CODE`, `EXT_JSON`, audit fields, and increments `VERSION`.
- Delete accepts Java-style array bodies, `idList`, `ids`, or single `id`, validates maintainer permission, logically deletes task and task-user rows with `DELETE_FLAG = DELETED`.
- Java source, database schema, frontend source, notification push, generated task `LOG` comments, data-change events, Composer files, and `.env` remain unchanged.

### 7. Forbidden Scope

- Do not implement standalone `/biz/bizteamprojecttaskuser/add`, `/edit`, or `/delete`.
- Do not generate `CATEGORY = LOG` task comments.
- Do not implement notification push, data-change event broadcasting, workflow actions, Java source changes, database schema changes, Composer changes, `.env` changes, or frontend source changes.

## Active Plan: api-agent/frontend-agent - Team Project Member Maintenance Compatibility

Status: completed on 2026-06-05.

Date: 2026-06-05

### 1. Current Goal

Add Java-compatible protected team-project member maintenance endpoints used by the copied team-project detail view:

- `POST /biz/bizteamprojectuser/add`
- `POST /biz/bizteamprojectuser/manage/add`
- `POST /biz/bizteamprojectuser/delete`

This slice only handles team-project member add/remove compatibility. It does not implement member role edit, notification push, Java data-change events, or unrelated team-project writes.

### 2. Involved Modules

- api-agent team-project member compatibility
- frontend-agent copied team-project detail API compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/TeamProjectUserController.php`
- `app/service/biz/TeamProjectService.php`
- `route/app.php`
- `docs/api/biz-team-project-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java add emits data-change events after member changes; this slice intentionally leaves those side effects deferred.
- Java delete physically removes member rows; this refactor should preserve imported data with logical deletion.
- Project member permissions are stored both as role defaults and imported relation rows, so new member writes must keep relation permission JSON compatible.
- Removing project leaders or the current user could lock users out of project management, so this slice rejects those operations.

### 5. Test Commands

```powershell
php -l app\controller\biz\TeamProjectUserController.php
php -l app\service\biz\TeamProjectService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- Member `add`, `manage/add`, and `delete` routes are registered behind token middleware.
- Add requires `teamProjectId` and `users`, validates submitted users exist, validates current-user project permission `addUser`, rejects active duplicates, restores previously deleted member rows when safe, and writes compatible relation permission JSON.
- Manage add requires current-user project permission `addManage` and creates or restores members with `ROLE_TYPE = MANAGE`.
- Delete accepts Java-style array bodies, `idList`, `ids`, or single `id`, validates current-user project permission, rejects leader/current-user removal, and logically deletes selected member rows with `DELETE_FLAG = DELETED`.
- Java source, database schema, frontend source, notification push, data-change events, Composer files, and `.env` remain unchanged.

### 7. Forbidden Scope

- Do not implement `/biz/bizteamprojectuser/edit`.
- Do not implement notification push, Java data-change event broadcasting, team-project base writes, task writes, Java source changes, database schema changes, Composer changes, `.env` changes, or frontend source changes.

## Active Plan: api-agent/frontend-agent - Customer Base Maintenance Compatibility

Status: completed on 2026-06-05.

Date: 2026-06-05

### 1. Current Goal

Add Java-compatible protected customer base maintenance endpoints used by the copied customer list and customer form:

- `POST /biz/customer/add`
- `POST /biz/customer/edit`
- `POST /biz/customer/delete`

This slice only maintains base `customer` rows and keeps customer follow-up writes unchanged.

### 2. Involved Modules

- api-agent customer API compatibility
- frontend-agent copied customer form compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/CustomerController.php`
- `app/service/biz/CustomerService.php`
- `route/app.php`
- `docs/api/biz-customer-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- Java emits customer data-change events on add/edit/delete; this slice intentionally leaves those side effects deferred.
- Java physical delete is replaced with logical deletion to preserve imported data.
- Imported customer phones are encrypted in the original data; this slice stores submitted values as received and does not introduce a new encryption strategy.
- The copied form requires `fileId`, but file upload/storage compatibility remains a separate slice.

### 5. Test Commands

```powershell
php -l app\controller\biz\CustomerController.php
php -l app\service\biz\CustomerService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- Customer `add`, `edit`, and `delete` routes are registered behind token middleware.
- Add accepts copied form fields: `name`, `contacts`, `phone`, `detailsAddress`, `address`, `sourceType`, `customType`, `status`, `sortCode`, `fileId`, `remark`, `firstContactTime`, and optional `extJson`.
- Add fills `DELETE_FLAG = NOT_DELETE`, audit fields, tenant id, `VERSION = 0`, and `DEAL_AMOUNT = 0`, and validates submitted owner/org against token write scope.
- Edit requires `id`, validates active customer visibility, updates only submitted base fields, audit fields, and increments `VERSION`.
- Delete accepts Java-style array bodies, `idList`, `ids`, or single `id`, validates active customer visibility, and logically deletes selected rows with `DELETE_FLAG = DELETED`.
- Java source, database schema, frontend source, file upload/storage, customer-head transfer, data-change events, Composer files, and `.env` remain unchanged.

### 7. Forbidden Scope

- Do not implement `/biz/customer/head/edit`.
- Do not implement `dev/file/upload*` storage routes, customer import/export, SM4 encryption migration, Java data-change event broadcasting, sale-project/customer side effects, Java source changes, database schema changes, Composer changes, `.env` changes, or frontend source changes.

## Active Plan: api-agent/frontend-agent - Supplier Base Maintenance Compatibility

Status: completed on 2026-06-05.

Date: 2026-06-05

### 1. Current Goal

Add Java-compatible protected supplier base maintenance endpoints used by the copied supplier list and supplier form:

- `POST /biz/supplier/add`
- `POST /biz/supplier/edit`
- `POST /biz/supplier/delete`

This slice only maintains base `supplier` rows and keeps purchase/order/payment side effects out of scope.

### 2. Involved Modules

- api-agent supplier API compatibility
- frontend-agent copied supplier page/form compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/SupplierController.php`
- `app/service/biz/SupplierService.php`
- `route/app.php`
- `docs/api/biz-supplier-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- Java uses MyBatis logic delete; this ThinkPHP slice should preserve imported data by setting `DELETE_FLAG = DELETED`.
- Supplier table uses lower-case physical column `org`, so writes must preserve the exact column spelling.
- Supplier data is used by purchase/payment pages, but those transactional side effects must not be introduced in this slice.
- Java data-scope fallback filters page reads by `CREATE_USER`; this slice should conservatively validate writes against admin roles, scoped org, or current creator.

### 5. Test Commands

```powershell
php -l app\controller\biz\SupplierController.php
php -l app\service\biz\SupplierService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- Supplier `add`, `edit`, and `delete` routes are registered behind token middleware.
- Add requires Java-required fields `name`, `contacts`, and `phone`, defaults empty `status` to `ENABLE`, writes `DELETE_FLAG = NOT_DELETE`, audit fields, tenant id, and current token org.
- Edit requires `id`, `name`, `contacts`, `phone`, and `status`, validates active supplier write scope, updates only submitted base fields, and writes update audit fields.
- Delete accepts Java-style array bodies, `idList`, `ids`, or single `id`, validates active supplier write scope, and logically deletes selected rows with `DELETE_FLAG = DELETED`.
- Java source, database schema, frontend source, purchase/payment side effects, Composer files, and `.env` remain unchanged.

### 7. Forbidden Scope

- Do not implement purchase, payment, procurement, inventory, workflow, supplier import/export, Java source changes, database schema changes, Composer changes, `.env` changes, or frontend source changes.

## Active Plan: api-agent/frontend-agent - Warehouse Base Maintenance Compatibility

Status: completed on 2026-06-06.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected warehouse base maintenance endpoints used by the copied warehouse list and warehouse form:

- `POST /biz/warehouses/add`
- `POST /biz/warehouses/edit`
- `POST /biz/warehouses/delete`

This slice only maintains base `warehouses` rows and keeps inventory, delivery, purchase, and workflow side effects out of scope.

### 2. Involved Modules

- api-agent warehouse API compatibility
- frontend-agent copied warehouse page/form compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/WarehousesController.php`
- `app/service/biz/WarehousesService.php`
- `route/app.php`
- `docs/api/biz-warehouses-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- Warehouse records are referenced by inventory, delivery, purchase, and sale-project invoice flows; this slice must not update those modules.
- Java uses MyBatis logic delete; this ThinkPHP slice should preserve imported data by setting `DELETE_FLAG = DELETED`.
- Java edit/delete checks warehouse ownership through the warehouse user and that user's organization. This slice should keep the same conservative write-scope idea.

### 5. Test Commands

```powershell
php -l app\controller\biz\WarehousesController.php
php -l app\service\biz\WarehousesService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- Warehouse `add`, `edit`, and `delete` routes are registered behind token middleware.
- Add requires SQL-required fields `name` and `code`, accepts copied optional fields `address`, `sortCode`, and `extJson`, and writes owner user/org from the current token.
- Edit requires `id`; if `name`, `code`, or `org` is submitted, it validates the value before writing; it updates only submitted base fields and audit fields.
- Delete accepts Java-style array bodies, `idList`, `ids`, or single `id`, validates active warehouse write scope, and logically deletes selected rows with `DELETE_FLAG = DELETED`.
- Java source, database schema, frontend source, inventory/delivery/purchase side effects, Composer files, and `.env` remain unchanged.

### 7. Forbidden Scope

- Do not implement inventory stock updates, delivery record writes, purchase-order writes, sale-project invoice writes, workflow behavior, Java source changes, database schema changes, Composer changes, `.env` changes, or frontend source changes.

## Active Plan: api-agent/frontend-agent - Product Status And Reconciliation Compatibility

Status: completed on 2026-06-06.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected product lightweight write endpoints used by the copied product list page:

- `POST /biz/bizproduct/edit/status`
- `POST /biz/bizproduct/reconciliation/edit`

This slice only updates `biz_product.status`, `RECONCILIATION_TYPE`, and `RECONCILIATION_AMOUNT`. Product add/edit/delete, kit product relation writes, inventory, purchase, sale-project, and workflow side effects stay deferred.

### 2. Involved Modules

- api-agent product API compatibility
- frontend-agent copied product list compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/ProductController.php`
- `app/service/biz/ProductService.php`
- `route/app.php`
- `docs/api/biz-product-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- Product add/edit/delete touches kit product relations and must remain out of this slice.
- Reconciliation editing can affect finance-facing product filters, so this slice must update only explicit product ids and must validate current token write scope.
- Product status toggling changes product visibility in default Java-compatible page reads; tests must restore imported product status after smoke checks.
- Java emits broader data-change/cache behavior elsewhere; this slice does not implement event broadcasting.

### 5. Test Commands

```powershell
php -l app\controller\biz\ProductController.php
php -l app\service\biz\ProductService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- Product `edit/status` and `reconciliation/edit` routes are registered behind token middleware.
- Status edit requires `id` and `status`, accepts only `ENABLE` or `DISABLE`, validates active product write scope, and updates audit fields.
- Reconciliation edit requires non-empty `ids` and `reconciliationType`, accepts only `ENABLE` or `DISABLE`, validates every active product write scope, accepts a non-negative optional `reconciliationAmount`, and updates audit fields.
- Java source, database schema, frontend source, product add/edit/delete, product relation writes, inventory, purchase, sale-project, workflow, Composer files, and `.env` remain unchanged.

### 7. Forbidden Scope

- Do not implement `/biz/bizproduct/add`, `/edit`, or `/delete`.
- Do not modify `product_relation`.
- Do not implement stock, purchase, sale-project, finance transaction, workflow, file upload/storage, Java source, database schema, Composer, `.env`, or frontend source changes.

## Active Plan: api-agent/frontend-agent - Product Base Maintenance Compatibility

Status: completed on 2026-06-06 after implementation, service smoke, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected product base maintenance endpoints used by the copied product page and product form:

- `POST /biz/bizproduct/add`
- `POST /biz/bizproduct/edit`
- `POST /biz/bizproduct/delete`

This slice maintains `biz_product` base rows and Java-style kit-product relations in `product_relation` for category `KIT_PRODUCT_DATA`. Inventory, purchase, sale-project, finance transaction, workflow, file upload/storage, and Java data-change/cache event behavior stay deferred.

### 2. Involved Modules

- api-agent product API compatibility
- frontend-agent copied product table/form compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/ProductController.php`
- `app/service/biz/ProductService.php`
- `route/app.php`
- `docs/api/biz-product-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- Java saves kit products by clearing and replacing `product_relation` rows for the product object; this slice may physically replace only relations for the product currently being edited.
- Product deletion must not remove imported relation rows; it should block products referenced as kit children and logically delete only `biz_product` rows.
- Product rows are used by inventory, purchase, sale-project, and reports, so this slice must not update stock, sales project items, purchase orders, or finance records.
- File upload/storage for `coverImage` remains separate; this slice stores submitted cover image ids/paths as-is.

### 5. Test Commands

```powershell
php -l app\controller\biz\ProductController.php
php -l app\service\biz\ProductService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- Product `add`, `edit`, and `delete` routes are registered behind token middleware.
- Add requires Java-required product base fields: `productName`, `category`, `productCategory`, `safetyStock`, `purchasePrice`, `salePrice`, and `minPrice`.
- Add accepts optional `specs` and `coverImage`, defaults `status = ENABLE`, writes tenant/audit fields, and defaults `ORG` from the current token user.
- Add for `KIT_PRODUCT` validates non-empty unique `productList`, each quantity >= 1, every child product exists and is active, and writes `product_relation` rows for `KIT_PRODUCT_DATA`.
- Edit requires `id`, validates active product write scope, updates submitted base fields and audit fields, and replaces kit relations only when the existing product is `KIT_PRODUCT` and `productList` is submitted.
- Delete accepts Java-style array bodies, `idList`, `ids`, or single `id`, rejects products referenced as kit child targets, validates write scope, and logically deletes selected `biz_product` rows with `DELETE_FLAG = DELETED`.
- Java source, database schema, frontend source, inventory, purchase, sale-project, finance transaction, workflow, Composer files, and `.env` remain unchanged.

### 7. Forbidden Scope

- Do not implement stock updates, purchase-order writes, sale-project item writes, finance transaction writes, workflow actions, file upload/storage, Java data-change/cache events, Java source changes, database schema changes, Composer changes, `.env` changes, or frontend source changes.
- Do not physically delete imported `biz_product` rows.
- Do not clear product relations except the Java-equivalent `KIT_PRODUCT_DATA` relations for the product object currently being added/edited.

## Active Plan: api-agent/frontend-agent - Sale Project Product Mark Compatibility

Status: completed on 2026-06-06 after implementation, service smoke with MARK restore, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected mark-edit endpoints used by copied sale-project delivery/invoice helpers:

- `POST /biz/saleprojectproductitemrelation/mark/edit`
- `POST /biz/saleprojectproductitem/mark/edit`

This slice updates only `MARK` on `sale_project_product_item_relation` and `biz_sale_project_product_item`. It must not implement product item add/edit/delete, delivery, invoice, inventory, workflow, finance, or sale-project state changes.

### 2. Involved Modules

- api-agent sale-project product item compatibility
- frontend-agent copied sale-project product helper compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/SaleProjectProductItemRelationController.php`
- `app/controller/biz/SaleProjectProductItemController.php`
- `app/service/biz/SaleProjectProductItemRelationService.php`
- `app/service/biz/SaleProjectProductItemService.php`
- `route/app.php`
- `docs/api/biz-saleproject-product-item-relation-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- Product item rows belong to sale projects and are later used by delivery, return, invoice, inventory, and reporting flows; this slice must update only `MARK`.
- The relation mark endpoint must validate visibility through the owning sale project before updating.
- The product item endpoint has a separate route group, so a tiny controller/service may be needed without opening broader product-item CRUD.
- Java does not validate mark enum values here, so this slice should accept submitted string/null values and only cap length to the physical column.

### 5. Test Commands

```powershell
php -l app\controller\biz\SaleProjectProductItemRelationController.php
php -l app\controller\biz\SaleProjectProductItemController.php
php -l app\service\biz\SaleProjectProductItemRelationService.php
php -l app\service\biz\SaleProjectProductItemService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- Both `mark/edit` routes are registered behind token middleware.
- Relation mark edit requires `id`, validates the active relation through its owning active product item and sale project, applies data-scope checks, and updates only `MARK`.
- Product item mark edit requires `id`, validates the active product item through its owning active sale project, applies data-scope checks, and updates only `MARK`.
- Smoke tests restore sampled imported rows to their original `MARK` values after mutation.
- Java source, database schema, frontend source, delivery, invoice, inventory, workflow, finance, Composer files, and `.env` remain unchanged.

### 7. Forbidden Scope

- Do not implement sale-project product item add, edit, delete, delivery, invoice, stock, return, workflow, finance, Java source changes, database schema changes, Composer changes, `.env` changes, or frontend source changes.

## Active Plan: api-agent/frontend-agent - Customer Head Reassignment Compatibility

Status: completed on 2026-06-06 after implementation, service smoke with customer owner restore, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected customer owner reassignment endpoint used by the copied customer API wrapper:

- `POST /biz/customer/head/edit`

This slice updates only `customer.USER`, `customer.ORG`, and update audit/version fields. It must not implement customer import/export, file upload/storage, SM4 plaintext migration, sale-project side effects, notifications, or Java data-change events.

### 2. Involved Modules

- api-agent customer API compatibility
- frontend-agent copied customer wrapper compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/biz/CustomerController.php`
- `app/service/biz/CustomerService.php`
- `route/app.php`
- `docs/api/biz-customer-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- Customer ownership controls list visibility, so tests must restore the sampled customer to its original owner and organization.
- Java validates target users through business-user data scope; this slice must mirror that with sys_user visibility based on admin-compatible roles, scoped organization ids, or current user fallback.
- Existing sale-project/customer side effects and data-change events remain deferred.

### 5. Test Commands

```powershell
php -l app\controller\biz\CustomerController.php
php -l app\service\biz\CustomerService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- Customer `head/edit` route is registered behind token middleware.
- Request requires `id` and `user`.
- The current token must be allowed to edit the active customer.
- The target user must exist, be active/not deleted, and be visible through admin-compatible roles, data-scope org ids, or current-user fallback.
- The endpoint updates only `USER`, `ORG`, update audit fields, and increments `VERSION`.
- Smoke tests restore sampled customer `USER`, `ORG`, and `VERSION` after mutation.
- Java source, database schema, frontend source, sale-project side effects, notifications, Composer files, and `.env` remain unchanged.

### 7. Forbidden Scope

- Do not implement customer import/export, file upload/storage, SM4 plaintext search migration, customer data-change events, sale-project/customer side effects, Java source changes, database schema changes, Composer changes, `.env` changes, or frontend source changes.
## Active Plan: user-agent/frontend-agent - User Center Self-Service Writes

Status: completed on 2026-06-06 after route check, strict PHP lint, backend/frontend reachability, no-token auth smoke, and wrong-password auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible self-service write endpoints for the copied user center frontend:

- `POST /sys/userCenter/updatePassword`
- `POST /sys/userCenter/updateAvatar`
- `POST /sys/userCenter/updateSignature`
- `POST /sys/userCenter/updateUserInfo`
- `POST /sys/userCenter/updateUserWorkbench`
- `POST /sys/userCenter/process/config/edit`
- `POST /biz/user/center/edit`

This slice only updates the current login user's own profile/workbench/process-config/password data.

### 2. Involved Modules

- user-agent user-center write compatibility
- frontend-agent copied user-center form compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/sys/UserCenterController.php`
- `app/service/user/UserCenterWriteService.php`
- `route/app.php`
- `docs/api/user-center-readonly-compat.md`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- Password changes must use the existing SM2 transport decoder and SM3 hashing compatibility without committing secrets.
- Avatar upload compatibility stores a bounded base64 data URI for the current user; full file-storage/provider cleanup remains deferred.
- Profile updates must not become user-management CRUD. The submitted `id` must match the current token user id.
- Phone/identity encryption parity with Java SM4 remains deferred; this slice does not migrate encrypted-field storage.
- Process config writes must update only the current user's `sys_user_process_config` row.

### 5. Test Commands

```powershell
php -l app\controller\sys\UserCenterController.php
php -l app\service\user\UserCenterWriteService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- User-center write routes are protected by `AuthMiddleware`.
- Password update requires current password and new password, verifies current password, stores a Java-compatible SM3 hash, and never logs or documents password values.
- Avatar/signature/profile/workbench/process-config writes only affect the current token user.
- `/biz/user/center/edit` reuses the same self-profile update guard.
- Java source, database schema, Composer files, `.env`, role grants, user management CRUD, imports/exports, and frontend source remain unchanged.

### 7. Forbidden Scope

- Do not implement `/sys/user/add`, `/edit`, `/delete`, enable/disable, reset password, grant role/resource/permission, import/export, Java source changes, database schema changes, Composer changes, `.env` changes, or broad frontend changes.

## Active Plan: user-agent/frontend-agent - User Message Mark-Read Compatibility

Status: completed on 2026-06-06 after service smoke with `EXT_JSON` restore, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Align the protected user-center message detail endpoint with Java behavior:

- `GET /sys/userCenter/loginUnreadMessageDetail`

When the current token user opens a message detail, update only that user's `dev_relation` row for `CATEGORY = MSG_TO_USER` so `EXT_JSON.read = true`.

### 2. Involved Modules

- user-agent message read-state compatibility
- frontend-agent copied user-center message list/detail compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/service/user/UserDirectoryService.php`
- `docs/api/user-center-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- The `dev_relation` table has only `ID`, `OBJECT_ID`, `TARGET_ID`, `CATEGORY`, and `EXT_JSON`, so this slice must not invent audit columns.
- Message detail must only mark the current token user's receiver relation, not all recipients and not the `dev_message` row.
- The frontend unread list may shrink after opening detail, so tests should restore sampled imported `EXT_JSON` after smoke checks when possible.

### 5. Test Commands

```powershell
php -l app\service\user\UserDirectoryService.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /sys/userCenter/loginUnreadMessageDetail` stays protected by existing auth middleware and no route changes are needed.
- The endpoint requires `id`, verifies the message belongs to the current token user, and returns `null` for non-owned messages.
- Opening detail changes only the current user's `dev_relation.EXT_JSON` read flag to `true`.
- The returned message detail and current user's receive-info row show `read = true`.
- Java source, database schema, route file, Composer files, `.env`, frontend source, message send/delete, SSE, and all other modules remain unchanged.

### 7. Forbidden Scope

- Do not implement message send, delete, all-mark-read, WebPush, full realtime push, Java source changes, database schema changes, route changes, Composer changes, `.env` changes, or frontend source changes.

## Active Plan: user-agent/frontend-agent - Index Message All-Mark-Read Compatibility

Status: completed on 2026-06-06 after service smoke with `EXT_JSON` restore, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected endpoint used by the copied homepage message drawer:

- `POST /sys/index/message/allMessageMarkRead`

This slice marks all current-token-user `MSG_TO_USER` relations as read and does not implement message send, delete, WebPush, full realtime push, or schedule writes.

### 2. Involved Modules

- user-agent message read-state compatibility
- frontend-agent homepage message drawer compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/sys/IndexController.php`
- `app/service/sys/IndexService.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/index-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- The route file is a locked public file; the change must be recorded in `docs/tasks/public-file-change-request.md`.
- Java bulk mark-read overwrites relation `EXT_JSON` with `{"read":true}`. This ThinkPHP slice should preserve any existing JSON keys while setting `read = true`.
- Frontend unread count is local/SSE-assisted; this slice only updates the database and returns a normal API success response.

### 5. Test Commands

```powershell
php -l app\controller\sys\IndexController.php
php -l app\service\sys\IndexService.php
php -l app\service\user\UserDirectoryService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /sys/index/message/allMessageMarkRead` is registered behind `AuthMiddleware`.
- The endpoint updates only the current token user's `dev_relation` rows where `CATEGORY = MSG_TO_USER`.
- Every updated relation has `EXT_JSON.read = true`, preserving other valid JSON keys if present.
- Java source, database schema, Composer files, `.env`, frontend source, message send/delete, WebPush/full realtime push, and schedule writes remain unchanged.

### 7. Forbidden Scope

- Do not implement message send, message delete, WebPush, full realtime push, schedule add/delete, Java source changes, database schema changes, Composer changes, `.env` changes, or frontend source changes.

## Active Plan: user-agent/frontend-agent - Index Schedule Self-Service Compatibility

Status: completed on 2026-06-06 after add/list/delete service smoke, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected homepage schedule write endpoints used by the copied Vue dashboard:

- `POST /sys/index/schedule/add`
- `POST /sys/index/schedule/deleteSchedule`

This slice creates and deletes only current-token-user schedule rows stored in `sys_relation` with `CATEGORY = SYS_USER_SCHEDULE_DATA`.

### 2. Involved Modules

- user-agent current-user schedule compatibility
- frontend-agent copied homepage schedule widget compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/sys/IndexController.php`
- `app/service/sys/IndexService.php`
- `route/app.php`
- `docs/api/index-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- The route file is a locked public file; the change must be recorded in `docs/tasks/public-file-change-request.md`.
- Java deletes schedules by ids directly; this ThinkPHP slice should constrain deletion to the current token user's `SYS_USER_SCHEDULE_DATA` rows.
- `sys_relation` has no audit columns, so this slice must not invent audit data.

### 5. Test Commands

```powershell
php -l app\controller\sys\IndexController.php
php -l app\service\sys\IndexService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- Both schedule write routes are registered behind `AuthMiddleware`.
- Add requires `scheduleDate`, `scheduleTime`, and `scheduleContent`.
- Add writes `OBJECT_ID = current user id`, `TARGET_ID = scheduleDate`, `CATEGORY = SYS_USER_SCHEDULE_DATA`, and Java-compatible `EXT_JSON` including current user id/name.
- Delete accepts Java-style array body, `idList`, `ids`, or single `id`, and deletes only current user's schedule rows.
- Java source, database schema, Composer files, `.env`, frontend source, other schedule users, message routes, and business modules remain unchanged.

### 7. Forbidden Scope

- Do not implement shared calendar behavior, schedule editing, notifications, Java source changes, database schema changes, Composer changes, `.env` changes, frontend source changes, or unrelated system/user mutations.

## Active Plan: auth-agent/frontend-agent - Session And Token Exit Compatibility

Status: completed on 2026-06-06 after token-index smoke, session/token exit service smoke, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected exit endpoints used by the copied auth monitor frontend:

- `POST /auth/session/b/exit`
- `POST /auth/session/c/exit`
- `POST /auth/token/b/exit`
- `POST /auth/token/c/exit`

This slice also adds a minimal cache-backed token index to `TokenService` so B-side session/token revocation can actually locate active ThinkPHP tokens.

### 2. Involved Modules

- auth-agent session/token monitor compatibility
- frontend-agent copied auth monitor API compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/auth/SessionController.php`
- `app/service/auth/SessionMonitorService.php`
- `app/service/auth/TokenService.php`
- `route/app.php`
- `docs/api/auth-session-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- Java Sa-Token can enumerate and revoke Redis sessions globally; current ThinkPHP tokens were previously stored only by hashed token key.
- New token indexes only apply to tokens created after this slice. Existing unindexed tokens can still revoke themselves through logout.
- Returning full `tokenValue` is needed for Java-compatible token exit; access must remain protected and management-sensitive.
- C-side client auth is still not implemented, so C-side exit endpoints should accept Java-shaped payloads but perform no B-side mutation.

### 5. Test Commands

```powershell
php -l app\controller\auth\SessionController.php
php -l app\service\auth\SessionMonitorService.php
php -l app\service\auth\TokenService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- All four exit routes are registered behind `AuthMiddleware`.
- B-side session exit accepts Java-style arrays of `{ userId }` and revokes indexed tokens for those users.
- B-side token exit accepts Java-style arrays of `{ tokenValue }` and revokes those tokens.
- Ordinary users can only operate on their own user id/token; admin-compatible accounts or roles may manage all indexed B-side sessions.
- Session monitor page rows use indexed token data where available and include Java-compatible full `tokenValue` for token exit.
- C-side exit endpoints return success-compatible results without mutating B-side tokens because C-side client auth is deferred.
- Java source, database schema, Composer files, `.env`, frontend source, workflow, user CRUD, and business modules remain unchanged.

### 7. Forbidden Scope

- Do not implement C-side login/client token storage, third-party login, OAuth callback, route permission middleware, frontend source changes, Java source changes, database schema changes, Composer changes, `.env` changes, or unrelated auth/user/business mutations.

## Active Plan: api-agent/frontend-agent - Dev Message Delete Compatibility

Status: completed on 2026-06-06 after temp-message delete smoke, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected station-message delete endpoint used by the copied Vue message management page:

- `POST /dev/message/delete`

This slice deletes selected `dev_message` rows and their `MSG_TO_USER` receiver relations. It must not implement message send, WebPush, full realtime push, file cleanup, or frontend source changes.

### 2. Involved Modules

- api-agent dev message compatibility
- frontend-agent copied dev message page compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/dev/MessageController.php`
- `app/service/dev/MessageService.php`
- `route/app.php`
- `docs/api/dev-message-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- Java physically removes `dev_message` rows. This slice follows that behavior, so tests must create and delete temporary rows only.
- Deleting a message must also remove `dev_relation` rows for `CATEGORY = MSG_TO_USER`.
- The current project does not have fine-grained route permission middleware, so this slice should conservatively allow admin-compatible users to delete tenant messages and ordinary users to delete only their own created messages.

### 5. Test Commands

```powershell
php -l app\controller\dev\MessageController.php
php -l app\service\dev\MessageService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /dev/message/delete` is registered behind `AuthMiddleware`.
- Request accepts Java-style arrays of `{ id }`, `idList`, `ids`, or a single `id`.
- The service validates non-empty ids and active messages.
- Admin-compatible accounts/roles may delete tenant messages.
- Ordinary users may delete only messages they created.
- Delete removes matching `dev_relation` receiver rows before deleting selected `dev_message` rows.
- Smoke tests insert temporary rows and verify both message and relation rows are removed.
- Java source, database schema, Composer files, `.env`, frontend source, message send, WebPush, and full realtime push remain unchanged.

### 7. Forbidden Scope

- Do not implement `/dev/message/send`, SSE/WebPush push behavior, file upload/storage cleanup, Java source changes, database schema changes, Composer changes, `.env` changes, frontend source changes, or unrelated user/workflow/business mutations.

## Active Plan: api-agent/frontend-agent - Dev Message Send Compatibility

Status: completed on 2026-06-06 after temp-message send/delete smoke, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected station-message send endpoint used by the copied Vue message management form:

- `POST /dev/message/send`

This slice creates one `dev_message` row and `MSG_TO_USER` receiver relations for selected users. It must not implement full SSE/WebPush realtime push, message templates, file upload/storage, or frontend source changes.

### 2. Involved Modules

- api-agent dev message compatibility
- frontend-agent copied dev message form compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/dev/MessageController.php`
- `app/service/dev/MessageService.php`
- `route/app.php`
- `docs/api/dev-message-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- Java sends SSE/WebPush notifications after persistence; this slice should keep persistence compatible and defer full realtime notification parity.
- The endpoint writes to live development data, so smoke tests must use temporary rows and clean them up.
- Fine-grained route permission middleware is not complete yet, so send access should be limited to admin-compatible accounts/roles.

### 5. Test Commands

```powershell
php -l app\controller\dev\MessageController.php
php -l app\service\dev\MessageService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /dev/message/send` is registered behind `AuthMiddleware`.
- Request accepts copied frontend fields: `subject`, `category`, `content`, `href`, and `receiverIdList`.
- `subject` and `receiverIdList` are required; `content` defaults to `subject`; `category` defaults to `SYS`.
- Only active receiver users are accepted, scoped to current tenant when token tenant exists.
- One `dev_message` row is inserted with Java-compatible `EXT_JSON.href`.
- One `dev_relation` row per receiver is inserted with `CATEGORY = MSG_TO_USER` and `EXT_JSON.read = false`.
- Smoke tests insert temporary message/relation rows and clean them up.
- Java source, database schema, Composer files, `.env`, frontend source, full SSE/WebPush behavior, and unrelated modules remain unchanged.

### 7. Forbidden Scope

- Do not implement full realtime SSE/WebPush push behavior, message templates, file upload/storage cleanup, Java source changes, database schema changes, Composer changes, `.env` changes, frontend source changes, or unrelated user/workflow/business mutations.

## Active Plan: api-agent/frontend-agent - Dev Message Detail Mark-Read Compatibility

Status: completed on 2026-06-06 after temp-message mark-read smoke, route check, strict PHP lint, backend/frontend reachability, and no-token route protection inherited from the existing protected route group.

Date: 2026-06-06

### 1. Current Goal

Align protected `GET /dev/message/detail` with Java `DevMessageServiceImpl.detail` read-state behavior:

- when the current token user is a receiver of the message, update that user's `MSG_TO_USER` relation `EXT_JSON.read` to `true`
- keep the detail response shape and `receiveInfoList` intact

This slice must not implement full SSE/WebPush realtime notification parity.

### 2. Involved Modules

- api-agent dev message compatibility
- frontend-agent copied dev message detail compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/dev/MessageController.php`
- `app/service/dev/MessageService.php`
- `docs/api/dev-message-readonly-compat.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- `GET /dev/message/detail` now has a Java-compatible write side effect for the current user's receiver relation.
- Smoke tests must use a temporary message and relation, then clean them up.
- Full SSE/WebPush refresh behavior remains deferred to avoid broad realtime infrastructure changes.

### 5. Test Commands

```powershell
php -l app\controller\dev\MessageController.php
php -l app\service\dev\MessageService.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `GET /dev/message/detail` remains protected by existing `AuthMiddleware` route group.
- Detail accepts the current token payload from the controller.
- If the current user has a `dev_relation` receiver row for the message and `CATEGORY = MSG_TO_USER`, `EXT_JSON.read` is set to `true`.
- Existing relation `EXT_JSON` keys are preserved.
- Detail response includes updated `receiveInfoList` and `readCount`.
- Java source, database schema, Composer files, `.env`, frontend source, full SSE/WebPush behavior, and unrelated modules remain unchanged.

### 7. Forbidden Scope

- Do not add routes, implement full realtime SSE/WebPush behavior, change frontend source, modify Java source, change database schema, touch Composer files, modify `.env`, or alter unrelated user/workflow/business modules.

## Active Plan: user-agent/frontend-agent - User Role Grant Save Compatibility

Status: completed on 2026-06-06 after sys/biz grant-role service smoke with original role restoration, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected role-grant save endpoints used by the copied system and business user pages:

- `POST /sys/user/grantRole`
- `POST /biz/user/grantRole`

This slice only clears and rewrites `sys_relation` rows where `CATEGORY = SYS_USER_HAS_ROLE` for the target user.

### 2. Involved Modules

- user-agent role assignment compatibility
- frontend-agent copied user management grant-role dialog compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/sys-user-grant-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- The route file is a locked public file; the change must be recorded in `docs/tasks/public-file-change-request.md`.
- Role grants are RBAC-sensitive and should require admin-compatible payloads or matching route/button permission codes.
- Biz user grants in Java apply data-scope checks before delegating to system role grants; this slice must keep a conservative organization/self fallback.
- Empty `roleIdList` should be accepted as a clear operation because Java relation save-with-clear can persist an empty target list.

### 5. Test Commands

```powershell
php -l app\controller\sys\UserController.php
php -l app\service\user\UserDirectoryService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- Both grant-role routes are registered behind `AuthMiddleware`.
- Request accepts `id` and `roleIdList` from the copied frontend JSON/body payload.
- Existing target user's `SYS_USER_HAS_ROLE` relations are cleared and rewritten with valid active role ids.
- Invalid role ids fail without partially changing target relations.
- Biz route enforces a conservative data-scope guard before saving.
- Java source, database schema, Composer files, `.env`, frontend source, resource/permission grants, user CRUD, role CRUD, workflow, and unrelated modules remain unchanged.

### 7. Forbidden Scope

- Do not implement `/sys/user/grantResource`, `/sys/user/grantPermission`, user add/edit/delete, reset password, enable/disable, import/export, Java source changes, database schema changes, Composer changes, `.env` changes, frontend source changes, or unrelated auth/workflow/business mutations.

## Active Plan: user-agent/frontend-agent - User Resource Grant Save Compatibility

Status: completed on 2026-06-06 after resource-grant service smoke with original resource restoration, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected resource-grant save endpoint used by the copied system user page:

- `POST /sys/user/grantResource`

This slice only clears and rewrites `sys_relation` rows where `CATEGORY = SYS_USER_HAS_RESOURCE` for the target user.

### 2. Involved Modules

- user-agent resource assignment compatibility
- frontend-agent copied system user resource-grant dialog compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/sys-user-grant-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- The route file is a locked public file; the change must be recorded in `docs/tasks/public-file-change-request.md`.
- Resource grants affect menu and button access and must require admin-compatible payloads or matching route/button permission codes.
- Java prevents system-module menu resources from being granted to non-super-admin target users; this protection must be preserved.
- Empty `grantInfoList` should be accepted as a clear operation because Java relation save-with-clear can persist an empty target list.

### 5. Test Commands

```powershell
php -l app\controller\sys\UserController.php
php -l app\service\user\UserDirectoryService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /sys/user/grantResource` is registered behind `AuthMiddleware`.
- Request accepts `id` and `grantInfoList` where each item contains `menuId` and `buttonInfo`.
- Existing target user's `SYS_USER_HAS_RESOURCE` relations are cleared and rewritten with valid active resource ids.
- Invalid menu or button ids fail without partially changing target relations.
- System-module resources are rejected when the target user does not have the super-admin-compatible role.
- Java source, database schema, Composer files, `.env`, frontend source, role grant, permission grant, user CRUD, workflow, and unrelated modules remain unchanged.

### 7. Forbidden Scope

- Do not implement `/sys/user/grantPermission`, user add/edit/delete, reset password, enable/disable, import/export, Java source changes, database schema changes, Composer changes, `.env` changes, frontend source changes, or unrelated auth/workflow/business mutations.

## Active Plan: user-agent/frontend-agent - User Permission Grant Save Compatibility

Status: completed on 2026-06-06 after permission-grant service smoke with original permission restoration, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected permission-grant save endpoint used by the copied system user page:

- `POST /sys/user/grantPermission`

This slice only clears and rewrites `sys_relation` rows where `CATEGORY = SYS_USER_HAS_PERMISSION` for the target user.

### 2. Involved Modules

- user-agent permission assignment compatibility
- frontend-agent copied system user permission-grant dialog compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/sys-user-grant-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- The route file is a locked public file; the change must be recorded in `docs/tasks/public-file-change-request.md`.
- Permission grants affect API/data-scope access and must require admin-compatible payloads or matching route/button permission codes.
- `scopeCategory` values must stay compatible with the copied frontend and Java data-scope model.
- Empty `grantInfoList` should be accepted as a clear operation because Java relation save-with-clear can persist an empty target list.

### 5. Test Commands

```powershell
php -l app\controller\sys\UserController.php
php -l app\service\user\UserDirectoryService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- `POST /sys/user/grantPermission` is registered behind `AuthMiddleware`.
- Request accepts `id` and `grantInfoList` where each item contains `apiUrl`, `scopeCategory`, and `scopeDefineOrgIdList`.
- Existing target user's `SYS_USER_HAS_PERMISSION` relations are cleared and rewritten with Java-compatible `EXT_JSON`.
- Invalid or empty API urls and unsupported scope categories fail without partially changing target relations.
- Custom organization ids are validated against active `sys_org` rows.
- Java source, database schema, Composer files, `.env`, frontend source, role/resource grants, user CRUD, workflow, and unrelated modules remain unchanged.

### 7. Forbidden Scope

- Do not implement role resource grants, mobile resource grants, user add/edit/delete, reset password, enable/disable, import/export, Java source changes, database schema changes, Composer changes, `.env` changes, frontend source changes, route-permission middleware, or unrelated auth/workflow/business mutations.

## Active Plan: user-agent/frontend-agent - User Enable Disable Compatibility

Status: completed on 2026-06-06 after status-toggle service smoke with original status restoration, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected status switch endpoints used by the copied system and business user pages:

- `POST /sys/user/disableUser`
- `POST /sys/user/enableUser`
- `POST /biz/user/disableUser`
- `POST /biz/user/enableUser`

This slice only updates `sys_user.USER_STATUS` between `ENABLE` and `DISABLED`.

### 2. Involved Modules

- user-agent user status compatibility
- frontend-agent copied system/business user table switch compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/api/sys-user-grant-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- The route file is a locked public file; the change must be recorded in `docs/tasks/public-file-change-request.md`.
- User status changes affect login/use access and must require admin-compatible payloads or matching route/button permission codes.
- Java business user status changes enforce organization data-scope; this slice must keep a conservative data-scope/self fallback for `/biz/user/*`.
- Smoke tests must restore the sampled user's original `USER_STATUS`.

### 5. Test Commands

```powershell
php -l app\controller\sys\UserController.php
php -l app\service\user\UserDirectoryService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- All four status routes are registered behind `AuthMiddleware`.
- Requests accept Java-style `{ id }` JSON/body payloads.
- System routes set `USER_STATUS` to `DISABLED` or `ENABLE` after admin/permission guard.
- Business routes also enforce data-scope or current-user fallback before saving.
- Only `sys_user.USER_STATUS` is changed; no user CRUD, role/resource/permission grants, password reset, or import/export behavior is added.
- Java source, database schema, Composer files, `.env`, frontend source, workflow, and unrelated modules remain unchanged.

### 7. Forbidden Scope

- Do not implement user add/edit/delete, reset-password-by-admin, import/export, role/resource/permission grant changes, route-permission middleware, Java source changes, database schema changes, Composer changes, `.env` changes, frontend source changes, or unrelated auth/workflow/business mutations.

## Active Plan: user-agent/frontend-agent - User Reset Password Compatibility

Status: completed on 2026-06-06 after reset-password service smoke with original password restoration, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected admin reset-password endpoints used by the copied system and business user pages:

- `POST /sys/user/resetPassword`
- `POST /biz/user/resetPassword`

This slice only updates `sys_user.PASSWORD` to the SM3 hash of the configured system default password.

### 2. Involved Modules

- user-agent admin user password reset compatibility
- frontend-agent copied system/business user reset-password menu compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/api/sys-user-grant-readonly.md`
- `docs/api/user-reset-password-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- The route file is a locked public file; the change must be recorded in `docs/tasks/public-file-change-request.md`.
- The default password value is sensitive enough to avoid printing in test output, logs, or status reports.
- Business reset must preserve Java's conservative data-scope or current-user fallback before saving.
- Smoke tests must restore the sampled user's original `PASSWORD` hash.

### 5. Test Commands

```powershell
php -l app\controller\sys\UserController.php
php -l app\service\user\UserDirectoryService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- Both reset-password routes are registered behind `AuthMiddleware`.
- Requests accept Java-style `{ id }` JSON/body payloads.
- System route updates only `sys_user.PASSWORD` after admin/permission guard.
- Business route also enforces organization data-scope or current-user fallback before saving.
- Default password is read from `dev_config.CONFIG_KEY = SNOWY_SYS_DEFAULT_PASSWORD` and hashed with existing SM3 compatibility.
- Smoke tests restore the original password hash after each reset path.
- Java source, database schema, Composer files, `.env`, frontend source, user CRUD, import/export, token invalidation, and unrelated modules remain unchanged.

### 7. Forbidden Scope

- Do not implement user add/edit/delete, import/export, route-permission middleware, token/session invalidation on reset, Java source changes, database schema changes, Composer changes, `.env` changes, frontend source changes, or unrelated auth/workflow/business mutations.

## Active Plan: user-agent/frontend-agent - User Delete Compatibility

Status: completed on 2026-06-06 after delete service smoke with user/director-reference restoration, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected user delete endpoints used by copied system and business user pages:

- `POST /sys/user/delete`
- `POST /biz/user/delete`

This slice only performs logical deletion on `sys_user.DELETE_FLAG` and clears Java-compatible director references.

### 2. Involved Modules

- user-agent user delete compatibility
- frontend-agent copied system/business user table delete and batch-delete compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/api/sys-user-grant-readonly.md`
- `docs/api/user-delete-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- The route file is a locked public file; the change must be recorded in `docs/tasks/public-file-change-request.md`.
- User deletion can hide accounts from login and selectors, so this slice must use logical delete and must smoke-test with full restoration.
- Java clears direct `DIRECTOR_ID`, `POSITION_JSON.directorId`, and `sys_org.DIRECTOR_ID` references; this slice must preserve that cleanup without deleting unrelated records.
- Business delete must preserve Java's conservative data-scope or current-user fallback before saving.
- Built-in/admin-compatible accounts should be protected from deletion.

### 5. Test Commands

```powershell
php -l app\controller\sys\UserController.php
php -l app\service\user\UserDirectoryService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- Both delete routes are registered behind `AuthMiddleware`.
- Requests accept copied frontend array payloads such as `[{ id }]` and common `id`, `ids`, or `idList` forms.
- System route logically deletes non-built-in users after admin/permission guard.
- Business route also enforces organization data-scope or current-user fallback before saving.
- Delete clears affected `sys_user.DIRECTOR_ID`, matching `directorId` entries in `sys_user.POSITION_JSON`, and affected `sys_org.DIRECTOR_ID`.
- Smoke tests restore the sampled user and all touched director references.
- Java source, database schema, Composer files, `.env`, frontend source, user add/edit/import/export, role/resource/permission grants, and unrelated modules remain unchanged.

### 7. Forbidden Scope

- Do not implement user add/edit, import/export, route-permission middleware, token/session invalidation on delete, Java data-change events, Java source changes, database schema changes, Composer changes, `.env` changes, frontend source changes, or unrelated auth/workflow/business mutations.

## Active Plan: user-agent/frontend-agent - User Add Edit Compatibility

Status: completed on 2026-06-06 after sys/biz add-edit service smoke with temporary-row cleanup, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected user add/edit endpoints used by copied system and business user forms:

- `POST /sys/user/add`
- `POST /sys/user/edit`
- `POST /biz/user/add`
- `POST /biz/user/edit`

This slice only writes base `sys_user` profile fields and keeps password/status/grant/import/export side effects out of scope.

### 2. Involved Modules

- user-agent user add/edit compatibility
- frontend-agent copied system/business user form compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/api/user-add-edit-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- The route file is a locked public file; the change must be recorded in `docs/tasks/public-file-change-request.md`.
- Add/edit touches many profile columns, so the implementation must map only known `sys_user` fields and avoid broad user/business side effects.
- New users require safe defaults for password hash, status, tenant, bank fields, avatar, and company employee id.
- Business add/edit must preserve Java's organization data-scope behavior.
- Java encrypts some profile fields through SM4; full encrypted-field migration remains deferred.

### 5. Test Commands

```powershell
php -l app\controller\sys\UserController.php
php -l app\service\user\UserDirectoryService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- All four add/edit routes are registered behind `AuthMiddleware`.
- Requests accept copied frontend form payloads and store Java-compatible `sys_user` fields.
- Add validates required account/name/org/position, uniqueness, active org/position, optional director, default password hash, default enabled status, tenant, bank defaults, avatar, and company employee id.
- Edit validates the same references, protects built-in/admin-compatible account names from being changed, and does not update password/status/create metadata.
- Business add/edit enforces conservative organization data-scope or current-user edit fallback.
- Smoke tests create and remove only temporary test users without changing real user rows.
- Java source, database schema, Composer files, `.env`, frontend source, import/export, token/session invalidation, grants, and unrelated modules remain unchanged.

### 7. Forbidden Scope

- Do not implement import/export, route-permission middleware, Java data-change events, token/session invalidation, encrypted-field migration, org/position CRUD, Java source changes, database schema changes, Composer changes, `.env` changes, frontend source changes, or unrelated auth/workflow/business mutations.

## Active Plan: user-agent/frontend-agent - Org Add Edit Delete Compatibility

Status: completed on 2026-06-06 after sys/biz org add-edit-delete service smoke with temporary-row cleanup, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected organization maintenance endpoints used by copied system and business organization pages:

- `POST /sys/org/add`
- `POST /sys/org/edit`
- `POST /sys/org/delete`
- `POST /biz/org/add`
- `POST /biz/org/edit`
- `POST /biz/org/delete`

This slice writes only base `sys_org` rows and protects referenced organizations from deletion.

### 2. Involved Modules

- user-agent organization tree maintenance compatibility
- frontend-agent copied system/business organization form and table action compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/sys/OrgController.php`
- `app/service/user/OrgService.php`
- `route/app.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/api/org-write-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- The route file is a locked public file; the change must be recorded in `docs/tasks/public-file-change-request.md`.
- Java physically removes organization rows, but this ThinkPHP slice should logically delete `sys_org.DELETE_FLAG` to preserve database safety during staged refactor.
- Organization deletion can break users, extra-position JSON, roles, and positions, so dependency checks must block referenced orgs and child orgs.
- Business organization writes must preserve Java's conservative data-scope behavior.
- Parent changes must reject moving an organization below itself or a descendant.

### 5. Test Commands

```powershell
php -l app\controller\sys\OrgController.php
php -l app\service\user\OrgService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- All six organization write routes are registered behind `AuthMiddleware`.
- Requests accept copied frontend form payloads and array delete payloads such as `[{ id }]`.
- Add validates required parent/name/category/sort fields, valid categories, optional director, same-level duplicate names, tenant compatibility, and default organization code.
- Edit validates active organization, parent existence, category, optional director, duplicate names, tenant compatibility, and parent cycle prevention.
- Delete expands selected organizations to child organizations, blocks active user/extra-position/role/position references, and logically deletes only safe `sys_org` rows.
- Business routes enforce conservative organization data-scope before writing.
- Smoke tests create, edit, logically delete, and physically clean up only temporary organization rows.
- Java source, database schema, Composer files, `.env`, frontend source, position CRUD, user CRUD, auth, workflow, finance, and unrelated modules remain unchanged.

### 7. Forbidden Scope

- Do not implement position CRUD, user import/export, route-permission middleware, Java data-change events, Java physical delete behavior, Java source changes, database schema changes, Composer changes, `.env` changes, frontend source changes, or unrelated auth/workflow/business mutations.

## Active Plan: user-agent/frontend-agent - Position Add Edit Delete Compatibility

Status: completed on 2026-06-06 after sys/biz position add-edit-delete service smoke with temporary-row cleanup, route check, strict PHP lint, backend/frontend reachability, and no-token auth smoke.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected position maintenance endpoints used by copied system and business position pages:

- `POST /sys/position/add`
- `POST /sys/position/edit`
- `POST /sys/position/delete`
- `POST /biz/position/add`
- `POST /biz/position/edit`
- `POST /biz/position/delete`

This slice writes only base `sys_position` rows and protects user-referenced positions from deletion.

### 2. Involved Modules

- user-agent position maintenance compatibility
- frontend-agent copied system/business position form and table action compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/sys/PositionController.php`
- `app/service/user/PositionService.php`
- `route/app.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/api/position-write-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- The route file is a locked public file; the change must be recorded in `docs/tasks/public-file-change-request.md`.
- Java physically removes position rows, but this ThinkPHP slice should logically delete `sys_position.DELETE_FLAG` to preserve database safety during staged refactor.
- Position deletion can break users through direct `POSITION_ID` or `POSITION_JSON`, so dependency checks must block referenced positions.
- Business position writes must preserve Java's organization data-scope behavior.
- This slice should not change user profile writes, organization writes, import/export, route-permission middleware, workflow, finance, or stock behavior.

### 5. Test Commands

```powershell
php -l app\controller\sys\PositionController.php
php -l app\service\user\PositionService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- All six position write routes are registered behind `AuthMiddleware`.
- Requests accept copied frontend form payloads and array delete payloads such as `[{ id }]`.
- Add validates required org/name/category/sort fields, active organization, valid categories, same-organization duplicate names, tenant compatibility, and default position code.
- Edit validates active position, active organization, category, duplicate names, and tenant compatibility.
- Delete blocks active direct users and active user extra-position JSON references, then logically deletes only safe `sys_position` rows.
- Business routes enforce conservative organization data-scope before writing.
- Smoke tests create, edit, logically delete, and physically clean up only temporary position rows.
- Java source, database schema, Composer files, `.env`, frontend source, organization CRUD, user CRUD/import/export, auth, workflow, finance, and unrelated modules remain unchanged.

### 7. Forbidden Scope

- Do not implement user import/export, route-permission middleware, Java data-change events, Java physical delete behavior, Java source changes, database schema changes, Composer changes, `.env` changes, frontend source changes, or unrelated auth/workflow/business mutations.

## Active Plan: user-agent/frontend-agent - User Export Download Compatibility

Status: completed on 2026-06-06.

Date: 2026-06-06

### 1. Current Goal

Add Java-compatible protected download endpoints used by copied system and business user pages:

- `GET /sys/user/downloadImportUserTemplate`
- `GET /sys/user/export`
- `GET /sys/user/exportUserInfo`
- `GET /biz/user/export`
- `GET /biz/user/exportUserInfo`

This slice is read-only. It does not implement `POST /sys/user/import`.

### 2. Involved Modules

- user-agent user export compatibility
- frontend-agent copied system/business user download button compatibility
- Java read-only reference under `F:\AI\projects\testJava\OA`
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`

### 3. Involved Files

- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/api/user-export-download-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- The route file is a locked public file; the change must be recorded in `docs/tasks/public-file-change-request.md`.
- The Java implementation exports `.xlsx` and `.docx` using libraries not currently installed in the ThinkPHP project; this slice must not modify Composer files.
- CSV/plain-text download compatibility should avoid returning `PASSWORD` or secrets.
- Business exports must preserve conservative organization data-scope behavior.
- Import parsing, Excel generation, Word template rendering, file upload, and storage provider behavior remain out of scope.

### 5. Test Commands

```powershell
php -l app\controller\sys\UserController.php
php -l app\service\user\UserDirectoryService.php
php -l route\app.php
php think route:list
composer dump-autoload
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git diff --check
```

### 6. Acceptance Criteria

- All five download routes are registered behind `AuthMiddleware`.
- System template download returns a CSV template blob without requiring extra Composer dependencies.
- System and business user exports accept `userIds`, `searchKey`, and `userStatus` filters.
- Exported user rows are sanitized and do not include passwords or token data.
- System and business user-info downloads accept `id` and return a single-user text profile blob.
- Business export routes enforce conservative organization data-scope before returning data.
- No Java source, database schema, Composer files, `.env`, frontend source, import parsing, upload handling, auth, workflow, finance, or unrelated modules are changed.

### 7. Forbidden Scope

- Do not implement `POST /sys/user/import`, Excel parser, Word template renderer, new Composer dependencies, file upload/storage behavior, route-permission middleware, Java data-change events, Java source changes, database schema changes, Composer changes, `.env` changes, frontend source changes, or unrelated auth/workflow/business mutations.

## Active Plan: test-agent - Smoke Runbook Automation

Status: completed on 2026-06-06 after smoke script execution passed.

Date: 2026-06-06

### 1. Current Goal

Turn the repeated post-slice manual checks into a focused test-agent smoke script and runbook for the integrated ThinkPHP project.

### 2. Involved Modules

- test-agent regression workflow
- ThinkPHP target under `F:\AI\projects\testJava\OA-ThinkPHP`
- Java read-only reference remains untouched under `F:\AI\projects\testJava\OA`

### 3. Involved Files

- `scripts/test-agent-smoke.ps1`
- `docs/tasks/test-agent-smoke-runbook.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- The script must not change `.env`, database data, Java source, Composer dependencies, or application code.
- DB-backed export smoke is still blocked by the local MySQL credential mismatch and must remain documented as an environment prerequisite.
- No-token HTTP smoke should remain optional because it requires a separately started backend server.

### 5. Test Commands

```powershell
.\scripts\test-agent-smoke.ps1
git diff --check
```

### 6. Acceptance Criteria

- The smoke script runs Composer autoload, ThinkPHP console bootstrap, route list, required route coverage checks, strict PHP lint, and whitespace checks.
- Optional no-token HTTP smoke can be run when a backend server is already available.
- The runbook records the DB credential blocker without exposing secrets.
- No business behavior, route registration, frontend source, Java source, `.env`, database schema, or Composer files are changed.

### 7. Forbidden Scope

- Do not implement new routes, services, controllers, database writes, imports, exports, browser automation, frontend source changes, `.env` edits, Java source edits, or dependency changes in this slice.

## Active Plan: test-agent - DB Smoke Script Automation

Status: completed on 2026-06-06 after script review and execution.

Date: 2026-06-06

### 1. Current Goal

Add a repeatable DB/Redis/export smoke script that can be run in future conversations after starting the user-provided local runtime bundle.

### 2. Involved Modules

- test-agent runtime validation
- local MySQL/Redis runtime described in `docs/tasks/local-runtime-services.md`
- user export service smoke for the current export/download compatibility slice

### 3. Involved Files

- `scripts/test-agent-db-smoke.ps1`
- `scripts/test-agent-smoke.ps1`
- `docs/tasks/test-agent-smoke-runbook.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- The script must not print or commit MySQL/Redis passwords.
- The script must read credentials only from ignored local `.env`.
- DB smoke must remain a validation step and must not mutate application data.
- No-token HTTP smoke must accept both JSON business `code = 401` and real HTTP 401 responses.

### 5. Test Commands

```powershell
.\scripts\test-agent-smoke.ps1 -SkipComposer
.\scripts\test-agent-db-smoke.ps1
git diff --check
```

### 6. Acceptance Criteria

- DB smoke confirms the expected database has application tables.
- Redis smoke confirms authenticated `PING` returns `PONG`.
- User export service smoke confirms system export, business export, and single-user profile export descriptors are valid.
- Sampled export content does not include `PASSWORD`.
- No `.env`, Java source, route, controller, service, frontend source, database schema, or Composer files are changed.

### 7. Forbidden Scope

- Do not implement business endpoints, browser automation, frontend changes, database writes, `.env` edits, Java source changes, route changes, or dependency changes in this slice.

## Active Plan: api-agent - Business Dictionary Edit Compatibility

Status: completed on 2026-06-06 after direct service smoke, route check, strict lint, and DB smoke.

Date: 2026-06-06

### 1. Current Goal

Add the copied frontend business dictionary edit endpoint:

- `POST /biz/dict/edit`

This slice edits only existing active business dictionary rows and does not add dictionary add/delete routes.

### 2. Involved Modules

- api-agent business dictionary route compatibility
- copied frontend `snowy-admin-web/src/api/biz/bizDictApi.js`
- ThinkPHP dictionary service under `app/service/dev`
- Java source remains read-only under `F:\AI\projects\testJava\OA`

### 3. Involved Files

- `app/controller/dev/DictController.php`
- `app/service/dev/DictService.php`
- `route/app.php`
- `docs/api/biz-dict-edit-compat.md`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- `dev_dict` is shared by system and business dictionaries; this slice must restrict writes to `CATEGORY = BIZ`.
- Same-parent duplicate labels must be rejected.
- Tenant mismatch must be rejected unless the payload is admin-compatible.
- Dictionary add/delete and system dictionary writes must remain deferred.
- Java dictionary cache invalidation parity is not implemented in this slice.

### 5. Test Commands

```powershell
php -l app\controller\dev\DictController.php
php -l app\service\dev\DictService.php
php -l route\app.php
php think route:list
php think
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
.\scripts\test-agent-smoke.ps1 -SkipComposer
.\scripts\test-agent-db-smoke.ps1
git diff --check
```

### 6. Acceptance Criteria

- `POST /biz/dict/edit` is registered behind `AuthMiddleware`.
- Controller accepts form POST, raw JSON, and request parameter payloads.
- Service edits only active `CATEGORY = BIZ` rows.
- Service validates `id`, `dictLabel`, numeric `sortCode`, optional business parent, duplicate labels, and tenant compatibility.
- Service preserves category, dict value, tenant, and create metadata.
- Service smoke edits only temporary rows, blocks duplicate labels, and cleans up temporary rows.
- No Java source, `.env`, database schema, Composer files, frontend source, system dictionary write behavior, or unrelated business modules are changed.

### 7. Forbidden Scope

- Do not implement `/biz/dict/add`, `/biz/dict/delete`, `/dev/dict` writes, dictionary cache invalidation parity, frontend source changes, Java source changes, database schema changes, Composer changes, `.env` changes, or unrelated business writes.

## Completed Plan: merge-agent - Collection Receipt Mark-Success Compatibility

Status: completed on 2026-06-11 after explorer review, route registration, PHP lint, and DB smoke.

### 1. Current Goal

Add old-frontend-compatible collection-receipt mark-success endpoint:

- `POST /biz/bizcollectionreceipt/mark/success/edit`

This slice mirrors Java `BizCollectionReceiptServiceImpl.markSettlement(String id)` as a single-table settlement-status marker.

### 2. Involved Modules

- `biz/bizcollectionreceipt` route/controller/service
- copied frontend `snowy-admin-web/src/api/biz/bizCollectionReceiptApi.js`
- Java source remains read-only under `F:\AI\projects\testJava\OA`

### 3. Involved Files

- `app/service/biz/CollectionReceiptService.php`
- `app/controller/biz/CollectionReceiptController.php`
- `route/app.php`
- `docs/api/biz-collection-receipt-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- `batchExpenditure` creates expenditure and settlement-account side effects and remains deferred.
- This mark-success route must not update settlement account balances, statements, payment records, expenditure records, receipt amount, or settlement amount.
- Write access must stay tenant-scoped and guarded by admin-compatible role, payment-record organization scope, or creator ownership.

### 5. Test Commands

```powershell
php -l app\service\biz\CollectionReceiptService.php
php -l app\controller\biz\CollectionReceiptController.php
php -l route\app.php
php think route:list
git diff --check
```

Focused DB smoke was run through ThinkPHP bootstrap using the user-designated local MySQL/Redis runtime.

### 6. Acceptance Criteria

- `POST /biz/bizcollectionreceipt/mark/success/edit` is registered behind `AuthMiddleware`.
- Controller accepts form POST, raw JSON, and request parameter payloads.
- Service validates `id`, finds only active current-tenant receipts, and rejects unauthorized writes.
- Service sets only `PLAY_STATUS = AlreadySettled`, `UPDATE_TIME`, `UPDATE_USER`, and `VERSION = VERSION + 1`.
- DB smoke verifies missing-id `400`, non-admin `403`, version increment, unchanged amount/payment fields, and no account/statement/payment/expenditure side effects.

### 7. Forbidden Scope

- Do not implement `batchExpenditure`, collection-receipt add/edit/delete, settlement-account balance changes, expenditure-record creation, frontend changes, Java source changes, database schema changes, Composer changes, `.env` changes, or unrelated finance writes in this slice.

## Completed Plan: merge-agent - Debit Note Mark-Success Compatibility

Status: completed on 2026-06-11 after explorer review, route registration, PHP lint, and DB smoke.

### 1. Current Goal

Add old-frontend-compatible debit-note mark-success endpoint:

- `POST /biz/bizdebitnote/mark/success/edit`

This slice mirrors Java `BizDebitNoteServiceImpl.markSettlement(String id)` as a single-table settlement-status marker.

### 2. Involved Modules

- `biz/bizdebitnote` route/controller/service
- copied frontend `snowy-admin-web/src/api/biz/bizDebitNoteApi.js`
- Java source remains read-only under `F:\AI\projects\testJava\OA`

### 3. Involved Files

- `app/service/biz/DebitNoteService.php`
- `app/controller/biz/DebitNoteController.php`
- `route/app.php`
- `docs/api/biz-debit-note-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `PLANS.md`
- `STATUS.md`

### 4. Risks

- `history/add` and `batchRepayment/edit` can create payment records and settlement-account side effects and remain deferred.
- This mark-success route must not update settlement account balances, statements, payment records, expenditure records, debit-note amount, settlement amount, or history amount.
- Write access must stay tenant-scoped and guarded by admin-compatible role, debit-note organization scope, or creator ownership.

### 5. Test Commands

```powershell
php -l app\service\biz\DebitNoteService.php
php -l app\controller\biz\DebitNoteController.php
php -l route\app.php
php think route:list
git diff --check
```

Focused DB smoke was run through ThinkPHP bootstrap using the user-designated local MySQL/Redis runtime.

### 6. Acceptance Criteria

- `POST /biz/bizdebitnote/mark/success/edit` is registered behind `AuthMiddleware`.
- Controller accepts form POST, raw JSON, and request parameter payloads.
- Service validates `id`, finds only active current-tenant debit notes, and rejects unauthorized writes.
- Service sets only `PLAY_STATUS = AlreadySettled`, `UPDATE_TIME`, `UPDATE_USER`, and `VERSION = VERSION + 1`.
- DB smoke verifies missing-id `400`, non-admin `403`, version increment, unchanged amount/history/expenditure/org fields, and no account/statement/payment/expenditure side effects.

### 7. Forbidden Scope

- Do not implement `history/add`, `batchRepayment/edit`, debit-note add/edit/delete, settlement-account balance changes, payment-record creation, frontend changes, Java source changes, database schema changes, Composer changes, `.env` changes, or unrelated finance writes in this slice.

## Completed Plan: merge-agent - Payroll Edit Batch-Edit Delete Compatibility

Status: completed on 2026-06-12 after payroll explorer review, route registration, PHP lint, route-list verification, and DB smoke with temporary-row cleanup.

### 1. Current Goal

Add old-frontend-compatible payroll low-risk write endpoints:

- `POST /biz/bizpayroll/edit`
- `POST /biz/bizpayroll/bath/edit`
- `POST /biz/bizpayroll/delete`

This slice mirrors Java `BizPayrollServiceImpl.edit`, `bathEdit`, and `delete` for base payroll rows only.

### 2. Involved Modules

- `biz/bizpayroll` route/controller/service
- copied frontend `snowy-admin-web/src/api/biz/bizPayrollApi.js`
- Java source remains read-only under `F:\AI\projects\testJava\OA`

### 3. Involved Files

- `app/service/biz/BizPayrollService.php`
- `app/controller/biz/BizPayrollController.php`
- `route/app.php`
- `docs/api/biz-payroll-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- Payroll generation, import, and export are broader flows and remain deferred.
- `edit` and `bath/edit` must update only Java `BizPayrollEditParam` fields.
- Batch edit must validate all ids before writing to avoid partial updates.
- Delete uses logical `DELETE_FLAG = DELETED` for staged refactor safety.

### 5. Test Commands

```powershell
php -l app\service\biz\BizPayrollService.php
php -l app\controller\biz\BizPayrollController.php
php -l route\app.php
php think route:list
git diff --check
```

Focused DB smoke was run through ThinkPHP bootstrap using the user-designated local MySQL/Redis runtime.

### 6. Acceptance Criteria

- All three payroll write routes are registered behind `AuthMiddleware`.
- Controller accepts form POST, raw JSON, and request parameter payloads.
- `edit` updates only Java edit fields and preserves non-edit fields such as `POST_WAGE`, `YEAR_END_BONUS`, `PUBLIC_ACCOUNT`, `PRIVATE_ACCOUNT`, `REMARK`, `USER`, `ORG`, and `SALARY_TIME`.
- `bath/edit` accepts `{ list: [...] }`, rejects missing or duplicate ids, and validates the full batch before any update.
- `delete` accepts Java-style `[{ id }]` payloads and logically deletes active rows.
- DB smoke verifies field preservation, batch update, missing-id rollback behavior, non-admin `403`, deleted-detail hiding, and temporary-row cleanup.

### 7. Forbidden Scope

- Do not implement payroll `add`, `generate/add`, `import`, `export`, `downloadImportTemplate`, Java source changes, database schema changes, Composer changes, `.env` changes, frontend changes, or unrelated workflow/business side effects in this slice.

## Completed Plan: merge-agent - Payroll Import Template Download Compatibility

Status: completed on 2026-06-12 after explorer review, route registration, PHP lint, service hash smoke, authenticated HTTP smoke, and business-table no-write check.

### 1. Current Goal

Add old-frontend-compatible payroll import template download endpoint:

- `GET /biz/bizpayroll/downloadImportTemplate`

This slice returns the original Java `userPayrollTemplate.xlsx` bytes as a blob response and does not implement payroll import.

### 2. Involved Modules

- `biz/bizpayroll` route/controller/service
- copied frontend `snowy-admin-web/src/api/biz/bizPayrollApi.js`
- copied frontend import dialog `snowy-admin-web/src/views/biz/bizpayroll/impExp.vue`
- Java source remains read-only under `F:\AI\projects\testJava\OA`

### 3. Involved Files

- `app/resources/biz/payroll/userPayrollTemplate.xlsx`
- `app/service/biz/BizPayrollService.php`
- `app/controller/biz/BizPayrollController.php`
- `route/app.php`
- `docs/api/biz-payroll-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### 4. Risks

- The template download is low risk, but `import` is not: Java import parses a complex workbook, allows partial success, writes payroll rows, and triggers data-change events.
- `export` is also deferred because Java uses EasyExcel with multi-level headers and merged rows.
- `generate/add` remains deferred because it aggregates users, projects, payment records, and leave records before writing payroll rows.

### 5. Test Commands

```powershell
php -l app\service\biz\BizPayrollService.php
php -l app\controller\biz\BizPayrollController.php
php -l route\app.php
php think route:list
git diff --check
```

Focused service and authenticated HTTP smokes were run through the user-designated local MySQL/Redis/runtime.

### 6. Acceptance Criteria

- `GET /biz/bizpayroll/downloadImportTemplate` is registered behind `AuthMiddleware`.
- The route returns a blob response, not a JSON envelope.
- The response content type is `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`.
- The downloaded file is 13427 bytes, starts with `PK`, and has SHA256 `4A98E66E74E8D310D6226A5F6DD60602652FC25FD6D0FB272281BBF19CD861B8`.
- `biz_payroll` row count remains stable before and after template download.

### 7. Forbidden Scope

- Do not implement payroll `import`, `export`, `generate/add`, `add`, Excel parsing/rendering, payroll calculation logic, Java source changes, database schema changes, Composer changes, `.env` changes, frontend changes, or unrelated workflow/business side effects in this slice.
