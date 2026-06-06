# Frontend Joint Test Workflow

Date: 2026-06-01

Agent: merge-agent / main control agent

## Purpose

From this point forward, frontend adaptation is part of the refactor workflow. Backend API slices are still developed in small steps, but each completed backend slice must be considered against the Vue frontend and, once the frontend is imported into the final repository, tested with the backend service running.

Future new Codex conversations should use real multi-Agent mode by default. The main conversation is the merge/coordinator session. It assigns scoped work to role-specific workers such as `frontend-agent`, `api-agent`, `test-agent`, `docs-agent`, and other module Agents, then integrates and commits the final result. Worker Agents must only handle the explicitly assigned slice and must not broaden into merge ownership or unrelated modules.

The final delivery is still one complete project at:

`F:\AI\projects\testJava\OA-ThinkPHP`

The worktrees remain temporary parallel workspaces and are not final standalone projects.

## Current Frontend Discovery

| Item | Current State |
| --- | --- |
| Original frontend source | `F:\AI\projects\testJava\OA\snowy-admin-web` |
| Original frontend write policy | Read-only, do not edit |
| Target repository frontend path | `F:\AI\projects\testJava\OA-ThinkPHP\snowy-admin-web` |
| frontend-agent worktree | `F:\AI\projects\testJava\OA-frontend`, currently still contains the ThinkPHP backend tree only |
| Frontend dev script | `npm run dev` or `npm run serve` |
| Frontend default dev port | `83` from `.env.development` |
| Frontend default backend target | `http://localhost:82` from `.env.development` |

## Frontend Baseline Import

The frontend baseline has been copied into the final target repository.

Target:

`F:\AI\projects\testJava\OA-ThinkPHP\snowy-admin-web`

Import notes:

- Copied from `F:\AI\projects\testJava\OA\snowy-admin-web` in read-only mode.
- Copied files: 908.
- Excluded files/directories: `.git`, `.idea`, `.vite`, `node_modules`, `dist`, `coverage`, `log`, `logs`, `stats.html`, `*.log`, and `vite.config.mjs.timestamp-*.mjs`.
- The first frontend import is an approved baseline exception to the normal small-commit size because it brings the existing Vue app under the target repository.
- After the baseline import, all adaptation commits should return to small commits.
- Do not commit `node_modules`, `dist`, local logs, local secrets, or runtime cache.

## Joint Startup Order

Use this order for future integrated testing.

### 1. Start MySQL And Redis

```powershell
Start-Process -FilePath "F:\project\socket\AI\testPhp\files\startServer1.bat" -WorkingDirectory "F:\project\socket\AI\testPhp\files" -WindowStyle Hidden
```

Then verify services before running application tests.

### 2. Start ThinkPHP Backend

```powershell
cd F:\AI\projects\testJava\OA-ThinkPHP
php think run --host 127.0.0.1 --port 82
```

Backend port `82` matches the current original frontend development environment.

### 3. Start Vue Frontend

After frontend import and dependency installation:

```powershell
cd F:\AI\projects\testJava\OA-ThinkPHP\snowy-admin-web
npm install
npm run dev
```

Expected browser URL:

`http://127.0.0.1:83`

## Frontend Adaptation Items

frontend-agent owns these items after the baseline import:

- request base URL and Vite proxy alignment
- token header alignment
- login flow compatibility
- menu and button permission rendering
- API wrapper cleanup for endpoints already migrated to ThinkPHP
- read-only page smoke checks against current backend routes
- build and browser smoke documentation

Backend agents own these items:

- missing ThinkPHP routes
- controller/service behavior
- response shape compatibility
- permission middleware behavior
- write-flow implementation

Do not let frontend-agent modify backend business code unless a separate public-file or module request is approved.

## Token Compatibility Note

Project backend convention:

`Authorization: Bearer <token>`

Original frontend convention observed:

`token: <token>`

Future adaptation should make the frontend send the backend convention. For transition safety, backend may also accept the legacy `token` header if a compatibility slice explicitly documents and tests it.

## Login Compatibility Note

The original frontend may encrypt the login password with SM2 when a public key is configured. The backend already isolates password verification logic, but browser login must be tested end to end after frontend import.

No SM2 key, password, Redis credential, database password, or token may be committed to the repository.

For local login smoke tests, read the superadmin credentials from the ignored project `.env`:

- `LOCAL_SUPER_ADMIN_ACCOUNT`
- `LOCAL_SUPER_ADMIN_PASSWORD`

Do not place plaintext local account names, passwords, tokens, or generated credential values in this document, screenshots, task notes, commits, or final reports.

## Joint Smoke Checklist

Run this checklist after frontend import and after every backend route slice that affects visible pages.

| Step | Expected Result |
| --- | --- |
| Backend `composer dump-autoload` | Pass |
| Backend `php think` | Pass |
| Backend `php think route:list` | Pass |
| Backend PHP lint | Pass |
| Frontend `npm install` if dependencies missing | Pass |
| Frontend `npm run dev` | Starts on port `83` |
| Login as superadmin test account | Browser reaches main layout |
| Current user and menu load | No fatal error, menu renders |
| User/org/position read pages | Page loads read data or records missing endpoint |
| Business read pages | Product, supplier, warehouse, inventory, finance, team project, and return order reads load where implemented |
| Write buttons and mutation flows | Either hidden by permission/deferred state or fail safely until implemented |
| Browser console | No blocking runtime error on tested pages |

## Gap Recording Rule

Any frontend call that fails because the backend route is missing must be recorded in:

`docs/tasks/api-gap-map.md`

If that file does not exist yet, create it before the first full browser test.

## Definition Of Done For Frontend Phase

frontend-agent is not complete until:

- frontend source exists in the target repository
- local dev server starts
- production build succeeds or known build blockers are documented
- login works against ThinkPHP
- menu and button permissions render from ThinkPHP data
- main migrated read-only pages are browser-smoked
- missing write-flow calls are recorded and assigned
- no secrets or generated dependency directories are committed
