# PLANS.md

## Completed Plan: frontend-agent Phase 1 - Frontend API Adaptation Analysis

Status: completed on 2026-05-28.

### Current Goal

Start frontend-agent without modifying frontend or backend business code. Analyze the Java OA frontend API conventions and document the compatibility requirements for the ThinkPHP backend migration.

### Involved Modules

- frontend-agent worktree: `F:\AI\projects\testJava\OA-frontend`
- Java OA source, read-only: `F:\AI\projects\testJava\OA`
- Frontend source, read-only: `F:\AI\projects\testJava\OA\snowy-admin-web`
- Final ThinkPHP target: `F:\AI\projects\testJava\OA-ThinkPHP`

### Frontend Inputs

- `snowy-admin-web/package.json`
- `snowy-admin-web/src/config/index.js`
- `snowy-admin-web/src/utils/request.js`
- `snowy-admin-web/src/api/sys/userCenterApi.js`
- `snowy-admin-web/src/utils/permission/index.js`
- menu and permission usage under `snowy-admin-web/src/layout` and `snowy-admin-web/src/views`

### Risks

- The frontend source is inside the original Java project path and must remain read-only in this phase.
- The current frontend request helper stores token in `TOKEN` and sends a configurable token header.
- The ThinkPHP auth convention uses `Authorization: Bearer <token>`, while the current frontend config defaults to `TOKEN_NAME: 'token'` and an empty prefix.
- The frontend expects API responses with `code`, `msg`, and `data`; backend planning currently standardizes on `code`, `message`, and `data`, so compatibility must be decided.
- Menu and button permission rendering depends on `USER_INFO.buttonCodeList`, `MENU`, and `/sys/userCenter/loginMenu`.

### Forbidden Scope

- Do not modify Java source files or `snowy-admin-web`.
- Do not copy frontend code into the ThinkPHP worktree in Phase 1.
- Do not modify locked public backend files.
- Do not implement API adapters or route changes in Phase 1.
- Do not change authentication behavior owned by auth-agent.

### Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```
