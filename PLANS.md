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
