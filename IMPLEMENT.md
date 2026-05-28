# IMPLEMENT.md

## docs-agent Workflow

docs-agent must follow:

1. Read local rules: `AGENTS.md`, `PLANS.md`, `IMPLEMENT.md`, `STATUS.md`.
2. Check current branch and worktree status.
3. Edit only documentation and status files in `F:\AI\projects\testJava\OA-docs`.
4. Do not modify Java source project files.
5. Do not modify ThinkPHP business code.
6. Run required checks.
7. Commit the completed documentation change.
8. Report changed files, test results, risks, and next steps.

## Phase 1 Implementation Notes

This phase records coordination documents only:

- current parallel Agent status
- final merge checklist
- post-launch realtime data sync reminder

The final deliverable remains one merged ThinkPHP OA project at:

`F:\AI\projects\testJava\OA-ThinkPHP`

The worktrees are temporary parallel workspaces, not separate final projects.

## Locked Files

docs-agent must not modify these files unless the main coordinator explicitly approves a public file change request:

- `composer.json`
- `composer.lock`
- `config/app.php`
- `config/database.php`
- `route/app.php`
- `.env`
- `.env.example`
- `app/common.php`
