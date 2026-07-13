# Lean Continuation Workflow

## Purpose

This workflow keeps the ThinkPHP OA refactor moving faster with lower context and token cost while preserving the existing quality bar. The default execution unit is now a complete feature-closure block, not a single easy route.

It is a process rule only. It must not be used as permission to change completed behavior, skip tests on risky work, edit the Java source project, or commit secrets.

## Non-Negotiables

- Preserve completed functionality unless the user explicitly asks for a correction.
- Treat `F:\AI\projects\testJava\OA` as read-only reference input.
- Keep credentials, tokens, database passwords, Redis passwords, and local login values only in ignored `.env` or the active shell environment.
- Work in complete feature-closure blocks with explicit dependency maps, side-effect maps, non-goals, and end-to-end verification. Use internal checkpoints only to manage risk.
- Do not add broad refactors, new dependencies, schema changes, destructive operations, or production data operations without a separate approved plan.
- Final online data sync remains a final-stage task and must not start early.

## Fast Startup Packet

For a normal continuation, do not read every large status file end to end. Start with this packet:

```powershell
Set-Location F:\AI\projects\testJava\OA-ThinkPHP
.\scripts\project-progress.ps1 -Lean
```

If local services are expected to be running, follow with:

```powershell
.\scripts\project-preflight.ps1
```

Use preflight skip switches only for intentionally unavailable layers, for example `-SkipWeb`, `-SkipRoleSelector`, or `-SkipDiffCheck`.

Use this manual equivalent only when the helper script is unavailable:

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

Check `docs/tasks/problem-optimization-log.md` before starting the feature block. If the current work repeats an existing problem, update that row instead of adding a duplicate.

Use `docs/tasks/context-handoff.md` when the current conversation becomes too large for precise work. Ask the user to open a new conversation before starting a broad, risky, or cross-module feature block if the current context is already overloaded.

For DB-backed, authenticated HTTP, or browser smoke work, run the relevant readiness helper first:

```powershell
.\scripts\project-preflight.ps1
.\scripts\runtime-ready.ps1
.\scripts\web-ready.ps1
.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean
```

## Feature Closure Triage

Classify each next task before editing:

| Type | Examples | Required Approach |
| --- | --- | --- |
| Read-only compatibility | page/list/detail/selector/preview | Java and frontend shape check, route/controller/service, no-write smoke |
| Isolated write | add/edit/delete on one base table | Java param whitelist, authorization/data-scope, rollback or no-partial-write smoke |
| Side-effect write | finance, stock, workflow, account balance, generated files | Separate design first, transaction plan, side-effect map, broader negative smoke |
| Frontend-visible fix | page load, upload, drawer, SSE fallback | Backend route check plus browser smoke when server is available |
| Infrastructure/deployment | env, runtime, server, queue, storage | Separate checklist, no secret output, no production mutation |

Prefer the smallest complete user-visible feature closure that resolves a real workflow. Do not choose a route only because it is easy; first map the related frontend callers, Java service logic, current PHP services, database tables, downstream reads, and rollback/smoke requirements.

## Multi-Agent Rules

Default to multi-Agent mode when the tools and quota are available:

- The main conversation is the merge/coordinator.
- Explorers answer bounded codebase questions and do not edit files.
- Workers edit only their assigned file/module ownership and must report changed files.
- The coordinator reviews, integrates, runs acceptance checks, and commits only when the user explicitly asks or the coordinator explicitly approves it.
- `docs/tasks/parallel-execution-plan.md` is the active coordination table for safe parallel tracks, serial shared files, deferred side-effect groups, and worker prompt templates.

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

Use this loop for each feature-closure block:

1. Check worktree status.
2. Search the related Java controller/service/params, frontend callers, ThinkPHP routes/controllers/services, database tables, downstream readers, docs, and tests.
3. Write a short local plan with feature scope, dependency map, side-effect map, touched files, non-goals, and acceptance checks.
4. Edit only the scoped files.
5. Update the narrow docs that future conversations need.
6. Record new recurring problems or mitigations in `docs/tasks/problem-optimization-log.md`.
7. Run risk-appropriate end-to-end checks, including downstream readback when behavior changes.
8. Commit only when the user explicitly asks or the main merge/coordinator explicitly approves committing this completed feature block.
9. Report changed files, checks, problem-log updates, residual risks, and next feature block.

Avoid re-reading stable decisions. Link to existing docs instead of restating them.

## Documentation Rules

Each feature block should update only the docs it actually changes:

- API behavior: one `docs/api/*` file or the existing module doc.
- Route/gap count: `docs/tasks/api-gap-map.md` when route coverage changes.
- Project progress: `docs/tasks/refactor-progress-dashboard.md` when capability or counts change.
- Recurring problems and workflow improvements: `docs/tasks/problem-optimization-log.md`.
- Future startup facts: `docs/tasks/new-conversation-bootstrap.md` only for reusable runtime, credential, smoke, or workflow facts.
- Long-running record: append concise entries to `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md` for completed implementation or process feature blocks.

Do not paste long command output into docs. Record pass/fail and the few facts needed for future verification.

## Problem Log Rules

Use `docs/tasks/problem-optimization-log.md` as the living problem table.

- Add a row when a problem is likely to recur, slows future work, creates ambiguity, blocks verification, or reveals a missing guardrail.
- Update an existing row when the same problem repeats or when a better mitigation is found.
- Keep entries practical: problem, impact, root cause, current mitigation, next optimization, and status.
- Do not store secrets, raw credentials, tokens, local login values, or production data in the problem log.
- Mention important problem-log changes in the completed feature block's `STATUS.md` entry.

## Context Handoff Rules

Use `docs/tasks/context-handoff.md` as the handoff contract for long conversations.

- Keep working in the current conversation while the active feature block is precise enough to inspect safely.
- Finish and verify the active coherent feature block before asking for a new conversation when practical.
- Ask for a new conversation before broad Java/frontend/backend inspections, side-effect-heavy work, or cross-module work if this thread is already long.
- Before handing off, update `STATUS.md` and `docs/tasks/problem-optimization-log.md`; update the dashboard, gap map, `PLANS.md`, or `IMPLEMENT.md` only when their facts changed.
- In the new conversation, start with `.\scripts\project-progress.ps1 -Lean`, then run `.\scripts\project-preflight.ps1` when local services are expected to be available.

## Quality Gates

Minimum checks for every code feature block:

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
Use `.\scripts\web-ready.ps1` first so a missing backend port `82` or frontend port `83` is recorded as an environment precondition instead of a frontend regression.

## Faster Check Selection

Use targeted checks first:

- `.\scripts\project-preflight.ps1` when local runtime, web, frontend route/deferred-wrapper, selector, and whitespace checks are all relevant.
- `php -l` only touched PHP files plus `route/app.php`.
- `php think route:list | Select-String "<route group>"` when only routes changed.
- Focused service smoke through ThinkPHP bootstrap for DB behavior.
- Existing optional HTTP smoke flags when they match the slice.

Use broad checks when the feature block touches shared foundations, before push/release, or after many accumulated changes:

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
