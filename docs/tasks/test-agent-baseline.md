# test-agent Baseline Test Plan

## Purpose

This document records the first test-agent baseline for the ThinkPHP OA refactor. It is used to keep later db/auth/user/workflow/api/frontend/docs branch merges measurable and repeatable.

## Scope

The baseline phase checks only project health. It does not repair business code and does not change application behavior.

## Worktree

- Agent: test-agent
- Worktree: `F:\AI\projects\testJava\OA-test`
- Branch: `refactor/test`
- Java source project, read-only: `F:\AI\projects\testJava\OA`
- Final integration project: `F:\AI\projects\testJava\OA-ThinkPHP`

## Baseline Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

Optional when the command exists:

```powershell
php think test
```

## Baseline Checks

| Check | Purpose | Expected Result |
| --- | --- | --- |
| Composer autoload | Validate dependency autoload generation | Command exits successfully |
| ThinkPHP console | Validate application bootstrap | Command exits successfully |
| Route list | Validate route registration | Command exits successfully |
| PHP lint | Validate syntax for app/config/route | No syntax errors |
| Optional tests | Run project tests if configured | Command exits successfully |

## Later Merge Test Items

### db-agent Merge

- Confirm model namespaces load.
- Confirm no syntax errors in generated models.
- Confirm database docs match model files.
- Confirm no database field deletion is introduced.

### auth-agent Merge

- Confirm login routes are registered.
- Confirm Token services load without Redis hard failure.
- Confirm RBAC service namespaces and database table names are valid.
- Confirm no secret, private key, or plaintext password is committed.

### user-agent Merge

- Confirm user, department, position, and organization routes register.
- Confirm user module does not overwrite auth permission files.
- Confirm model relations do not conflict with db-agent naming.

### workflow-agent Merge

- Confirm workflow routes register.
- Confirm process model namespaces load.
- Confirm workflow database dependencies are declared in docs.

### api-agent Merge

- Confirm Java Controller to ThinkPHP Controller mapping remains consistent.
- Confirm API response format stays unified.
- Confirm route names do not collide across agents.

### frontend-agent Merge

- Confirm frontend API paths match backend routes.
- Confirm Token header convention uses `Authorization: Bearer <token>`.
- Confirm menu and button permission integration points are documented.

### docs-agent Merge

- Confirm docs do not contradict implementation branch behavior.
- Confirm deployment and API notes include latest merge state.

## Baseline Results

Recorded on 2026-05-28 in `F:\AI\projects\testJava\OA-test`.

| Command | Result | Notes |
| --- | --- | --- |
| `composer dump-autoload` | Passed after install | Initial run failed because `vendor` was incomplete and `think\App` was missing |
| `composer install --no-interaction --prefer-dist` | Passed | Installed dependencies from `composer.lock`; no tracked application files changed |
| `php think` | Passed | ThinkPHP version `8.1.4` |
| `php think route:list` | Passed | Current baseline routes: `think`, `hello/<name>` |
| PHP lint for `app`, `config`, `route` | Passed | No syntax errors detected |
| `php think test` | Not available | Current ThinkPHP console command list does not include `test` |

## Baseline Status

The test-agent worktree is healthy for baseline purposes. It is ready to be used as a test planning branch, and the same command set should be rerun after each future merge checkpoint.
