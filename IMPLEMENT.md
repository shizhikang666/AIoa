锘? IMPLEMENT.md

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
