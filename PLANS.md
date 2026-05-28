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
