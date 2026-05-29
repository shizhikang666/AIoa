# PLANS.md

## Completed Plan: api-agent Phase 1 - Controller Inventory And Route Boundary

Status: completed on 2026-05-28.

### Current Goal

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
- Do not modify locked public files in Phase 1.
- Do not create ThinkPHP Controller implementations in Phase 1.
- Do not implement auth, user, workflow, frontend, or database business logic.
- Do not delete fields, tables, seed data, or routes.

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
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```
