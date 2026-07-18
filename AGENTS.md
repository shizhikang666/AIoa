# AGENTS.md

## Project Role

This repository uses a main architect Agent plus multiple module Agents.

Future new Codex conversations should default to the real multi-Agent mode for this project. The main conversation acts as the merge/coordinator session, and work is split into explicit worker roles such as `frontend-agent`, `api-agent`, `test-agent`, `docs-agent`, and other module Agents defined below.

New conversation startup details are tracked in `docs/tasks/new-conversation-bootstrap.md`. Use the lean startup packet there together with `docs/tasks/lean-continuation-workflow.md` before continuing project work. Read full `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md` only when targeted search or the current task requires deeper history.

The current main architect Agent is responsible for:

- architecture control
- branch and worktree planning
- module boundaries
- final merge planning
- conflict and integration strategy
- assigning scoped worker tasks
- integrating worker results into the final target project
- committing only after reviewing the combined changes

The main architect Agent may implement a small scoped slice directly when acting as the merge/coordinator and the user has asked to continue project execution. It must still preserve the multi-Agent discipline: define scope, use explorers/workers when available and useful, review the result, run acceptance checks, and commit only coherent reviewed changes.

Worker Agents must only operate inside their explicitly assigned task scope. They do not broaden the task, take over merge/coordinator responsibilities, or edit unrelated modules. The main conversation is responsible for consolidating worker output, resolving overlap, and preparing the final commit.

## Project Goal

Refactor the Java OA system into one complete runnable ThinkPHP OA system.

Important:

- Multiple Agents do not mean multiple projects.
- Multiple worktrees are temporary parallel workspaces.
- The final deliverable is one merged project.
- Final merged project path: `F:\AI\projects\testJava\OA-ThinkPHP`

## Project Paths

- Java source project, read-only: `F:\AI\projects\testJava\OA`
- ThinkPHP target project: `F:\AI\projects\testJava\OA-ThinkPHP`

No Agent may add, edit, delete, rename, or format files inside the Java source project.

## Branch Model

Shared integration branch:

- `refactor/thinkphp-main`

Module branches:

- `refactor/auth`
- `refactor/user`
- `refactor/workflow`
- `refactor/db`
- `refactor/api`
- `refactor/frontend`
- `refactor/test`
- `refactor/docs`

All module branches must eventually merge into `refactor/thinkphp-main`.

## Worktree Model

| Agent | Branch | Worktree |
| --- | --- | --- |
| auth-agent | `refactor/auth` | `F:\AI\projects\testJava\OA-auth` |
| user-agent | `refactor/user` | `F:\AI\projects\testJava\OA-user` |
| workflow-agent | `refactor/workflow` | `F:\AI\projects\testJava\OA-workflow` |
| db-agent | `refactor/db` | `F:\AI\projects\testJava\OA-db` |
| api-agent | `refactor/api` | `F:\AI\projects\testJava\OA-api` |
| frontend-agent | `refactor/frontend` | `F:\AI\projects\testJava\OA-frontend` |
| test-agent | `refactor/test` | `F:\AI\projects\testJava\OA-test` |
| docs-agent | `refactor/docs` | `F:\AI\projects\testJava\OA-docs` |

## Agent Scope Rules

Each Agent:

- must work only in its assigned worktree
- must work only on its assigned module
- must follow the explicit role and file/task scope from the current user request
- must check `git status --short --branch` before editing
- must commit its completed work
- must not modify unrelated modules
- must not modify the Java source project
- must not delete database fields
- must not do broad unrelated refactors

## Lean Continuation Rules

Use `docs/tasks/lean-continuation-workflow.md` for faster continuation with the same quality bar.

Key rules:

- Start with `git status`, `AGENTS.md`, `new-conversation-bootstrap.md`, `lean-continuation-workflow.md`, the dashboard head, and the latest `STATUS.md` tail.
- Use targeted `rg` / `Select-String` searches before reading large logs.
- Classify each slice as read-only, isolated write, side-effect write, frontend-visible fix, or infrastructure.
- Run risk-appropriate checks; do not skip DB/negative/side-effect smoke for write routes.
- If sub-Agent tools or quota are unavailable, emulate explorer, implementation, test, and docs passes in the main conversation and report the limitation when relevant.

## Module Scope

auth-agent:

- login
- Token
- RBAC
- menu
- permissions

user-agent:

- users
- departments
- positions
- organization tree

workflow-agent:

- approval flows
- process definitions
- process instances

db-agent:

- database structure
- ThinkPHP Model design
- field mapping
- indexes

api-agent:

- Java Controller mapping
- ThinkPHP Controller mapping
- API standardization

frontend-agent:

- frontend API adaptation
- Token adaptation
- menu and button permission adaptation

test-agent:

