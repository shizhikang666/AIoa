锘块敇鍧楁晣? IMPLEMENT.md

## db-agent Implementation Flow

Every db-agent phase must follow this order:

1. Read `AGENTS.md`, `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md`.
2. Check `git status --short --branch`.
3. Analyze Java SQL/entity/mapper files from `F:\AI\projects\testJava\OA` as read-only input.
4. Analyze existing ThinkPHP files in `F:\AI\projects\testJava\OA-db`.
## auth-agent Implementation Flow

Every auth-agent phase must follow this order:

1. Read `AGENTS.md`, `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md`.
2. Check `git status --short --branch`.
3. Analyze Java auth/sys source files from `F:\AI\projects\testJava\OA` as read-only input.
4. Analyze the current ThinkPHP files in `F:\AI\projects\testJava\OA-auth`.
5. Write the smallest safe change set.
6. Avoid public locked files unless a change request is written and confirmed.
7. Run required tests:
# workflow-agent Implementation Log

## Phase 1 Procedure

Date: 2026-05-28

### 1. Analyze Java Original Code

Read-only sources:

- `bpmn/*.bpmn`
- `bpmn/personnel/Process_ask_leave.bpmn`
- `snowy-plugin-biz/.../bizprocess/controller/BizProcessController.java`
- `snowy-plugin-biz/.../bizprocess/controller/BizProcessProjectController.java`
- `snowy-plugin-biz/.../bizprocess/controller/BizTaskController.java`
- `snowy-plugin-biz/.../bizprocess/service/impl/BizProcessServiceImpl.java`
- `snowy-plugin-biz/.../bizprocess/service/impl/BizProjectProcessServiceImpl.java`
- `snowy-plugin-biz/.../bizprocess/service/impl/BizTaskServiceImpl.java`
- `snowy-plugin-biz/.../bizprocess/service/impl/BizBaseProcessServiceImpl.java`
- `snowy-plugin-biz/.../bizprocess/provider/ProcessApiProvider.java`
- `snowy-plugin-biz/.../bizprocess/annotation/*`
- `snowy-plugin-biz/.../bizprocess/aspect/*`
- `snowy-plugin-biz/.../bizprocess/enums/*`
- `snowy-plugin-sys/.../userprocessconfig/*`
- `oa2026.sql`

### 2. Analyze Current ThinkPHP Project

This phase does not create PHP workflow classes. The current worktree is used only for docs and status tracking.

### 3. Minimal Change

Create the workflow analysis documents and phase status files only.

### 4. Test

Run baseline commands after document generation:

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

8. Run `git status --short --branch`.
9. Run `git add .`.
10. Run `git commit -m "db-agent: <clear summary>"`.
10. Run `git commit -m "auth-agent: <clear summary>"`.
11. Append completion status to `STATUS.md`.
12. Report completed content, modified files, test results, current issues, and next plan.

## Public File Change Request Rule

The following files are locked for db-agent by default:
The following files are locked for auth-agent by default:
# IMPLEMENT.md

## user-agent Implementation Flow

Every user-agent phase must follow:

1. Read `AGENTS.md`, `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md`.
2. Check `git status --short --branch`.
3. Analyze Java source under `F:\AI\projects\testJava\OA` as read-only input.
4. Analyze the current ThinkPHP worktree under `F:\AI\projects\testJava\OA-user`.
5. Write the smallest safe change set.
6. Avoid locked public files unless a change request is written and confirmed.
7. Run baseline checks.
8. Commit with a message containing `user-agent`.
# IMPLEMENT.md

## api-agent Implementation Flow

Every api-agent phase must follow:

1. Read `AGENTS.md`, `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md`.
2. Check `git status --short --branch`.
3. Analyze Java controller source under `F:\AI\projects\testJava\OA` as read-only input.
4. Analyze the current ThinkPHP worktree under `F:\AI\projects\testJava\OA-api`.
5. Write the smallest safe change set.
6. Avoid locked public files unless a change request is written and confirmed.
7. Run baseline checks.
8. Commit with a message containing `api-agent`.
9. Report modified files, tests, current issues, and next plan.

## Scope

user-agent owns:

- users
- departments and organizations
- positions
- user-center profile APIs
- user selectors and organization trees

user-agent must not own:

- login, token, RBAC session state, or menu permissions already handled by auth-agent
- workflow engine logic
- frontend adaptation
- database schema changes
api-agent owns:

- Java Controller inventory
- ThinkPHP Controller mapping plans
- API response standardization
- route grouping proposals
- request/response compatibility notes
- integration order for controller migration

api-agent must not own:

- database schema or model generation
- login, token, RBAC, menu, or permission service logic
- user/organization/position service logic
- workflow engine logic
- frontend component changes

## Public File Rule

The following files are locked:

- `composer.json`
- `composer.lock`
- `config/app.php`
- `config/database.php`
- `route/app.php`
- `.env`
- `.env.example`
- `app/common.php`

If a future db-agent phase needs one of these files, create:

`F:\AI\projects\testJava\OA-db\docs\tasks\public-file-change-request.md`

Then wait for confirmation before editing the locked file.

## Model Generation Rule

Foundation Models created by db-agent must:

- preserve physical table names
- preserve database column spelling and casing
- use comments to record Java entity/source table relations
- avoid controller/service logic
- avoid query behavior that belongs to auth-agent, user-agent, workflow-agent, or api-agent

If auth-agent needs one of these files, create:

`F:\AI\projects\testJava\OA-auth\docs\tasks\public-file-change-request.md`

Then wait for confirmation before editing the locked file.

## Auth Scope Rule

auth-agent may work on:

- login
- Token
- Redis-backed auth/session state
- RBAC
- menu and button permission lookup
- auth middleware

auth-agent must not implement:

- user CRUD or organization management
- workflow business logic
- frontend adaptation
- non-auth controllers and services
- database schema changes

## Token + Redis Rule

- Use `Authorization: Bearer <token>`.
- Store Token/session state with Redis-compatible key prefix `oa:auth:`.
- Do not commit Redis credentials, API keys, passwords, or plaintext secrets.
- Token payload must not contain plaintext password or sensitive identity data.
If a route or config change is needed, document it in `docs/tasks/public-file-change-request.md` and wait for confirmation.
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

### 5. Git

After tests:

```powershell
git status --short --branch
git add .
git commit -m "workflow-agent: add workflow analysis plan"
```

### 6. Report

Report:

- modified files
- Java modules analyzed
- SQL tables analyzed
- test results
- current problems
- next phase recommendation
If route registration is needed, document it in `docs/tasks/public-file-change-request.md` and wait for confirmation.
