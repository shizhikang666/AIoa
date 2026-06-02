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

- Java mark-success and batch-expenditure methods mutate settlement state and expenditure records, so they must remain deferred.
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

- Only read-only `/biz/bizcollectionreceipt/page`, `/biz/bizcollectionreceipt/list`, and `/biz/bizcollectionreceipt/detail` routes are added.
- No collection-receipt add/edit/delete/mark-success/batch-expenditure route is added.
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

- Java debit-note history add, mark success, and batch repayment methods mutate debit-note settlement state, payment records, and settlement accounts, so they must remain deferred.
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

- Only read-only `/biz/bizdebitnote/page`, `/biz/bizdebitnote/list`, and `/biz/bizdebitnote/detail` routes are added.
- No debit-note history add, mark-success, batch-repayment, add, edit, or delete route is added.
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
