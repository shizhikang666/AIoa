锘块敇鍧楁晣閸ф鏅ｉ柛褎顨嗛弲? STATUS.md

## 2026-05-28 15:36 +08:00

Agent: db-agent

### Completed Content

- Completed db-agent foundation database mapping phase.
- Analyzed Java SQL snapshot, system/auth/client/mobile/tenant entities, mapper XML, and RBAC relation categories.
- Generated passive ThinkPHP foundation Models.
- Generated database mapping, relation, and index analysis documents.
- Created long-term workflow tracking files required by the multi-agent process.
## 2026-05-28 17:25 +08:00

Agent: auth-agent

### Completed Content

- Started auth-agent after db-agent completion.
- Confirmed worktree is on `refactor/auth` and clean before edits.
- Read project rules from `AGENTS.md`.
- Confirmed `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md` were missing in `OA-auth`; created them for the long-term workflow.
- Analyzed Java auth controller/service/config and system login user provider at a high level.
- Identified that Java-compatible auth routes require modifying locked file `route/app.php`.
- Created public file change request before any route or auth business implementation.
# STATUS.md

## 2026-05-28 17:35 +08:00

Agent: user-agent

### Completed Content

- Started user-agent Phase 1 after db-agent/auth-agent foundations.
- Confirmed `OA-user` worktree is clean before edits.
- Created long-term workflow files for user-agent.
- Analyzed Java user, user-center, org, and position controllers at API level.
- Analyzed primary database tables from `oa2026.sql`.
- Documented module boundaries, route risks, and next implementation order.
# workflow-agent Status

## 2026-05-28 - workflow-agent - Phase 1 Started

### Completed Content

- Read `AGENTS.md`.
- Confirmed `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md` were missing and created them in the workflow worktree.
- Analyzed Java workflow/process source code in read-only mode.
- Analyzed BPMN process definitions.
- Analyzed workflow-related SQL tables in `oa2026.sql`.
- Generated workflow mapping and phase notes documents.
# STATUS.md

## 2026-05-28 18:05 +08:00

Agent: api-agent

### Completed Content

- Started api-agent Phase 1 after db-agent, auth-agent, and user-agent foundations.
- Confirmed `OA-api` worktree is clean before edits.
- Created long-term workflow files for api-agent.
- Inventoried Java Controller files at a project-wide level.
- Documented controller ownership boundaries and route integration risks.
- Kept Phase 1 documentation-only and avoided locked public files.
# STATUS.md

## 2026-05-28 - test-agent - Phase 1 Baseline

### Completed Content

- Read root agent rules from `AGENTS.md`.
- Confirmed missing local workflow files need to be created in the test-agent worktree only.
- Created test-agent workflow files for Plan -> Implement -> Test -> Commit -> Report.
- Created multi-worktree baseline test plan and merge risk list.
- Ran ThinkPHP baseline checks in `F:\AI\projects\testJava\OA-test`.

### Modified Files
# STATUS.md

## 2026-05-28 - docs-agent - Phase 1 Started

## Completed Content

- Confirmed docs-agent worktree path: `F:\AI\projects\testJava\OA-docs`.
- Confirmed current branch: `refactor/docs`.
- Confirmed Java source project exists and remains read-only: `F:\AI\projects\testJava\OA`.
- Confirmed updated SQL reference exists: `F:\AI\projects\testJava\OA\oa2026.sql`.
- Added docs-agent workflow files because `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md` were missing.
- Added multi-agent parallel status, final merge checklist, and post-launch data sync reminder documents.

## Modified Files

- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

### Previously Added db-agent Files

- `docs/database/table-map.md`
- `docs/database/relation-map.md`
- `docs/database/index-analysis.md`
- `app/model/BaseModel.php`
- `app/model/SysUser.php`
- `app/model/SysRole.php`
- `app/model/SysResource.php`
- `app/model/SysRelation.php`
- `app/model/SysOrg.php`
- `app/model/SysPosition.php`
- `app/model/SysUserProcessConfig.php`
- `app/model/Tenant.php`
- `app/model/AuthThirdUser.php`
- `app/model/ClientUser.php`
- `app/model/ClientRelation.php`
- `app/model/MobileResource.php`

### Test Results

- `Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer install --no-interaction --prefer-dist`: passed in `OA-db`; `vendor` remains ignored.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.

### Current Issues

- `F:\AI\projects\testJava\OA` currently shows an untracked `oa2026.sql` file. db-agent did not modify or commit Java source project files.
- `refactor/db` is ahead of `origin/refactor/db` by local commits and has not been pushed because remote push was not requested.

### Next Plan

- Wait for confirmation of the active plan in `PLANS.md`.
- After confirmation, start db-agent Phase 2 for high-priority OA business table analysis and passive Model generation.

## 2026-05-28 15:43 +08:00

Agent: db-agent

### Completed Content

- Recorded the updated SQL reference file provided by the user.
- Marked `F:\AI\projects\testJava\OA\oa2026.sql` as the primary read-only SQL source for future db-agent analysis.
- Added a final-stage reminder for online realtime data synchronization into the completed ThinkPHP OA project.
- `docs/tasks/user-agent-java-map.md`
- `docs/tasks/user-agent-phase1-notes.md`
- `docs/api/controller-inventory.md`
- `docs/tasks/api-agent-phase1-notes.md`

### Test Results

- Phase 1 is documentation only.
- `composer install --no-interaction --prefer-dist` restored the local `vendor` directory because it was missing in this worktree.
- `composer dump-autoload` passed.
- `php think` passed and reported ThinkPHP `8.1.4`.
- `php think route:list` passed with only baseline routes in this branch.
- PHP syntax lint passed for `app`, `config`, and `route`.
- `git diff --check` passed.

### Current Issues

- user-agent must not duplicate auth-agent's `GET /sys/userCenter/loginMenu` route.
- User grant role/resource/permission operations overlap auth-agent RBAC data and need a clear boundary.
- Import/export, avatar upload, and encrypted profile fields should be deferred.

### Next Plan

- Implement read-only organization and position tree/query services first.
- Then implement user page/detail selectors.
- Defer write operations, grants, import/export, and uploads until API routing ownership is confirmed.

## 2026-05-29 09:20 +08:00

Agent: user-agent

### Completed Content

- Added read-only user-agent service layer for organization, position, and user directory queries.
- Added a reusable tree builder for Java OA compatible organization trees.
- Kept Phase 2 route-free and controller-free to avoid locked public files.
- Documented db-agent model dependency for the final merge order.

### Current Issues

- `route/app.php` is a locked public file, so route registration must be handled through a public file change request or merge-agent integration step.
- Some Java controllers overlap module agents, especially auth, user, workflow, and database-backed CRUD modules.
- Upload, export, SSE, job, generator, and tenant APIs need separate decisions before implementation.

### Next Plan

- Turn the controller inventory into a route migration queue after module agents confirm service boundaries.
- Add public-file route change requests only when a concrete controller group is ready.
- Keep api-agent focused on controller adapters and API compatibility rather than domain service implementation.

## 2026-05-29 09:35 +08:00

Agent: api-agent

### Completed Content

- Added a read-only user directory route map for organization, position, user, and user-center endpoints.
- Added a public file change request for future `route/app.php` registration.
- Kept Phase 2 documentation-only and did not modify locked public files.
- Explicitly excluded `loginMenu` because auth-agent owns it.
- `docs/tasks/test-agent-baseline.md`
- `docs/tasks/test-agent-risk-list.md`

### Test Results

- Initial `composer dump-autoload` failed because the worktree had an incomplete `vendor` directory and `think\App` was missing.
- `composer install --no-interaction --prefer-dist`: passed after installing dependencies from `composer.lock`.
- `composer dump-autoload`: passed after dependency installation.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed, current routes are `think` and `hello/<name>`.
- PHP lint for `app`, `config`, and `route`: passed with no syntax errors.
- `php think test`: not run because the current console command list does not include `test`.

### Current Issues

- `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md` were missing before this phase and were created.
- No project test runner is configured yet.
- Current test branch only has starter ThinkPHP routes; later module branch merges must rerun the same baseline checks.

### Next Plan

- Commit the test-agent baseline plan.
- After db/auth/user/workflow/api/frontend branches are merged, rerun Composer, ThinkPHP console, route list, and PHP lint checks after each merge.

## 2026-05-29 - test-agent - Phase 2 Integration Test Matrix

### Completed Content

- Added integration test matrix for merge-agent validation.
- Covered Composer, ThinkPHP console, route list, PHP lint, auth response shape, read-only user directory endpoints, and read-only workflow endpoints.
- Kept this phase documentation-only and did not modify locked public files or business code.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/final-data-sync-reminder.md`
- `docs/tasks/integration-test-matrix.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }`: passed.

### Current Issues

- The SQL file is inside the Java source project and must remain read-only.
- Online realtime data sync is a deferred final-stage task and must not be started without a confirmed plan, backup, and user approval.

### Next Plan

- Commit the documentation reminder.
- Continue to wait for confirmation before starting db-agent Phase 2 implementation.

## 2026-05-28 16:05 +08:00

Agent: db-agent

### Completed Content

- Completed db-agent Phase 2 for high-priority OA business tables.
- Used `F:\AI\projects\testJava\OA\oa2026.sql` as the primary read-only SQL reference.
- Analyzed Java `biz` entity/table mappings and generated business table mapping notes.
- Generated passive ThinkPHP Models for 15 dependency-heavy business tables.
- Kept all generated Models database-only, with no controller, service, route, workflow, auth, or frontend logic.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/database/biz-table-map.md`
- `docs/database/biz-model-plan.md`
- `app/model/BizCcRecords.php`
- `app/model/BizFileRelation.php`
- `app/model/BizLeaveApplication.php`
- `app/model/BizPaymentRecord.php`
- `app/model/BizExpenditureRecord.php`
- `app/model/BizPurchaseOrder.php`
- `app/model/BizPurchaseOrderItem.php`
- `app/model/BizSaleProject.php`
- `app/model/BizSaleProjectProductItem.php`
- `app/model/BizTeamProject.php`
- `app/model/BizTeamProjectTask.php`
- `app/model/Customer.php`
- `app/model/Supplier.php`
- `app/model/Warehouses.php`
- `app/model/Inventory.php`

### Test Results

- `Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/auth-agent-java-auth-map.md`

### Test Results

- `composer install --no-interaction --prefer-dist`: passed; `vendor` generated locally and remains untracked/ignored.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `php think test`: not run because the current ThinkPHP console does not expose a `test` command.

### Current Issues

- Phase 2 intentionally did not cover every remaining business/support table; additional low-priority relation and document tables should be handled in a later db-agent slice.
- Online realtime production data synchronization remains a final-stage requirement and must wait until the merged ThinkPHP OA project is complete and a confirmed sync plan exists.
- `refactor/db` remains ahead of `origin/refactor/db`; no push has been performed.

### Next Plan

- Commit db-agent Phase 2.
- Prepare db-agent Phase 3 for remaining database coverage, or move to auth-agent after the user confirms the db-agent foundation is sufficient.

## 2026-05-28 16:28 +08:00

Agent: db-agent

### Completed Content

- Completed db-agent Phase 3 for sales project support tables.
- Used `F:\AI\projects\testJava\OA\oa2026.sql` as the primary read-only SQL reference.
- Analyzed Java sales/product/follow-up/return/warehouse entities and mapper package coverage.
- Generated a sales support table mapping document for later api-agent, workflow-agent, and frontend-agent work.
- Generated passive ThinkPHP Models for 15 sales support tables.
- Kept all generated Models database-only, with no controller, service, route, workflow, auth, or frontend logic.
- `route/app.php` is locked by project rules, but Java-compatible auth endpoints need explicit route declarations.
- `vendor` is not present in the `OA-auth` worktree yet; `composer install --no-interaction --prefer-dist` is required before baseline ThinkPHP checks.
- auth-agent should not implement route or business code until the route change request is confirmed.

### Next Plan

- Run baseline checks after installing Composer dependencies if needed.
- Commit auth-agent planning and public file request.
- Wait for confirmation to edit `route/app.php` before implementing auth endpoints.

## 2026-05-28 16:34 +08:00

Agent: auth-agent

### Completed Content

- Implemented the first Java-compatible auth code slice after the route public-file change was confirmed by the continued instruction.
- Added B-side auth routes for captcha, account login, phone-login placeholder, logout, current user, and safe password verification.
- Added unified JSON response helper matching the project API response convention.
- Added Token service using `Authorization: Bearer <token>` and Redis-compatible `oa:auth:` cache keys.
- Added RBAC service that reads `sys_relation`, `sys_role`, `sys_resource`, and `mobile_resource` to assemble role, permission, menu, button, and mobile button context.
- Added auth controller and middleware scaffolding for later protected routes.
- Documented auth-agent Phase 2 compatibility notes and deferred items.
- `docs/tasks/workflow-agent-java-map.md`
- `docs/tasks/workflow-agent-phase1-notes.md`
- `docs/tasks/workflow-table-map.md`

### Test Results

- `composer dump-autoload`: passed after running `composer install --no-interaction --prefer-dist` because the worktree vendor directory was incomplete.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed, current route baseline only contains `think` and `hello/<name>`.
- PHP lint for `app`, `config`, and `route`: passed.
- `git status --short --branch`: passed; only workflow-agent docs/status files are untracked before commit.
- `php think test`: not run because the current ThinkPHP console does not expose a `test` command.

### Current Problems

- Workflow runtime implementation needs a later decision: Camunda-compatible tables can be read, but Java delegates cannot run in PHP.
- Workflow side effects are spread across finance, sale project, warehouse, procurement, and leave modules.
- Public route/config files remain locked and were not modified.

### Next Plan

- Phase 2 should choose the ThinkPHP workflow runtime strategy before any Controller or Service implementation.

## 2026-05-29 - workflow-agent - Phase 2 Runtime Strategy

### Completed Content

- Documented the recommended workflow runtime strategy.
- Chose a transitional ThinkPHP runtime that keeps existing Camunda `act_*` tables read-compatible.
- Mapped first read-only workflow API batch, config batch, and deferred mutation batch.
- Mapped Java delegate side effects to future explicit PHP services.
- Kept Phase 2 documentation-only and did not modify routes, models, services, or Java source.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/database/sales-support-table-map.md`
- `app/model/BizProduct.php`
- `app/model/ProductRelation.php`
- `app/model/BizSaleProjectInvoice.php`
- `app/model/BizSaleProjectInvoiceItem.php`
- `app/model/BizSaleProjectInvoicing.php`
- `app/model/BizSaleProjectProductInfo.php`
- `app/model/BizSaleProjectReissueOrder.php`
- `app/model/SaleProjectProductItemRelation.php`
- `app/model/SaleProjectFollowUp.php`
- `app/model/CustomerFollowUp.php`
- `app/model/SaleProjectRate.php`
- `app/model/SalesProjectFieldChangeLog.php`
- `app/model/ReturnOrder.php`
- `app/model/ReturnOrderItem.php`
- `app/model/DeliveryRecord.php`

### Test Results

- `Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `route/app.php`
- `app/controller/auth/AuthController.php`
- `app/middleware/AuthMiddleware.php`
- `app/service/auth/AuthService.php`
- `app/service/auth/RbacService.php`
- `app/service/auth/TokenService.php`
- `app/support/ApiResponse.php`
- `docs/tasks/auth-agent-phase2-notes.md`

### Test Results

