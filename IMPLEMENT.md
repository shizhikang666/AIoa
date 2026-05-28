# IMPLEMENT.md

## test-agent Implementation Workflow

test-agent must use the following workflow for every phase:

1. Read `AGENTS.md`, `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md`.
2. Confirm the current worktree is `F:\AI\projects\testJava\OA-test`.
3. Confirm the branch is `refactor/test`.
4. Check `git status --short --branch` before editing.
5. Modify only test-agent planning and task documentation unless the user explicitly approves more.
6. Run baseline test commands.
7. Record test results and risks in `STATUS.md` and `docs/tasks`.
8. Run `git status --short --branch`.
9. Stage only related files.
10. Commit with a message that starts with `test-agent:`.

## Phase 1 Scope

This phase creates a baseline test plan and risk list. It does not fix business code and does not change application behavior.

## Locked Files

The following files are locked for test-agent in this phase:

- `composer.json`
- `composer.lock`
- `config/app.php`
- `config/database.php`
- `route/app.php`
- `.env`
- `.env.example`
- `app/common.php`

If a later test phase requires changing any locked file, create `docs/tasks/public-file-change-request.md` and wait for confirmation.

