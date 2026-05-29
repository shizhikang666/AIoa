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
