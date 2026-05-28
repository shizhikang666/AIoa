# PLANS.md

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
- Do not modify database schema or seed data.
- Do not implement user CRUD, organization management, workflow, frontend, SMS sending, or web push behavior.
- Do not modify locked config files other than the confirmed `route/app.php`.

### Test Commands

```powershell
composer dump-autoload
php think
php think route:list
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
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```
