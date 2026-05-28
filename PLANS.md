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

- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`
- `docs/tasks/test-agent-baseline.md`
- `docs/tasks/test-agent-risk-list.md`

### 4. Risks

- Module branches may diverge and produce route, namespace, or model conflicts during final merge.
- `php think route:list` may fail after route files are changed by later agents.
- Composer dependency drift can appear if multiple branches edit dependency files.
- Database/model changes from db-agent may be required before auth/user/workflow tests are meaningful.

### 5. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
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