- tests
- syntax checks
- route checks
- namespace checks
- Composer fixes

docs-agent:

- documentation
- API notes
- deployment notes

## Merge Agent

A dedicated Merge Agent is required.

The Merge Agent only handles:

- merge
- conflicts
- review
- syntax checks
- route checks
- namespace checks
- Composer checks
- relation checks
- integration test fixes

The Merge Agent must not develop business features.

## Final Merge Order

After all module Agents complete their commits, return to:

`F:\AI\projects\testJava\OA-ThinkPHP`

Then check out:

`refactor/thinkphp-main`

Merge in this order:

1. `refactor/db`
2. `refactor/auth`
3. `refactor/user`
4. `refactor/workflow`
5. `refactor/api`
6. `refactor/test`
7. `refactor/docs`

This order is mandatory because database and model foundations must land before module logic and API wiring.

## Final Delivery Requirements

The final merged ThinkPHP OA system must satisfy:

- `composer install` works
- `php think` works
- `php think route:list` works
- database structure is complete
- APIs can run
- all module branches merge successfully
- final project exists as one complete project at `F:\AI\projects\testJava\OA-ThinkPHP`

## Forbidden Actions

- Do not modify `F:\AI\projects\testJava\OA`.
- Do not delete files or directories without explicit user approval.
- Do not delete database fields.
- Do not write API keys, passwords, tokens, or secrets.
- Do not bypass Git status checks.
- Do not introduce large dependencies without approval.
- Do not treat worktrees as independent final projects.

## Test Commands

Baseline checks:

```powershell
php -v
composer --version
git --version
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
```

## Local Runtime Services

Use the user-provided local runtime bundle for database-backed and Redis-backed checks.

Start it from:

```powershell
Set-Location E:\project\socket\AI\testPhp\files
.\startServer1.bat
```

Expected local endpoints:

- MySQL: `127.0.0.1:3306`, database `phpoa20026`
- Redis: `127.0.0.1:6379`
- PHP FastCGI: `127.0.0.1:9000`

The ThinkPHP project reads credentials from the ignored local `.env` in `F:\AI\projects\testJava\OA-ThinkPHP`. Do not print or commit `DB_PASS`, `REDIS_PASSWD`, or other secrets.

Local browser/login smoke credentials must also come from the ignored local `.env`. Use:

- `LOCAL_SUPER_ADMIN_ACCOUNT`
- `LOCAL_SUPER_ADMIN_PASSWORD`

Do not write plaintext login accounts, passwords, tokens, or other secrets into tracked files, task notes, test logs, commits, or final reports.

Detailed runtime notes: `docs/tasks/local-runtime-services.md`.

Composer checks:

```powershell
composer validate
composer install
```

## Git Commit Rules

- Branch naming: `refactor/<module>`
- Commit format: `refactor(<module>): <summary>`
- Documentation-only format: `docs(<scope>): <summary>`
- Test-only format: `test(<scope>): <summary>`
- Commit only related files.
- Record test results in the final Agent report.

## API Response Format

Unified JSON response:

```json
{
  "code": 200,
  "message": "成功",
  "data": {}
}
```

Status code convention:

- API 响应的 `message`/`msg` 必须使用中文；不得向前端返回英文异常、校验、未实现、鉴权或服务器错误文案。技术细节写入服务端日志或文档，不写入接口返回消息。
- `200`: success
- `400`: bad request or validation error
- `401`: unauthenticated or invalid Token
- `403`: permission denied
- `500`: server error

Response field shape: the copied Vue frontend expects camelCase keys, not raw `UPPER_SNAKE` DB columns. Read routes (list/detail/tree-select/form) must return the frontend shape. For plain key-case conversion use the canonical `app\support\RowMapper` (`RowMapper::toCamel($row)` / `toCamelList($rows)` / `camelKey($k)`); do not hand-roll new per-service `camelKey()` copies. Field-shape mismatch is the most recurring bug class in this refactor (MT-001, MT-002, P-022); the pre-commit baseline gate (`git config core.hooksPath .githooks`, or `composer check`) lints PHP and boots the route table before each commit.

## Token + Redis Convention

- Use `Authorization: Bearer <token>`.
- Store access token and refresh token state in Redis.
- Recommended Redis key prefix: `oa:auth:`.
- Token payload may include user id, tenant id, role codes, and permission codes.
- Token payload must not include plaintext password, secrets, or sensitive identity data.

## Directory Convention

- `docs/java-analysis`: Java source analysis
- `docs/thinkphp-design`: ThinkPHP design
- `docs/database`: database mapping and schema notes
- `docs/api`: API mapping and response notes
- `docs/tasks`: Agent task plans, merge plans, and worktree records
- `.codex`: project-level Codex configuration without secrets
