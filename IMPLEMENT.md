# IMPLEMENT.md

## frontend-agent Implementation Flow

Every frontend-agent phase must follow:

1. Read `AGENTS.md`, `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md`.
2. Check `git status --short --branch`.
3. Analyze frontend source under `F:\AI\projects\testJava\OA\snowy-admin-web` as read-only input.
4. Analyze the current ThinkPHP worktree under `F:\AI\projects\testJava\OA-frontend`.
5. Write the smallest safe documentation or adapter change set.
6. Avoid locked public files unless a change request is written and confirmed.
7. Run baseline checks.
8. Commit with a message containing `frontend-agent`.
9. Report modified files, tests, current issues, and next plan.

## Scope

frontend-agent owns:

- frontend API compatibility notes
- token header adaptation plan
- menu and button permission adaptation plan
- upload, download, SSE, and blob response adaptation plan
- frontend-backend contract risk tracking

frontend-agent must not own:

- backend auth service implementation
- database schema or model generation
- workflow engine implementation
- route registration without approved change request
- direct edits to the read-only Java OA frontend source

## Public File Rule

The following backend files are locked:

- `composer.json`
- `composer.lock`
- `config/app.php`
- `config/database.php`
- `route/app.php`
- `.env`
- `.env.example`
- `app/common.php`

If backend route or config changes are needed, document them in `docs/tasks/public-file-change-request.md` and wait for confirmation.
