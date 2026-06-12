# Lean Continuation Workflow

## Purpose

This workflow keeps the ThinkPHP OA refactor moving faster with lower context and token cost while preserving the existing quality bar.

It is a process rule only. It must not be used as permission to change completed behavior, skip tests on risky work, edit the Java source project, or commit secrets.

## Non-Negotiables

- Preserve completed functionality unless the user explicitly asks for a correction.
- Treat `F:\AI\projects\testJava\OA` as read-only reference input.
- Keep credentials, tokens, database passwords, Redis passwords, and local login values only in ignored `.env` or the active shell environment.
- Work in small slices with explicit scope, non-goals, and targeted verification.
- Do not add broad refactors, new dependencies, schema changes, destructive operations, or production data operations without a separate approved plan.
- Final online data sync remains a final-stage task and must not start early.

## Fast Startup Packet

For a normal continuation, do not read every large status file end to end. Start with this packet:

```powershell
git status --short --branch
Get-Content -Raw AGENTS.md
Get-Content -Raw docs\tasks\new-conversation-bootstrap.md
Get-Content -Raw docs\tasks\lean-continuation-workflow.md
Get-Content docs\tasks\refactor-progress-dashboard.md -TotalCount 90
Get-Content STATUS.md -Tail 140
```

Then use targeted search for the current module:

```powershell
rg -n "<route|module|class|service>" app route docs PLANS.md IMPLEMENT.md STATUS.md snowy-admin-web\src
```

Read full `PLANS.md`, `IMPLEMENT.md`, or `STATUS.md` only when:

- targeted search does not answer the current scope question;
- the task touches cross-module behavior;
- a merge, audit, release, or user-requested full status report requires it.

## Slice Triage

Classify each next task before editing:

| Type | Examples | Required Approach |
| --- | --- | --- |
| Read-only compatibility | page/list/detail/selector/preview | Java and frontend shape check, route/controller/service, no-write smoke |
| Isolated write | add/edit/delete on one base table | Java param whitelist, authorization/data-scope, rollback or no-partial-write smoke |
| Side-effect write | finance, stock, workflow, account balance, generated files | Separate design first, transaction plan, side-effect map, broader negative smoke |
| Frontend-visible fix | page load, upload, drawer, SSE fallback | Backend route check plus browser smoke when server is available |
| Infrastructure/deployment | env, runtime, server, queue, storage | Separate checklist, no secret output, no production mutation |

Prefer the smallest safe slice that unlocks a visible frontend gap or removes a clear backend blocker.

## Multi-Agent Rules

Default to multi-Agent mode when the tools and quota are available:

- The main conversation is the merge/coordinator.
- Explorers answer bounded codebase questions and do not edit files.
- Workers edit only their assigned file/module ownership and must report changed files.
- The coordinator reviews, integrates, runs acceptance checks, and commits.

If sub-Agent tools or quota are unavailable, continue in single-conversation fallback:

- State the limitation once.
- Emulate roles explicitly: explorer pass, implementation pass, test pass, docs pass.
- Keep the same scope and acceptance gates.
- Record the limitation in the final report only if it affects confidence or coverage.

Good explorer prompts ask for concrete output:

- Java controller/service behavior and route mapping.
- Copied frontend wrapper and component expectations.
- Current ThinkPHP route/controller/service state.
- Smallest safe implementation plan, files, non-goals, and smoke tests.

## Implementation Loop

Use this loop for each slice:

1. Check worktree status.
2. Search only relevant Java, frontend, route, controller, service, docs, and tests.
3. Write a short local plan with scope, touched files, non-goals, and acceptance checks.
4. Edit only the scoped files.
5. Update the narrow docs that future conversations need.
6. Run risk-appropriate checks.
7. Commit a small coherent changeset.
8. Report changed files, checks, residual risks, and next slice.

Avoid re-reading stable decisions. Link to existing docs instead of restating them.

## Documentation Rules

Each slice should update only the docs it actually changes:

- API behavior: one `docs/api/*` file or the existing module doc.
- Route/gap count: `docs/tasks/api-gap-map.md` when route coverage changes.
- Project progress: `docs/tasks/refactor-progress-dashboard.md` when capability or counts change.
- Future startup facts: `docs/tasks/new-conversation-bootstrap.md` only for reusable runtime, credential, smoke, or workflow facts.
- Long-running record: append concise entries to `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md` for completed implementation slices.

Do not paste long command output into docs. Record pass/fail and the few facts needed for future verification.

## Quality Gates

Minimum checks for every code slice:

```powershell
php -l <touched php files>
php -l route\app.php
php think route:list
git diff --check
```

Add focused service or HTTP smoke when behavior changes.

For read-only routes, verify:

- expected response shape;
- missing required parameter behavior;
- unauthenticated route protection when applicable;
- no database writes or file writes when no writes are intended.

For isolated writes, verify:

- Java-compatible field whitelist;
- authorization and tenant/data-scope guard;
- missing-id or malformed payload rejection before partial writes;
- non-admin or out-of-scope rejection;
- expected logical or physical delete behavior;
- no unrelated table side effects;
- temporary smoke data cleanup.

For side-effect-heavy writes, do not start implementation until a separate side-effect map and transaction smoke plan are written.

For frontend-visible changes, run browser smoke when the backend/frontend servers are active. If not run, say so and record why.

## Faster Check Selection

Use targeted checks first:

- `php -l` only touched PHP files plus `route/app.php`.
- `php think route:list | Select-String "<route group>"` when only routes changed.
- Focused service smoke through ThinkPHP bootstrap for DB behavior.
- Existing optional HTTP smoke flags when they match the slice.

Use broad checks when the slice touches shared foundations, before push/release, or after many accumulated changes:

- full `.\scripts\test-agent-smoke.ps1 -SkipComposer`;
- `.\scripts\test-agent-db-smoke.ps1`;
- full PHP lint over `app`, `config`, and `route`;
- browser smoke on affected pages.

Do not run `composer install` unless dependencies or autoload assumptions changed. Prefer `composer dump-autoload` for new classes when needed.

## Token-Saving Habits

- Use `rg` and `Select-String -Context` instead of dumping large files.
- Read Java source only around the target controller/service/method.
- Read frontend only around the wrapper/component calling the route.
- Summarize sub-Agent results; do not paste full transcripts.
- Keep final reports short: what changed, what passed, what remains.
- Do not restate stable environment credentials or secrets; refer to `.env` and runtime docs.

## Stop Conditions

Stop and ask before:

- destructive file operations;
- schema deletion or broad migration;
- production or online data operations;
- secret handling beyond reading ignored `.env` locally;
- new dependencies;
- uncertain merge conflicts;
- side-effect-heavy write flows without a written plan.