- `Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed and listed the new `/auth/b/*` routes.
- `php think test`: not run because the current ThinkPHP console does not expose a `test` command.

### Current Issues

- Java `ProductRelation` declares `PRODUCT_RELATION`, while the updated SQL dump contains `product_relation`; the ThinkPHP Model uses the SQL physical table name and documents the mismatch.
- Finance/settlement and team collaboration support tables are still deferred to a later db-agent slice.
- Online realtime production data synchronization remains a final-stage requirement and must wait until the merged ThinkPHP OA project is complete and a confirmed sync plan exists.
- `refactor/db` remains ahead of `origin/refactor/db`; no push has been performed.

### Next Plan

- Commit db-agent Phase 3.
- Continue with db-agent Phase 4 for finance and collaboration support tables, or pause db-agent and start auth-agent after user confirmation.

## 2026-05-28 16:45 +08:00

Agent: db-agent

### Completed Content

- Completed db-agent Phase 4 for finance and settlement support tables.
- Used `F:\AI\projects\testJava\OA\oa2026.sql` as the primary read-only SQL reference.
- Analyzed Java collection receipt, debit note, settlement account, and account statement entities.
- Generated a finance settlement table mapping document for later api-agent and workflow-agent work.
- Generated passive ThinkPHP Models for 4 finance/settlement tables.
- Kept all generated Models database-only, with no controller, service, route, workflow, auth, or frontend logic.
- Java password compatibility still needs a dedicated SM2 decrypt plus SM3 hash slice; Phase 2 only isolates password verification and supports direct, PHP password hash, and SHA-256 fallback checks.
- Redis connection/store configuration is not changed in this phase because config files are locked and credentials must not be committed.
- Runtime DB verification requires a configured database using the mapped OA schema.
- Phone-code login and web-push behavior remain deferred.

### Next Plan

- Add Java password compatibility analysis and implementation plan before claiming full login compatibility.
- Decide whether Redis cache store config should be handled by merge-agent/test-agent or through a separate public-file change request.
- Continue with auth-agent menu tree shaping only after frontend-agent confirms required frontend route schema.

## 2026-05-28 16:45 +08:00

Agent: auth-agent

### Completed Content

- Analyzed Java password flow from `CommonCryptogramUtil`, `AuthServiceImpl`, and the old frontend SM2 login code.
- Confirmed `oa2026.sql` stores Java-compatible 64-character SM3 hashes in `sys_user.PASSWORD`.
- Added pure PHP SM3 hashing without introducing a Composer dependency.
- Added `PasswordService` and wired login verification through SM3 so imported Java user passwords can be checked.
- Updated safe-password verification to compare the submitted password before opening the short-lived `oa:auth:safe:` cache marker.
- Documented the SM2 boundary without writing any private key or secret into the ThinkPHP project.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/database/finance-table-map.md`
- `app/model/BizCollectionReceipt.php`
- `app/model/BizDebitNote.php`
- `app/model/SettlementAccount.php`
- `app/model/SettlementAccountStatement.php`

### Test Results

- `Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `app/service/auth/AuthService.php`
- `app/service/auth/PasswordService.php`
- `app/service/auth/Sm3Hasher.php`
- `docs/tasks/auth-agent-phase3-password-compat.md`

### Test Results

- `php -r "require 'vendor/autoload.php'; echo app\\service\\auth\\Sm3Hasher::hash('abc');"`: passed, matched the standard SM3 test vector.
- `php -r "require 'vendor/autoload.php'; echo app\\service\\auth\\Sm3Hasher::hash('123456');"`: passed, matched the default password hash in `oa2026.sql`.
- `PasswordService::verify('123456', <SQL default hash>)`: passed.
- `Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed and auth routes remained registered.
- `php think test`: not run because the current ThinkPHP console does not expose a `test` command.

### Current Issues

- `settlement_account.org` is lower-case in the SQL dump; the ThinkPHP Model documents and preserves this spelling.
- Team collaboration support tables are still deferred to a later db-agent slice.
- Online realtime production data synchronization remains a final-stage requirement and must wait until the merged ThinkPHP OA project is complete and a confirmed sync plan exists.
- `refactor/db` remains ahead of `origin/refactor/db`; no push has been performed.

### Next Plan

- Commit db-agent Phase 4.
- Continue with db-agent Phase 5 for team collaboration support tables, or pause db-agent and start auth-agent after user confirmation.

## 2026-05-28 17:02 +08:00

Agent: db-agent

### Completed Content

- Completed db-agent Phase 5 for team collaboration support tables.
- Used `F:\AI\projects\testJava\OA\oa2026.sql` as the primary read-only SQL reference.
- Analyzed Java team project comment, reply, task comment, task category, team user, and task user relation entities.
- Generated a team collaboration table mapping document for later api-agent, auth-agent, and workflow-agent work.
- Generated passive ThinkPHP Models for 6 team collaboration tables.
- Kept all generated Models database-only, with no controller, service, route, workflow, auth, or frontend logic.
- Existing Java frontend SM2 ciphertext is detected but not decrypted yet, because no SM2 private key or secret material may be committed.
- Full legacy frontend compatibility needs a secure SM2 adapter or a frontend-agent login adaptation over HTTPS.
- Runtime DB verification still requires configured database/cache services.

### Next Plan

- Continue auth-agent with menu/tree permission shaping and endpoint response compatibility.
- Keep Redis store wiring as a later public-file/config decision unless explicitly approved.
- After auth-agent reaches a stable checkpoint, move to user-agent according to the staged order.

## 2026-05-28 16:52 +08:00

Agent: auth-agent

### Completed Content

- Analyzed the old frontend login flow after successful token creation.
- Confirmed the frontend calls `GET /auth/b/getLoginUser`, then `GET /sys/userCenter/loginMenu`.
- Confirmed Java builds the login menu from user/role resource relations and returns a tree for the frontend router.
- Identified a module-boundary issue: menu permission data belongs to auth-agent, but the compatibility route path belongs to user center.
- Updated the public-file change request with ownership options for `GET /sys/userCenter/loginMenu`.
- Added a pending Phase 4 plan and did not modify route or business code.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/database/team-collaboration-table-map.md`
- `app/model/BizTeamProjectComment.php`
- `app/model/BizTeamProjectCommentReply.php`
- `app/model/BizTeamProjectTaskComment.php`
- `app/model/BizTeamProjectTaskCategory.php`
- `app/model/BizTeamProjectUser.php`
- `app/model/BizTeamProjectTaskUser.php`

### Test Results

- `Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `php think test`: not run because the current ThinkPHP console does not expose a `test` command.

### Current Issues

- Role and permission interpretation for team collaboration rows is intentionally deferred to auth-agent/api-agent.
- Remaining lower-priority support tables still need a short audit before deciding whether db-agent is complete.
- Online realtime production data synchronization remains a final-stage requirement and must wait until the merged ThinkPHP OA project is complete and a confirmed sync plan exists.
- `refactor/db` remains ahead of `origin/refactor/db`; no push has been performed.

### Next Plan

- Commit db-agent Phase 5.
- Run db-agent Phase 6 remaining support table audit, then decide whether to stop db-agent and begin auth-agent.

## 2026-05-28 17:14 +08:00

Agent: db-agent

### Completed Content

- Completed db-agent Phase 6 remaining support table audit.
- Compared Java biz entities against generated ThinkPHP Models.
- Documented remaining low-priority or shared tables that are not required for auth-agent startup.
- Decided that db-agent can pause and hand over to auth-agent.
- Kept this phase documentation-only, with no controller, service, route, workflow, auth, or frontend logic.
- `docs/tasks/public-file-change-request.md`

### Test Results

- No code was changed in this boundary-planning step.
- Previous Phase 3 checks remain passing in this clean worktree state.
- `git status --short --branch`: will be checked before committing this planning update.

### Current Issues

- `GET /sys/userCenter/loginMenu` requires an ownership decision before implementation because it may overlap auth-agent and user-agent responsibilities.
- Implementing the compatibility route requires modifying locked `route/app.php`.

### Next Plan

- Wait for confirmation on the login menu route ownership option.
- If confirmed for auth-agent, implement only menu tree compatibility and avoid user profile/organization/workbench APIs.
- If deferred to user-agent or merge-agent, keep auth-agent code as login, token, password, RBAC, and permission foundation.

## 2026-05-28 17:13 +08:00

Agent: auth-agent

### Completed Content

- Started multi-agent parallel work with user-agent, test-agent, and docs-agent in separate worktrees.
- Implemented auth-owned compatibility for `GET /sys/userCenter/loginMenu`.
- Added menu tree building from `SYS_USER_HAS_RESOURCE` and `SYS_ROLE_HAS_RESOURCE`.
- Added parent menu/module inclusion and frontend-compatible `meta` fields for router loading.
- Kept user center ownership narrow: no user profile, organization, position, workbench, process config, or message APIs were implemented.
- Updated the public-file change request status for the single route addition.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/database/remaining-table-audit.md`

### Test Results

- `Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `route/app.php`
- `app/controller/auth/UserCenterAuthController.php`
- `app/service/auth/MenuService.php`
- `docs/tasks/auth-agent-phase4-menu-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed and listed `sys/userCenter/loginMenu`.
- `php think test`: not run because the current ThinkPHP console does not expose a `test` command.

### Current Issues

- Remaining unmapped low-priority tables include `biz_draft`, `biz_history_excel`, `biz_payroll`, `biz_user_vacation`, `BIZ_RELATION`, `DEV_FILE`, and `DEV_DICT`.
- These do not block auth-agent startup and can be handled by later agents or a small db-agent follow-up if a concrete dependency appears.
- Online realtime production data synchronization remains a final-stage requirement and must wait until the merged ThinkPHP OA project is complete and a confirmed sync plan exists.
- `refactor/db` remains ahead of `origin/refactor/db`; no push has been performed.

### Next Plan

- Commit db-agent Phase 6 audit.
- Start auth-agent next in `F:\AI\projects\testJava\OA-auth` after confirming branch/worktree status and syncing the latest db-agent foundation strategy into the handoff plan.

## 2026-05-29 09:55 +08:00

Agent: db-agent

### Completed Content

- Completed db-agent Phase 7 workflow engine table model coverage.
- Used `F:\AI\projects\testJava\OA\oa2026.sql` as the primary read-only SQL reference.
- Added passive ThinkPHP Models for Camunda-style `act_*` runtime, repository, and history tables.
- Added `ActBaseModel` with `ID_` as the primary key for Camunda-compatible tables.
- Documented workflow engine model coverage and final data sync implications.
- Kept this phase database/model-only with no controller, service, route, workflow runtime, or side-effect logic.
- Runtime DB verification still requires a configured OA database and cache store.
- The rest of `/sys/userCenter/*` remains for user-agent.
- If user-agent later implements a richer `loginMenu`, merge-agent must keep only one route and compare output compatibility.

### Next Plan

- Commit auth-agent Phase 4.
- Wait for user-agent/test-agent/docs-agent reports.
- Review parallel agent outputs before starting the next module slice.

## 2026-05-29 10:25 +08:00

Agent: auth-agent

### Completed Content

- Added frontend-compatible `msg` field to unified API responses.
- Preserved the existing `message` field and response shape.
- Kept the change limited to response compatibility with no auth/token/RBAC behavior changes.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/database/workflow-engine-models.md`
- `app/model/ActBaseModel.php`
- `app/model/ActGeBytearray.php`
- `app/model/ActReDeployment.php`
- `app/model/ActReProcdef.php`
- `app/model/ActRuExecution.php`
- `app/model/ActRuTask.php`
- `app/model/ActRuVariable.php`
- `app/model/ActRuIdentitylink.php`
- `app/model/ActHiProcinst.php`
- `app/model/ActHiTaskinst.php`
- `app/model/ActHiVarinst.php`
- `app/model/ActHiActinst.php`
- `app/model/ActHiComment.php`
- `app/model/ActHiIdentitylink.php`

### Test Results

- `Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `php think test`: not run because the current ThinkPHP console does not expose a `test` command.

### Current Issues

- These models are passive wrappers only; workflow runtime behavior remains workflow-agent scope.
- Active process/task data must be included in the final online realtime data synchronization plan after project completion.

### Next Plan

- Commit db-agent Phase 7.
- workflow-agent can later build read-only query services on these models after final merge order brings db-agent first.
- `app/support/ApiResponse.php`
- `docs/tasks/workflow-runtime-design.md`
- `docs/tasks/workflow-api-map.md`
- `docs/tasks/workflow-side-effect-map.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed and listed auth/login/menu routes.
- PHP lint for `app`, `config`, and `route`: passed.

### Current Issues

- Old frontend and new backend docs now both have a response message field, but later frontend-agent should decide whether to keep dual fields permanently or remove `msg` after frontend migration.

### Next Plan

- Run baseline checks.
- Commit auth-agent Phase 5.
- `app/service/user/TreeBuilder.php`
- `app/service/user/OrgService.php`
- `app/service/user/PositionService.php`
- `app/service/user/UserDirectoryService.php`
- `docs/tasks/user-agent-phase2-services.md`
- `docs/api/user-directory-route-map.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload` passed.
- `php think` passed and reported ThinkPHP `8.1.4`.
- `php think route:list` passed with only baseline routes in this branch.
- PHP syntax lint passed for `app`, `config`, and `route`.
- `TreeBuilder` smoke test passed with a root-child sample tree.

### Current Issues

- Runtime DB-backed service testing must wait until `refactor/db` is merged before `refactor/user`.
- Controller and route integration still requires a public file change request or merge-agent step.
- Write operations, grants, import/export, avatar/signature upload, and process config edits remain deferred.

### Next Plan

- Add route/controller change request for read-only user/org/position endpoints.
- After approval, let api-agent or merge-agent wire routes to these services.
- Keep auth/RBAC/menu behavior owned by auth-agent.
- `php think route:list`: passed, current route baseline only contains `think` and `hello/<name>`.
- PHP lint for `app`, `config`, and `route`: passed.

### Current Problems

- Runtime workflow code is still blocked until db/auth/user foundations are merged and tested.
- Route registration still requires a public file change request before modifying `route/app.php`.
- Approval mutation and side effects remain high risk and must be implemented process by process.

### Next Plan

- Start workflow code with read-only query services only after db-agent model coverage for `act_*` tables is confirmed.
- Defer approve/reject and process start routes until test-agent has baseline route/task checks.

## 2026-05-29 - workflow-agent - Phase 3 Query Services

### Completed Content

- Added read-only workflow query services.
- Added variable normalization for runtime and historic Camunda variables.
- Covered pending task count/list/page, historic task page, started process page, and process detail query shape.
- Kept Phase 3 free of routes, controllers, approve/reject/cancel/start behavior, and business side effects.
- Documented dependency on db-agent `Act*` models.

### Current Issues

- `route/app.php` remains locked; route registration is pending confirmation or merge-agent action.
- Controller implementation should wait until user-agent services are merged after db-agent and auth-agent.
- Response compatibility still needs one final decision for frontend `msg` versus backend `message`.

### Next Plan

- After route change approval, add thin controller adapters that delegate to user-agent services.
- Keep actual domain behavior inside user-agent services.
- Continue API mapping for workflow and business modules only after their service boundaries are stable.

## 2026-05-29 10:45 +08:00

Agent: api-agent

### Completed Content

- Added thin read-only Controller adapters for organization, position, user, and user-center directory endpoints.
- Kept controllers as delegation only; user service behavior remains user-agent scope.
- Did not modify `route/app.php`; route registration remains pending through the documented public file change request.
- Documented controller dependencies on auth-agent response helper and user-agent services.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/workflow/WorkflowVariableService.php`
- `app/service/workflow/WorkflowQueryService.php`
- `docs/tasks/workflow-query-services.md`
- `app/controller/sys/BaseSysController.php`
- `app/controller/sys/OrgController.php`
- `app/controller/sys/PositionController.php`
- `app/controller/sys/UserController.php`
- `app/controller/sys/UserCenterController.php`
- `docs/api/user-directory-controller-adapters.md`

### Test Results
- `docs/tasks/parallel-agent-status.md`
- `docs/tasks/final-merge-checklist.md`
- `docs/tasks/post-launch-data-sync-reminder.md`

## Test Results

- `git status --short --branch`: passed; only docs-agent documentation files are untracked before commit.
- `composer install --no-interaction --prefer-dist`: passed; dependencies installed because `vendor/autoload.php` was missing.
- `composer dump-autoload`: passed.
- `php think`: passed; ThinkPHP console starts and reports version 8.1.4.
- `php think route:list`: passed; default ThinkPHP routes are listed.

## Current Issues

- `composer dump-autoload`, `php think`, and `php think route:list` initially failed before dependencies were installed because `vendor/autoload.php` was missing.
- After `composer install --no-interaction --prefer-dist`, the checks passed.
- No business code or locked public files were modified.

## Next Plan

- Commit documentation changes without pushing.
- Continue docs-agent later with API/deployment documentation after module Agents provide stable outputs.

## 2026-05-29 - docs-agent - Phase 2 Autonomous Execution Rules

## Completed Content

- Confirmed all module worktrees are clean and synced with remote after push:
  - `refactor/db`
  - `refactor/auth`
  - `refactor/user`
  - `refactor/workflow`
  - `refactor/api`
  - `refactor/frontend`
  - `refactor/test`
  - `refactor/docs`
- Added autonomous execution rules for the main control Agent.
- Added copyable user authorization text for safe long-running autonomous work.

## Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/autonomous-execution-rules.md`
- `docs/tasks/parallel-agent-status.md`

## Test Results

- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed, current route baseline only contains `think` and `hello/<name>`.
- PHP lint for `app`, `config`, and `route`: passed.

### Current Problems

- Runtime DB query testing must wait until `refactor/db` is merged before `refactor/workflow`.
- API routes are still not registered and require a public file change request.
- Mutation behavior and Java delegate replacement remain deferred.

### Next Plan

- Add workflow public route change request for the read-only API batch.
- After merged model/service validation, add thin controller adapters that call these services.
- `php think route:list`: passed with only baseline routes in this branch.
- PHP lint for `app`, `config`, and `route`: passed.

### Current Issues

- Runtime validation must wait for final merge because this branch does not yet contain auth-agent `ApiResponse` or user-agent services.
- Route registration remains pending authorization because `route/app.php` is locked.

### Next Plan

- Wait for documented route authorization or merge-agent action before modifying `route/app.php`.
- Continue API mapping for workflow read-only endpoints after workflow route request is prepared.

## 2026-05-29 11:20 +08:00

Agent: api-agent

### Completed Content

- Added thin read-only Controller adapters for workflow task and process query endpoints.
- Added a public file change request section for workflow read-only route registration.
- Kept controllers as delegation only; workflow behavior remains workflow-agent scope.
- Did not modify `route/app.php`.
- Explicitly excluded approve, reject, cancel, process start, delegate side effects, SSE, and file operations.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/BaseWorkflowController.php`
- `app/controller/biz/TaskController.php`
- `app/controller/biz/ProcessController.php`
- `docs/api/workflow-readonly-controller-adapters.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed with only baseline routes in this branch.
- PHP lint for `app`, `config`, and `route`: passed.

### Current Issues

- Runtime validation must wait for final merge because this branch does not yet contain auth-agent `ApiResponse` or workflow-agent services.
- Workflow route registration remains pending authorization because `route/app.php` is locked.

### Next Plan

- Continue with frontend-agent contract notes for newly prepared user/workflow endpoints.
- Leave actual route registration to documented approval or merge-agent.
- `php think route:list`: passed, current routes are `think` and `hello/<name>`.
- PHP lint for `app`, `config`, and `route`: passed with no syntax errors.

### Current Issues

- Endpoint runtime tests must wait until module branches are merged and routes are registered.
- Database/cache-backed checks require configured OA database and Redis/cache.

### Next Plan

- Rerun baseline checks.
- Commit test-agent Phase 2.

## Current Issues

- Route registration and final merge still need either explicit user authorization or documented approval scope.
- Destructive operations, Java source edits, database schema changes, secrets, and production data synchronization must remain stop conditions.

## Next Plan

- Commit docs-agent Phase 2.
- Continue implementation only inside approved agent scopes.

## 2026-05-29 - merge-agent - Runtime Verification Readiness

### Completed Content

- Checked merged `refactor/thinkphp-main` runtime prerequisites after final branch integration.
- Confirmed PHP has `pdo_mysql`, `mysqli`, and `redis` extensions.
- Confirmed `F:\AI\projects\testJava\OA\oa2026.sql` exists and remains read-only.
- Confirmed no `.env` file exists in `F:\AI\projects\testJava\OA-ThinkPHP`.
- Confirmed `mysql` and `redis-cli` are not available in the current PATH.
- Confirmed Windows service `MySQL80` exists but is stopped.
- Added Redis store support to `config/cache.php` while keeping the default cache driver as `file`.
- Added `docs/tasks/runtime-verification-plan.md` for safe local database import and smoke testing.

### Modified Files

- `config/cache.php`
- `docs/tasks/runtime-verification-plan.md`
- `STATUS.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed, 28 routes listed.
- PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed, with Git line-ending normalization warnings only.

### Current Issues

- Runtime endpoint testing is blocked until a local `.env`, local MySQL database, and Redis runtime are configured.
- The SQL import is a database-modifying action and must not be executed automatically without explicit confirmation.
- Online realtime production data sync remains deferred until the project is complete and accepted.

### Next Plan

- Run non-destructive ThinkPHP checks after the Redis cache configuration update.
- Commit and push the runtime readiness update if checks pass.
- Wait for explicit confirmation before starting MySQL, creating a database, importing `oa2026.sql`, or writing `.env`.

## 2026-05-29 - merge-agent - Local Database And Redis Runtime

### Completed Content

- Accepted the user-designated long-term local runtime database configuration for this project.
- Confirmed actual secrets are stored only in ignored local `.env`; no password is committed.
- Confirmed `F:\project\socket\AI\testPhp\files\tools\mysql\bin\mysql.exe` is usable.
- Confirmed MySQL server version `8.0.45`.
- Created local database `phpoa20026`.
- Imported `F:\AI\projects\testJava\OA\oa2026.sql` into `phpoa20026`.
- Confirmed imported table count is 121.
- Confirmed key table counts:
  - `sys_user`: 121
  - `sys_org`: 55
  - `sys_position`: 79
  - `sys_role`: 32
  - `sys_resource`: 272
  - `sys_relation`: 3894
  - `act_ru_task`: 77
  - `act_hi_procinst`: 2915
- Confirmed Redis `PING` with authentication returns `PONG`.
- Confirmed ThinkPHP can read `sys_user` and write/read/delete a Redis cache probe.
- Started ThinkPHP dev server at `http://127.0.0.1:8000`.
- Ran HTTP smoke checks for captcha, organization tree, user page, task count/page, and process page.

### Modified Files

- `config/cache.php`
- `docs/tasks/runtime-verification-plan.md`
- `STATUS.md`
- `.env` local only, ignored by Git and not committed

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed, ThinkPHP version `8.1.4`.
- `php think route:list`: passed.
- `php runtime/probe-runtime.php`: passed with DB and Redis probes.
- HTTP smoke checks returned `code=200` for:
  - `GET /auth/b/getPicCaptcha`
  - `GET /sys/org/tree`
  - `GET /sys/user/page?pageNo=1&pageSize=2`
  - `GET /biz/task/count?userId=1894269031672111106`
  - `GET /biz/task/page?userId=1894269031672111106&pageNo=1&pageSize=2`
  - `GET /biz/process/page?userId=1894269031672111106&pageNo=1&pageSize=2`

### Current Issues

- `startServer1.bat` was not found under `F:\project\socket\AI\testPhp\files\tools\mysql`, but MySQL and Redis were already running and reachable.
- Real login flow still needs an explicit user-provided test account/password or explicit approval to test an imported account.
- Online realtime production data sync remains deferred until the project is complete and accepted.

### Next Plan

- Commit and push the non-secret runtime configuration/documentation update.
- Keep `.env`, import wrapper, probe scripts, and logs local and ignored.
- Continue with login/API compatibility only after a safe test account is confirmed.

## 2026-05-29 - merge-agent - Auth Token Smoke Test

### Completed Content

- Used the user-provided `bizAdmin` test account for local auth smoke testing.
- Confirmed the account exists in `sys_user`, is enabled, belongs to tenant `1`, and is not deleted.
- Verified login returns a 64-character token.
- Verified `GET /auth/b/getLoginUser` returns the current user and auth context.
- Verified `GET /sys/userCenter/loginMenu` returns authorized top-level menus.
- Verified `GET /auth/b/doLogout` revokes the token.
- Verified reusing the same token after logout returns `401 unauthenticated`.

### Modified Files

- `docs/tasks/runtime-verification-plan.md`
- `STATUS.md`

### Test Results

- `POST /auth/b/doLogin`: `code=200`.
- `GET /auth/b/getLoginUser`: `code=200`, account `bizAdmin`, roles `1`, permissions `205`.
- `GET /sys/userCenter/loginMenu`: `code=200`, top-level menus `2`.
- `GET /auth/b/doLogout`: `code=200`.
- `GET /auth/b/getLoginUser` after logout with the same token: `code=401`.

### Current Issues

- The user-provided login password was used only in local shell memory for the smoke test and was not written to tracked files.
- Full frontend login compatibility still needs browser/frontend-agent verification.

### Next Plan

- Commit and push the non-sensitive auth smoke test record.
- Continue with frontend/API compatibility checks against the running local backend.

## 2026-05-29 - merge-agent - Frontend Token Route Compatibility

### Completed Content

- Ran frontend-style API smoke checks with a valid bearer token.
- Found token-only requests failed with `missing userId` on current-user-dependent user center and workflow routes.
- Confirmed the cause: controllers expected `auth_payload` from middleware, but the route groups did not attach `AuthMiddleware`.
- Added `AuthMiddleware` to:
  - `sys/userCenter`
  - `biz/task`
  - `biz/process`
- Kept the fix limited to route middleware wiring; no Controller or Service business logic was changed.
- Documented the public route file change in `docs/tasks/public-file-change-request.md`.

### Modified Files

- `route/app.php`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/runtime-verification-plan.md`
- `STATUS.md`

### Test Results

- Before fix:
  - `GET /sys/userCenter/loginOrgTree` with token: `400 missing userId`.
  - `GET /sys/userCenter/loginPositionInfo` with token: `400 missing userId`.
  - `GET /biz/task/count` with token: `400 missing userId`.
  - `GET /biz/task/page` with token: `400 missing userId`.
  - `GET /biz/process/page` with token: `400 missing userId`.
- After fix:
  - `GET /sys/userCenter/loginOrgTree` with token: `code=200`.
  - `GET /sys/userCenter/loginPositionInfo` with token: `code=200`.
  - `GET /biz/task/count` with token: `code=200`.
  - `GET /biz/task/page` with token: `code=200`.
  - `GET /biz/process/page` with token: `code=200`.
  - Protected route checks without token return `code=401 unauthenticated`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed.
- PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with Git line-ending normalization warnings only.

### Current Issues

- Full browser-based old frontend verification is still pending.
- Mutation workflow endpoints are still intentionally deferred.

### Next Plan

- Commit and push this route middleware compatibility fix.
- Continue frontend-agent verification for old frontend request/response assumptions.

## 2026-05-29 - merge-agent - Frontend Read-Only Selector Compatibility

### Completed Content

- Compared old Vue frontend system API modules with current ThinkPHP routes.
- Added missing read-only selector/list aliases for user, organization, position, role selector, and user-center list-by-id helpers.
- Kept the change limited to compatibility endpoints and did not implement write, import/export, upload, grant, or workflow mutation behavior.
- Removed password hashes from user directory responses.
- Documented the locked `route/app.php` change as a public file change request.

### Modified Files

- `PLANS.md`
- `STATUS.md`
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

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the new read-only selector/list routes.
- PHP lint for `app`, `config`, and `route`: passed.
- HTTP smoke checks with a valid bearer token returned `code=200` for:
  - `GET /sys/user/orgTreeSelector`
  - `GET /sys/user/positionSelector`
  - `GET /sys/user/roleSelector`
  - `GET /sys/user/userSelector`
  - `GET /sys/org/page`
  - `GET /sys/org/list`
  - `GET /sys/org/userSelector`
  - `GET /sys/position/list`
  - `GET /sys/position/orgTreeSelector`
  - `POST /sys/userCenter/getOrgListByIdList`
  - `POST /sys/userCenter/getRoleListByIdList`
  - `GET /sys/userCenter/getAvatarById`
- `GET /sys/user/page?pageSize=1` omits the `PASSWORD` field.

### Current Issues

- Full browser-based frontend verification is still pending.
- Write endpoints, grants, uploads, imports, exports, process config, user-center workbench/message, and workflow mutations remain deferred.

### Next Plan

- Run Composer/ThinkPHP/PHP lint checks.
- Run token-based HTTP smoke checks for the newly added endpoints.
- Commit and push if checks pass.

## 2026-05-29 - merge-agent - Protect System Directory Routes

### Completed Content

- Added `AuthMiddleware` to read-only system directory route groups:
  - `sys/org`
  - `sys/position`
  - `sys/user`
- Kept the change limited to route protection. No Controller, Service, Model, database, Java source, or write endpoint behavior was changed.
- Documented the locked `route/app.php` change in the public file change request log.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime token/no-token smoke checks passed:
  - `GET /sys/org/tree`: token `200`, no token `401`
  - `GET /sys/position/page?pageSize=1`: token `200`, no token `401`
  - `GET /sys/user/page?pageSize=1`: token `200`, no token `401`

### Current Issues

- Existing unauthenticated smoke checks for system directory routes must now send a bearer token.

### Next Plan

- Verify token requests return `200` and no-token requests return `401`.
- Commit and push if checks pass.

## 2026-05-29 - merge-agent - RBAC Role Read-Only Compatibility

### Completed Content

- Analyzed the old Vue `sys/role` API module and Java `SysRoleController`.
- Added read-only role service/controller adapters for role page, detail, existing grants, and selector trees.
- Registered protected `/sys/role/*` GET routes behind `AuthMiddleware`.
- Kept all role write and grant mutation endpoints deferred.
- Documented the public route change and read-only compatibility scope.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/auth/RoleService.php`
- `app/controller/sys/RoleController.php`
- `route/app.php`
- `docs/api/rbac-role-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/sys/role/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke checks with a valid bearer token returned `code=200` for:
  - `GET /sys/role/page`
  - `GET /sys/role/detail`
  - `GET /sys/role/ownResource`
  - `GET /sys/role/ownMobileMenu`
  - `GET /sys/role/ownPermission`
  - `GET /sys/role/ownUser`
  - `GET /sys/role/orgTreeSelector`
  - `GET /sys/role/resourceTreeSelector`
  - `GET /sys/role/mobileMenuTreeSelector`
  - `GET /sys/role/permissionTreeSelector`
  - `GET /sys/role/roleSelector`
  - `GET /sys/role/userSelector`
- `GET /sys/role/page` without a bearer token returned `code=401`.

### Current Issues

- `permissionTreeSelector` derives available API permission targets from existing `sys_relation` data until route-level permission metadata is modeled in ThinkPHP.
- Grant mutations still need a later dedicated implementation with validation and audit behavior.

### Next Plan

- Run Composer/ThinkPHP/PHP lint checks.
- Run token-based HTTP smoke checks for representative `/sys/role/*` routes.
- Commit and push if checks pass.

## 2026-05-29 - merge-agent - Auth SM2 Transport Compatibility

### Completed Content

- Analyzed old Vue login SM2 transport and Java decrypt-then-SM3 behavior.
- Added an optional pure PHP SM2 decrypt adapter for C1C3C2 ciphertext.
- Updated password verification flow so SM2-looking passwords are decrypted only when `AUTH_SM2_PRIVATE_KEY` is configured at runtime.
- Preserved plaintext local login support for smoke testing.
- Documented that private key material must stay out of Git and tracked docs.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/auth/Sm2Decryptor.php`
- `app/service/auth/PasswordService.php`
- `app/service/auth/AuthService.php`
- `docs/api/auth-sm2-compatibility.md`
- `docs/tasks/runtime-verification-plan.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed.
- PHP lint for `app`, `config`, and `route`: passed.
- Plaintext local login smoke still returns `code=200` and a 64-character token.
- SM2-looking ciphertext without `AUTH_SM2_PRIVATE_KEY` returns `code=400` with a clear runtime configuration message.

### Current Issues

- SM2 encrypted browser login still needs runtime testing with a local/deployment-only private key.
- The legacy Java key pair should be reviewed and likely rotated before production.

### Next Plan

- Run baseline checks and plaintext login smoke.
- Confirm no private key or password was committed.
- Commit and push if checks pass.

## 2026-05-29 - merge-agent - User Center Read-Only Compatibility

### Completed Content

- Analyzed Java `SysUserCenterController`, `SysUserServiceImpl`, `SysUserProcessConfigServiceImpl`, and `DevMessageServiceImpl`.
- Added read-only compatibility for login workbench, current user process config, login unread message page, and message detail lookup.
- Kept Java message detail mark-read behavior deferred so this phase remains read-only.
- Registered protected user-center routes and documented the locked route-file change.
- Documented old-frontend compatibility behavior and deferred write endpoints.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/user/UserDirectoryService.php`
- `app/controller/sys/UserCenterController.php`
- `route/app.php`
- `docs/api/user-center-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the new `/sys/userCenter/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned:
  - `GET /sys/userCenter/loginWorkbench`: `code=200`
  - `POST /sys/userCenter/process/config`: `code=200`, 9 default process config items for the current test user
  - `GET /sys/userCenter/loginUnreadMessagePage`: `code=200`
  - `GET /sys/userCenter/loginUnreadMessageDetail?id=missing`: `code=200`, `data=null`
  - `GET /sys/userCenter/loginWorkbench` without a token: `code=401`
- Secret scan found no committed database password, superadmin password, SM2 private key, or SM2 public key in tracked project paths.

### Current Issues

- The current test superadmin has no login message records in `dev_relation`, so an existing-message detail smoke still needs a user account with message relations.
- Message detail is intentionally read-only and does not mark messages as read yet.

### Next Plan

- Commit and push this read-only user-center compatibility slice.
- Continue with the next small compatibility slice after reviewing old frontend API usage, likely index message/workbench shortcuts or safe user-center write endpoints with explicit validation.

## 2026-05-29 - merge-agent - Index Read-Only Compatibility

### Completed Content

- Analyzed old Vue `indexApi.js` and Java `SysIndexController` / `SysIndexServiceImpl`.
- Added read-only homepage schedule list, message list/page/detail, visit log list, and operation log list endpoints.
- Reused the user-center message lookup path so message detail remains read-only and does not mark messages as read.
- Deferred schedule add/delete, all-message-mark-read, and SSE routes.
- Documented the route-file change and endpoint behavior.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/user/UserDirectoryService.php`
- `app/service/sys/IndexService.php`
- `app/controller/sys/IndexController.php`
- `route/app.php`
- `docs/api/index-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/sys/index/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /sys/index/schedule/list?scheduleDate=2026-05-29`
  - `GET /sys/index/message/list`
  - `GET /sys/index/message/page`
  - `GET /sys/index/message/detail?id=missing`
  - `GET /sys/index/visLog/list`
  - `GET /sys/index/opLog/list`
- `GET /sys/index/message/list` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, or SM2 public key in tracked project paths.

### Current Issues

- Current test superadmin message and schedule lists are empty in the imported SQL data.
- SSE and message mark-read behavior remain deferred because they are not read-only.

### Next Plan

- Commit and push this index read-only compatibility slice.
- Continue frontend compatibility by scanning remaining old API modules for read-only endpoints with high page-load impact.

## 2026-05-29 - merge-agent - System Resource Read-Only Compatibility

### Completed Content

- Analyzed old Vue module/menu/button API usage and Java `SysModuleController`, `SysMenuController`, and `SysButtonController`.
- Added read-only compatibility for module page/detail, menu page/tree/detail/selectors, and button page/detail.
- Registered protected `/sys/module/*`, `/sys/menu/*`, and `/sys/button/*` GET routes behind `AuthMiddleware`.
- Kept module/menu/button add, edit, delete, menu change-module, and grant mutations deferred.
- Documented the locked route-file change and endpoint behavior.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/sys/ResourceService.php`
- `app/controller/sys/ModuleController.php`
- `app/controller/sys/MenuController.php`
- `app/controller/sys/ButtonController.php`
- `route/app.php`
- `docs/api/resource-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected system resource read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /sys/module/page`
  - `GET /sys/module/detail`
  - `GET /sys/menu/page`
  - `GET /sys/menu/tree`
  - `GET /sys/menu/moduleSelector`
  - `GET /sys/menu/menuTreeSelector`
  - `GET /sys/button/page`
  - `GET /sys/button/detail`
- `GET /sys/module/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- System resource write endpoints remain deferred because they mutate menu/button permission state and need validation/audit rules.
- The old field management API is not implemented yet; no matching Java controller was found in the scanned system resource package.

### Next Plan

- Commit and push this resource read-only compatibility slice.
- Continue frontend compatibility scanning for the next high-impact read-only API group before considering safe write endpoints.

## 2026-05-29 - merge-agent - Mobile Resource Read-Only Compatibility

### Completed Content

- Analyzed old Vue mobile resource API modules and Java `MobileModuleController`, `MobileMenuController`, and `MobileButtonController`.
- Added read-only compatibility for mobile module page/detail, mobile menu tree/detail/selectors, and mobile button page/detail.
- Registered protected `/mobile/module/*`, `/mobile/menu/*`, and `/mobile/button/*` GET routes behind `AuthMiddleware`.
- Preserved Java mobile menu tree descending `SORT_CODE` behavior.
- Kept mobile resource add, edit, delete, menu change-module, and grant mutations deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/mobile/MobileResourceService.php`
- `app/controller/mobile/ModuleController.php`
- `app/controller/mobile/MenuController.php`
- `app/controller/mobile/ButtonController.php`
- `route/app.php`
- `docs/api/mobile-resource-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected mobile resource read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /mobile/module/page`
  - `GET /mobile/module/detail`
  - `GET /mobile/menu/tree`
  - `GET /mobile/menu/moduleSelector`
  - `GET /mobile/menu/menuTreeSelector`
  - `GET /mobile/button/page`
  - `GET /mobile/button/detail`
- `GET /mobile/module/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Mobile resource write endpoints remain deferred because they mutate mobile menu permission state and must coordinate with role grant behavior.
- Mobile grant result shaping may need a later dedicated endpoint if the old role grant UI needs the Java `mobileMenuTreeSelector` aggregate format.

### Next Plan

- Commit and push this mobile resource read-only compatibility slice.
- Continue scanning development/support API modules, likely dev config/dict/message/log read-only endpoints next.

## 2026-05-29 - merge-agent - Dev Dict Read-Only Compatibility

### Completed Content

- Analyzed old Vue dictionary API usage and Java `DevDictController` / `DevDictServiceImpl`.
- Added read-only dictionary page, list, tree, and detail endpoints.
- Registered protected `/dev/dict/*` GET routes behind `AuthMiddleware`.
- Shaped dictionary tree nodes with `name`, `dictLabel`, and `dictValue` so the old frontend `DICT_TYPE_TREE_DATA` cache can drive select options.
- Kept dictionary add, edit, delete, and translation cache mutation behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/DictService.php`
- `app/controller/dev/DictController.php`
- `route/app.php`
- `docs/api/dev-dict-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/dev/dict/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /dev/dict/tree`
  - `GET /dev/dict/page`
  - `GET /dev/dict/list`
  - `GET /dev/dict/detail`
- `GET /dev/dict/tree` returned nodes with `name` and `dictValue`.
- `GET /dev/dict/tree` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Dictionary mutation and translation cache refresh behavior remain deferred.
- Business dictionary tenant administration rules need a later write-endpoint plan before add/edit/delete are enabled.

### Next Plan

- Commit and push this dictionary read-only compatibility slice.
- Continue with dev config/log/message read-only endpoints, keeping sensitive config value exposure under review.

## 2026-05-29 - merge-agent - Dev Log Read-Only Compatibility

### Completed Content

- Analyzed old Vue log API usage and Java `DevLogController` / `DevLogServiceImpl`.
- Added read-only log page, detail, visit chart, and operation chart endpoints.
- Registered protected `/dev/log/*` GET routes behind `AuthMiddleware`.
- Kept log page responses lightweight by omitting large fields from page rows, matching Java behavior.
- Kept destructive `/dev/log/delete` behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/LogService.php`
- `app/controller/dev/LogController.php`
- `route/app.php`
- `docs/api/dev-log-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/dev/log/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /dev/log/page`
  - `GET /dev/log/detail`
  - `GET /dev/log/vis/lineChartData`
  - `GET /dev/log/vis/pieChartData`
  - `GET /dev/log/op/barChartData`
  - `GET /dev/log/op/pieChartData`
- Page rows omit large log fields while detail returns the full row.
- `GET /dev/log/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Log delete/clear is intentionally not implemented.
- Log detail can expose historical request/response payloads to authorized users, so it must stay behind authenticated admin routes.

### Next Plan

- Commit and push this log read-only compatibility slice.
- Continue with dev message read-only endpoints or carefully scoped config reads after reviewing sensitive value exposure.

## 2026-05-29 - merge-agent - Dev Message Read-Only Compatibility

### Completed Content

- Analyzed Java `DevMessageController` / `DevMessageServiceImpl` and the `dev_message` / `dev_relation` tables.
- Added read-only station-message page and detail compatibility endpoints.
- Registered protected `/dev/message/*` GET routes behind `AuthMiddleware`.
- Added receiver read-status shaping through `receiveInfoList` without mutating `dev_relation`.
- Kept message send, delete, SSE push, and Java detail read-state update behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/MessageService.php`
- `app/controller/dev/MessageController.php`
- `route/app.php`
- `docs/api/dev-message-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\dev\MessageService.php`: passed.
- `php -l app\controller\dev\MessageController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/dev/message/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /dev/message/page`
  - `GET /dev/message/detail`
- `GET /dev/message/detail` returned `receiveInfoList` when a message row existed.
- `GET /dev/message/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Message send/delete remain deferred because they mutate `dev_message` and `dev_relation`.
- Java detail marks unread messages as read and sends SSE notifications; this PHP slice intentionally stays read-only and does not reproduce that side effect yet.

### Next Plan

- Commit and push this message read-only compatibility slice.
- Continue with carefully scoped development support APIs, likely config reads after reviewing sensitive value exposure.

## 2026-05-29 - merge-agent - Dev Config Safe Read-Only Compatibility

### Completed Content

- Analyzed Java `DevConfigController` / `DevConfigServiceImpl`, frontend `configApi.js`, login-page usage, and `dev_config` SQL seed data.
- Added public read-only `/dev/config/sysBaseList` for login-page system base configuration.
- Added protected read-only `/dev/config/page`, `/dev/config/list`, and `/dev/config/detail` routes behind `AuthMiddleware`.
- Masked sensitive config values when `configKey` contains password, secret, token, private, access-key, or app-key markers.
- Kept config add, edit, delete, editBatch, and Redis config cache mutation behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/ConfigService.php`
- `app/controller/dev/ConfigController.php`
- `route/app.php`
- `docs/api/dev-config-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\dev\ConfigService.php`: passed.
- `php -l app\controller\dev\ConfigController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the public `/dev/config/sysBaseList` route plus protected `/dev/config/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke returned `code=200` for public `GET /dev/config/sysBaseList` without a token.
- `GET /dev/config/sysBaseList` excluded `SNOWY_SYS_DEFAULT_PASSWORD`.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /dev/config/page`
  - `GET /dev/config/list`
  - `GET /dev/config/detail`
- Sensitive config rows returned masked `configValue`.
- Protected `GET /dev/config/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Config writes remain deferred because they need permission, audit, validation, and "keep existing secret" semantics.
- Full-value secret reads are intentionally not implemented; later write endpoints should avoid requiring the frontend to round-trip secret values.

### Next Plan

- Commit and push this config read-only compatibility slice.
- Continue scanning the old frontend for the next safe read-only API group before enabling any write endpoint.

## 2026-05-29 - merge-agent - Dev File Metadata Read-Only Compatibility

### Completed Content

- Analyzed Java `DevFileController` / `DevFileServiceImpl`, frontend `fileApi.js`, file management page usage, and the `dev_file` table from `oa2026.sql`.
- Added protected read-only file metadata page, list, and detail endpoints.
- Registered protected `/dev/file/*` GET routes behind `AuthMiddleware`.
- Kept file upload, delete, and actual file download streaming behavior deferred.
- Adjusted `/dev/file/list` to return at most 200 lightweight metadata rows without thumbnail payloads after smoke testing found the full list could trigger a 500 response due to large base64 thumbnail data.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/FileService.php`
- `app/controller/dev/FileController.php`
- `route/app.php`
- `docs/api/dev-file-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\dev\FileService.php`: passed.
- `php -l app\controller\dev\FileController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/dev/file/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /dev/file/page`
  - `GET /dev/file/list`
  - `GET /dev/file/detail`
- `/dev/file/page` returns thumbnail metadata for paginated table compatibility.
- `/dev/file/list` returns lightweight metadata without thumbnail payloads.
- `GET /dev/file/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- File upload, delete, and download streaming remain deferred because they need a storage root, cloud credential, validation, permission, audit, and safe path plan.
- Existing `DOWNLOAD_PATH` values in imported data may point at the old Java backend domain; a later frontend/runtime compatibility step should decide whether to rewrite them at response time or migrate values.

### Next Plan

- Commit and push this file metadata read-only compatibility slice.
- Continue with another safe read-only support module, likely email/SMS metadata pages, before planning write endpoints.

## 2026-05-29 - merge-agent - Dev Email And Sms Read-Only Compatibility

### Completed Content

- Analyzed Java `DevEmailController` / `DevEmailServiceImpl`, Java `DevSmsController` / `DevSmsServiceImpl`, frontend `emailApi.js` / `smsApi.js`, and the `dev_email` / `dev_sms` tables from `oa2026.sql`.
- Added protected read-only email and SMS record page/detail endpoints.
- Registered protected `/dev/email/*` and `/dev/sms/*` GET routes behind `AuthMiddleware`.
- Kept email/SMS send and delete behavior deferred because those operations call external providers or mutate historical send records.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/EmailService.php`
- `app/service/dev/SmsService.php`
- `app/controller/dev/EmailController.php`
- `app/controller/dev/SmsController.php`
- `route/app.php`
- `docs/api/dev-email-sms-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\dev\EmailService.php`: passed.
- `php -l app\service\dev\SmsService.php`: passed.
- `php -l app\controller\dev\EmailController.php`: passed.
- `php -l app\controller\dev\SmsController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/dev/email/*` and `/dev/sms/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /dev/email/page`
  - `GET /dev/email/detail`
  - `GET /dev/sms/page`
  - `GET /dev/sms/detail`
- Protected `GET /dev/email/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Email/SMS send endpoints require provider credential handling, validation, rate limiting, permission checks, and audit logging before they can be safely enabled.
- Delete endpoints remain deferred because they mutate historical send records.

### Next Plan

- Commit and push this email/SMS read-only compatibility slice.
- Continue with another safe read-only support module before planning write endpoints.

## 2026-05-29 - merge-agent - Dev Job Read-Only Compatibility

### Completed Content

- Analyzed Java `DevJobController` / `DevJobServiceImpl`, frontend `jobApi.js`, job task classes, and the `dev_job` table from `oa2026.sql`.
- Added protected read-only scheduled-job page, list, detail, and action-class lookup endpoints.
- Registered protected `/dev/job/*` GET routes behind `AuthMiddleware`.
- Kept job add, edit, delete, stop, run, and run-now behavior deferred because those operations mutate scheduler/database state or execute task classes.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/JobService.php`
- `app/controller/dev/JobController.php`
- `route/app.php`
- `docs/api/dev-job-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\dev\JobService.php`: passed.
- `php -l app\controller\dev\JobController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/dev/job/*` read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /dev/job/page`
  - `GET /dev/job/list`
  - `GET /dev/job/detail`
  - `GET /dev/job/getActionClass`
- Protected `GET /dev/job/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Java job action classes cannot run inside ThinkPHP; a later scheduler design must replace Java `CommonTimerTaskRunner` classes with explicit PHP jobs or external orchestration.
- `getActionClass` currently returns distinct stored active `ACTION_CLASS` values rather than scanning executable PHP job classes.

### Next Plan

- Commit and push this job read-only compatibility slice.
- Continue with another safe read-only support module before planning scheduler or write endpoints.

## 2026-05-29 - merge-agent - Sys Config Read-Only Compatibility

### Completed Content

- Analyzed Java `SysConfigController` / `SysConfigServiceImpl`, frontend `sysConfigApi.js`, login-flow usage, process config page usage, and the `sys_config` table from `oa2026.sql`.
- Added protected read-only `/sys/sysConfig/detail` compatibility endpoint.
- Decoded `CONFIG_JSON` into the old frontend's expected `processConfigMap` shape.
- Kept system config edit and generate-default behavior deferred because they mutate `sys_config` and tenant cache.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/sys/SysConfigService.php`
- `app/controller/sys/SysConfigController.php`
- `route/app.php`
- `docs/api/sys-config-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\sys\SysConfigService.php`: passed.
- `php -l app\controller\sys\SysConfigController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/sys/sysConfig/detail` read-only route.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for `GET /sys/sysConfig/detail`.
- Runtime HTTP smoke confirmed `processConfigMap` contains 11 process config keys.
- Protected `GET /sys/sysConfig/detail` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Missing or invalid config returns an in-memory default object and does not generate a database row.
- System config writes need workflow process validation and cache invalidation rules before they are enabled.

### Next Plan

- Commit and push this sys config read-only compatibility slice.
- Continue with `/dev/monitor/serverInfo` read-only compatibility, using explorer findings, while keeping `networkInfo` deferred.

## 2026-05-29 - merge-agent - Dev Monitor Server Info Read-Only Compatibility

### Completed Content

- Used multi-agent explorer output to confirm the safe monitor scope before implementation.
- Analyzed Java `DevMonitorController`, `DevMonitorServiceImpl`, `DevMonitorServerResult`, and frontend `monitorApi.js`.
- Added protected read-only `/dev/monitor/serverInfo` compatibility route.
- Returned Java monitor group keys for CPU, memory, storage, server, and JVM-shaped runtime data.
- Used only safe PHP built-ins and left `/dev/monitor/networkInfo` deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/dev/MonitorService.php`
- `app/controller/dev/MonitorController.php`
- `route/app.php`
- `docs/api/dev-monitor-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\dev\MonitorService.php`: passed.
- `php -l app\controller\dev\MonitorController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected `/dev/monitor/serverInfo` route.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for `GET /dev/monitor/serverInfo`.
- Runtime HTTP smoke confirmed monitor payload includes `devMonitorCpuInfo` and `devMonitorMemoryInfo`.
- Protected `GET /dev/monitor/serverInfo` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- CPU usage, physical core count, JVM start time, and JVM run time are safe placeholders because PHP cannot provide the Java OSHI/JVM metrics without extensions or system commands.
- `/dev/monitor/networkInfo` remains deferred because the Java implementation uses platform commands and sampling delay.

### Next Plan

- Commit and push this monitor read-only compatibility slice.
- Continue with the next safe read-only compatibility group, likely generator metadata reads, using the previously completed explorer findings.

## 2026-05-29 - merge-agent - Gen Metadata Read-Only Compatibility

### Completed Content

- Used the earlier gen explorer findings to keep scope limited to safe metadata reads.
- Analyzed Java `GenBasicController`, `GenConfigController`, `GenBasicServiceImpl`, `GenConfigServiceImpl`, frontend generator API files, and `gen_basic` / `gen_config` SQL tables.
- Added protected read-only generator basic page/detail and mobile module selector endpoints.
- Added protected read-only generator config list/detail endpoints.
- Kept generator execution, code preview, table scanning, column scanning, and all write routes deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/gen/BasicService.php`
- `app/service/gen/ConfigService.php`
- `app/controller/gen/BasicController.php`
- `app/controller/gen/ConfigController.php`
- `route/app.php`
- `docs/api/gen-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\gen\BasicService.php`: passed.
- `php -l app\service\gen\ConfigService.php`: passed.
- `php -l app\controller\gen\BasicController.php`: passed.
- `php -l app\controller\gen\ConfigController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected generator read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /gen/basic/page`
  - `GET /gen/basic/detail`
  - `GET /gen/config/list`
  - `GET /gen/basic/mobileModuleSelector`
- `GET /gen/basic/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- The imported `gen_config` table currently has no rows for the existing `gen_basic` seed row, so runtime smoke covered `config/list` returning an empty list.
- `/gen/basic/tables` and `/gen/basic/tableColumns` remain deferred because they expose schema metadata and need an allow-list design.
- Generator preview and execution remain deferred because they can render or write generated code.

### Next Plan

- Commit and push this generator metadata read-only compatibility slice.
- Continue scanning old frontend calls for the next safe read-only group, while leaving generator write/execution routes disabled.

## 2026-05-29 - merge-agent - Auth Session Current Token Read-Only Compatibility

### Completed Content

- Analyzed old frontend `auth/monitorApi.js` and Java `AuthSessionController` / `AuthSessionServiceImpl`.
- Added protected read-only session monitor endpoints for analysis, B-side page, and C-side page.
- Returned a current-token B-side session page row from the authenticated bearer token and `sys_user`.
- Returned an empty C-side page because client auth is not implemented yet.
- Kept all session exit and token exit routes deferred.
- Did not add a global token index or change login token write behavior.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/auth/SessionMonitorService.php`
- `app/controller/auth/SessionController.php`
- `route/app.php`
- `docs/api/auth-session-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\auth\SessionMonitorService.php`: passed.
- `php -l app\controller\auth\SessionController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected session monitor routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /auth/session/analysis`
  - `GET /auth/session/b/page`
  - `GET /auth/session/c/page`
- Runtime smoke confirmed analysis `currentSessionTotalCount=1`, B page `total=1`, and C page `total=0`.
- `GET /auth/session/analysis` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- This slice cannot enumerate all online sessions because the current `TokenService` stores token payloads by hashed token key only and has no searchable index.
- `tokenSignList.tokenValue` is masked intentionally because token exit routes are not implemented and full token disclosure is unnecessary for this read-only slice.
- Full session management needs a later auth-agent token-index design.

### Next Plan

- Commit and push this auth session read-only compatibility slice.
- Continue scanning old frontend calls for another safe read-only group before planning any mutation endpoints.

## 2026-05-29 - merge-agent - Tenants Read-Only Compatibility

### Completed Content

- Analyzed old frontend `tenant/tenantsApi.js`, Java `TenantsController`, `TenantsServiceImpl`, and the `tenants` SQL table.
- Added protected read-only tenant page and detail endpoints.
- Preserved mixed-case physical column access for `Tenant_ID` and `Tenant_Name`.
- Returned Java-style camelCase tenant rows.
- Kept tenant add, edit, delete, default system data generation, and tenant cache/event mutation deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/tenant/TenantsService.php`
- `app/controller/tenant/TenantsController.php`
- `route/app.php`
- `docs/api/tenants-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\tenant\TenantsService.php`: passed.
- `php -l app\controller\tenant\TenantsController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected tenant read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /tenants/tenant/page`
  - `GET /tenants/tenant/detail`
- Runtime smoke confirmed tenant page `total=5` and detail lookup returned tenant id `0`.
- `GET /tenants/tenant/page` without a token returned `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Tenant add/edit/delete remain deferred because they mutate tenant data and can trigger default user, role, resource, and permission generation.
- Any later tenant write support must include system-tenant protection and safe-password verification.

### Next Plan

- Commit and push this tenant read-only compatibility slice.
- Continue with another safe read-only business or admin module after scanning old frontend calls.

## 2026-05-29 - merge-agent - Biz Product Read-Only Compatibility

### Completed Content

- Analyzed old frontend `bizProductApi.js`, Java `BizProductController` / `BizProductServiceImpl`, Java product entity/result classes, `biz_product`, and `product_relation`.
- Added protected read-only product master page, list, detail, and kit-product children endpoints.
- Returned lower-camel product rows compatible with Java JSON serialization while preserving the physical SQL columns, including lower-case `status`.
- Registered protected `/biz/bizproduct/*` read-only routes behind `AuthMiddleware`.
- Kept product add, edit, delete, reconciliation edit, status edit, product relation writes, and data-change events deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/ProductService.php`
- `app/controller/biz/ProductController.php`
- `route/app.php`
- `docs/api/biz-product-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\ProductService.php`: passed.
- `php -l app\controller\biz\ProductController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected product read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizproduct/page`
  - `GET /biz/bizproduct/list`
  - `GET /biz/bizproduct/detail`
  - `POST /biz/bizproduct/children`
- Runtime smoke confirmed product page total `3322`, product list search returned `348` rows, and one kit product returned `4` child rows.
- `GET /biz/bizproduct/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Java applies a richer login-user data-scope fallback than the current token payload. This slice applies tenant filtering and token data-scope org ids when present, but does not force the Java `CREATE_USER = loginId` fallback yet.
- Product write endpoints need validation, permission, kit relation writes, audit, and data-change event behavior before they can be enabled.

### Next Plan

- Commit and push this product read-only compatibility slice.
- Continue with another foundational read-only business master-data module, likely customer or supplier, before enabling any product write endpoint.

## 2026-05-29 - merge-agent - Biz Supplier Read-Only Compatibility

### Completed Content

- Analyzed old frontend `supplierApi.js`, Java `SupplierController` / `SupplierServiceImpl`, Java supplier entity/params/enums, and the `supplier` SQL table.
- Added protected read-only supplier page, list, enabled name lookup, and detail endpoints.
- Returned lower-camel supplier rows compatible with Java JSON serialization while preserving the physical SQL columns, including lower-case `org`.
- Registered protected `/biz/supplier/*` read-only routes behind `AuthMiddleware`.
- Kept supplier add, edit, delete, and write validation deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/SupplierService.php`
- `app/controller/biz/SupplierController.php`
- `route/app.php`
- `docs/api/biz-supplier-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\SupplierService.php`: passed.
- `php -l app\controller\biz\SupplierController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected supplier read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/supplier/page`
  - `GET /biz/supplier/list`
  - `GET /biz/supplier/list/query/name`
  - `GET /biz/supplier/detail`
- Runtime smoke confirmed supplier page total `186`, supplier list search returned `22` rows, and name lookup returned `1` row.
- `GET /biz/supplier/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.

### Current Issues

- Java applies a richer login-user data-scope fallback than the current token payload. This slice applies tenant filtering and token data-scope org ids when present, but does not force the Java `CREATE_USER = loginId` fallback yet.
- Supplier write endpoints need validation, permission, audit, and downstream purchase/settlement impact checks before they can be enabled.

### Next Plan

- Commit and push this supplier read-only compatibility slice.
- Revisit customer read-only migration after a safe SM4 encrypted-field strategy is documented, or continue with another non-encrypted master-data module such as warehouse/inventory read-only APIs.

## 2026-05-29 - merge-agent - Biz Warehouses Read-Only Compatibility

### Completed Content

- Analyzed old frontend `warehousesApi.js`, Java `WarehousesController` / `WarehousesServiceImpl`, Java warehouse entity and page params, and the `warehouses` SQL table.
- Added protected read-only warehouse page, list, and detail endpoints.
- Returned lower-camel warehouse rows compatible with Java JSON serialization while preserving physical SQL columns such as `SORT_CODE`, `USER`, and `ORG`.
- Resolved warehouse owner display name from `sys_user.NAME` and organization display name from `sys_org.NAME`.
- Registered protected `/biz/warehouses/*` read-only routes behind `AuthMiddleware`.
- Kept warehouse add, edit, delete, stock movement, and downstream inventory effects deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/WarehousesService.php`
- `app/controller/biz/WarehousesController.php`
- `route/app.php`
- `docs/api/biz-warehouses-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\WarehousesService.php`: passed.
- `php -l app\controller\biz\WarehousesController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected warehouse read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/warehouses/page`
  - `GET /biz/warehouses/list`
  - `GET /biz/warehouses/detail`
- Runtime smoke confirmed warehouse page total `4` for tenant `1`.
- `GET /biz/warehouses/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Java page reads apply richer login-user data scope through the warehouse owner user. This slice applies tenant filtering and token data-scope org ids when present, but does not force the Java `USER = loginId` fallback yet.
- Warehouse write endpoints need validation, permission checks, audit behavior, and inventory/purchase/sales impact checks before they can be enabled.

### Next Plan

- Commit and push this warehouse read-only compatibility slice.
- Continue with inventory read-only compatibility, because it depends on product and warehouse foundations and should remain separate from stock-changing write routes.

## 2026-05-29 - merge-agent - Biz Inventory Read-Only Compatibility

### Completed Content

- Analyzed old frontend `inventoryApi.js`, `views/biz/inventory/index.vue`, Java `InventoryController` / `InventoryServiceImpl`, Java `ProductInventory`, and the `inventory` SQL table.
- Added protected read-only inventory page, list, and detail endpoints.
- Implemented Java-compatible warehouse validation for page/list reads that require `warehousesId`.
- Joined enabled `biz_product` records to return product display fields used by the old inventory page.
- Registered protected `/biz/inventory/*` read-only routes behind `AuthMiddleware`.
- Kept inventory add, delete, stock in/out, batch stock movement, and data-change event behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/InventoryService.php`
- `app/controller/biz/InventoryController.php`
- `route/app.php`
- `docs/api/biz-inventory-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\InventoryService.php`: passed.
- `php -l app\controller\biz\InventoryController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected inventory read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/inventory/page`
  - `GET /biz/inventory/list`
  - `GET /biz/inventory/detail`
- Runtime smoke selected the first tenant `1` warehouse, confirmed inventory page total `261`, list rows `261`, and detail product display data.
- `GET /biz/inventory/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Java stock-changing operations publish warehouse inventory data-change events. The ThinkPHP replacement for those events still needs a later write-endpoint design.
- Inventory writes need permission checks, validation, audit behavior, optimistic-lock handling, and downstream purchase/sales workflow impact checks before they can be enabled.

### Next Plan

- Commit and push this inventory read-only compatibility slice.
- Continue with the next safe read-only business module after scanning frontend usage, while leaving customer reads paused until the SM4 encrypted-field strategy is documented.

## 2026-05-29 - merge-agent - Biz Delivery Record Read-Only Compatibility

### Completed Content

- Analyzed old frontend `deliveryRecordApi.js`, product inventory history view, inventory export view, Java `DeliveryRecordController` / `DeliveryRecordServiceImpl`, Java delivery record params/entity, and the `delivery_record` SQL table.
- Added protected read-only warehouse delivery-record page, export-other-company-records list, and detail compatibility endpoints.
- Enriched delivery records with `warehousesName`, `productName`, and `operatorName` display fields.
- Supported frontend `completionTime` range and Java-style `deliveryStartTime` / `deliveryEndTime` filters for export reads.
- Registered protected `/biz/warehouses/delivery/*` read-only routes behind `AuthMiddleware`.
- Kept delivery record add, inventory stock changes, batch stock movement, and data-change event behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/DeliveryRecordService.php`
- `app/controller/biz/DeliveryRecordController.php`
- `route/app.php`
- `docs/api/biz-delivery-record-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\DeliveryRecordService.php`: passed.
- `php -l app\controller\biz\DeliveryRecordController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected delivery-record read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/warehouses/delivery/page`
  - `GET /biz/warehouses/delivery/detail`
  - `GET /biz/warehouses/delivery/exportOtherCompanyRecordsList`
- Runtime smoke confirmed delivery page total `2582` and detail product display data.
- Export smoke returned `code=200`; the sampled warehouse/product-org combination currently returned `0` rows, which is valid for the read-only query shape.
- `GET /biz/warehouses/delivery/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Java delivery record `add` mutates inventory and publishes data-change events, so it needs a later write-endpoint design with permission, audit, optimistic locking, and stock consistency checks.
- Java controller does not expose a detail mapping in the analyzed source, but the old frontend API wrapper includes `deliveryRecordDetail`; this slice adds it as read-only compatibility.

### Next Plan

- Commit and push this delivery-record read-only compatibility slice.
- Continue scanning business frontend calls for another safe read-only module, with customer reads still deferred until the SM4 encrypted-field strategy is documented.

## 2026-05-29 - merge-agent - Biz Purchase Order Read-Only Compatibility

### Completed Content

- Analyzed old frontend `bizPurchaseOrderApi.js`, Java `BizPurchaseOrderController` / `BizPurchaseOrderServiceImpl`, Java purchase-order query/id/detail params, purchase-order item entity, and the SQL tables for purchase orders, order items, products, organizations, and expenditure records.
- Added protected read-only purchase-order page, list, detail-list, and detail compatibility endpoints.
- Decoded supplier display data from `EXT_JSON.supplier` and supported supplier-name filtering with JSON validity guards.
- Enriched purchase-order items with product display fields from `biz_product`.
- Returned Java-compatible detail wrapper data: `bizPurchaseOrder`, `bizPurchaseOrderItemList`, and `bizExpenditureRecordList`.
- Registered protected `/biz/bizpurchaseorder/*` read-only routes behind `AuthMiddleware`.
- Kept purchase-order add, edit, audit edit, delete, cancel, warehouse add, and warehouse one-add behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/PurchaseOrderService.php`
- `app/controller/biz/PurchaseOrderController.php`
- `route/app.php`
- `docs/api/biz-purchase-order-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\PurchaseOrderService.php`: passed.
- `php -l app\controller\biz\PurchaseOrderController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected purchase-order read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizpurchaseorder/page`
  - `GET /biz/bizpurchaseorder/list`
  - `GET /biz/bizpurchaseorder/detail/list`
  - `GET /biz/bizpurchaseorder/detail`
- Runtime smoke confirmed purchase-order page total `417`, detail-list count `1`, detail item count `1`, and related goods expenditure count `1` for the sampled order.
- Supplier-name JSON filter smoke returned `code=200` and `61` rows for the sampled keyword.
- `GET /biz/bizpurchaseorder/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Java purchase-order write flows affect workflow/audit state, expenditure records, warehouse stock-in, inventory quantities, and optimistic-lock versions. Those routes need a later write-endpoint design before enabling them.
- Customer-related reads remain deferred until the SM4 encrypted-field strategy is documented.

### Next Plan

- Commit and push this purchase-order read-only compatibility slice.
- Continue scanning business frontend calls for another safe read-only module, likely settlement-account or sale-project reads depending on encrypted-field impact.

## 2026-05-29 - merge-agent - Biz Settlement Account Read-Only Compatibility

### Completed Content

- Analyzed old frontend `settlementAccountApi.js`, Java `SettlementAccountController` / `SettlementAccountServiceImpl`, Java settlement-account page/query params, entity, and the `settlement_account` SQL table.
- Added protected read-only settlement-account page, enabled-list, detail, and queryName compatibility endpoints.
- Preserved SQL lower-case `org` field and enriched rows with `orgName` from `sys_org`.
- Supported Java/old-frontend filters for account name, account number, account status, org id, search key, sorting, and pagination.
- Registered protected `/biz/settlementaccount/*` read-only routes behind `AuthMiddleware`.
- Kept account add, edit, delete, status change, expense correction, income correction, and transfer behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/SettlementAccountService.php`
- `app/controller/biz/SettlementAccountController.php`
- `route/app.php`
- `docs/api/biz-settlement-account-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\SettlementAccountService.php`: passed.
- `php -l app\controller\biz\SettlementAccountController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected settlement-account read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/settlementaccount/page`
  - `GET /biz/settlementaccount/list`
  - `GET /biz/settlementaccount/detail`
  - `GET /biz/settlementaccount/queryName`
- Runtime smoke confirmed settlement-account page total `33`, enabled-list count `32`, detail name present, queryName present, and account-name filtered total `8`.
- `GET /biz/settlementaccount/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Settlement-account writes affect balances and related statement/payment/expenditure records. Those routes need a later write-endpoint design with transaction boundaries, optimistic locking, and audit behavior.
- Customer-related reads remain deferred until the SM4 encrypted-field strategy is documented.

### Next Plan

- Commit and push this settlement-account read-only compatibility slice.
- Continue scanning business frontend calls for another safe read-only module.

## 2026-05-29 - merge-agent - Biz Payment Record Read-Only Compatibility

### Completed Content

- Analyzed old frontend `bizPaymentRecordApi.js`, Java `BizPaymentRecordController` / `BizPaymentRecordServiceImpl`, Java payment-record page/query params, entity, and the `biz_payment_record` SQL table.
- Added protected read-only payment-record page, listdetails, list, and detail compatibility endpoints.
- Enriched payment-record rows with settlement account name/number from `settlement_account` and `orgName` from `sys_org`.
- Supported Java/old-frontend filters for object id, object ids, target id, serial id, process id, settlement category, payer time, create time, amount, account name, org id, search key, sorting, and pagination.
- Registered protected `/biz/bizpaymentrecord/*` read-only routes behind `AuthMiddleware`.
- Kept payment-record edit and account-switch behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/PaymentRecordService.php`
- `app/controller/biz/PaymentRecordController.php`
- `route/app.php`
- `docs/api/biz-payment-record-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\PaymentRecordService.php`: passed.
- `php -l app\controller\biz\PaymentRecordController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected payment-record read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizpaymentrecord/page`
  - `GET /biz/bizpaymentrecord/listdetails`
  - `GET /biz/bizpaymentrecord/list`
  - `GET /biz/bizpaymentrecord/detail`
- Runtime smoke confirmed payment-record page total `535`, sampled listdetails count `44`, sampled list count `44`, detail account-name enrichment, and account-name filtered total `101`.
- `GET /biz/bizpaymentrecord/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Payment-record write flows affect settlement-account balances and settlement statements. Those routes need a later write-endpoint design with transactions, optimistic locking, audit behavior, and data-change events.
- Customer-related reads remain deferred until the SM4 encrypted-field strategy is documented.

### Next Plan

- Commit and push this payment-record read-only compatibility slice.
- Continue with the next safe read-only settlement/business module.

## 2026-05-29 - merge-agent - Biz Expenditure Record Read-Only Compatibility

### Completed Content

- Analyzed old frontend `bizExpenditureRecordApi.js`, Java `BizExpenditureRecordController` / `BizExpenditureRecordServiceImpl`, Java expenditure-record page/query params, entity, and the `biz_expenditure_record` SQL table.
- Added protected read-only expenditure-record page, listDetails, list, and detail compatibility endpoints.
- Enriched expenditure-record rows with settlement account name/number from `settlement_account` and `orgName` from `sys_org`.
- Supported Java/old-frontend filters for object id, object ids, target id, serial id, process id, settlement category, payer, bank, remark, payer time, create time, amount, account name, org id, search key, sorting, and pagination.
- Registered protected `/biz/bizexpenditurerecord/*` read-only routes behind `AuthMiddleware`.
- Kept expenditure-record add, edit, delete, and account-switch behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/ExpenditureRecordService.php`
- `app/controller/biz/ExpenditureRecordController.php`
- `route/app.php`
- `docs/api/biz-expenditure-record-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\ExpenditureRecordService.php`: passed.
- `php -l app\controller\biz\ExpenditureRecordController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected expenditure-record read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizexpenditurerecord/page`
  - `GET /biz/bizexpenditurerecord/listDetails`
  - `GET /biz/bizexpenditurerecord/list`
  - `GET /biz/bizexpenditurerecord/detail`
- Runtime smoke confirmed expenditure-record page total `1535`, sampled listDetails count `207`, sampled list count `207`, detail account-name enrichment, and account-name filtered total `231`.
- `GET /biz/bizexpenditurerecord/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Expenditure-record write flows affect settlement-account balances and settlement statements. Those routes need a later write-endpoint design with transactions, optimistic locking, audit behavior, and data-change events.
- Customer-related reads remain deferred until the SM4 encrypted-field strategy is documented.

### Next Plan

- Commit and push this expenditure-record read-only compatibility slice.
- Continue with the next safe read-only settlement/business module.

## 2026-05-29 - merge-agent - Biz Collection Receipt Read-Only Compatibility

### Completed Content

- Analyzed old frontend `bizCollectionReceiptApi.js`, Java `BizCollectionReceiptController` / `BizCollectionReceiptServiceImpl`, Java collection-receipt page params, entity, mapper, and the `biz_collection_receipt` SQL table.
- Added protected read-only collection-receipt page, list, and detail compatibility endpoints.
- Enriched collection-receipt rows with linked payment-record payer time, settlement category, payer/bank fields, settlement account name/number, and organization name.
- Supported Java/old-frontend filters for play status, remark, account name, search key, sorting, pagination, payment record id, and tenant id.
- Registered protected `/biz/bizcollectionreceipt/*` read-only routes behind `AuthMiddleware`.
- Kept collection-receipt batch expenditure, mark success, add, edit, and delete behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/CollectionReceiptService.php`
- `app/controller/biz/CollectionReceiptController.php`
- `route/app.php`
- `docs/api/biz-collection-receipt-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\CollectionReceiptService.php`: passed.
- `php -l app\controller\biz\CollectionReceiptController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected collection-receipt read-only routes.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizcollectionreceipt/page`
  - `GET /biz/bizcollectionreceipt/list`
  - `GET /biz/bizcollectionreceipt/detail`
- Runtime smoke confirmed collection-receipt page total `18`, `AlreadySettled` list count `16`, sampled detail account-name enrichment, and account-name filtered total `9`.
- `GET /biz/bizcollectionreceipt/page` without a token returned business `code=401`.

### Current Issues

- Collection-receipt mark-success and batch-expenditure flows mutate settlement state and expenditure records. Those routes need a later transaction design before implementation.
- Customer-related reads remain deferred until the SM4 encrypted-field strategy is documented.

### Next Plan

- Commit and push this collection-receipt read-only compatibility slice.
- Continue with the next safe read-only business module, likely debit-note read endpoints.

## 2026-05-29 - merge-agent - Biz Debit Note Read-Only Compatibility

### Completed Content

- Analyzed old frontend `bizDebitNoteApi.js`, Java `BizDebitNoteController` / `BizDebitNoteServiceImpl`, Java debit-note page params, entity, and the `biz_debit_note` SQL table.
- Added protected read-only debit-note page, list, and detail compatibility endpoints.
- Enriched debit-note rows with linked expenditure-record payer time, settlement category, payer/bank fields, settlement account name/number, and organization name.
- Supported Java/old-frontend filters for play status, create time range, remark, account name, category, search key, sorting, pagination, expenditure record id, org id, amount, and tenant id.
- Registered protected `/biz/bizdebitnote/*` read-only routes behind `AuthMiddleware`.
- Kept debit-note history add, mark success, batch repayment, add, edit, and delete behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/DebitNoteService.php`
- `app/controller/biz/DebitNoteController.php`
- `route/app.php`
- `docs/api/biz-debit-note-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\DebitNoteService.php`: passed.
- `php -l app\controller\biz\DebitNoteController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected debit-note read-only routes.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizdebitnote/page`
  - `GET /biz/bizdebitnote/list`
  - `GET /biz/bizdebitnote/detail`
- Runtime smoke confirmed debit-note page total `106`, `AlreadySettled` list count `84`, sampled detail organization/account enrichment, and account-name filtered total `2`.
- `GET /biz/bizdebitnote/page` without a token returned business `code=401`.
- PHP lint for `app`, `config`, and `route`: passed.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Debit-note history add, mark-success, and batch-repayment flows mutate settlement state, payment records, and settlement accounts. Those routes need a later transactional write design before implementation.
- Customer-related reads remain deferred until the SM4 encrypted-field strategy is documented.

### Next Plan

- Commit and push this debit-note read-only compatibility slice.
- Continue with the next safe read-only business module.

## 2026-05-29 - merge-agent - Biz File Relation Read-Only Compatibility

### Completed Content

- Analyzed old frontend `bizFileRelationApi.js`, Java `BizFileRelationController` / `BizFileRelationServiceImpl`, Java file-relation params, entity, mapper, `BizFile`, and the `biz_file_relation` / `dev_file` SQL tables.
- Added protected read-only file-relation page, list, and detail compatibility endpoints.
- Enriched file-relation rows with linked dev-file engine, bucket, name, suffix, size, object name, storage path, download path, thumbnail, and creator display fields.
- Supported Java/old-frontend filters for object id, target id, category, file name, creator, create time range, search key, sorting, pagination, suffix, and tenant id.
- Registered protected `/biz/bizfilerelation/*` read-only routes behind `AuthMiddleware`.
- Kept file-relation add, edit, delete, and project-case delete behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/FileRelationService.php`
- `app/controller/biz/FileRelationController.php`
- `route/app.php`
- `docs/api/biz-file-relation-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\FileRelationService.php`: passed.
- `php -l app\controller\biz\FileRelationController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected file-relation read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizfilerelation/page`
  - `GET /biz/bizfilerelation/list`
  - `GET /biz/bizfilerelation/detail`
- Runtime smoke confirmed file-relation page total `716`, sampled category `Process_reimbursement`, sampled list count `1`, sampled detail file name enrichment, and download-path enrichment.
- `GET /biz/bizfilerelation/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- File-relation write flows mutate attachment links and can affect process/project attachment views. Those routes need a later write-endpoint design before implementation.
- `dev_file` rows can contain large thumbnails; future frontend/API tuning may need a lightweight list mode if payload size becomes a problem.
- The local MySQL/Redis helper script exists at `F:\project\socket\AI\testPhp\files\startServer1.bat`; the originally provided mysql subdirectory did not contain the script.

### Next Plan

- Commit and push this file-relation read-only compatibility slice.
- Continue with the next safe read-only business module, likely sale-project or team-project attachment consumers.

## 2026-05-29 - merge-agent - Biz Team Project Read-Only Foundation

### Completed Content

- Analyzed old frontend `bizTeamProjectApi.js`, `bizTeamProjectUserApi.js`, team-project list/detail views, Java `BizTeamProjectController` / `BizTeamProjectServiceImpl`, Java `BizTeamProjectUserController` / `BizTeamProjectUserServiceImpl`, role-permission enum, and the `biz_team_project` / `biz_team_project_user` SQL tables.
- Added protected read-only project page and project detail compatibility endpoints.
- Added protected read-only team-project-user page, list, and detail compatibility endpoints.
- Preserved Java-style current-user membership filtering for project page/detail access.
- Enriched project and member rows with creator, owner, organization, avatar, role name, and permission-code fields needed by the old frontend detail screen.
- Kept project add, edit, delete, member add, member manage-add, member edit, and member delete flows deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/TeamProjectService.php`
- `app/controller/biz/TeamProjectController.php`
- `app/controller/biz/TeamProjectUserController.php`
- `route/app.php`
- `docs/api/biz-team-project-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\TeamProjectService.php`: passed.
- `php -l app\controller\biz\TeamProjectController.php`: passed.
- `php -l app\controller\biz\TeamProjectUserController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed after rerunning with approved escalation because the sandbox could not unlink `runtime\route_list.php`.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizteamproject/page`
  - `GET /biz/bizteamproject/detail`
  - `GET /biz/bizteamprojectuser/list`
  - `GET /biz/bizteamprojectuser/page`
  - `GET /biz/bizteamprojectuser/detail`
- Runtime smoke confirmed project total `1`, sampled project id `1903996479133360129`, current user role `LEADER`, member list count `27`, member page total `27`, and non-empty permission-code output.
- `GET /biz/bizteamproject/page` without a token returned business `code=401`.

### Current Issues

- Team project task category, task, comment, and attachment read APIs are still needed for a complete project detail page.
- Team project write flows mutate project membership and project state; those routes remain deferred for a later transactional write design.
- `php think route:list` may need elevated execution in this workspace when the sandbox blocks ThinkPHP route cache cleanup.

### Next Plan

- Commit and push this team-project read-only compatibility slice.
- Continue with team project task/category/comment read-only endpoints before implementing write flows.

## 2026-05-29 - merge-agent - Biz Team Project Task Read-Only Compatibility

### Completed Content

- Analyzed old frontend team-project task, task-category, project-comment, task-comment, and comment-reply API usage.
- Analyzed Java `BizTeamProjectTaskCategoryController` / service, `BizTeamProjectTaskController` / service, `BizTeamProjectCommentController` / service, `BizTeamProjectTaskCommentController` / service, and comment-reply service.
- Added protected read-only task-category page, list, and detail endpoints.
- Added protected read-only task page, list, and detail endpoints; task detail includes assigned task users.
- Added protected read-only project-comment page and list endpoints; list includes nested comment replies.
- Added protected read-only task-comment page, list, and detail endpoints.
- Added current-user project membership gating for project-scoped reads and direct task/comment id lookups.
- Kept all task, task-category, project-comment, comment-reply, and task-user write flows deferred.

### Modified Files

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

### Test Results

- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l app\controller\biz\TeamProjectTaskCategoryController.php`: passed.
- `php -l app\controller\biz\TeamProjectTaskController.php`: passed.
- `php -l app\controller\biz\TeamProjectCommentController.php`: passed.
- `php -l app\controller\biz\TeamProjectTaskCommentController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected team-project task/category/comment read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/bizteamprojecttaskcategory/list`
  - `GET /biz/bizteamprojecttaskcategory/page`
  - `GET /biz/bizteamprojecttaskcategory/detail`
  - `GET /biz/bizteamprojecttask/list`
  - `GET /biz/bizteamprojecttask/page`
  - `GET /biz/bizteamprojecttask/detail`
  - `GET /biz/bizteamprojectcomment/list`
  - `GET /biz/bizteamprojectcomment/page`
  - `GET /biz/bizteamprojecttaskcomment/list`
  - `GET /biz/bizteamprojecttaskcomment/page`
  - `GET /biz/bizteamprojecttaskcomment/detail`
- Runtime smoke confirmed project `1903996479133360129`, category count `1`, task count `4`, first task id `2033724343755141122`, task detail user count `2`, project-comment count `2`, task-comment count `10`, and nested project-comment reply array presence.
- `GET /biz/bizteamprojecttask/list` without a token returned business `code=401`.

### Current Issues

- Team project task/category/comment write routes remain deferred because they mutate category order, task state, task users, comments, replies, and data-change event side effects.
- Standalone project-comment-reply read routes were not added because the Java controller does not expose them; project-comment list embeds replies instead.
- Some frontend interactions on the task board still call write routes (`edit`, `user/edit`, `sort/edit`, `add`, `delete`) and need later transactional implementation.

### Next Plan

- Commit and push this team-project task read-only compatibility slice.
- Continue with the next safe read-only business module or begin a separate write-flow design for team project tasks after review.

## 2026-06-01 - merge-agent - Biz Return Order Read-Only Compatibility

### Completed Content

- Analyzed old frontend `returnOrderApi.js`, sale-project return-order consumers, Java `ReturnOrderController` / `ReturnOrderServiceImpl`, Java return-order params/entities, and the `return_order` / `return_order_item` SQL tables.
- Added protected read-only return-order page, query, and detail compatibility endpoints.
- Enriched return-order rows with project name, warehouse name, current handler name, and organization name.
- Added `productList` child rows for `query` and `detail`, including project-product and product display fields.
- Preserved Java-style data-scope shape: explicit org filter, token data-scope org ids when present, then current user fallback.
- Registered protected `/biz/returnorder/*` read-only routes behind `AuthMiddleware`.
- Kept return-order add/edit/delete/status, warehouse delivery, inventory stock, refund, and workflow mutation behavior deferred.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/biz/ReturnOrderService.php`
- `app/controller/biz/ReturnOrderController.php`
- `route/app.php`
- `docs/api/biz-return-order-readonly-compat.md`
- `docs/tasks/public-file-change-request.md`

### Test Results

- `php -l app\service\biz\ReturnOrderService.php`: passed.
- `php -l app\controller\biz\ReturnOrderController.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists the protected return-order read-only routes.
- PHP lint for `app`, `config`, and `route`: passed.
- Runtime HTTP smoke with a valid bearer token returned `code=200` for:
  - `GET /biz/returnorder/page`
  - `GET /biz/returnorder/query`
  - `GET /biz/returnorder/detail`
- Runtime smoke confirmed return-order page total `1`, sampled order id `2052251605190221825`, project id `2013520917029085185`, query count `1`, query `productList` count `1`, and detail `productList` count `1`.
- `GET /biz/returnorder/page` without a token returned business `code=401`.
- Secret scan found no committed database password, superadmin password, SM2 private key, SM2 public key, or temporary encoded smoke-test password in tracked project paths.
- `git diff --check`: passed with existing CRLF normalization warnings only.

### Current Issues

- Return-order write flows create warehouse delivery-in records, affect inventory stock, update settlement/refund state, and emit data-change events. Those routes need a later transactional write design before implementation.
- The current token payload does not always carry expanded Java data-scope org ids, so fallback behavior may be narrower than Java for users without populated `data_scope_org_ids`.
- Customer-related sale-project reads remain deferred until the SM4 encrypted-field strategy is documented.

### Next Plan

- Commit and push this return-order read-only compatibility slice.
- Continue with the next safe read-only business module, likely sale-project read endpoints after customer encryption strategy is handled, or another non-encrypted support module.

## 2026-06-01 - merge-agent - Progress Dashboard

### Completed Content

- Reviewed current project rules and repository status.
- Counted current ThinkPHP Models, Controllers, Services, API docs, database docs, and route entries.
- Counted Java original Controllers, frontend API files, and SQL table definitions as comparison baselines.
- Created a persistent progress dashboard for future real-time tracking.

### Modified Files

- `STATUS.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- Documentation-only update.
- `git status --short --branch`: checked before editing and was clean/synced.

### Current Issues

- Overall production-ready completion is estimated at about 45%; read-only API compatibility is further along than write/workflow/frontend completion.
- Business write flows, workflow side effects, frontend adaptation, deployment, and final online data sync remain the main work.

### Next Plan

- Keep updating `docs/tasks/refactor-progress-dashboard.md` after each completed slice.
- Generate an API gap map from remaining frontend API files before selecting the next read-only business endpoint.

## 2026-06-01 - merge-agent - Frontend Joint Test Workflow

### Completed Content

- Accepted the user requirement that frontend adaptation must proceed together with backend refactor work.
- Confirmed the original frontend exists at `F:\AI\projects\testJava\OA\snowy-admin-web` and remains read-only.
- Confirmed `F:\AI\projects\testJava\OA-ThinkPHP` does not yet contain the frontend source.
- Confirmed `F:\AI\projects\testJava\OA-frontend` currently contains the ThinkPHP worktree, not the imported Vue frontend.
- Documented the future backend plus frontend startup and smoke-test workflow.
- Updated the progress dashboard so frontend adaptation starts as a parallel track.

### Modified Files

- `STATUS.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/frontend-joint-test-workflow.md`

### Test Results

- Documentation-only update.
- `git status --short --branch`: checked before editing and was clean/synced.

### Current Issues

- The frontend has not been imported into the target repository yet, so browser testing against the adapted ThinkPHP project cannot start until frontend-agent performs the baseline import.
- The original frontend sends a legacy `token` header, while the ThinkPHP convention is `Authorization: Bearer <token>`.
- Browser login may need SM2 compatibility testing after frontend import.

### Next Plan

- Create an API gap map from the original frontend API files.
- Prepare a frontend-agent baseline import plan for `snowy-admin-web` without copying `node_modules`, `dist`, logs, or secrets.
- After the baseline import, start MySQL/Redis, ThinkPHP on port `82`, and Vue on port `83` for joint browser smoke tests.

## 2026-06-01 - frontend-agent - Frontend Baseline Import

### Completed Content

- Copied the original frontend from `F:\AI\projects\testJava\OA\snowy-admin-web` into `F:\AI\projects\testJava\OA-ThinkPHP\snowy-admin-web`.
- Kept the Java source frontend read-only and did not edit any file under `F:\AI\projects\testJava\OA`.
- Copied 908 frontend source/config/static files.
- Excluded IDE, dependency, build, coverage, log, and Vite timestamp artifacts.
- Verified the copied frontend includes `package.json`, Vite config, environment files, `public`, and `src`.
- Checked copied frontend environment keys without printing values.

### Modified Files

- `snowy-admin-web/**`
- `STATUS.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/frontend-joint-test-workflow.md`

### Test Results

- Target frontend file count: 908.
- Excluded directories/files were not present in the copied target.
- High-risk secret-marker scan found only frontend configuration form field names for `SECRET_KEY`; no committed credential values were printed or identified.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed.
- `git diff --cached --check`: passed after whitespace cleanup in the copied frontend files.
- Frontend dependency install and browser startup were not run in this import-only step.

### Current Issues

- `package-lock.json` exists in the copied frontend directory but is ignored by the original frontend `.gitignore`.
- The copied frontend still uses the original request/token behavior and must be adapted before full browser testing.
- The backend convention is `Authorization: Bearer <token>`, while the original frontend code uses a legacy token header.

### Next Plan

- Commit and push the frontend baseline import.
- Generate `docs/tasks/api-gap-map.md` from the copied frontend API files.
- Adapt request/token/menu behavior in small frontend-agent commits.
- Start MySQL/Redis, backend port `82`, and frontend port `83` for joint smoke testing after the first adaptation slice.

## 2026-06-01 - frontend-agent - Frontend Request Boundary Adaptation

### Completed Content

- Adapted the copied Vue frontend request boundary for ThinkPHP local joint testing.
- Switched browser calls to use a Vite proxy prefix instead of directly calling the backend URL from the browser.
- Removed the duplicated Axios `/api` base URL so Vite rewrites requests from `/api/...` to the backend route path correctly.
- Updated the frontend token convention to `Authorization: Bearer <token>` for normal requests, uploads, and SSE connections.
- Adapted the frontend first-menu selection helper to treat `children: []` as a leaf node.
- Moved SM2 public-key usage to `VITE_PUBLIC_KEY`; local development without a configured key now submits plaintext password values for the ThinkPHP compatibility path.
- Kept the original Java frontend source read-only and did not edit any file under `F:\AI\projects\testJava\OA`.

### Modified Files

- `snowy-admin-web/.env.development`
- `snowy-admin-web/.env.production`
- `snowy-admin-web/src/config/index.js`
- `snowy-admin-web/src/utils/smCrypto.js`
- `snowy-admin-web/src/components/XnUpload/index.vue`
- `snowy-admin-web/src/layout/components/message.vue`
- `snowy-admin-web/src/layout/components/panel-message/index.vue`
- `snowy-admin-web/src/utils/request.js`
- `snowy-admin-web/src/utils/routerUtil.js`
- `docs/tasks/frontend-adaptation-notes.md`
- `STATUS.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed.
- `npm ci --no-audit --no-fund`: passed.
- `npm run build`: passed with upstream warnings only.
- `git diff --check`: passed with CRLF conversion warnings only.
- Local MySQL and Redis startup: passed using the user-specified helper.
- ThinkPHP dev server on port `82`: started.
- Vue dev server on port `83`: started.
- Browser smoke: fresh-origin login succeeded and reached `/sys/org`.
- Browser smoke: `/sys/org` and `/sys/user` loaded with menus, tables, and pagination.

### Current Issues

- The frontend API gap map is still pending.
- Some frontend pages will still hit routes that are not implemented or are intentionally read-only.
- Org/user table rows show partially blank fields and missing dictionary labels, so response-field and dictionary compatibility still need a follow-up slice.
- Frontend SSE calls `/dev/message/createSseConnect`, which is not yet implemented in ThinkPHP and currently returns 404.

### Next Plan

- Generate `docs/tasks/api-gap-map.md` from the copied frontend API files.
- Add a small compatibility plan for org/user field names and dictionary labels.
- Add or defer a safe SSE compatibility route after reviewing the Java implementation.
- Commit and push this adaptation slice after the joint smoke result is recorded.

## 2026-06-01 - frontend-agent - Frontend API Gap Map

### Completed Content

- Generated the frontend API gap map from the copied Vue API wrappers under `snowy-admin-web/src/api`.
- Compared static frontend endpoint references with the current ThinkPHP route table.
- Classified gaps into already routed endpoints, missing read/selector/report candidates, and deferred write/side-effect candidates.
- Updated the progress dashboard with frontend endpoint metrics and next execution order.
- Kept this slice documentation-only; no Controller, Service, Model, route, database, or Java source file was modified.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- Static scan source: 76 frontend API wrapper files.
- Static scan result: 545 unique frontend endpoints.
- Current route baseline: 179 ThinkPHP route entries.
- Matched routes: 173 frontend endpoint paths.
- Missing read/selector/report candidates: 165.
- Deferred write/side-effect candidates: 207.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- The old frontend still has missing route consumers, especially sale-project, customer, biz org/user/position selectors, workflow query/runtime detail, upload, SSE, and report endpoints.
- Several existing visible pages load but still need response-field and dictionary-label compatibility cleanup.
- Write-heavy routes remain intentionally deferred.

### Next Plan

- Run a small frontend-agent/api-agent follow-up for visible org/user field display and dictionary labels.
- Review Java SSE behavior before deciding whether to add `/dev/message/createSseConnect`.
- Start api-agent read-only slices for `biz/saleproject` and `biz/customer`.
- Keep production online realtime data sync deferred until project completion and user confirmation.

## 2026-06-01 - user-agent - Sys Org/User Display Field Compatibility

### Completed Content

- Added camelCase display aliases to existing read-only system organization, user, and position service responses.
- Preserved uppercase SQL fields in responses for current backend compatibility.
- Added batched `orgName` and `positionName` enrichment to user rows and selectors to avoid N+1 lookups.
- Added `genderName` fallback from `dev_dict` where available.
- Added pagination aliases `current`, `size`, and `pages` on org/user/position page responses for copied frontend table compatibility.
- Documented the compatibility contract in `docs/api/sys-user-org-display-compat.md`.
- Kept this slice read-only; no route, Controller, database, Java source, or write endpoint was changed.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/service/user/OrgService.php`
- `app/service/user/UserDirectoryService.php`
- `app/service/user/PositionService.php`
- `docs/api/sys-user-org-display-compat.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\service\user\OrgService.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l app\service\user\PositionService.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- `npm run build`: passed after rerunning with filesystem permission escalation; warnings are upstream bundle size, Browserslist age, CSS comment syntax, and `eval` in `docx-templates`.
- Direct backend API probes with a fresh token passed:
  - `/sys/org/page` returns `id`, `parentId`, `name`, `category`, and `sortCode`.
  - `/sys/org/tree` returns normalized tree nodes with `id`, `parentId`, `name`, `category`, `sortCode`, and `children`.
  - `/sys/user/page` returns `id`, `name`, `orgName`, `positionName`, `userStatus`, and `sortCode`.
- Browser smoke reached `/sys/org` and `/sys/user`; the remaining visible issue is still the known missing SSE route `/dev/message/createSseConnect`.

### Current Issues

- The browser session may still show empty table state until a fresh reload/login clears stale page state, but direct API probes confirm the response fields are now present.
- `/dev/message/createSseConnect` remains missing and still logs a frontend 404.
- Write actions on org/user/position pages remain intentionally deferred.

### Next Plan

- Review Java message/SSE behavior and decide whether to add a safe `/dev/message/createSseConnect` compatibility route.
- Start small api-agent read-only slices for `biz/saleproject` and `biz/customer`.
- Keep final online realtime data sync deferred until the full system is complete and the user confirms the plan.

## 2026-06-01 - api-agent/frontend-agent - Dev Message SSE Compatibility Review

### Completed Content

- Reviewed the Java SSE route behind `/dev/message/createSseConnect` from the read-only Java OA source.
- Confirmed the copied Vue frontend opens the same route from the layout message components with a bearer-token EventSource header.
- Documented the compatible first-slice behavior for ThinkPHP: authenticated `text/event-stream`, initial `code = 0` client id event, and lightweight heartbeat.
- Added a public-file change request because implementing the route requires editing locked file `route/app.php`.
- Kept this slice planning-only; no route, Controller, Service, frontend, database, Java source, Composer, or `.env` file was changed.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/api/dev-message-sse-compat-plan.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; SSE route remains intentionally absent because this slice did not edit `route/app.php`.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- `/dev/message/createSseConnect` remains unimplemented until the public-file route request is approved or handled by merge-agent.
- Full realtime message/task push behavior remains deferred because it crosses message mutation, workflow, and later Redis/pub-sub design.

### Next Plan

- After approval, add the minimal SSE route/controller behavior and browser-smoke the layout console.
- In parallel-safe order, continue api-agent read-only slices for `biz/saleproject` and `biz/customer`.
- Keep final online realtime data sync deferred until the full system is complete and the user confirms the sync plan.

## 2026-06-01 - api-agent - Minimal Dev Message SSE Compatibility

### Completed Content

- Added the protected ThinkPHP route `GET /dev/message/createSseConnect` under the existing `dev/message` route group.
- Added `MessageController::createSseConnect` and delegated the response generation to a new `MessageSseService`.
- Added a minimal Java-compatible SSE response with:
  - `Content-Type: text/event-stream`
  - initial `code = 0` client id event
  - `code = 200` `FlushMessageNotice` compatibility event
  - heartbeat comment
- Kept the response short-lived to avoid blocking the local `php think run` development server.
- Updated the SSE compatibility doc and marked the public-file request as applied.
- Did not modify Java source, database schema, frontend files, Composer files, `.env`, message mutation routes, workflow side effects, Redis pub/sub, or production realtime sync.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/dev/MessageController.php`
- `app/service/dev/MessageSseService.php`
- `docs/api/dev-message-sse-compat-plan.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\dev\MessageController.php`: passed.
- `php -l app\service\dev\MessageSseService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route table includes `dev/message/createSseConnect`.
- Unauthenticated HTTP probe: returned API `code = 401` from auth middleware.
- Authenticated HTTP probe: returned HTTP 200 with `text/event-stream` and initial `code = 0` client id event.
- Browser smoke on `http://localhost:83/index`: page loaded to the system home view and recent logs showed no new `createSseConnect` / EventSource 404.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- This is not full realtime push. It only removes the missing-route gap and returns compatible initial events.
- Full long-lived SSE, Redis pub/sub fanout, and workflow/message push side effects remain deferred.

### Next Plan

- Commit and push this small api-agent slice.
- Continue with read-only `biz/saleproject` and `biz/customer` API slices.

## 2026-06-02 - api-agent - Biz Sale Project Read-Only API Compatibility

### Completed Content

- Analyzed the Java sale-project Controller/Service and the `oa2026.sql` table structures for sale-project read flows.
- Added protected ThinkPHP read routes for:
  - `/biz/saleproject/page`
  - `/biz/saleproject/case/page`
  - `/biz/saleproject/operation/page`
  - `/biz/saleproject/public/page`
  - `/biz/saleproject/list/detail`
  - `/biz/saleproject/detail`
  - `/biz/saleproject/product`
- Added a thin `SaleProjectController` and read-only `SaleProjectService`.
- Returned Java/frontend-compatible fields for sale project list/detail/product-item display, including customer/user/org/account display names and aggregate detail lists.
- Preserved product child `extJson` compatibility for frontend parsing.
- Fixed the ThinkORM case-list join to use `join(..., 'INNER')` because `innerJoin()` is not available in this installed ORM version.
- Registered nested saleproject paths as explicit full routes to avoid stale local route-cache behavior during `php think run`.
- Confirmed and documented the corrected local MySQL/Redis helper path: `F:\project\socket\AI\testPhp\files\startServer1.bat`.
- Kept Java source, database schema, frontend files, Composer files, `.env`, and sale-project write endpoints unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `think`
- `app/controller/biz/SaleProjectController.php`
- `app/service/biz/SaleProjectService.php`
- `docs/api/biz-saleproject-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/runtime-verification-plan.md`

### Test Results

- `php -l app\controller\biz\SaleProjectController.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all seven saleproject read routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL and Redis local services are reachable.
- Backend smoke on `http://127.0.0.1:82`: passed.
- Frontend smoke on `http://127.0.0.1:83`: passed.
- Unauthenticated `/biz/saleproject/page`: returned API `code = 401`.
- Authenticated saleproject probes with a fresh local token:
  - `/biz/saleproject/page`: `code = 200`, total `8`.
  - `/biz/saleproject/detail`: `code = 200`.
  - `/biz/saleproject/product`: `code = 200`.
  - `/biz/saleproject/case/page`: `code = 200`.
  - `/biz/saleproject/operation/page`: `code = 200`.
  - `/biz/saleproject/public/page`: `code = 200`.
  - `/biz/saleproject/list/detail`: `code = 200`.

### Current Issues

- Sale-project write routes remain intentionally deferred.
- Weighted-average inventory cost endpoints remain intentionally deferred because they require a dedicated inventory/finance plan.
- Customer detail and other adjacent page APIs may still need the next read-only `biz/customer` slice.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this saleproject read-only slice.
- Continue with the next api-agent read-only slice for `biz/customer`, because sale-project pages depend on customer detail/follow-up endpoints.
- Keep frontend and backend services available for the user's continued local testing.

## 2026-06-02 - api-agent - Biz Customer Read-Only API Compatibility

### Completed Content

- Analyzed the Java customer and customer-follow-up Controller/Service/entity flow and the `oa2026.sql` table structures for customer read flows.
- Added protected ThinkPHP read routes for:
  - `/biz/customer/page`
  - `/biz/customer/detail`
  - `/biz/customer/detail/list`
  - `/biz/customerfollowup/page`
  - `/biz/customerfollowup/detail`
- Added thin `CustomerController` and `CustomerFollowUpController` adapters.
- Added read-only `CustomerService` and `CustomerFollowUpService` query services.
- Returned Java/frontend-compatible fields for customer display, including `headName`, `orgName`, `createUserName`, `downloadPath`, and `firstContactTime`.
- Returned Java/frontend-compatible follow-up display fields, including `customerName`, `createUserName`, `avatar`, `createUserOrgId`, and `createUserOrgName`.
- Documented the SM4 limitation for customer `PHONE` and `DETAILS_ADDRESS`: stored values are preserved, while plaintext decrypt/search remains deferred.
- Kept Java source, database schema, frontend files, Composer files, `.env`, and customer/follow-up write endpoints unchanged.

### Modified Files

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

### Test Results

- `php -l app\controller\biz\CustomerController.php`: passed.
- `php -l app\controller\biz\CustomerFollowUpController.php`: passed.
- `php -l app\service\biz\CustomerService.php`: passed.
- `php -l app\service\biz\CustomerFollowUpService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all five customer and follow-up read routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL, Redis, backend port `82`, and frontend port `83` are reachable.
- Frontend `/index` HTTP smoke on `http://127.0.0.1:83`: returned HTTP 200.
- Unauthenticated `/biz/customer/page`: returned API `code = 401`.
- Authenticated customer probes:
  - `/biz/customer/page`: `code = 200`, total `5020`.
  - `/biz/customer/detail`: `code = 200`.
  - `/biz/customer/detail/list`: `code = 200`, export-compatible rows returned.
  - `/biz/customerfollowup/page`: `code = 200`, total `53`.
  - `/biz/customerfollowup/detail`: `code = 200`.

### Current Issues

- Customer and customer-follow-up write routes remain intentionally deferred.
- Customer phone and detail-address plaintext decrypt/search remains deferred until an approved SM4 compatibility plan.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this customer read-only slice.
- Continue the api-agent read-only backlog with standalone invoice/invoicing/reissue/rating pages and remaining frontend-visible business reads.
- Keep backend and frontend services available for continued local testing.

## 2026-06-02 - api-agent - Sale Project Billing Read-Only API Compatibility

### Completed Content

- Analyzed Java sale-project invoicing, delivery invoice, reissue-order, and project-rate Controller/Service flow as read-only input.
- Added protected ThinkPHP read routes for:
  - `/biz/saleprojectinvoicing/page`
  - `/biz/saleprojectinvoicing/customer`
  - `/biz/saleprojectinvoicing/detail`
  - `/biz/saleprojectinvoice/page`
  - `/biz/saleprojectinvoice/list`
  - `/biz/saleprojectreissueorder/list/query`
  - `/biz/projectrate/page`
  - `/biz/projectrate/list`
- Added thin Controller adapters for invoicing, invoice, reissue-order, and project-rate reads.
- Added a read-only `SaleProjectBillingService` with Java/frontend-compatible page/list/detail structures.
- Preserved Java's invoiceable project state filter for invoicing pages: `PARTIALLY_SHIPPED`, `SHIPPED`, and `COMPLETED`.
- Returned invoice list entries with `bizSaleProjectInvoice` and `invoiceItems`.
- Returned reissue list entries with `order` and `productItemList`; product items include `children` and preserve relation `extJson`.
- Kept Java source, database schema, frontend files, Composer files, `.env`, and all billing/write/side-effect endpoints unchanged.

### Modified Files

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

### Test Results

- `php -l` for all new Controllers, the new Service, and `route/app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all eight new routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL, Redis, backend port `82`, and frontend port `83` are reachable.
- Unauthenticated `/biz/saleprojectinvoice/list`: returned API `code = 401`.
- Authenticated probes:
  - `/biz/saleprojectinvoicing/page`: `code = 200`, total `131`.
  - `/biz/saleprojectinvoicing/detail`: `code = 200`.
  - `/biz/saleprojectinvoice/page`: `code = 200`, total `236`.
  - `/biz/saleprojectinvoice/list`: `code = 200`.
  - `/biz/projectrate/page`: `code = 200`, total `62`.
  - `/biz/projectrate/list`: `code = 200`.
  - `/biz/saleprojectreissueorder/list/query`: `code = 200`; a known project with a reissue order returned the expected `order` and `productItemList` shape.

### Current Issues

- Billing, invoice, invoicing, reissue, project-rate, workflow, inventory, and finance write routes remain intentionally deferred.
- A one-off CLI DB probe hit a runtime log file permission lock while the local server was active; required framework and HTTP smoke checks passed, so no runtime files were modified.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project billing read-only slice.
- Continue the api-agent read-only backlog with remaining frontend-visible selectors/detail consumers.
- Keep backend and frontend services available for continued local testing.

## 2026-06-02 - user-agent - Biz Directory Alias Read-Only API Compatibility

### Completed Content

- Analyzed copied Vue wrappers for `/biz/org`, `/biz/user`, `/biz/position`, and `/biz/dict`.
- Analyzed Java `BizUserController` and service methods for `list/detail` and `ownRole` as read-only input.
- Added protected legacy business-side directory read routes for organization, user, position, and dictionary wrappers.
- Reused existing ThinkPHP `sys` and `dev` read controllers instead of duplicating user/org/position/dict business logic.
- Added `UserDirectoryService::listDetail()` for Java-compatible `/biz/user/list/detail` reads, including organization-child expansion for `orgId`.
- Added `UserDirectoryService::ownRole()` for Java-compatible `/biz/user/ownRole` reads from `sys_relation` category `SYS_USER_HAS_ROLE`.
- Added `DictService::treeAll()` for frontend-compatible `/biz/dict/treeAll` reads.
- Documented the route aliases and deferred write routes.
- Kept Java source, database schema, frontend files, Composer files, `.env`, and all user/org/position/dict write endpoints unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/sys/UserController.php`
- `app/controller/dev/DictController.php`
- `app/service/user/UserDirectoryService.php`
- `app/service/dev/DictService.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\UserController.php`: passed.
- `php -l app\controller\dev\DictController.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l app\service\dev\DictService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all twenty-two `/biz/org`, `/biz/user`, `/biz/position`, and `/biz/dict` read aliases are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL, Redis, and frontend port `83` were reachable during this phase.
- Initial HTTP smoke before backend restart confirmed unauthenticated `/biz/org/tree` returned API `code = 401`; authenticated selector routes returned data.

### Current Issues

- After stopping a previously hung local backend process, repeated attempts to restart the ThinkPHP built-in server on port `82` from this sandbox did not produce a stable responding HTTP process, even though foreground `php think run` can start. This appears to be a local process-management issue, not a route or syntax failure.
- Because of the backend process-management issue, the final HTTP smoke matrix for all new aliases was not completed in this turn.
- User/org/position/dict write routes, role grant, user status/password actions, import/export, and profile writes remain intentionally deferred.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this directory alias read-only slice.
- When the user or next test slice starts the backend manually, re-run browser/HTTP smoke for `/biz/org`, `/biz/user`, `/biz/position`, and `/biz/dict`.
- Continue the read-only backlog with workflow query/detail reads and business report reads.

## 2026-06-02 - workflow-agent/api-agent - Workflow Read Alias Compatibility

### Completed Content

- Analyzed Java `BizProcessController`, `BizProcessProjectController`, and `BizTaskController` as read-only input.
- Analyzed copied Vue workflow wrappers for `/biz/process/*` and `/biz/task/*`.
- Added protected read-only workflow routes for:
  - `/biz/process/all/page`
  - `/biz/process/query`
  - `/biz/process/query/list`
  - `/biz/process/project/runtime/query/list`
  - `/biz/process/fileList`
  - `/biz/task/runtime/activity/detail`
- Added frontend-friendly workflow process row aliases including `id`, `instanceId`, `category`, `title`, `status`, and `variable`.
- Made process detail and variable reads accept either `processInstanceId` or Java/frontend `id`.
- Added workflow detail response compatibility fields: `userProcess`, `startUser`, `startOrgTree`, `userActivityList`, and `ccUser`.
- Added runtime activity detail reads from `act_ru_task` and normalized runtime variables.
- Updated API docs, API gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, process starts, task approve/reject, task SSE, and workflow side effects unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/ProcessController.php`
- `app/controller/biz/TaskController.php`
- `app/service/workflow/WorkflowQueryService.php`
- `docs/api/biz-workflow-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\ProcessController.php`: passed.
- `php -l app\controller\biz\TaskController.php`: passed.
- `php -l app\service\workflow\WorkflowQueryService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all six new workflow read routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI workflow smoke passed:
  - `allProcessPage`: returned 2 rows from total 2915.
  - `processDetail`: returned Java/frontend-compatible shape.
  - `projectRuntimeQueryList`: returned 1 row.
  - `runtimeActivityDetail`: skipped because current imported runtime data has no assigned pending task.

### Current Issues

- Task approve/reject, process start/cancel, task SSE, and workflow side effects remain intentionally deferred.
- Runtime ACL for ThinkPHP `runtime` was repaired for the current local Codex user so normal `php think route:list` can write generated route/log files.
- Full browser smoke for workflow pages still needs the backend dev server running stably on port `82`.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this workflow read alias slice.
- Continue with business report, payroll, leave, sale-project-product-info, and settlement-account-payment read-only slices.
- Keep frontend and backend joint smoke in the loop after the backend dev server is stable.

## 2026-06-03 - api-agent - Sale Project Product Info Read-Only API Compatibility

### Completed Content

- Analyzed Java `BizSaleProjectProductInfoController`, entity, param, and service implementation as read-only input.
- Analyzed copied Vue `bizSaleProjectProductInfoApi` wrapper and `saleprojectproductinfo` page usage.
- Added protected read-only routes for:
  - `/biz/saleprojectproductinfo/page`
  - `/biz/saleprojectproductinfo/list`
  - `/biz/saleprojectproductinfo/detail`
- Added a thin `SaleProjectProductInfoController` adapter.
- Added a read-only `SaleProjectProductInfoService` with Java/frontend-compatible fields.
- Preserved Java `targetIds` list behavior and accepted comma-separated frontend values.
- Added creator/updater and product display aliases for expanded frontend rows.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, add/edit/delete, workflow, inventory, finance, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/SaleProjectProductInfoController.php`
- `app/service/biz/SaleProjectProductInfoService.php`
- `docs/api/biz-saleproject-product-info-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectProductInfoController.php`: passed.
- `php -l app\service\biz\SaleProjectProductInfoService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all three sale-project-product-info routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Redis port was reachable after the helper service start.
- MySQL initially rejected connections, then listened on port `3306` after starting the user-provided helper script.
- CLI read-only smoke passed:
  - `page`: returned 2 rows from total 9.
  - `detail`: returned row `1882232045490913281`.
  - `list`: returned 1 row for the sampled `targetId`.

### Current Issues

- Sale-project-product-info add/edit/delete routes remain intentionally deferred.
- The frontend page still has modal actions wired to write endpoints; those actions should be tested only after a dedicated write plan is approved.
- Reading CIM process details for local `mysqld.exe` was denied by Windows permissions, but port and database smoke checks passed.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project-product-info read-only slice.
- Continue with the remaining safe read-only backlog: business data reports, payroll, leave, and settlement-account-payment.
- Keep backend and frontend joint smoke in the loop for pages the user opens in browser testing.

## 2026-06-03 - api-agent - Biz Datareport Sale Project Details Read-Only API Compatibility

### Completed Content

- Analyzed Java `BizDataReportController`, `BizDataReportServiceImp`, and `BizDataReportQueryParam` as read-only input.
- Analyzed copied Vue `bizDataReportApi` and the sale-project-product-info page dependency on `saleProjectList/details`.
- Added protected read-only route:
  - `POST /biz/bizdatareport/saleProjectList/details`
- Added a thin `BizDataReportController` adapter.
- Added a read-only `BizDataReportService` that returns Java/frontend-compatible sale-project rows with nested `productList`, product item `children`, and `returnOrders`.
- Applied Java-compatible completion date, organization subtree, data-scope, and sale-project deal-state filters.
- Preserved long ID fields as strings and only normalized known amount/quantity fields.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, report/profit/unpaid-payment/summary endpoints, workflow, inventory, finance, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `docs/api/biz-datareport-saleproject-details-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizDataReportController.php`: passed.
- `php -l app\service\biz\BizDataReportService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `POST /biz/bizdatareport/saleProjectList/details` is listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - `details`: returned 31 sale-project rows for the sampled January 2026 scope.
  - Response shape included `productList` and `returnOrders`.
  - First sampled project `id` remained a string.

### Current Issues

- The rest of `bizdatareport` remains intentionally deferred: sale profit, saleproject summary/list/report, unpaid payment, and summary statistics.
- The new route is used by the existing frontend sale-project-product-info page, but full browser smoke of that page should still be run with backend and frontend servers active.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this biz-datareport sale-project details read-only slice.
- Continue with the remaining safe read-only backlog: payroll, leave, settlement-account-payment, and remaining report reads in small slices.
- Keep backend and frontend joint smoke in the loop for visible pages.

## 2026-06-03 - api-agent - Biz Leave Application Read-Only API Compatibility

### Completed Content

- Analyzed Java `BizLeaveApplicationController`, `BizLeaveApplicationServiceImpl`, entity, and page param as read-only input.
- Analyzed copied Vue `bizLeaveApplicationApi`, leave list page, modal selector page, and detail component usage.
- Added protected read-only routes for:
  - `/biz/bizleaveapplication/page`
  - `/biz/bizleaveapplication/my/page`
  - `/biz/bizleaveapplication/detail`
- Added a thin `BizLeaveApplicationController` adapter.
- Added a read-only `BizLeaveApplicationService` with Java/frontend-compatible filters and fields.
- Preserved Java page behavior: data-scope organization filtering when available, current-user fallback otherwise.
- Preserved Java my-page behavior by always restricting records to the current user.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, add/edit/delete, workflow start/approval/cancel, finance, inventory, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizLeaveApplicationController.php`
- `app/service/biz/BizLeaveApplicationService.php`
- `docs/api/biz-leave-application-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizLeaveApplicationController.php`: passed.
- `php -l app\service\biz\BizLeaveApplicationService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all three leave-application read routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - `page`: returned 2 rows from total 6 in the sampled data-scope organization.
  - `myPage`: returned 2 rows from total 4 for the sampled user.
  - `detail`: returned sampled row `2008808074807599105` with applicant name.
  - `filterPage`: returned 1 row from total 1 for sampled category and start-time filters.

### Current Issues

- Leave add/edit/delete and workflow start/approval/cancel routes remain intentionally deferred.
- Full browser smoke for the leave-application page should still be run with backend and frontend servers active.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this leave-application read-only slice.
- Continue with the remaining safe read-only backlog: payroll, settlement-account-payment, and remaining report reads in small slices.
- Keep backend and frontend joint smoke in the loop for visible pages.

## 2026-06-03 - api-agent - Settlement Account Payment Read-Only API Compatibility

### Completed Content

- Analyzed Java `SettlementAccountStatementController`, `SettlementAccountStatementServiceImpl`, entity, page param, and query param as read-only input.
- Analyzed copied Vue `settlementAccountPaymentApi` and the settlement-account detail statement tab usage.
- Added protected read-only routes for:
  - `/biz/settlementaccountpayment/page`
  - `/biz/settlementaccountpayment/list`
- Added a thin `SettlementAccountPaymentController` adapter.
- Added a read-only `SettlementAccountPaymentService` with Java/frontend-compatible filters and fields.
- Supported Java `startPlayTime/endPlayTime` filters and frontend `startPayerTime/endPayerTime` aliases.
- Added display aliases for account name, account number, organization name, creator name, and updater name.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, settlement account payment/transfer/income/expense mutations, workflow side effects, and balance changes unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/SettlementAccountPaymentController.php`
- `app/service/biz/SettlementAccountPaymentService.php`
- `docs/api/biz-settlement-account-payment-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SettlementAccountPaymentController.php`: passed.
- `php -l app\service\biz\SettlementAccountPaymentService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; both settlement-account-payment read routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - `page`: returned 2 rows from total 218 for the sampled account.
  - `list`: returned 218 rows for the sampled account with `payerTime` descending sort.
  - `filter`: returned 1 row for sampled `startPlayTime/endPlayTime`.

### Current Issues

- Settlement account payment creation, transfer, income/expense mutations, workflow side effects, and balance changes remain intentionally deferred.
- Full browser smoke for the settlement-account detail statement tab should still be run with backend and frontend servers active.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this settlement-account-payment read-only slice.
- Continue with the remaining safe read-only backlog: payroll and remaining report reads in small slices.
- Keep backend and frontend joint smoke in the loop for visible pages.

## 2026-06-03 - api-agent - Biz Payroll Read-Only API Compatibility

### Completed Content

- Analyzed Java `BizPayrollController`, `BizPayrollServiceImpl`, entity, page param, and `biz_payroll` SQL table as read-only input.
- Analyzed copied Vue payroll page and user payroll tab usage.
- Added protected read-only routes for:
  - `/biz/bizpayroll/page`
  - `/biz/bizpayroll/mypage`
  - `/biz/bizpayroll/detail`
- Added a thin `BizPayrollController` adapter.
- Added a read-only `BizPayrollService` with Java/frontend-compatible salary fields, salary month filters, organization subtree filtering, current-user my-page filtering, and data-scope guards.
- Added display aliases for `headName`, `name`, `userAccount`, `orgName`, creator name, and updater name.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, payroll import/export/generate/add/edit/batch edit/delete behavior, workflow, finance, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizPayrollController.php`
- `app/service/biz/BizPayrollService.php`
- `docs/api/biz-payroll-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizPayrollController.php`: passed.
- `php -l app\service\biz\BizPayrollService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all three payroll read routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - `biz_payroll` currently has no imported rows in the configured database.
  - Empty page query returned `page=0` and `total=0` without SQL or service errors.

### Current Issues

- Payroll import, export, generate, add, edit, batch edit, and delete routes remain intentionally deferred.
- Current database has no `biz_payroll` records, so detail-row smoke should be repeated after payroll data is imported or created by a confirmed write flow.
- Full browser smoke for payroll pages should still be run with backend and frontend servers active.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this payroll read-only slice.
- Continue with the remaining safe read-only backlog: business report endpoints and remaining detail consumers in small slices.
- Keep backend and frontend joint smoke in the loop for visible pages.

## 2026-06-03 - api-agent - Biz Datareport Sale Project Summary Reads

### Completed Content

- Analyzed Java `BizDataReportController`, `BizDataReportServiceImp`, query params, and sale-project report result classes as read-only input.
- Analyzed copied Vue `bizDataReportApi` and data-report dashboard usage.
- Added protected read-only routes for:
  - `/biz/bizdatareport/saleproject`
  - `/biz/bizdatareport/saleproject/list`
  - `/biz/bizdatareport/saleproject/report`
- Extended the existing thin `BizDataReportController` adapter.
- Extended `BizDataReportService` with:
  - sale-project total amount aggregation;
  - sale-project amount row list;
  - sale-project status/time report list.
- Preserved Java filter behavior:
  - `saleproject` and `saleproject/list` filter by completion date and成交 project states;
  - `saleproject/report` filters by create time or completion date and returns status/time rows;
  - data scope uses token organization ids with current-user fallback.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, sale profit, unpaid payment, settlement income/expenses, summary statistics, workflow, finance mutation, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `docs/api/biz-datareport-saleproject-summary-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizDataReportController.php`: passed.
- `php -l app\service\biz\BizDataReportService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; all three sale-project summary report routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - `saleProjectAmount`: returned sampled amount `0`.
  - `saleProjectList`: returned 1 row for the sampled organization and completion month.
  - `saleProjectReport`: returned 1 status/time row for the sampled organization and month.
  - The sampled row itself has `TOTAL_PRICE = 0.00`, matching the amount smoke result.

### Current Issues

- Sale profit, unpaid payment, settlement income/expenses, and summary statistics remain intentionally deferred.
- Full browser smoke for the data-report dashboard should still be run with backend and frontend servers active.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this biz-datareport sale-project summary read-only slice.
- Continue with remaining business report reads in small slices, likely unpaid payment first because it is close to the existing sale-project report query.
- Keep backend and frontend joint smoke in the loop for visible pages.

## 2026-06-03 - api-agent - Biz Datareport Sale Project Unpaid Payment Read

### Completed Content

- Analyzed Java `BizDataReportServiceImp#querySaleProjectUnpaidPayment` and `BizDataReportEnum` as read-only input.
- Confirmed copied Vue dashboard calls `bizSaleProjectDataReportUnpaidPayment` for the current-month unpaid card.
- Added protected read-only route:
  - `/biz/bizdatareport/saleproject/UnpaidPayment`
- Extended the existing thin `BizDataReportController` adapter.
- Extended `BizDataReportService` with Java-compatible unpaid amount aggregation.
- Preserved Java filter behavior:
  - completion-date range filter;
  -成交 project states;
  - `UNPAID` and `PARTIALLY_PAID` play states;
  - data-scope organization ids with current-user fallback;
  - org subtree expansion.
- Preserved Java calculation: `totalPrice - amountCollected + totalReturnAmount`.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, sale profit, settlement income/expenses, summary statistics, workflow, finance mutation, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `docs/api/biz-datareport-saleproject-unpaid-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizDataReportController.php`: passed.
- `php -l app\service\biz\BizDataReportService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; the unpaid-payment report route is listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - sampled project `2008016272152326146`;
  - formula check returned expected `6000`;
  - service returned `amount=6000`.

### Current Issues

- Sale profit, settlement income/expenses, and summary statistics remain intentionally deferred.
- Full browser smoke for the data-report dashboard should still be run with backend and frontend servers active.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this unpaid-payment read-only slice.
- Continue with remaining business report reads in small slices, likely settlement income/expenses next because they are pure read aggregations but touch payment/expenditure records.
- Keep backend and frontend joint smoke in the loop for visible pages.

## 2026-06-03 - api-agent - Biz Datareport Settlement Income And Expenses Reads

### Completed Content

- Analyzed Java `BizDataReportController` settlement report routes and `BizDataReportServiceImp#queryIncomeRecord/#queryExpenditureRecord` as read-only input.
- Confirmed copied Vue data-report pages call settlement income and expenses endpoints for frontend aggregation.
- Added protected read-only routes for:
  - `/biz/bizdatareport/settlement/income`
  - `/biz/bizdatareport/settlement/expenses`
- Extended the existing thin `BizDataReportController` adapter.
- Extended `BizDataReportService` with Java-compatible payment and expenditure record list reads.
- Preserved Java filter behavior:
  - selected organization plus child organizations;
  - token data-scope organization ids;
  - current-login-user fallback;
  - income category filter;
  - `startCreateTime/endCreateTime` applied to `PAYER_TIME`.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, settlement mutations, account-balance updates, sale profit, summary statistics, workflow, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `docs/api/biz-datareport-settlement-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizDataReportController.php`: passed.
- `php -l app\service\biz\BizDataReportService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; both settlement report routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - `settlementIncome`: returned 1 row for sampled payment record `2053774062208327681`.
  - `settlementExpenses`: returned 1 row for sampled expenditure record `2054438640814563330`.

### Current Issues

- Sale profit and summary statistics remain intentionally deferred.
- Settlement account payment, transfer, income, expenses, and balance mutation write routes remain deferred.
- Full browser smoke for the data-report settlement page should still be run with backend and frontend servers active.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this settlement report read-only slice.
- Continue with remaining business report reads in small slices, likely sale profit or summary statistics next.
- Keep backend and frontend joint smoke in the loop for visible pages.

## 2026-06-03 - api-agent - Biz Datareport Sale Profit Read

### Completed Content

- Analyzed Java `BizDataReportController#querySaleProfitReport`, `BizDataReportServiceImp#getSaleProfitResult`, `SaleProfitResult`, and the copied Vue `saleProfit` page/WebWorker as read-only input.
- Confirmed the frontend expects raw Java-compatible collections and calculates profit in `saleProfit/webWork/calcProfit.js`.
- Added protected read-only route:
  - `/biz/bizdatareport/saleProfit`
- Extended the existing thin `BizDataReportController` adapter.
- Extended `BizDataReportService` with Java-compatible `projectlist`, `orderList`, and `bizProducts` output.
- Preserved Java sale-project filtering:
  - selected organization plus child organizations;
  - token data-scope organization ids;
  - current-login-user fallback;
  - completion-date range;
  -成交 project states.
- Preserved Java purchase-order filtering:
  - completed settlement status;
  - token data-scope organization ids;
  - current-login-user fallback through `CREATE_USER`.
- Added nested data needed by the frontend worker:
  - sale-project `productList`;
  - sale-project `returnOrders.productList`;
  - completed purchase-order `orderItems`;
  - product lookup rows.
- Omitted empty `children` arrays from sale-profit product rows so the frontend does not treat single products as kit products.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, summary statistics, purchase/sale/return/inventory/settlement mutations, workflow, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `docs/api/biz-datareport-sale-profit-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizDataReportController.php`: passed.
- `php -l app\service\biz\BizDataReportService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; the sale-profit route is listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - sample project `2054401155761872898` returned `projectlist=1`, `bizProducts=3324`, `productList=3`, and no empty `children` arrays.
  - sample completed purchase order `2053022436501659650` returned `orderList=114` and `orderItems=1` for the sampled order.

### Current Issues

- Summary statistics remains intentionally deferred.
- Full browser smoke for the sale-profit page should still be run with backend and frontend servers active.
- Purchase, sale, return, inventory, settlement, payment, workflow, and account-balance write behavior remains deferred.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-profit read-only slice.
- Continue with the remaining `bizdatareport` read slice: `summary/statistics`.
- Keep backend and frontend joint smoke in the loop for visible pages.

## 2026-06-03 - api-agent - Biz Datareport Summary Statistics Read

### Completed Content

- Analyzed Java `BizDataReportController#querySummaryStatistics`, `BizDataReportServiceImp#querySummaryStatistics`, `BizQuerySummaryStatisticsResult`, and the copied Vue `summaryStatistics` page/WebWorker.
- Confirmed the frontend expects raw company-scoped collections and calculates annual/monthly finance values in `summaryStatistics/components/webWork/calcStatisics.js`.
- Added protected read-only route:
  - `/biz/bizdatareport/summary/statistics`
- Extended the existing thin `BizDataReportController` adapter.
- Extended `BizDataReportService` with Java-compatible summary output:
  - `org`
  - `settlementAccounts`
  - `paymentRecords`
  - `bizExpenditureRecords`
  - `bizSaleProjects`
  - `bizDebitNotes`
- Preserved the Java summary behavior of returning data grouped by company organization and bounded by selected-year end time.
- Kept the endpoint strictly read-only:
  - no settlement/account-balance mutation;
  - no workflow start/approval behavior;
  - no database schema change.
- Updated API docs, gap map, public-file route request, and progress dashboard.
- Kept Java source, database schema, frontend files, Composer files, `.env`, finance mutations, workflow writes, and business write behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/BizDataReportController.php`
- `app/service/biz/BizDataReportService.php`
- `docs/api/biz-datareport-summary-statistics-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizDataReportController.php`: passed.
- `php -l app\service\biz\BizDataReportService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; the summary-statistics route is listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- CLI read-only smoke passed:
  - sample company scope returned 1 company summary object;
  - first summary object contains `org`, `settlementAccounts`, `paymentRecords`, `bizExpenditureRecords`, `bizSaleProjects`, and `bizDebitNotes`;
  - sample counts were: settlement accounts 19, payment records 263, expenditure records 731, sale projects 98, debit notes 52.

### Current Issues

- Full browser smoke for the summary-statistics page should still be run with backend and frontend servers active.
- Settlement account payment, transfer, income, expense, correction, and balance mutation write behavior remains deferred.
- Purchase, sale, return, inventory, workflow, and account-balance side effects remain deferred.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this summary-statistics read-only slice.
- Run backend plus frontend browser smoke for the summary statistics page when both services are active.
- Continue with the next safe read-only business detail/selector slice before opening write-heavy finance or workflow behavior.

## 2026-06-03 - test-agent - Summary Statistics Browser Smoke

### Completed Content

- Kept the Java project read-only and made no business-code changes.
- Confirmed local backend and frontend services were running:
  - ThinkPHP backend on `http://127.0.0.1:82`
  - Vue frontend on `http://127.0.0.1:83`
- Browser login smoke reached the authenticated layout.
- Opened `/biz/bizdatareport/summaryStatistics` through the copied Vue frontend.
- Confirmed browser title `汇总统计 - 福地科技`.
- Confirmed visible page content renders:
  - `汇总统计表`
  - month finance columns
  - company finance data rows
  - `未回款统计表`
- Checked ThinkPHP runtime log for new backend exceptions after the smoke.
- Recorded the frontend console observations in `docs/tasks/frontend-adaptation-notes.md`.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- Backend port `82`: open.
- Frontend port `83`: open.
- Browser smoke login: passed.
- Browser smoke `/biz/bizdatareport/summaryStatistics`: passed.
- Visible loading state after wait: not present.
- ThinkPHP runtime exception check: no new smoke-related runtime exception found.

### Current Issues

- Local WebPush permission failure still appears in browser console.
- Realtime message connection still logs disconnect errors from the layout message panel.
- Vite still reports upstream `docx-templates` Node built-in compatibility warnings.
- Screenshot capture timed out in the in-app browser on this heavy report page; DOM and visible text checks were used instead.
- Write-heavy finance, workflow, stock, and account-balance side effects remain deferred.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this browser-smoke documentation slice.
- Continue with the next safe read-only visible business page or detail API before opening finance/workflow writes.
- Add a later test-agent task for the realtime message/WebPush console noise.

## 2026-06-03 - api-agent - Sale Project Cost Read

### Completed Content

- Analyzed Java `BizSaleProjectController#cost`, `BizSaleProjectController#costDetails`, `BizSaleProjectServiceImpl#calculateSaleItemCostByWeightedAverageDetail`, `BizPurchaseOrderServiceImpl#calcProductCost`, and cost result classes.
- Added protected read-only routes:
  - `/biz/saleproject/cost`
  - `/biz/saleproject/cost/details`
- Extended `SaleProjectController` with thin guarded adapters for both routes.
- Extended `SaleProjectService` with read-only cost detail calculation:
  - verifies sale-project access through the existing data-scope-aware query;
  - reads sale-project product items;
  - expands combo-product child rows;
  - attaches return orders with `productList`;
  - reads completed purchase order item unit amounts;
  - returns `items`, `productItems`, and `returnOrders`.
- Added API documentation and public-file route change request.
- Updated the API gap map and progress dashboard.
- Ran a browser smoke for `/biz/saleproject` after the route slice.
- Kept Java source, database schema, frontend files, Composer files, `.env`, purchase/inventory/finance/workflow writes, and account-balance behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/SaleProjectController.php`
- `app/service/biz/SaleProjectService.php`
- `docs/api/biz-saleproject-cost-readonly.md`
- `docs/api/biz-saleproject-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectController.php`: passed.
- `php -l app\service\biz\SaleProjectService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; both sale-project cost routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Direct service smoke passed:
  - sample project returned `items=11`, `productItems=2`, `returnOrders=0`, and a numeric cost.
- Authenticated route smoke passed:
  - local login `code=200`;
  - `/biz/saleproject/page` `code=200`;
  - `/biz/saleproject/cost/details` `code=200` for a visible sample project;
  - `/biz/saleproject/cost` `code=200` for a visible sample project;
  - unauthenticated `/biz/saleproject/cost/details` returned `code=401`.
- Browser smoke for `/biz/saleproject` passed for page load:
  - title `销售项目管理 - 福地科技`;
  - table header visible;
  - no loading state after wait.

### Current Issues

- The browser sale-project table showed `暂无数据`, while backend API smoke returned visible project records. This should be investigated later as frontend query/filter/display compatibility.
- The current browser-visible sale-project result did not expose a project with product items, so deep cost-tab browser smoke remains deferred.
- Local realtime message connection console noise still appears from the layout message panel.
- Vite still reports upstream `docx-templates` Node built-in compatibility warnings.
- Sale project writes, purchase/inventory mutations, finance writes, workflow side effects, and account-balance updates remain deferred.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project cost read-only slice.
- Continue with the remaining safe read-only candidates from the refreshed frontend scan:
  - sale-project follow-up reads;
  - sale-project product-item relation list;
  - draft/history/cc-record visible reads.
- Keep finance, inventory, workflow, and account-balance writes deferred until their dedicated plans are confirmed.

## 2026-06-03 - api-agent - Sale Project Follow-Up Read

### Completed Content

- Analyzed Java `SaleProjectFollowUpController`, `SaleProjectFollowUpServiceImpl`, `SaleProjectFollowUp`, page/id params, and the `sale_project_follow_up` SQL table from `oa2026.sql`.
- Analyzed copied Vue callers:
  - `snowy-admin-web/src/api/biz/saleProjectFollowUpApi.js`;
  - standalone `saleprojectfollowup/index.vue`;
  - sale-project detail follow-up tab.
- Added protected read-only routes:
  - `/biz/saleprojectfollowup/page`
  - `/biz/saleprojectfollowup/detail`
- Added a thin ThinkPHP controller and data-scope-aware read service.
- Returned Java/frontend-compatible fields including `projectName`, creator display fields, avatar, creator org display, and unchanged `extJson`.
- Kept follow-up add/edit/delete, upload, attachment persistence, workflow, sale-project writes, finance, account-balance behavior, frontend files, Java source, Composer files, `.env`, and database schema unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/SaleProjectFollowUpController.php`
- `app/service/biz/SaleProjectFollowUpService.php`
- `docs/api/biz-saleproject-followup-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectFollowUpController.php`: passed.
- `php -l app\service\biz\SaleProjectFollowUpService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; both sale-project follow-up routes are listed.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Direct service smoke passed:
  - local sample returned `followup_total=836`, `followup_records=3`;
  - detail for the first sampled record matched the sampled id.
- Authenticated HTTP smoke passed:
  - local login `code=200`;
  - `/biz/saleprojectfollowup/page` `code=200`, `total=836`;
  - `/biz/saleprojectfollowup/detail` `code=200`;
  - unauthenticated `/biz/saleprojectfollowup/page` returned `code=401`.
- Browser smoke found `/biz/saleprojectfollowup` currently renders the copied Vue 404 page because the standalone route/menu entry is not exposed; browser was restored to `/biz/saleproject`.

### Current Issues

- Standalone sale-project follow-up page route/menu exposure remains a frontend adaptation task.
- Sale-project detail follow-up tab deep smoke remains tied to the existing sale-project table visibility mismatch.
- Follow-up add/edit/delete writes remain deferred.
- Local realtime message connection console noise and Vite upstream warnings remain non-blocking known issues.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project follow-up read-only slice.
- Continue with the next small read-only sale-project/detail consumer, likely sale-project product-item relation or visible history/cc-record reads.
- Keep write-heavy sale-project, workflow, inventory, finance, file upload, and account-balance behavior deferred until dedicated write plans are confirmed.

## 2026-06-03 - api-agent - Sale Project Product Item Relation List Read

### Completed Content

- Analyzed Java `SaleProjectProductItemRelationController`, `SaleProjectProductItemRelationServiceImpl`, `SaleProjectProductItemRelation`, and the `sale_project_product_item_relation` SQL table from `oa2026.sql`.
- Analyzed copied Vue API wrapper `snowy-admin-web/src/api/biz/saleProjectProductItemRelationApi.js` and sale-project delivery/invoice helper usage.
- Added protected read-only route:
  - `/biz/saleprojectproductitemrelation/list`
- Added a thin ThinkPHP controller and read-only service.
- Returned relation rows with Java/frontend-compatible camelCase fields, `productId` alias, joined product display fields, and `extJson` fallback when missing.
- Scoped relation reads through `biz_sale_project_product_item -> biz_sale_project`.
- Kept relation mark edits, product item mark edits, delivery/invoice writes, sale-project writes, inventory, workflow, finance, account-balance behavior, frontend files, Java source, Composer files, `.env`, and database schema unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `route/app.php`
- `app/controller/biz/SaleProjectProductItemRelationController.php`
- `app/service/biz/SaleProjectProductItemRelationService.php`
- `docs/api/biz-saleproject-product-item-relation-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- Initial local syntax check passed:
  - `php -l app\controller\biz\SaleProjectProductItemRelationController.php`
  - `php -l app\service\biz\SaleProjectProductItemRelationService.php`
  - `php -l route\app.php`
- `php think route:list`: passed; `/biz/saleprojectproductitemrelation/list` is listed and route count is 253.
- Direct service smoke passed:
  - sample object id `2007746037931307010`;
  - returned 10 relation rows;
  - first sampled row included `productId` and non-empty `extJson`.
- Full baseline checks passed:
  - `composer dump-autoload`;
  - `php think`;
  - `php think route:list`;
  - PHP lint for `app`, `config`, and `route`;
  - `git diff --check` with CRLF conversion warnings only.
- Authenticated HTTP smoke passed:
  - local login returned a bearer token;
  - `/biz/saleprojectproductitemrelation/list` returned `code = 200`, 10 rows, `productId`, and non-empty `extJson`;
  - unauthenticated `/biz/saleprojectproductitemrelation/list` returned `code = 401`.

### Current Issues

- Relation/product item mark edit routes remain deferred because they mutate data.
- Deep browser smoke remains deferred until a visible sale-project delivery/invoice helper flow is available.
- Full online realtime data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project product item relation read-only slice.
- Continue with the next small read-only sale-project/detail consumer or move to frontend-agent investigation of the sale-project table empty-state mismatch.

## 2026-06-03 - api-agent/frontend-agent - Sale Project Page Data Scope Smoke Fix

### Completed Content

- Investigated the copied Vue `/biz/saleproject` page while the in-app browser was on `http://127.0.0.1:83/biz/saleproject`.
- Confirmed the page title and table shell loaded, but the table previously showed `暂无数据`.
- Read the sale-project page source and confirmed it forces `projectState=FOLLOW` before calling `/biz/saleproject/page`, then calls `/biz/process/query` for workflow amount lookup.
- Compared Java sale-project page filtering and existing ThinkPHP customer/follow-up/billing data-scope patterns.
- Added admin-compatible data-scope bypass to `SaleProjectService` for accounts/roles `bizAdmin`, `superadmin`, `tenantadmin`, and `bizadmin`.
- Kept ordinary user data scope, org filters, tenant filters, frontend files, route files, Java source, database schema, Composer files, `.env`, sale-project writes, workflow writes, inventory, finance, and account-balance behavior unchanged.

### Modified Files

- `app/service/biz/SaleProjectService.php`
- `docs/api/biz-saleproject-readonly.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### Test Results

- `php -l app\service\biz\SaleProjectService.php`: passed.
- Authenticated frontend-shaped `/biz/saleproject/page?projectState=FOLLOW&showDiscard=false`: returned `code = 200`, `total = 254`, and 10 rows.
- `/biz/process/query` for those sale-project ids returned `code = 200` and 10 workflow lookup items.
- Browser reload of `/biz/saleproject`: page title remained `销售项目管理 - 福地科技` and pagination showed `1-10 共 254 条`.
- Full baseline checks passed:
  - `composer dump-autoload`;
  - `php think`;
  - `php think route:list`;
  - PHP lint for `app`, `config`, and `route`;
  - `git diff --check` with CRLF conversion warnings only.
- Unauthenticated `/biz/saleproject/page` returned `code = 401`.
- Browser screenshot captured the sale-project table with real rows and pagination.

### Current Issues

- Realtime message connection console noise still appears from the layout message panel.
- Vite `docx-templates` browser compatibility warnings still appear.
- Broader non-admin data-scope token alignment should be reviewed in a later auth/user-agent slice; this commit only fixes the admin smoke-account visibility gap.
- Sale-project write routes, workflow side effects, inventory/finance/account-balance behavior, and online realtime production data sync remain deferred.

### Next Plan

- Commit and push this sale-project page smoke fix.
- Continue with the next visible read-only page or a focused auth/user-agent data-scope review after this commit.

## 2026-06-03 - test-agent/frontend-agent - Sale Project Detail Tab Browser Smoke

### Completed Content

- Continued browser smoke from `http://127.0.0.1:83/biz/saleproject`.
- Opened the detail modal for visible project `赣州开放大学心理中心`.
- Confirmed the information tab rendered project and customer details.
- Confirmed the `项目跟进记录` tab rendered existing read data, including one follow-up record and its pagination.
- Confirmed the `项目案例` tab rendered its current empty/read state without a new backend runtime failure.
- Confirmed the `审核中的流程` tab rendered its current empty/read state without a new backend runtime failure.
- Avoided all visible write controls, including add, edit, discard, upload, and form submit actions.
- Kept Java source, database schema, frontend files, routes, services, controllers, models, Composer files, `.env`, workflow writes, finance behavior, inventory behavior, file upload, and account-balance behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- Browser smoke `/biz/saleproject`: passed.
- Sale-project detail information tab: passed.
- Sale-project detail follow-up tab: passed.
- Sale-project detail case tab: passed with empty/read state.
- Sale-project detail pending-process tab: passed with empty/read state.
- Browser console still shows only known non-blocking realtime message disconnects and upstream `docx-templates` warnings during this smoke.

### Current Issues

- Follow-up add/edit/delete remains deferred.
- Case upload/add behavior remains deferred.
- Pending workflow action behavior remains deferred.
- Realtime message connection console noise remains a later test-agent task.
- Broader non-admin data-scope token alignment remains a later auth/user-agent task.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project detail smoke documentation slice.
- Continue with the next visible read-only page or start a focused auth/user-agent data-scope review.

## 2026-06-03 - api-agent/frontend-agent - Sale Project Cost Route Precedence Fix

### Completed Content

- Browser-smoked `/biz/saleproject/dealProjectList` and opened a completed historical project detail modal.
- Confirmed the completed-project cost tab initially rendered a 500 result.
- Reproduced the route issue with authenticated HTTP smoke:
  - `POST /biz/saleproject/cost/details` returned the numeric aggregate response because `cost` was registered before `cost/details`.
- Reordered the sale-project route group so `cost/details` is registered before `cost`.
- Documented the public-file route change request and cost API route precedence note.
- Kept Java source, database schema, frontend files, controllers, services, models, Composer files, `.env`, sale-project writes, delivery/invoice/return writes, workflow writes, inventory/finance behavior, file upload, and account-balance behavior unchanged.

### Modified Files

- `route/app.php`
- `docs/api/biz-saleproject-cost-readonly.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `PLANS.md`
- `STATUS.md`

### Test Results

- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed and lists `biz/saleproject/cost/details` before `biz/saleproject/cost`.
- Full PHP syntax sweep for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Authenticated HTTP smoke passed:
  - `/biz/saleproject/cost/details` returned `code = 200` with `items`, `productItems`, and `returnOrders`.
  - `/biz/saleproject/cost` returned `code = 200` with numeric aggregate `0` for the sampled historical project.
- Browser smoke passed:
  - `/biz/saleproject/dealProjectList` completed-project cost tab no longer renders the 500 result;
  - the tab renders zero-value statistics and an empty product table for the sampled historical project.

### Current Issues

- Historical zero-amount completed projects can show `NaN%` for gross profit rate in the copied frontend cost component. This is a frontend display cleanup candidate for the next small frontend-agent slice.
- Realtime message connection console noise remains a later test-agent task.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this route precedence fix if verification passes.

## 2026-06-03 - frontend-agent - Sale Project Cost Zero-Revenue Display Fix

### Completed Content

- Fixed the copied Vue completed-project cost tab so historical zero-revenue projects no longer calculate gross profit rate by dividing by zero.
- Kept the existing Decimal.js formula for non-zero revenue projects.
- Kept backend cost data, routes, Java source, database schema, Composer files, `.env`, sale-project writes, workflow behavior, inventory behavior, finance behavior, file upload, and account-balance behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `snowy-admin-web/src/views/biz/saleproject/saleProjectTab/cost/index.vue`

### Test Results

- `npm run build`: passed.
- Source verification confirmed zero or empty sales revenue returns gross profit rate `0`.
- Browser automation against the already-open local `/biz/saleproject/dealProjectList` tab was blocked by the browser URL policy, so no workaround was used and visual confirmation remains a manual/user smoke item.

### Current Issues

- Realtime message connection console noise remains a later test-agent task.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Run `git diff --check`, commit, and push this frontend display fix.
- Continue with the next safe read-only frontend/API compatibility slice.

## 2026-06-03 - test-agent/frontend-agent - Sale Project Detail Remaining Tab API Smoke

### Completed Content

- Verified additional read-only sale-project detail tab data paths after the completed-project cost tab fixes.
- Selected an imported sale project with payment, invoice, and file rows.
- Direct-smoked the existing read-only services used by the copied Vue payment, return-order, invoice, and file tabs.
- Kept Java source, database schema, backend business source, frontend component source, routes, Composer files, `.env`, sale-project writes, workflow behavior, inventory behavior, finance behavior, file upload writes, and account-balance behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php think route:list`: passed.
- Direct authenticated service smoke used project `2007642126725550081`.
- `PaymentRecordService::page`: passed, `2/2` rows.
- `ReturnOrderService::page`: passed, `0/0` rows for the sampled project.
- `SaleProjectBillingService::invoiceList`: passed, `1` row.
- `FileRelationService::list`: passed, `2` rows.

### Current Issues

- Browser automation for the local sale-project page remains blocked by URL policy in this session; manual browser verification is still useful.
- Realtime message connection console noise remains a later test-agent/frontend-agent task.
- Sale-project write actions, file upload writes, workflow transitions, inventory mutations, finance mutations, and account-balance behavior remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this documentation-only smoke record.
- Continue with the next safe read-only visible page or the frontend SSE noise task.

## 2026-06-03 - frontend-agent - Message SSE Noise Fallback

### Completed Content

- Updated the copied layout message panel SSE client for the current ThinkPHP short-lived compatibility stream.
- Added SSE source and reconnect timer cleanup on component unmount.
- Changed reconnect behavior from unbounded 5-second retries to a bounded compatibility fallback:
  - retries at 30-second intervals;
  - stops after 3 short-lived disconnect retries;
  - resets the retry count only after a stable connection lasts longer than 60 seconds.
- Reconnect requests now read the latest stored `CLIENTID`.
- Kept backend SSE service, route files, Java source, database schema, message writes, workflow writes, Redis/queue behavior, Composer files, `.env`, and production data sync behavior unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `snowy-admin-web/src/layout/components/panel-message/index.vue`

### Test Results

- `npm run build`: passed with known Vite warnings only.
- `php think route:list`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Browser smoke:
  - opened authenticated `/sys/org`;
  - page title was `组织管理 - 福地科技`;
  - observed browser logs for 42 seconds after reload;
  - no relevant SSE/message connection error or warning logs were captured during that observation window.

### Current Issues

- Full realtime message push is still deferred until Redis/queue/message workflow behavior is designed.
- Message send/delete/read-state writes remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this frontend-agent compatibility fix.
- Continue with the next safe visible page or read-only API compatibility slice.

## 2026-06-03 - user-agent/frontend-agent - Sys User Grant Echo Read-Only Compatibility

### Completed Content

- Added read-only user grant echo support for the copied `/sys/user` grant dialogs.
- Routed `/sys/user/list/detail`, `/sys/user/ownRole`, `/sys/user/ownResource`, and `/sys/user/ownPermission` behind token middleware.
- Preserved Java-compatible `sys_relation.EXT_JSON` grant payloads for resource and permission echoes.
- Kept user grant writes, user CRUD, enable/disable, reset password, import/export, Java source, database schema, Composer files, `.env`, and deployment configuration unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/frontend-readonly-selector-compat.md`
- `docs/api/sys-user-grant-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l app\controller\sys\UserController.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; added `/sys/user/list/detail`, `/sys/user/ownRole`, `/sys/user/ownResource`, and `/sys/user/ownPermission`.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Direct service smoke: passed on user `1543837863788879870`; role echo returned 1 row, resource/permission echoes returned stable empty lists, and `PASSWORD` was not returned.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- Grant save actions remain intentionally deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice if verification passes.
- Continue the next safe visible read-only page/API slice.

## 2026-06-04 - api-agent/workflow-agent - Biz CC Records Read-Only Compatibility

### Completed Content

- Added read-only copy/CC record endpoints for the copied workflow copy-task page.
- Implemented current-user filtering to match Java `BizCcRecordsServiceImpl.page`.
- Returned `promoterName`, `userName`, and `instanceId` display/detail fields.
- Kept copy/CC delete, add/edit, workflow copy delegate writes, approval/reject/start/cancel, Java source, database schema, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/CcRecordsController.php`
- `app/service/biz/CcRecordsService.php`
- `route/app.php`
- `docs/api/biz-cc-records-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\CcRecordsController.php`: passed.
- `php -l app\service\biz\CcRecordsService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/ccrecords/page` and `/biz/ccrecords/detail` are registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Direct service smoke: passed on copied-data user `2007637932689985538`; total 18 rows, first page returned 2 rows, first detail matched `2007638333690613761`, current-user filter held, and `instanceId`, `promoterName`, and `userName` keys were present.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- The copied copy-task page still exposes delete controls, but delete is intentionally deferred.
- Full workflow write runtime remains deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-05 - api-agent/frontend-agent - Customer Follow-Up Write Compatibility

### Completed Content

- Added protected Java-compatible customer follow-up write endpoints: `/biz/customerfollowup/add`, `/edit`, and `/delete`.
- Reused the existing customer follow-up read service boundaries and added transaction-wrapped write methods.
- Added write permission checks against the owning customer row, matching the Java rule of data-scope org IDs first and customer owner fallback.
- Implemented logical delete through `DELETE_FLAG = DELETED` instead of physical deletion.
- Preserved optional `extJson` submitted by the copied frontend form without implementing file upload/storage cleanup.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Started local ThinkPHP service on `http://127.0.0.1:82/` and Vue frontend on `http://127.0.0.1:83/` for follow-up browser testing.
- Kept Java source, database schema, customer writes, attachment upload/storage, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/CustomerFollowUpController.php`
- `app/service/biz/CustomerFollowUpService.php`
- `route/app.php`
- `docs/api/biz-customer-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\CustomerFollowUpController.php`: passed.
- `php -l app\service\biz\CustomerFollowUpService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; route count is 284 and `/biz/customerfollowup/add`, `/edit`, and `/delete` are registered.
- Direct service write smoke: passed; created follow-up `1780626570481441402`, edited content, then logically deleted it with `DELETE_FLAG = DELETED`.
- MySQL `127.0.0.1:3306`: listening.
- Redis `127.0.0.1:6379`: listening.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Full PHP lint over `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend reachability: `http://127.0.0.1:82/` returned HTTP 200.
- Frontend reachability: `http://127.0.0.1:83/` returned HTTP 200.

### Current Issues

- Customer add/edit/delete and head-owner reassignment remain deferred.
- Follow-up attachment upload/storage cleanup and notifications remain deferred.
- The service smoke leaves one logically deleted smoke row in `customer_follow_up`; no visible active data remains.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this customer follow-up write compatibility slice.
- Continue with the next low-risk write candidate after confirming the visible customer follow-up form in the browser.

## 2026-06-05 - api-agent/frontend-agent - Sale Project Follow-Up Write Compatibility

### Completed Content

- Added protected Java-compatible sale-project follow-up write endpoints: `/biz/saleprojectfollowup/add`, `/edit`, and `/delete`.
- Added transaction-wrapped write methods to the existing sale-project follow-up service.
- Preserved Java add behavior by storing submitted `fileList` under `EXT_JSON` as `{"fileList":[...]}`.
- Added write permission checks against the owning sale project row, using admin account/roles, data-scope org ids, or project owner fallback.
- Tightened edit safety by validating both the existing follow-up row's project and the submitted project when they differ.
- Implemented logical delete through `DELETE_FLAG = DELETED` instead of physical deletion.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, sale-project writes, upload/storage cleanup, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SaleProjectFollowUpController.php`
- `app/service/biz/SaleProjectFollowUpService.php`
- `route/app.php`
- `docs/api/biz-saleproject-followup-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectFollowUpController.php`: passed.
- `php -l app\service\biz\SaleProjectFollowUpService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; route count is 287 and `/biz/saleprojectfollowup/add`, `/edit`, and `/delete` are registered.
- Direct service write smoke: passed; created follow-up `1780627713838248763`, verified `EXT_JSON.fileList[0].name = codex-smoke.txt`, edited content/category, then logically deleted it with `DELETE_FLAG = DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Full PHP lint over `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend reachability: `http://127.0.0.1:82/` returned HTTP 200.
- Frontend reachability: `http://127.0.0.1:83/` returned HTTP 200.

### Current Issues

- File upload/storage implementation and physical file cleanup remain deferred.
- Sale-project add/edit/delete, amount/status edits, workflow starts, finance, inventory, and notification side effects remain deferred.
- The service smoke leaves one logically deleted smoke row in `sale_project_follow_up`; no visible active data remains.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project follow-up write compatibility slice.
- Continue with another low-risk write slice or browser-smoke the sale-project detail follow-up tab before moving into heavier sale-project state changes.

## 2026-06-05 - api-agent/frontend-agent - Sale Project Product Info Write Compatibility

### Completed Content

- Added protected Java-compatible software package/version info write endpoints: `/biz/saleprojectproductinfo/add`, `/edit`, and `/delete`.
- Added transaction-wrapped add/edit/logical-delete methods to `SaleProjectProductInfoService`.
- Kept Java add validation shape by requiring `productId`, `targetId`, and `contentText`.
- Kept Java edit flexibility by requiring only `id` and updating submitted mutable fields.
- Implemented logical delete through `DELETE_FLAG = DELETED` instead of physical deletion.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, product master data, sale-project product-item data, inventory, delivery, workflow, finance, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SaleProjectProductInfoController.php`
- `app/service/biz/SaleProjectProductInfoService.php`
- `route/app.php`
- `docs/api/biz-saleproject-product-info-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectProductInfoController.php`: passed.
- `php -l app\service\biz\SaleProjectProductInfoService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service write smoke: passed; created info `1780630026237839440`, edited `contentText` and `alias`, then logically deleted it with `DELETE_FLAG = DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route count is 290 and `/biz/saleprojectproductinfo/add`, `/edit`, and `/delete` are registered.
- Initial broad PHP lint emitted a local PHP startup/pagefile warning near the end; strict rerun over 232 PHP files passed with `STRICT_LINT_OK`.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend reachability: `http://127.0.0.1:82/` returned HTTP 200.
- Frontend reachability: `http://127.0.0.1:83/` returned HTTP 200.

### Current Issues

- Product master-data writes, sale-project product-item changes, import/export, report generation, inventory, delivery, workflow, and finance side effects remain deferred.
- The service smoke leaves one logically deleted smoke row in `biz_sale_project_product_info`; no visible active data remains.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project product-info write compatibility slice.
- Continue with another isolated low-risk write route, or browser-smoke `/biz/saleprojectproductinfo` add/edit/delete before entering heavier sale-project state writes.

## 2026-06-05 - api-agent/frontend-agent - Gen Basic Metadata Read-Only Compatibility

### Completed Content

- Added protected read-only generator database metadata endpoints for the copied generator form.
- Implemented `/gen/basic/tables` using MySQL `information_schema.TABLES`, returning Java-compatible `tableName` and `tableRemark`.
- Implemented `/gen/basic/tableColumns` using MySQL `information_schema.COLUMNS`, returning Java-compatible upper-case `columnName`, upper-case `typeName`, and `columnRemark`.
- Preserved the Java behavior that excludes `ACT_` workflow engine tables from generator table options.
- Updated API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, generator writes, code generation preview/execution, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/gen/BasicController.php`
- `app/service/gen/BasicService.php`
- `route/app.php`
- `docs/api/gen-basic-metadata-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\gen\BasicController.php`: passed.
- `php -l app\service\gen\BasicService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; `tables(searchKey=sys_user)` returned `sys_user` metadata and `tableColumns(sys_user)` returned 71 columns.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route count is 280 and `/gen/basic/tables` plus `/gen/basic/tableColumns` are registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Full secret scan found only pre-existing default-password compatibility references; this slice diff added no database, Redis, or super-admin secrets.

### Current Issues

- `/gen/basic/previewGen`, `/execGenZip`, and `/execGenPro` remain deferred because they generate or write code.
- `/gen/basic/add`, `/edit`, and `/delete` remain deferred until the generator module is explicitly opened for write work.
- Generator metadata reads depend on the configured MySQL database being available.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only frontend/API gap, likely another selector/detail consumer before write endpoints.

## 2026-06-05 - auth-agent/frontend-agent - Auth Third User Page Read-Only Compatibility

### Completed Content

- Added protected read-only third-party user binding pagination endpoint.
- Implemented `/auth/third/page` against `auth_third_user` with Java-compatible filters: `category`, `searchKey`, pagination, and safe sort fields.
- Returned Java-compatible camelCase binding fields including `thirdId`, `userId`, `avatar`, `name`, `nickname`, `gender`, `category`, `extJson`, and audit fields.
- Re-scanned copied frontend API wrappers: 224 explicit safe page/list/detail/query/selector wrappers now have 0 missing backend routes.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, OAuth render/callback, user binding writes, user creation, token issuance, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/auth/ThirdController.php`
- `app/service/auth/ThirdService.php`
- `route/app.php`
- `docs/api/auth-third-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\auth\ThirdController.php`: passed.
- `php -l app\service\auth\ThirdService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; imported local database has 0 `auth_third_user` rows and the endpoint service returned a stable empty page.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route count is 281 and `/auth/third/page` is registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Explicit safe frontend wrapper scan: passed; 224 wrappers scanned, 0 missing routes.
- `git diff --check`: passed with CRLF conversion warnings only.
- This slice diff added no database, Redis, or super-admin secrets.

### Current Issues

- `/auth/third/render` and `/auth/third/callback` remain deferred until OAuth provider configuration and security review are planned.
- Third-party login binding, user creation, and token issuing are not implemented in this slice.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Move from read-only wrapper closure into a dedicated write-readiness plan before adding the first low-risk write endpoint.

## 2026-06-05 - main-control-agent - Runtime Database And Redis Target Confirmation

### Completed Content

- Accepted the user-confirmed runtime rule that this project must continue using the designated local MySQL database and Redis runtime.
- Verified local `.env` is ignored by Git and contains local-only runtime secrets.
- Verified the MySQL/Redis helper startup script exists at `F:\project\socket\AI\testPhp\files\startServer1.bat`.
- Verified MySQL is reachable on `127.0.0.1:3306`.
- Verified `phpoa20026` exists, creating it with `CREATE DATABASE IF NOT EXISTS` if it was missing.
- Verified Redis is reachable on `127.0.0.1:6379` and authenticated `PING` returns `PONG`.
- Updated runtime verification documentation without writing MySQL or Redis passwords to repository files.

### Modified Files

- `STATUS.md`
- `docs/tasks/runtime-verification-plan.md`

### Test Results

- `git status --short --branch`: clean before documentation update.
- MySQL port probe: passed.
- Startup script path probe: passed.
- MySQL database probe/create-if-missing: passed; `phpoa20026` returned from `INFORMATION_SCHEMA.SCHEMATA`.
- Redis port probe: passed.
- Redis authenticated `PING`: passed with `PONG`.

### Current Issues

- Do not commit local `.env` because it contains database and Redis passwords.
- If any later phase needs to change database name, account, password, Redis host, Redis port, Redis password, or Redis expiration, stop and ask the user to confirm.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Run documentation-scope baseline checks.
- Commit and push this runtime target confirmation.
- Continue the next planned safe read-only compatibility slice after the runtime rule is recorded.

## 2026-06-05 - api-agent/frontend-agent - Team Project Comment Reply Read-Only Compatibility

### Completed Content

- Added protected read-only project comment detail endpoint for copied team-project comment consumers.
- Added protected read-only project comment reply page and detail endpoints for copied comment-reply consumers.
- Reused `TeamProjectTaskReadService` and existing project comment/reply row normalization.
- Kept standalone reply reads within the current user team-project membership boundary by joining reply target comments, owning projects, and project members.
- Preserved nested `bizTeamProjectCommentReplies` on project comment detail.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, comment/reply add/edit/delete, notifications, data-change events, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectCommentController.php`
- `app/controller/biz/TeamProjectCommentReplyController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/team-project-comment-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectCommentController.php`: passed.
- `php -l app\controller\biz\TeamProjectCommentReplyController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on copied-data project comment `2038855658414485505`; page returned total 2 rows, detail matched the sample id, and nested `bizTeamProjectCommentReplies` key was present.
- Reply table data check: `biz_team_project_comment_reply` currently has 0 rows in the imported local database.
- Direct reply page smoke: passed with an empty page result containing `records`, `total=0`, and `count=0`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/bizteamprojectcomment/detail`, `/biz/bizteamprojectcommentreply/page`, and `/biz/bizteamprojectcommentreply/detail` are registered.
- Route count check: passed with 274 registered routes.
- Full PHP lint over `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- The imported local database has no project comment reply rows, so reply detail could not be smoke-tested against a real row; route, syntax, service page, and visibility query paths were verified.
- `/biz/bizteamprojectcomment/add`, `/delete`, `/biz/bizteamprojectcommentreply/add`, `/edit`, and `/delete` remain intentionally deferred.
- Notifications, data-change events, task/project writes, and file upload behavior remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe frontend-visible read-only route gap, likely system field reads or generator metadata reads, before opening write APIs.

## 2026-06-05 - user-agent/api-agent/frontend-agent - Sys Field Read-Only Compatibility

### Completed Content

- Added protected read-only system field resource endpoints for the copied field drawer.
- Added `FieldController` with page, tree, detail, and menu tree selector reads.
- Extended `ResourceService` with `FIELD` category page/tree readers.
- Routed frontend-compatible `/sys/field/MenuTreeSelector` to existing menu tree selector data.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, field add/edit/delete, menu/button/module writes, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/FieldController.php`
- `app/service/sys/ResourceService.php`
- `route/app.php`
- `docs/api/sys-field-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\FieldController.php`: passed.
- `php -l app\service\sys\ResourceService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; `fieldPageTotal=0`, `fieldPageCount=0`, `fieldTreeCount=0`, `menuSelectorCount=20`, and page result contains `records`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/sys/field/page`, `/tree`, `/detail`, and `/MenuTreeSelector` are registered.
- Route count check: passed with 278 registered routes.
- Full PHP lint over `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- The imported local database currently has no `FIELD` rows in `sys_resource`, so field detail could not be smoke-tested against a real row; empty page/tree and menu selector behavior were verified.
- Java backend field controller was not found in the current source scan; this compatibility route is inferred from the copied Vue field wrapper and `sys_resource.CATEGORY = FIELD` convention.
- `/sys/field/add`, `/edit`, and `/delete` remain intentionally deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe frontend-visible read-only route gap, likely generator metadata reads or system field/detail follow-up if FIELD data appears later.

## 2026-06-05 - workflow-agent/api-agent/frontend-agent - Biz User Vacation Page Read-Only Compatibility

### Completed Content

- Added protected read-only annual-leave/vacation balance page endpoint.
- Preserved existing `detail` behavior and kept the new page route behind token middleware.
- Page reads non-deleted `biz_user_vacation` rows, joins `sys_user` for `userName`, and supports pagination plus safe whitelisted sorting.
- Returned rows with `id`, `userId`, `userName`, `amount`, `usedAmount`, `category`, audit fields, tenant id, and version for copied frontend compatibility.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, vacation generation/reduction, leave approval deductions, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/BizUserVacationController.php`
- `app/service/biz/BizUserVacationService.php`
- `route/app.php`
- `docs/api/biz-user-vacation-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizUserVacationController.php`: passed.
- `php -l app\service\biz\BizUserVacationService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; returned 3 copied-data rows, exposed `userName` and `amount`, and existing detail still returned the sample user/category.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/bizuservacation/page` and `/biz/bizuservacation/detail` are registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Route count check: passed with 271 registered routes.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- Java controller wires only `detail`, while Java service and copied frontend wrapper expose `page`; this ThinkPHP endpoint is intentionally read-only compatibility.
- `/biz/bizuservacation/add`, `/edit`, `/delete`, generation/reduction helpers, and approval-time vacation deductions remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-04 - api-agent - Biz Draft Detail Read-Only Compatibility

### Completed Content

- Added read-only sale-project draft detail endpoint for the copied sale-project draft flow.
- Matched Java `BizDraftServiceImpl.detail` behavior by querying `biz_draft.TARGET_ID`.
- Preserved raw `EXT_JSON` as `extJson` so frontend form/file draft parsing remains compatible.
- Kept draft save, sale-project add/edit, workflow start, file upload, Java source, database schema, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/BizDraftController.php`
- `app/service/biz/BizDraftService.php`
- `route/app.php`
- `docs/api/biz-draft-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizDraftController.php`: passed.
- `php -l app\service\biz\BizDraftService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/bizdraft/detail` is registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Direct service smoke: passed on copied-data target id `2007642126725550081`; detail matched draft `2007721895165038593`, `targetId` matched, and `extJson` plus `category` were present.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- `/biz/bizdraft/saleproject/add` remains intentionally deferred.
- Sale-project add/edit, workflow start, and file upload side effects remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-04 - workflow-agent/api-agent - Biz User Vacation Detail Read-Only Compatibility

### Completed Content

- Added read-only annual-leave balance detail endpoint for copied leave-process pages.
- Matched Java `BizUserVacationServiceImpl.detail` defaults: current login user when `userId` is omitted, `annualLeave` when `category` is omitted, and current-year records by `CREATE_TIME`.
- Returned a zero-balance annual-leave object when no row exists, preserving copied frontend calculations.
- Kept vacation generation/reduction, leave approval deductions, workflow writes, Java source, database schema, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/BizUserVacationController.php`
- `app/service/biz/BizUserVacationService.php`
- `route/app.php`
- `docs/api/biz-user-vacation-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizUserVacationController.php`: passed.
- `php -l app\service\biz\BizUserVacationService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/bizuservacation/detail` is registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Direct service smoke: passed on copied-data user `1543837863788879870`; detail matched current-year annual-leave row `2006394917698801666`, `amount=5`, `usedAmount=0`, and missing-user fallback returned zero annual-leave balance.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- `/biz/bizuservacation/page`, `/add`, `/edit`, and `/delete` remain intentionally deferred.
- Vacation generation/reduction and leave approval balance deductions remain deferred until workflow write runtime is opened.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-04 - api-agent/frontend-agent - Biz History Excel Read-Only Compatibility

### Completed Content

- Added read-only historical EXCEL endpoints for the copied `/biz/bizhistoryexcel` page.
- Matched Java page/detail scope for `BizHistoryExcelController` with protected `page` and `detail` routes.
- Preserved raw `EXT_JSON` as `extJson` for spreadsheet display and kept audit/tenant fields.
- Kept Java source, database schema, Excel import/export, spreadsheet parsing, add/edit/delete routes, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/BizHistoryExcelController.php`
- `app/service/biz/BizHistoryExcelService.php`
- `route/app.php`
- `docs/api/biz-history-excel-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizHistoryExcelController.php`: passed.
- `php -l app\service\biz\BizHistoryExcelService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/bizhistoryexcel/page` and `/biz/bizhistoryexcel/detail` are registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Direct service smoke: passed; local `biz_history_excel` has 2 raw rows and 0 non-deleted visible rows, so page returns `total=0` under Java-compatible logical-delete filtering.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- The copied page still exposes add/edit/delete controls, but those write routes remain intentionally deferred.
- Local imported history Excel rows are currently logical deleted, so the page can validly show an empty list.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-04 - api-agent/frontend-agent - Sale Project Invoice Item Page Read-Only Compatibility

### Completed Content

- Added Java-compatible read-only delivery invoice item page endpoint for copied sale-project invoice/detail consumers.
- Matched Java `BizSaleProjectInvoiceItemServiceImpl.page` filters for `invoiceId` and `warehousesId`.
- Preserved Java's default `PROJECT_PRODUCT_ITEM_ID` ascending sort and added a safe sorting whitelist.
- Returned existing product and warehouse display aliases used by sale-project invoice detail reads.
- Kept Java source, database schema, invoice item writes, invoice/delivery/stock/project-state/finance side effects, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SaleProjectInvoiceItemController.php`
- `app/service/biz/SaleProjectBillingService.php`
- `route/app.php`
- `docs/api/sale-project-invoice-item-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectInvoiceItemController.php`: passed.
- `php -l app\service\biz\SaleProjectBillingService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/saleprojectinvoiceItem/page` is registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Direct service smoke: passed on copied-data invoice `2008383542460407810`; total 1 row, first row `2008383542565265410`, and `productName` plus `warehousesName` keys were present.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- `biz_sale_project_product_item` read routes in Java are commented out, so they were not added even though wrappers mention them.
- Invoice item add/edit/delete and delivery/stock/finance side effects remain deferred.
- MySQL startup through `F:\project\socket\AI\testPhp\files\startServer1.bat` can take around 30 seconds before port 3306 listens.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-04 - api-agent/frontend-agent - Sales Project Field Change Log Read-Only Compatibility

### Completed Content

- Added Java-compatible read-only sale-project field change log page and detail endpoints.
- Matched Java `SalesProjectFieldChangeLogServiceImpl.page` default sorting by `ID` ascending and used a safe sorting whitelist for requested sort fields.
- Returned change fields, audit fields, tenant id, project display name, and creator display name for copied sale-project history/detail consumers.
- Kept Java source, database schema, change-log add/edit/delete, sale-project amount/change writes, workflow, finance, audit side effects, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SalesProjectFieldChangeLogController.php`
- `app/service/biz/SalesProjectFieldChangeLogService.php`
- `route/app.php`
- `docs/api/sales-project-field-change-log-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SalesProjectFieldChangeLogController.php`: passed.
- `php -l app\service\biz\SalesProjectFieldChangeLogService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/salesprojectfieldchangelog/page` and `/biz/salesprojectfieldchangelog/detail` are registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Direct service smoke: passed on copied-data change log `2016674049317908481`; page returned total 5 rows, detail matched the sample id and exposed `objectId`, `projectName`, and `createUserName`.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- Imported SQL uses mixed collations between `sales_project_field_change_log.OBJECT_ID` and `biz_sale_project.ID`; the read join uses explicit collation without changing schema.
- `/biz/salesprojectfieldchangelog/add`, `/edit`, and `/delete` remain intentionally deferred.
- Sale-project amount/change writes and audit side effects remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-04 - api-agent/frontend-agent - Team Project Task User Read-Only Compatibility

### Completed Content

- Added Java-compatible read-only team-project task user page and detail endpoints.
- Reused `TeamProjectTaskReadService` and existing `TASK_USER_FIELDS`/row normalization for task assignment rows.
- Kept existing ThinkPHP team-project visibility boundary by returning only task-user rows from projects where the current login user is a project member.
- Returned Java-compatible translated user aliases `headName` and `avatar`.
- Kept Java source, database schema, task-user add/edit/delete, task assignment writes, task status/progress writes, notifications, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectTaskUserController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/team-project-task-user-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectTaskUserController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/bizteamprojecttaskuser/page` and `/biz/bizteamprojecttaskuser/detail` are registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- Direct service smoke: passed on copied-data project `1903996479133360129` as member `2007699574773649410`; page returned total 7 rows, detail matched task-user `2033724343780306945`, and `headName` plus `avatar` keys were present.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- Some older imported `biz_team_project_task_user` rows point to deleted tasks or projects, so smoke tests must pick rows where task, project, and project membership are all visible.
- `/biz/bizteamprojecttaskuser/add`, `/edit`, and `/delete` remain intentionally deferred.
- Task assignment writes, task status/progress writes, and notifications remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-04 - api-agent/frontend-agent - Dev Monitor Network Info Read-Only Compatibility

### Completed Content

- Added Java-compatible read-only dev monitor network info endpoint.
- Matched Java `DevMonitorController.networkInfo` response shape with `devMonitorNetworkInfo.upLinkRate` and `devMonitorNetworkInfo.downLinkRate`.
- Sampled local OS network counters twice and formatted per-second upload/download rates.
- Added safe fallback to `0 B/s` when OS counters are unavailable.
- Kept Java source, database schema, monitor writes/server control, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/dev/MonitorController.php`
- `app/service/dev/MonitorService.php`
- `route/app.php`
- `docs/api/dev-monitor-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\dev\MonitorController.php`: passed.
- `php -l app\service\dev\MonitorService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; returned `devMonitorNetworkInfo` with `upLinkRate` and `downLinkRate`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/dev/monitor/networkInfo` is registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- Network rate depends on local OS counter availability; unsupported counters intentionally degrade to `0 B/s`.
- Monitor writes, server process control, and metric persistence remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-05 - api-agent/frontend-agent - Sale Project Rate Detail Read-Only Compatibility

### Completed Content

- Added protected read-only sale-project customer rating detail endpoint.
- Reused existing `SaleProjectBillingService::rateQuery` so detail keeps the same tenant, delete-flag, and project-scope boundaries as rating page/list reads.
- Returned the same normalized rating shape used by page/list, including `projectName`, `customerName`, and raw `extJson`.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, rating add/edit/delete, rating image upload, sale-project writes, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SaleProjectRateController.php`
- `app/service/biz/SaleProjectBillingService.php`
- `route/app.php`
- `docs/api/biz-saleproject-billing-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectRateController.php`: passed.
- `php -l app\service\biz\SaleProjectBillingService.php`: passed.
- `php -l route\app.php`: passed.
- MySQL was not listening at first; started it through `F:\project\socket\AI\testPhp\files\startServer1.bat`, then port 3306 listened.
- Direct service smoke: passed on copied-data rating `2009867439677366274`; detail matched the sample id and exposed `projectName`, `customerName`, and `extJson`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/projectrate/detail` is registered.
- Full PHP lint over `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.

### Current Issues

- Java controller does not wire a concrete `/biz/projectrate/detail` mapping, but the Java service has `detail/queryEntity` and the copied frontend wrapper exposes `saleProjectRateDetail`; this ThinkPHP route is kept read-only for frontend compatibility.
- `/biz/projectrate/add`, `/edit`, and `/delete` remain intentionally deferred.
- Rating image upload, sale-project writes, file storage, workflow, finance, and project-state side effects remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this read-only compatibility slice.
- Continue the next safe visible read-only page/API slice.

## 2026-06-05 - api-agent/frontend-agent - Sale Project Field Change Log Write Compatibility

### Completed Content

- Added Java-compatible protected sale-project field change log `add`, `edit`, and `delete` endpoints.
- Matched Java validation requirements for `objectId`, `fieldName`, `fieldLabel`, `beforeValue`, `afterValue`, and `changeReason`.
- Kept existing `page` and `detail` read behavior, including project and creator display aliases.
- Added transactional write methods with audit fields and tenant preservation from the owning sale project.
- Used `DELETE_FLAG = DELETED` for delete safety instead of physical removal.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, sale-project main writes, workflow, finance, inventory, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SalesProjectFieldChangeLogController.php`
- `app/service/biz/SalesProjectFieldChangeLogService.php`
- `route/app.php`
- `docs/api/sales-project-field-change-log-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SalesProjectFieldChangeLogController.php`: passed.
- `php -l app\service\biz\SalesProjectFieldChangeLogService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/salesprojectfieldchangelog/add`, `/edit`, and `/delete` are registered.
- Direct service smoke: passed on copied-data project `2007642126725550081`; add returned test row `1780634305327997228`, edit changed `afterValue`, and delete set `DELETE_FLAG=DELETED`.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL started through `F:\project\socket\AI\testPhp\files\startServer1.bat`; backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- Sale-project generated history creation from amount/change edit flows remains deferred.
- Workflow, finance, inventory, notifications, and audit side effects remain deferred.
- Delete intentionally leaves a logically deleted local test row for traceability instead of physically removing data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this write compatibility slice.
- Continue the next isolated low-risk write endpoint before opening side-effect-heavy sale-project, finance, stock, or workflow flows.

## 2026-06-05 - api-agent/frontend-agent - Biz History Excel Write Compatibility

### Completed Content

- Added Java-compatible protected historical Excel data `add`, `edit`, and `delete` endpoints.
- Matched Java parameter shape: add stores `name` and `extJson`; edit requires `id` and updates submitted `extJson`.
- Kept existing `page` and `detail` read behavior and raw `EXT_JSON` payload preservation.
- Added transactional writes with audit fields and tenant id defaults.
- Used `DELETE_FLAG = DELETED` for delete safety instead of physical removal.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, `biz_history_excel_row`, frontend Excel parser, file storage/import/export, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/BizHistoryExcelController.php`
- `app/service/biz/BizHistoryExcelService.php`
- `route/app.php`
- `docs/api/biz-history-excel-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\BizHistoryExcelController.php`: passed.
- `php -l app\service\biz\BizHistoryExcelService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/bizhistoryexcel/add`, `/edit`, and `/delete` are registered.
- Direct service smoke: passed; add returned test row `1780635064432452528`, edit changed `extJson`, and delete set `DELETE_FLAG=DELETED`.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL was already listening; backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- Frontend Excel parsing remains unchanged and still submits the whole parsed payload as `extJson`.
- `biz_history_excel_row` row-table parsing/writes remain deferred because Java controller does not use it in this CRUD flow.
- Import/export and physical file storage changes remain deferred.
- Delete intentionally leaves a logically deleted local test row for traceability instead of physically removing data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this write compatibility slice.
- Continue another isolated low-risk write endpoint before opening finance, inventory, workflow, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent - Sale Project Rate Write Compatibility

### Completed Content

- Added Java-compatible protected sale-project rating `add` and `delete` endpoints.
- Matched the Java-exposed controller surface: `/biz/projectrate/add` and `/delete`; `/edit` remains deferred because the Java controller does not expose it in the current reference.
- Preserved existing `page`, `list`, and `detail` read behavior.
- Stored submitted `imgList` under `EXT_JSON` as `{"imgList":[...]}` for the copied frontend parser.
- Added transactional writes with audit fields, project write-scope checks, tenant id defaults, and logical delete.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, frontend source, file upload/storage, sale-project state, workflow, finance, inventory, Composer files, and `.env` unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SaleProjectRateController.php`
- `app/service/biz/SaleProjectBillingService.php`
- `route/app.php`
- `docs/api/biz-saleproject-billing-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SaleProjectRateController.php`: passed.
- `php -l app\service\biz\SaleProjectBillingService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on copied-data project `2007642126725550081`; add returned test row `1780638185496634189`, detail returned `imgList`, and delete set `DELETE_FLAG=DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/projectrate/add` and `/delete` are registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL TCP check returned OK; backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- `/biz/projectrate/edit` remains deferred.
- Image upload/storage cleanup, sale-project state, workflow, finance, inventory, and notifications remain deferred.
- Delete intentionally leaves a logically deleted local test row for traceability instead of physically removing data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this write compatibility slice.
- Continue another isolated low-risk write endpoint before opening finance, inventory, workflow, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent/workflow-agent - CC Records Delete Compatibility

### Completed Content

- Added Java-compatible protected workflow copy/CC record `delete` endpoint.
- Preserved existing `page` and `detail` read behavior.
- Matched Java's delete guard by requiring `USER` to equal the current token user id.
- Added optional tenant guard when the token payload includes `tenantId` or `tenant_id`.
- Used `DELETE_FLAG = DELETED` for delete safety instead of physical removal.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, `/biz/ccrecords/add`, `/edit`, workflow copy-user delegate writes, approval/reject/start/cancel flows, Composer files, `.env`, and frontend source unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/CcRecordsController.php`
- `app/service/biz/CcRecordsService.php`
- `route/app.php`
- `docs/api/biz-cc-records-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\CcRecordsController.php`: passed.
- `php -l app\service\biz\CcRecordsService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; inserted local test row `1780638742848257170` for user `1543837863788879870`, detail read succeeded, and delete set `DELETE_FLAG=DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; `/biz/ccrecords/delete` is registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL TCP check returned OK; backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- `/biz/ccrecords/add` and `/edit` remain deferred.
- Workflow copy-user delegate writes and approval/reject/start/cancel side effects remain deferred.
- Delete intentionally leaves a logically deleted local test row for traceability instead of physically removing data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this write compatibility slice.
- Continue another isolated low-risk write endpoint before opening side-effect-heavy workflow, finance, inventory, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent - Team Project Comment Add Compatibility

### Completed Content

- Added Java-compatible protected team-project timeline comment `add` endpoint.
- Added Java-compatible protected team-project comment-reply `add` endpoint.
- Preserved existing `page`, `list`, and `detail` read behavior.
- Required current-user membership of the owning team project before either write.
- Stored submitted `mentionableUsers` under `EXT_JSON` as `{"mentionableUsers":[...]}`.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, frontend source, comment delete, reply edit/delete, notification push, data-change events, task state/progress writes, Composer files, and `.env` unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
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

### Test Results

- `php -l app\controller\biz\TeamProjectCommentController.php`: passed.
- `php -l app\controller\biz\TeamProjectCommentReplyController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on team project `1903996479133360129` for user `1543837863788879873`; comment `1780639737042353386` and reply `1780639737256204805` were inserted, read back, and then marked `DELETE_FLAG=DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route entry rows = 301 and `/biz/bizteamprojectcomment/add`, `/biz/bizteamprojectcommentreply/add` are registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- MySQL/Redis were started through `F:\project\socket\AI\testPhp\files\startServer1.bat`; `netstat` showed MySQL 3306 and Redis 6379 listening.
- Backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200 after Vite startup finished.

### Current Issues

- `/biz/bizteamprojectcomment/delete` remains deferred.
- `/biz/bizteamprojectcommentreply/edit` and `/delete` remain deferred.
- Notification push, data-change events, team-project mutations, task mutations, and task state/progress writes remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this write compatibility slice.
- Continue another isolated low-risk frontend-visible write endpoint before opening side-effect-heavy workflow, finance, inventory, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent - Team Project Comment Maintenance Compatibility

### Completed Content

- Added Java-compatible protected team-project timeline comment `delete` endpoint.
- Added Java-compatible protected team-project comment-reply `edit` and `delete` endpoints.
- Preserved existing comment/reply read and add behavior.
- Converted Java physical deletes to project-standard logical deletes with `DELETE_FLAG = DELETED`.
- Added `delComment` project resource permission validation from imported `biz_relation` records for comment maintenance.
- Allowed reply edit/delete for the reply creator or a project user with imported `delComment` permission.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, frontend source, team-project mutations, task/category/task-user writes, task state/progress writes, notification push, data-change events, Composer files, and `.env` unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
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

### Test Results

- `php -l app\controller\biz\TeamProjectCommentController.php`: passed.
- `php -l app\controller\biz\TeamProjectCommentReplyController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on team project `1903996479133360129` for user `1543837863788879873`; comment `1780644969022144266` and reply `1780644969138213218` were inserted, reply edit was read back, reply delete hid the reply, and comment delete hid the comment.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route entry rows = 304 and `/biz/bizteamprojectcomment/delete`, `/biz/bizteamprojectcommentreply/edit`, `/biz/bizteamprojectcommentreply/delete` are registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- MySQL 3306, Redis 6379, backend 82, and frontend 83 were listening.
- Backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- Notification push and data-change events remain deferred.
- Team-project add/edit/delete, task/category/task-user writes, task comment writes, and task state/progress writes remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this maintenance compatibility slice.
- Continue another isolated low-risk frontend-visible write endpoint before opening side-effect-heavy workflow, finance, inventory, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent - Team Project Task User Edit Compatibility

### Completed Content

- Added Java-compatible protected `POST /biz/bizteamprojecttask/user/edit`.
- Preserved existing task `page`, `list`, and `detail` read behavior.
- Accepted frontend task-detail assignee payloads as id strings, comma-separated ids, or user objects with `id`, `userId`, or `value`.
- Required current-user membership of the owning team project plus imported `addUser` project permission or task-level `MANAGE` role.
- Required submitted assignees to already be non-deleted members of the same team project.
- Inserted new assignees as task-user `MEMBER` rows and logically deleted removed assignees with `DELETE_FLAG = DELETED`.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, frontend source, task add/edit/delete, category writes, task comments, task status/progress/content writes, notification push, data-change events, Composer files, and `.env` unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectTaskController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectTaskController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on team project `1903996479133360129`, task `2033724343755141122`, current user `1543837863788879873`; candidate assignee `1543837863788879873` was added through object-shaped frontend payload and then restored to the original task-user list.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route entry rows = 305 and `/biz/bizteamprojecttask/user/edit` is registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP check for `/biz/bizteamprojecttask/user/edit`: returned HTTP 200 envelope with `code=401`.
- MySQL 3306, Redis 6379, backend 82, and frontend 83 were listening.
- Backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- The smoke test intentionally leaves logically deleted task-user test rows for traceability instead of physically deleting imported-style data.
- Task add/edit/delete, category writes, task comment writes, task status/progress/content writes, notification push, and data-change events remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this task assignee compatibility slice.
- Continue another isolated frontend-visible write/read gap, likely task comment maintenance or a low-risk customer/sale-project helper, before opening side-effect-heavy workflow, finance, inventory, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent - Team Project Task Comment Add Compatibility

### Completed Content

- Added Java-compatible protected `POST /biz/bizteamprojecttaskcomment/add`.
- Preserved existing task-comment `page`, `list`, and `detail` read behavior.
- Required current-user membership of the owning team project before adding a task comment.
- Derived `TEAM_PROJECT_ID` and tenant id from the existing task/project instead of trusting the request body.
- Stored new task comments with `CATEGORY = COMMENT`, `DELETE_FLAG = NOT_DELETE`, current-user audit fields, and raw `CONTENT_TEXT`.
- Stored submitted `files` under `EXT_JSON` as `{"file":[...]}` for compatibility with the copied task detail drawer.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, frontend source, task comment edit/delete, task add/edit/delete, category writes, task status/progress/content writes, notification push, data-change events, Composer files, and `.env` unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectTaskCommentController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/api/team-project-comment-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectTaskCommentController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on team project `1903996479133360129`, task `2033724343755141122`, current user `1543837863788879873`; comment `1780647300714334323` was inserted with `CATEGORY = COMMENT`, `EXT_JSON.file[0].name = smoke.txt`, read back, and then logically marked `DELETE_FLAG = DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route entry rows = 306 and `/biz/bizteamprojecttaskcomment/add` is registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP check for `/biz/bizteamprojecttaskcomment/add`: returned HTTP 200 envelope with `code=401`.
- MySQL 3306, Redis 6379, backend 82, and frontend 83 were listening.
- Backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- The smoke test intentionally leaves a logically deleted task-comment test row for traceability instead of physically deleting imported-style data.
- Task comment edit/delete, task add/edit/delete, category writes, task status/progress/content writes, notification push, and data-change events remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this task comment add compatibility slice.
- Continue another isolated frontend-visible write/read gap before opening side-effect-heavy workflow, finance, inventory, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent - Team Project Task Comment Maintenance Compatibility

### Completed Content

- Added Java-compatible protected `POST /biz/bizteamprojecttaskcomment/edit`.
- Added Java-compatible protected `POST /biz/bizteamprojecttaskcomment/delete`.
- Preserved existing task-comment `page`, `list`, `detail`, and `add` behavior.
- Restricted maintenance to user comments with `CATEGORY = COMMENT`.
- Kept generated task logs with `CATEGORY = LOG` read-only.
- Allowed maintenance for the comment creator, a project user with imported `delComment`, or a task-level `MANAGE` user.
- Edit updates only `CONTENT_TEXT`, `EXT_JSON`, `UPDATE_TIME`, and `UPDATE_USER`.
- Delete uses logical deletion through `DELETE_FLAG = DELETED`.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, frontend source, task add/edit/delete, category writes, task status/progress/content writes, notification push, data-change events, Composer files, and `.env` unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectTaskCommentController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/api/team-project-comment-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectTaskCommentController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on team project `1903996479133360129`, task `2033724343755141122`, current user `1543837863788879873`; comment `1780647795941342103` was inserted, edited with `EXT_JSON.file[0].name = edit.txt`, logically deleted with `DELETE_FLAG = DELETED`, and an existing `CATEGORY = LOG` row was rejected as read-only.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route entry rows = 308 and `/biz/bizteamprojecttaskcomment/edit` plus `/delete` are registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP check for `/biz/bizteamprojecttaskcomment/edit`: returned HTTP 200 envelope with `code=401`.
- MySQL 3306, Redis 6379, backend 82, and frontend 83 were listening.
- Backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- The smoke test intentionally leaves a logically deleted task-comment test row for traceability instead of physically deleting imported-style data.
- Generated task-log edit/delete remains intentionally blocked.
- Task add/edit/delete, category writes, task status/progress/content writes, notification push, and data-change events remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this task comment maintenance compatibility slice.
- Continue another isolated frontend-visible write/read gap before opening side-effect-heavy workflow, finance, inventory, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent - Team Project Task Category Maintenance Compatibility

### Completed Content

- Added Java-compatible protected `POST /biz/bizteamprojecttaskcategory/add`.
- Added Java-compatible protected `POST /biz/bizteamprojecttaskcategory/edit`.
- Added Java-compatible protected `POST /biz/bizteamprojecttaskcategory/sort/edit`.
- Added Java-compatible protected `POST /biz/bizteamprojecttaskcategory/delete`.
- Preserved existing task-category `page`, `list`, and `detail` read behavior.
- Required project maintainer permission for category maintenance: team-project `LEADER`, team-project `MANAGE`, or imported `addUser` project resource permission.
- Defaulted new category `SORT_CODE` to `99`.
- Allowed category edit to update only `TITLE`, optional `EXT_JSON`, optional `SORT_CODE`, and audit fields.
- Reordered submitted categories by Java-style ordered `[{id: ...}]` payloads.
- Rejected deletion of categories that still contain active tasks.
- Used logical deletion through `DELETE_FLAG = DELETED` instead of physical removal.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, frontend source, task add/edit/delete, task drag-to-category, task status/progress/content writes, notification push, data-change events, Composer files, and `.env` unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectTaskCategoryController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectTaskCategoryController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on team project `1903996479133360129`, current user `1543837863788879873`; category `1780648771828319110` was added with default `SORT_CODE = 99`, edited, sorted to `SORT_CODE = 0`, and logically deleted with `DELETE_FLAG = DELETED`.
- Direct service negative smoke: passed; existing non-empty category `2032372934740733953` with 4 active tasks was rejected for deletion.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route entry rows = 312 and the four task-category maintenance routes are registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP check for `/biz/bizteamprojecttaskcategory/add`: returned HTTP 200 envelope with `code=401`.
- Backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- The smoke test intentionally leaves a logically deleted task-category test row for traceability instead of physically deleting imported-style data.
- Task add/edit/delete, task drag-to-category, task status/progress/content writes, notification push, and data-change events remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this task category maintenance compatibility slice.
- Continue another isolated frontend-visible gap before opening side-effect-heavy workflow, finance, inventory, or sale-project state flows.

## 2026-06-05 - api-agent/frontend-agent - Team Project Task Base Maintenance Compatibility

### Completed Content

- Added Java-compatible protected `POST /biz/bizteamprojecttask/add`.
- Added Java-compatible protected `POST /biz/bizteamprojecttask/edit`.
- Added Java-compatible protected `POST /biz/bizteamprojecttask/delete`.
- Preserved existing task `page`, `list`, `detail`, and `user/edit` behavior.
- Add now validates current-user project membership and category/project match.
- Add stores new tasks with `STATUS = TODO`, `PROGRESS = 0`, `DELETE_FLAG = NOT_DELETE`, `VERSION = 0`, current-user audit fields, and tenant id.
- Add creates the current token user as task `MANAGE`, and submitted project users as task `MEMBER`.
- Edit updates only submitted base task fields: `TITLE`, `STATUS`, `CONTENT_TEXT`, `PROGRESS`, `TEAM_PROJECT_TASK_CATEGORY_ID`, `SORT_CODE`, `EXT_JSON`, audit fields, and `VERSION`.
- Edit validates task status values against `TODO`, `CANCEL`, and `COMPLETE`.
- Edit/delete are allowed for the task creator, a task-level `MANAGE` user, or a project maintainer.
- Delete uses logical deletion through `DELETE_FLAG = DELETED` for the task and active task-user rows.
- Updated API docs, frontend notes, API gap map, public route-change request, progress dashboard, plan, and status records.
- Kept Java source, database schema, frontend source, standalone task-user CRUD, generated task `LOG` comments, notification push, data-change events, Composer files, and `.env` unchanged.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectTaskController.php`
- `app/service/biz/TeamProjectTaskReadService.php`
- `route/app.php`
- `docs/api/biz-team-project-task-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectTaskController.php`: passed.
- `php -l app\service\biz\TeamProjectTaskReadService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed on team project `1903996479133360129`, category `2032372934740733953`, current user `1543837863788879873`; task `1780649358908519769` was added, assigned current-user `MANAGE` plus submitted member `2007632954432819201`, edited to `STATUS = COMPLETE`, `PROGRESS = 55`, `SORT_CODE = 12`, `VERSION = 1`, rejected invalid status `BROKEN`, and logically deleted with active task-user rows also deleted.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed; route entry rows = 315 and `/biz/bizteamprojecttask/add`, `/edit`, and `/delete` are registered.
- Strict full PHP lint over `app`, `config`, and `route`: passed; 232 PHP files checked.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP check for `/biz/bizteamprojecttask/add`: returned HTTP 200 envelope with `code=401`.
- Backend `http://127.0.0.1:82/` returned 200; frontend `http://127.0.0.1:83/` returned 200.

### Current Issues

- The smoke test intentionally leaves a logically deleted task and task-user rows for traceability instead of physically deleting imported-style data.
- Java-generated task `LOG` comments, notification push, data-change events, workflow actions, and full drag ordering remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this task base maintenance compatibility slice.
- Continue another isolated frontend-visible gap, likely team-project member maintenance or a low-risk profile/selector helper, before opening workflow, finance, inventory, or sale-project state flows.

## 2026-06-05 17:20 +08:00 - api-agent/frontend-agent - Team Project Member Maintenance Compatibility

### Completed

- Added protected compatibility routes for team-project member add, manager add, and delete.
- Added `TeamProjectUserController` POST handlers with JSON/form body compatibility.
- Added `TeamProjectService` member maintenance logic for active duplicate detection, deleted-row restore, project permission checks, relation permission JSON sync, and logical deletion.
- Updated team-project API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, and active plan status.

### Modified Files

- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/TeamProjectUserController.php`
- `app/service/biz/TeamProjectService.php`
- `route/app.php`
- `docs/api/biz-team-project-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\TeamProjectUserController.php`: passed.
- `php -l app\service\biz\TeamProjectService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 318 route entries; member `add`, `manage/add`, and `delete` listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 232 files.
- `git diff --check`: passed.
- Service smoke: passed for add member, duplicate rejection, delete, restore as manager, relation permission sync, final delete, and leader-delete rejection.
- No-token HTTP smoke for `POST /biz/bizteamprojectuser/add`: returned `code=401`.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Java notification push and data-change event side effects remain deferred by design.
- `/biz/bizteamprojectuser/edit` remains deferred.
- The local service smoke leaves a logically deleted test member row in the local database, preserving imported data by avoiding physical cleanup.

### Next Plan

- Continue the next small frontend-visible business compatibility slice.
- Candidate next slice: team-project member role edit only if the copied frontend exposes it during browser testing; otherwise return to remaining sale/customer/finance write gaps.

## 2026-06-05 17:33 +08:00 - api-agent/frontend-agent - Customer Base Maintenance Compatibility

### Completed

- Added protected compatibility routes for customer add, edit, and delete.
- Added `CustomerController` POST handlers with JSON/form body and Java-style delete payload compatibility.
- Added `CustomerService` base customer maintenance logic for whitelisted field mapping, owner/org defaults, write-scope validation, audit fields, version increments, and logical deletion.
- Updated customer API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/CustomerController.php`
- `app/service/biz/CustomerService.php`
- `route/app.php`
- `docs/api/biz-customer-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\CustomerController.php`: passed.
- `php -l app\service\biz\CustomerService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; customer `1780652225237444593` was added, edited, version-incremented, and logically deleted with `DELETE_FLAG = DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 322 route entries; customer `add`, `edit`, and `delete` listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 232 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP smoke for `POST /biz/customer/add`: returned `code=401`.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- `/biz/customer/head/edit` remains deferred.
- SM4 plaintext phone/detail-address compatibility remains deferred pending an approved crypto compatibility plan.
- File upload/storage cleanup, Java data-change events, sale-project/customer side effects, and customer ownership reassignment remain deferred.
- The smoke test intentionally leaves a logically deleted customer row in the local database for traceability instead of physically deleting data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this customer base maintenance compatibility slice.
- Continue the next isolated business compatibility slice, likely a safe Java-exposed frontend write with limited side effects, before opening sale-project state, finance, inventory, or workflow transition writes.

## 2026-06-05 17:48 +08:00 - api-agent/frontend-agent - Supplier Base Maintenance Compatibility

### Completed

- Added protected compatibility routes for supplier add, edit, and delete.
- Added `SupplierController` POST handlers with JSON/form body and Java-style delete payload compatibility.
- Added `SupplierService` base supplier maintenance logic for Java-required validation, whitelisted field mapping, lower-case `org` column preservation, write-scope validation, audit fields, default `ENABLE` status, and logical deletion.
- Updated supplier API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/SupplierController.php`
- `app/service/biz/SupplierService.php`
- `route/app.php`
- `docs/api/biz-supplier-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\SupplierController.php`: passed.
- `php -l app\service\biz\SupplierService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; supplier `1780652856134702052` was added with default `ENABLE`, edited, and logically deleted with `DELETE_FLAG = DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 325 route entries; supplier `add`, `edit`, and `delete` listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 232 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP smoke for `POST /biz/supplier/add`: returned `code=401`.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Supplier import/export remains deferred.
- Purchase, payment, procurement, inventory, workflow, and other supplier side effects remain deferred.
- The smoke test intentionally leaves a logically deleted supplier row in the local database for traceability instead of physically deleting data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this supplier base maintenance compatibility slice.
- Continue another isolated low-risk master-data or page-local write endpoint before opening sale-project state, finance, inventory, or workflow transition writes.

## 2026-06-06 09:05 +08:00 - api-agent/frontend-agent - Warehouse Base Maintenance Compatibility

### Completed

- Added protected compatibility routes for warehouse add, edit, and delete.
- Added `WarehousesController` POST handlers with JSON/form body parsing and Java-style delete payload compatibility.
- Added `WarehousesService` base warehouse maintenance logic for SQL-required `name`/`code` validation, whitelisted field mapping, token owner/org defaults, admin/scoped-org/owner write-scope validation, audit fields, and logical deletion.
- Updated warehouse API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.
- Started the local backend on `http://127.0.0.1:82/` and the copied Vue frontend on `http://127.0.0.1:83/` for joint smoke testing.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/WarehousesController.php`
- `app/service/biz/WarehousesService.php`
- `route/app.php`
- `docs/api/biz-warehouses-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\WarehousesController.php`: passed.
- `php -l app\service\biz\WarehousesService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; warehouse `1780707586896778747` was added, edited, and logically deleted with `DELETE_FLAG = DELETED`; one earlier smoke row with an overlong test `CODE` was also logically deleted.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 327 route entries; warehouse `add`, `edit`, and `delete` listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 232 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP smoke for `POST /biz/warehouses/add`: returned business `code=401`.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Inventory stock updates, delivery records, purchase-order writes, sale-project invoice writes, and workflow side effects remain deferred by design.
- File upload/storage, notifications, and Java data-change event side effects remain deferred.
- The smoke test intentionally leaves logically deleted warehouse rows in the local database for traceability instead of physically deleting data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this warehouse base maintenance compatibility slice.
- Continue the next isolated low-risk frontend-visible write or route cleanup slice before opening stock, finance, workflow, or sale-project state-transition writes.

## 2026-06-06 09:25 +08:00 - api-agent/frontend-agent - Product Status And Reconciliation Compatibility

### Completed

- Added protected compatibility routes for product status toggling and selected-product reconciliation edits.
- Added `ProductController` POST handlers with JSON/form body parsing.
- Added `ProductService` lightweight product write logic for `status`, `RECONCILIATION_TYPE`, `RECONCILIATION_AMOUNT`, write-scope validation, non-negative amount validation, and update audit fields.
- Updated product API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.
- Kept the local backend on `http://127.0.0.1:82/` and the copied Vue frontend on `http://127.0.0.1:83/` for joint smoke testing.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/ProductController.php`
- `app/service/biz/ProductService.php`
- `route/app.php`
- `docs/api/biz-product-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\ProductController.php`: passed.
- `php -l app\service\biz\ProductService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; smoke product `1780709036278310490` was inserted for testing, status was changed to `DISABLE`, reconciliation fields were updated to `ENABLE` and `12.34`, then the smoke product was logically deleted with `DELETE_FLAG = DELETED`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 329 route entries; product `edit/status` and `reconciliation/edit` listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 232 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP smoke for `POST /biz/bizproduct/edit/status`: returned business `code=401`.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Product add, edit, delete, and kit product relation writes remain deferred by design.
- Inventory, purchase, sale-project, finance transaction, workflow, file upload/storage, and Java data-change/cache event behavior remain deferred.
- The smoke test intentionally leaves one logically deleted product row in the local database for traceability instead of physically deleting data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this product status/reconciliation compatibility slice.
- Continue with another isolated low-risk frontend-visible route, or split product base add/edit/delete plus kit relation writes into a separate plan before touching it.

## 2026-06-06 09:41 +08:00 - api-agent/frontend-agent - Product Base Maintenance Compatibility

### Completed

- Added protected compatibility routes for product add, edit, and delete.
- Added `ProductController` POST handlers with JSON/form body parsing and Java-style delete payload compatibility.
- Added `ProductService` base product maintenance logic for Java-required field validation, status/default audit fields, tenant/org defaults, write-scope validation, kit-product child validation, `product_relation.CATEGORY = KIT_PRODUCT_DATA` clear-and-replace, referenced-child delete blocking, and logical deletion.
- Updated product API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.
- Kept the local backend on `http://127.0.0.1:82/` and the copied Vue frontend on `http://127.0.0.1:83/` for joint smoke testing.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/ProductController.php`
- `app/service/biz/ProductService.php`
- `route/app.php`
- `docs/api/biz-product-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\ProductController.php`: passed.
- `php -l app\service\biz\ProductService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; single product `1780709947044271606` was added, edited, and logically deleted; kit product `1780709947533366689` was added with two child relations, rejected deletion of referenced child product `1843547479813316610`, replaced kit relations with one quantity-3 child relation, then was logically deleted. The generated smoke kit relation rows were physically cleaned because they belonged only to this temporary test product object.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 332 route entries; product `add`, `edit`, `delete`, `edit/status`, and `reconciliation/edit` are listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 232 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP smoke for `POST /biz/bizproduct/add`: returned business `code=401`.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Inventory stock updates, purchase-order writes, sale-project item writes, finance transaction writes, workflow actions, file upload/storage implementation, and Java data-change/cache events remain deferred by design.
- The smoke test intentionally leaves logically deleted product rows in the local database for traceability instead of physically deleting imported-style data.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this product base maintenance compatibility slice.
- Continue the next isolated frontend-visible write/read compatibility slice, avoiding stock, finance, workflow, and sale-project state side effects until their module-specific plans are opened.

## 2026-06-06 10:01 +08:00 - api-agent/frontend-agent - Sale Project Product Mark Compatibility

### Completed

- Added protected compatibility route `POST /biz/saleprojectproductitemrelation/mark/edit`.
- Added protected compatibility route `POST /biz/saleprojectproductitem/mark/edit`.
- Added `SaleProjectProductItemRelationController.editMark` and `SaleProjectProductItemRelationService.editMark`.
- Added tiny `SaleProjectProductItemController` and `SaleProjectProductItemService` for product-item `MARK` writes only.
- Validated both writes through the owning active sale project with admin-compatible, data-scope org, or project-user visibility.
- Updated API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
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

### Test Results

- `php -l app\controller\biz\SaleProjectProductItemRelationController.php`: passed.
- `php -l app\controller\biz\SaleProjectProductItemController.php`: passed.
- `php -l app\service\biz\SaleProjectProductItemRelationService.php`: passed.
- `php -l app\service\biz\SaleProjectProductItemService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; sampled product item `2007746037914529793` and relation `2007746037960667138` were updated and then restored to their original `MARK` values.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 334 route entries; both mark-edit routes are listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 234 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP smoke for both mark-edit routes returned business `code=401`.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Product item add/edit/delete, delivery, invoice, return, inventory, finance, workflow, sale-project state changes, and Java data-change/cache events remain deferred by design.
- The smoke test restored sampled imported rows to their original `MARK` values and did not leave test data behind.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this sale-project product mark compatibility slice.
- Continue another isolated frontend-visible endpoint, still avoiding stock, finance, workflow, and state-transition side effects until their module plans are explicitly opened.

## 2026-06-06 10:09 +08:00 - api-agent/frontend-agent - Customer Head Reassignment Compatibility

### Completed

- Added protected compatibility route `POST /biz/customer/head/edit`.
- Added `CustomerController.headEdit`.
- Added `CustomerService.headEdit` for Java-compatible customer owner reassignment.
- Validated current-token customer write scope before reassignment.
- Validated target users through admin-compatible roles, data-scope organization ids, or current-user fallback.
- Updated customer API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/biz/CustomerController.php`
- `app/service/biz/CustomerService.php`
- `route/app.php`
- `docs/api/biz-customer-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\biz\CustomerController.php`: passed.
- `php -l app\service\biz\CustomerService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; sampled customer `2007641838392315905` was reassigned to user `1543837863788879871` and org `1543842934270394368`, then restored to its original `USER`, `ORG`, `VERSION`, `UPDATE_TIME`, and `UPDATE_USER` values.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 335 route entries; `/biz/customer/head/edit` is listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 234 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- No-token HTTP smoke for `/biz/customer/head/edit`: returned business `code=401`.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Customer import/export, file upload/storage cleanup, SM4 plaintext search, sale-project/customer side effects, notifications, and Java data-change events remain deferred by design.
- The smoke test restored sampled imported customer ownership fields and did not leave test data behind.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit and push this customer head reassignment compatibility slice.
- Continue another isolated frontend-visible endpoint while keeping workflow, stock, finance, and sale-project state transitions behind explicit module plans.

## 2026-06-06 10:55 +08:00 - user-agent/frontend-agent - User Center Self-Service Writes

### Completed

- Added protected compatibility routes for current-user personal-center writes.
- Added `UserCenterWriteService` for password, avatar, signature, profile, workbench, and process-config edits.
- Added `/biz/user/center/edit` as a self-profile alias matching Java `BizUserController.editUser` behavior by forcing the current token user id.
- Updated user-center API docs, business directory alias docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/UserCenterController.php`
- `app/service/user/UserCenterWriteService.php`
- `route/app.php`
- `docs/api/user-center-readonly-compat.md`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\UserCenterController.php`: passed.
- `php -l app\service\user\UserCenterWriteService.php`: passed.
- `php -l route\app.php`: passed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 342 route entries; all new user-center routes are listed.
- Strict PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for `/sys/userCenter/updateSignature` and `/biz/user/center/edit`: returned business `code=401`.
- Authenticated wrong-password smoke for `/sys/userCenter/updatePassword`: login returned `code=200`; password update returned `code=401` and did not modify the password.

### Current Issues

- Avatar compatibility stores a bounded base64 data URI; full file-provider storage and cleanup remain deferred.
- Java SM4 encrypted-field migration for phone and identity fields remains deferred.
- Admin-side user CRUD, grants, reset-password-by-admin, import/export, and enable/disable remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user-center self-service compatibility slice.
- Continue with another isolated frontend-visible slice, or start data-scope/permission tightening before heavy finance, stock, workflow, and sale-project state writes.

## 2026-06-06 11:12 +08:00 - user-agent/frontend-agent - User Message Detail Mark-Read Compatibility

### Completed

- Added Java-compatible read-state behavior to `GET /sys/userCenter/loginUnreadMessageDetail`.
- Kept the existing route unchanged and protected by current auth middleware.
- Marked only the current token user's `dev_relation` receiver row for `CATEGORY = MSG_TO_USER` as `read = true`.
- Preserved `dev_message` and all other recipients' relations.
- Updated user-center API docs, frontend adaptation notes, API gap map, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/service/user/UserDirectoryService.php`
- `docs/api/user-center-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\service\user\UserDirectoryService.php`: passed.
- Direct service smoke: passed; sampled unread message relation was marked read in the returned detail and receiver info, database `EXT_JSON` changed to `read=true`, then the original `EXT_JSON` was restored.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 342 route entries; no route changes were made.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for `/sys/userCenter/loginUnreadMessageDetail`: returned business `code=401`.

### Current Issues

- Message send/delete, all-mark-read, WebPush, and full realtime push remain deferred.
- Admin-side user CRUD, grants, reset-password-by-admin, import/export, encrypted profile fields, and full file-provider storage remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user message detail mark-read compatibility slice.
- Continue with another isolated frontend-visible user/system compatibility endpoint, or move to data-scope and permission tightening before heavier finance, stock, workflow, and sale-project state writes.

## 2026-06-06 11:21 +08:00 - user-agent/frontend-agent - Index Message All-Mark-Read Compatibility

### Completed

- Added protected compatibility route `POST /sys/index/message/allMessageMarkRead`.
- Added homepage index controller and service handlers for all-message mark-read.
- Added `UserDirectoryService.markAllMessagesRead` for current-user `MSG_TO_USER` relation updates.
- Preserved existing valid `EXT_JSON` keys while setting `read = true`.
- Updated index API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/IndexController.php`
- `app/service/sys/IndexService.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/index-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\IndexController.php`: passed.
- `php -l app\service\sys\IndexService.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; sampled current-user unread relation changed to read, then original `EXT_JSON` was restored.
- Initial broader smoke with a larger sample timed out before completion, so a smaller deterministic sample was used and restored successfully.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 343 route entries; `/sys/index/message/allMessageMarkRead` is listed.
- Strict PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for `/sys/index/message/allMessageMarkRead`: returned business `code=401`.

### Current Issues

- Message send/delete, WebPush, and full realtime push remain deferred.
- Schedule add/delete remains deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this index message all-mark-read compatibility slice.
- Continue with another isolated frontend-visible endpoint or start targeted data-scope/permission tightening before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 11:34 +08:00 - user-agent/frontend-agent - Index Schedule Self-Service Compatibility

### Completed

- Added protected compatibility routes `POST /sys/index/schedule/add` and `POST /sys/index/schedule/deleteSchedule`.
- Added homepage index controller and service handlers for current-user schedule add/delete.
- Stored schedule rows in `sys_relation` with `CATEGORY = SYS_USER_SCHEDULE_DATA`, `OBJECT_ID = current user`, and `TARGET_ID = scheduleDate`.
- Delete accepts Java-style array bodies, `idList`, `ids`, or a single `id`, and is constrained to current-user schedule rows.
- Updated index API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/IndexController.php`
- `app/service/sys/IndexService.php`
- `route/app.php`
- `docs/api/index-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\IndexController.php`: passed.
- `php -l app\service\sys\IndexService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; one temporary current-user schedule row was added, listed, deleted, and confirmed with zero residual rows.
- `composer dump-autoload`: passed.
- `php think`: passed.
- `php think route:list`: passed, 345 route entries; both schedule write routes are listed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for `/sys/index/schedule/add` and `/sys/index/schedule/deleteSchedule`: returned business `code=401`.

### Current Issues

- Shared calendars, schedule editing, schedule notifications, and cross-user schedule management remain deferred.
- Message send/delete, WebPush, and full realtime push remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this index schedule self-service compatibility slice.
- Continue with another low-risk frontend-visible route or begin targeted data-scope/permission tightening before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 12:08 +08:00 - auth-agent/frontend-agent - Session And Token Exit Compatibility

### Completed

- Added protected Java-compatible auth monitor exit routes:
  - `POST /auth/session/b/exit`
  - `POST /auth/session/c/exit`
  - `POST /auth/token/b/exit`
  - `POST /auth/token/c/exit`
- Added cache-backed B-side token indexing in `TokenService` for tokens created after this slice.
- Added B-side session exit by user id and token exit by token value in `SessionMonitorService`.
- Kept C-side exit endpoints as success-compatible no-op responses until C-side client auth is implemented.
- Limited ordinary users to their own user id/token while allowing admin-compatible accounts or roles to manage indexed B-side sessions.
- Updated auth API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/auth/SessionController.php`
- `app/service/auth/SessionMonitorService.php`
- `app/service/auth/TokenService.php`
- `route/app.php`
- `docs/api/auth-session-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\auth\SessionController.php`: passed.
- `php -l app\service\auth\SessionMonitorService.php`: passed.
- `php -l app\service\auth\TokenService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; the four exit routes are listed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- TokenService smoke: passed; temporary cache tokens were created, revoked by token value, revoked by user id, and confirmed removed.
- SessionMonitorService smoke: passed; B-side token exit, B-side session exit, and C-side deferred no-op responses behaved as expected.
- No-token HTTP smoke for all four exit routes: returned business `code=401`.
- Strict PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Existing tokens created before this slice are not globally indexed; they can still revoke themselves through logout or direct bearer token handling.
- C-side client auth/login/token storage remains deferred.
- Fine-grained route permission middleware for auth monitor access remains deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this auth session/token exit compatibility slice.
- Continue with another isolated frontend-visible route or start targeted permission/data-scope hardening before heavier workflow, finance, stock, and sale-project state writes.

## 2026-06-06 12:31 +08:00 - api-agent/frontend-agent - Dev Message Delete Compatibility

### Completed

- Added protected Java-compatible route `POST /dev/message/delete`.
- Added request body parsing for Java-style arrays of `{ id }`, `idList`, `ids`, or single `id`.
- Added `MessageService::delete` to remove `MSG_TO_USER` receiver relations and then delete selected `dev_message` rows.
- Added conservative delete scope: admin-compatible accounts/roles may delete tenant messages; ordinary users may delete only messages they created.
- Updated dev-message API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/dev/MessageController.php`
- `app/service/dev/MessageService.php`
- `route/app.php`
- `docs/api/dev-message-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\dev\MessageController.php`: passed.
- `php -l app\service\dev\MessageService.php`: passed.
- `php -l route\app.php`: passed.
- Direct service smoke: passed; one temporary `dev_message` row and one temporary `dev_relation` row were inserted, deleted, and confirmed with zero residual rows.
- `php think route:list`: passed; route entries now count 350 and `/dev/message/delete` is listed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for `/dev/message/delete`: returned business `code=401`.

### Current Issues

- `/dev/message/send` remains deferred.
- SSE/WebPush realtime push behavior remains minimal and deferred for full parity.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this dev-message delete compatibility slice.
- Continue with another small browser-visible compatibility endpoint or move into targeted permission/data-scope hardening before heavier workflow, finance, stock, and sale-project state writes.

## 2026-06-06 13:05 +08:00 - api-agent/frontend-agent - Dev Message Send Compatibility

### Completed

- Added protected Java-compatible route `POST /dev/message/send`.
- Added request body parsing for copied frontend JSON/body payloads.
- Added `MessageService::send` to create one station-message row and receiver relations.
- Added receiver parsing for string ids and selector objects containing `id`, `userId`, `value`, or `key`.
- Defaulted blank `content` to `subject` and blank `category` to `SYS`.
- Limited send access to admin-compatible accounts or roles until fine-grained route permission middleware is complete.
- Updated dev-message API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/dev/MessageController.php`
- `app/service/dev/MessageService.php`
- `route/app.php`
- `docs/api/dev-message-readonly-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\dev\MessageController.php`: passed.
- `php -l app\service\dev\MessageService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; `/dev/message/send` and `/dev/message/delete` are listed.
- Direct service smoke: passed; one temporary `dev_message` row and one temporary `dev_relation` row were inserted, verified, deleted, and confirmed with zero residual rows.
- No-token HTTP smoke for `/dev/message/send`: returned business `code=401`.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.

### Current Issues

- Full SSE/WebPush realtime push behavior remains deferred for parity with Java notification side effects.
- Fine-grained route permission middleware for dev-message send remains deferred; current guard uses admin-compatible account/role detection.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this dev-message send compatibility slice.
- Continue with targeted permission/data-scope tightening or the next isolated browser-visible compatibility endpoint before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 13:36 +08:00 - api-agent/frontend-agent - Dev Message Detail Mark-Read Compatibility

### Completed

- Aligned `GET /dev/message/detail` with Java detail read-state behavior.
- Passed the current auth payload into `MessageService::detail`.
- Marked only the current token user's `MSG_TO_USER` receiver relation as read when viewing message detail.
- Preserved existing relation `EXT_JSON` keys while setting `read = true`.
- Kept the existing route and response shape unchanged.
- Updated dev-message API docs, frontend adaptation notes, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/dev/MessageController.php`
- `app/service/dev/MessageService.php`
- `docs/api/dev-message-readonly-compat.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\dev\MessageController.php`: passed.
- `php -l app\service\dev\MessageService.php`: passed.
- `php think route:list`: passed; `/dev/message/detail`, `/send`, and `/delete` are listed.
- Direct service smoke: passed; one temporary message relation changed from `read=false` to `read=true` on detail read, then all temporary rows were removed.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200 after restarting the ThinkPHP local server.
- Frontend `http://127.0.0.1:83/`: HTTP 200 after restarting the Vite local server.

### Current Issues

- Full SSE/WebPush realtime push behavior remains deferred for parity with Java notification side effects.
- Fine-grained route permission middleware remains deferred.
- Vite generated an untracked temporary config file during local frontend startup; it was not committed or deleted.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this dev-message detail mark-read compatibility slice.
- Continue with targeted permission/data-scope tightening or the next isolated browser-visible compatibility endpoint before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 12:55 +08:00 - user-agent/frontend-agent - User Role Grant Save Compatibility

### Completed

- Added protected Java-compatible role grant save routes:
  - `POST /sys/user/grantRole`
  - `POST /biz/user/grantRole`
- Added controller handlers for system and business user role grant saves.
- Added `UserDirectoryService::grantRole` to clear and rewrite `SYS_USER_HAS_ROLE` relations for a target user.
- Validated active users, active tenant-compatible role ids, admin-compatible payloads, route/button permission payloads, and business data-scope/self fallback.
- Kept empty `roleIdList` as a supported clear operation.
- Updated grant API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/sys-user-grant-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\UserController.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; `/sys/user/grantRole` and `/biz/user/grantRole` are listed.
- Direct service smoke: passed; one active user's roles were replaced with one active tenant-compatible role, then cleared through the business-scope path, and the original `SYS_USER_HAS_ROLE` relation rows were restored.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for both new POST routes: returned business `code=401`.

### Current Issues

- Resource and permission grant save endpoints remain deferred.
- Admin-side user CRUD, enable/disable, reset-password-by-admin, import/export, and encrypted profile-field migration remain deferred.
- Fine-grained route permission middleware remains deferred; this slice uses payload-based admin/route/button guards.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user role grant save compatibility slice.
- Continue with another small frontend-visible compatibility route or begin targeted permission/data-scope tightening before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 13:20 +08:00 - user-agent/frontend-agent - User Resource Grant Save Compatibility

### Completed

- Added protected Java-compatible resource grant save route:
  - `POST /sys/user/grantResource`
- Added controller handler for system user resource grant saves.
- Added `UserDirectoryService::grantResource` to clear and rewrite `SYS_USER_HAS_RESOURCE` relations for a target user.
- Preserved Java-compatible `EXT_JSON` with `menuId` and `buttonInfo`.
- Validated active users, active menu/button resources, admin-compatible payloads or route/button permission payloads, and Java's system-module/super-admin target safeguard.
- Kept empty `grantInfoList` as a supported clear operation.
- Updated grant API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/sys-user-grant-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\UserController.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; `/sys/user/grantResource` is listed.
- Direct service smoke: passed; one active user's resource grants were replaced with one non-system menu grant and button info, then the original `SYS_USER_HAS_RESOURCE` relation rows were restored.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for the new POST route: returned business `code=401`.

### Current Issues

- Permission grant save endpoint remains deferred.
- Role resource grants, mobile resource grants, admin-side user CRUD, enable/disable, reset-password-by-admin, import/export, and encrypted profile-field migration remain deferred.
- Fine-grained route permission middleware remains deferred; this slice uses payload-based admin/route/button guards.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user resource grant save compatibility slice.
- Continue with user permission grant save compatibility, or pause user grants and move to targeted permission/data-scope hardening before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 13:45 +08:00 - user-agent/frontend-agent - User Permission Grant Save Compatibility

### Completed

- Added protected Java-compatible permission grant save route:
  - `POST /sys/user/grantPermission`
- Added controller handler for system user permission grant saves.
- Added `UserDirectoryService::grantPermission` to clear and rewrite `SYS_USER_HAS_PERMISSION` relations for a target user.
- Preserved Java-compatible `EXT_JSON` with `apiUrl`, `scopeCategory`, and `scopeDefineOrgIdList`.
- Validated active users, supported data-scope categories, custom organization ids, and admin-compatible payloads or route/button permission payloads.
- Kept empty `grantInfoList` as a supported clear operation.
- Updated grant API docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/sys-user-grant-readonly.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\UserController.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; `/sys/user/grantPermission` is listed.
- Direct service smoke: passed; one active user's permission grants were replaced with one API/data-scope grant, then the original `SYS_USER_HAS_PERMISSION` relation rows were restored.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for the new POST route: returned business `code=401`.

### Current Issues

- Route-permission middleware remains deferred; this slice uses payload-based admin/route/button guards.
- Role permission grants, admin-side user CRUD, enable/disable, reset-password-by-admin, import/export, and encrypted profile-field migration remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user permission grant save compatibility slice.
- Continue with targeted permission/data-scope hardening or move to the next low-risk frontend-visible write route before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 14:10 +08:00 - user-agent/frontend-agent - User Enable Disable Compatibility

### Completed

- Added protected Java-compatible user status routes:
  - `POST /sys/user/disableUser`
  - `POST /sys/user/enableUser`
  - `POST /biz/user/disableUser`
  - `POST /biz/user/enableUser`
- Added controller handlers for system and business user status switches.
- Added `UserDirectoryService::setUserStatus` to update only `sys_user.USER_STATUS`.
- Preserved business user data-scope guarding with organization scope or current-user fallback.
- Updated status API docs, biz directory alias docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
- `app/controller/sys/UserController.php`
- `app/service/user/UserDirectoryService.php`
- `route/app.php`
- `docs/api/biz-directory-alias-readonly.md`
- `docs/api/user-status-compat.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/public-file-change-request.md`
- `docs/tasks/refactor-progress-dashboard.md`

### Test Results

- `php -l app\controller\sys\UserController.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; all four status routes are listed, route rows count is 360.
- Direct service smoke: passed; one active user's status was changed through the system path, restored through the business path, and then confirmed restored to the original value.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/think`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for all four new POST routes: returned business `code=401`.

### Current Issues

- User add/edit/delete, reset-password-by-admin, import/export, token/session invalidation on status change, and route-permission middleware remain deferred.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user enable/disable compatibility slice.
- Continue with the next low-risk frontend-visible user write route, or move to targeted permission/data-scope hardening before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 14:35 +08:00 - user-agent/frontend-agent - User Reset Password Compatibility

### Completed

- Added protected Java-compatible reset-password routes:
  - `POST /sys/user/resetPassword`
  - `POST /biz/user/resetPassword`
- Added controller handlers for system and business user reset-password actions.
- Added `UserDirectoryService::resetPassword` to update only `sys_user.PASSWORD`.
- Reused the existing Java-compatible SM3 hasher for default password hashing.
- Preserved business user data-scope guarding with organization scope or current-user fallback.
- Kept default password value and generated hash out of API responses, test output, and documentation.
- Updated reset-password API docs, biz directory alias docs, user grant/status docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
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

### Test Results

- `php -l app\controller\sys\UserController.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; both reset-password routes are listed, route rows count is 362.
- Direct service smoke: passed; the configured default password record exists, one sampled active user's password was reset through both system and business paths, and the original password hash was restored after each path.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for both new POST routes on the backend root path: returned business `code=401`.

### Current Issues

- User add/edit/delete, import/export, token/session invalidation after reset, route-permission middleware, and encrypted profile-field migration remain deferred.
- Direct backend test path is the current PHP server root path; `/think/...` returns a ThinkPHP 404 in this local server mode, while the frontend proxy can still apply its own prefix behavior.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user reset-password compatibility slice.
- Continue with a focused user CRUD planning slice or targeted permission/data-scope hardening before side-effect-heavy workflow, finance, stock, and sale-project state writes.

## 2026-06-06 15:05 +08:00 - user-agent/frontend-agent - User Delete Compatibility

### Completed

- Added protected Java-compatible user delete routes:
  - `POST /sys/user/delete`
  - `POST /biz/user/delete`
- Added controller handlers for system and business user row-delete/batch-delete actions.
- Added `UserDirectoryService::deleteUsers` to logically delete only `sys_user` rows by setting `DELETE_FLAG = DELETED`.
- Added payload compatibility for copied frontend array deletes and common `id`, `ids`, `idList`, and `userIds` forms.
- Added Java-compatible cleanup for `sys_user.DIRECTOR_ID`, `sys_user.POSITION_JSON[*].directorId`, and `sys_org.DIRECTOR_ID`.
- Preserved business user data-scope guarding with organization scope or current-user fallback.
- Rejected built-in/admin-compatible accounts from deletion.
- Updated user-delete API docs, biz directory alias docs, user grant/status docs, frontend adaptation notes, API gap map, public route-change request, progress dashboard, implementation notes, and active plan status.

### Modified Files

- `IMPLEMENT.md`
- `PLANS.md`
- `STATUS.md`
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

### Test Results

- `php -l app\controller\sys\UserController.php`: passed.
- `php -l app\service\user\UserDirectoryService.php`: passed.
- `php -l route\app.php`: passed.
- `php think route:list`: passed; both delete routes are listed, route rows count is 364.
- Direct service smoke: passed; one sampled non-admin active user was logically deleted through both system and business paths, affected user and organization director references were cleared, `POSITION_JSON` supervisor data was cleaned, and all touched values were restored.
- `composer dump-autoload`: passed.
- `php think`: passed.
- Strict PHP lint for `app`, `config`, and `route`: passed, 235 files.
- `git diff --check`: passed with CRLF conversion warnings only.
- Backend `http://127.0.0.1:82/`: HTTP 200.
- Frontend `http://127.0.0.1:83/`: HTTP 200.
- No-token HTTP smoke for both new POST routes on the backend root path: returned business `code=401`.

### Current Issues

- User add/edit, import/export, token/session invalidation after delete, Java data-change event publishing, route-permission middleware, and encrypted profile-field migration remain deferred.
- Direct backend test path is the current PHP server root path; `/think/...` returns a ThinkPHP 404 in this local server mode, while the frontend proxy can still apply its own prefix behavior.
- Full online realtime production data sync remains deferred until the complete ThinkPHP system is finished and the user confirms the sync plan.

### Next Plan

- Commit this user delete compatibility slice.
- Continue with a focused user add/edit planning slice, because that path needs broader field validation, default-password hashing, org/position validation, uniqueness checks, and role grant coordination.
