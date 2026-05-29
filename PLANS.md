# PLANS.md

## Completed Plan: user-agent Phase 1 - User Organization Analysis

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

### Forbidden Scope

- Do not modify Java source files.
- Do not modify locked public files.
- Do not implement auth, workflow, frontend, or unrelated business modules.
- Do not delete database fields or seed data.
- Do not add routes in Phase 1.

### Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

## Current Plan: user-agent Phase 2 - Read-Only Directory Services

Status: in progress on 2026-05-29.

### Current Goal

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

### Forbidden Scope

- Do not modify Java source files.
- Do not modify locked public files.
- Do not create ThinkPHP controllers or route entries in this phase.
- Do not implement auth, RBAC, menu, workflow, or frontend behavior.
- Do not change database fields or seed data.

### Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```
