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

If route registration is needed, document it in `docs/tasks/public-file-change-request.md` and wait for confirmation.
