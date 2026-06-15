# Parallel Execution Plan

Date: 2026-06-15

Purpose: define how to run multiple conversations or sub-agents without corrupting the ThinkPHP OA refactor workspace.

## Coordination Model

The main conversation is the merge coordinator.

Coordinator responsibilities:

- Choose the next small slice.
- Assign bounded read-only or disjoint implementation tasks.
- Keep side-effect boundaries explicit.
- Review sub-agent or extra-conversation output before any merge.
- Run `.\scripts\project-preflight.ps1` after integrated changes when local services are expected to be up.
- Commit completed, verified slices when the user has approved coordinator-driven commits.

Parallel workers or extra conversations must not take over merge coordination.

## Safe Parallel Tracks

| Track | Allowed Work | Output | Write Permission |
| --- | --- | --- | --- |
| Selector/read smoke scouting | Inspect existing `biz/org`, `biz/user`, `biz/position`, `biz/dict`, `sys/*` selector/read endpoints and propose smoke coverage | Endpoint list, payload fields, risk notes | Read-only unless coordinator assigns a smoke-script slice |
| Remaining read-only API scouting | Find already routed safe page/list/detail/query/report endpoints that need regression coverage | Ranked candidate list and exact assertions | Read-only unless coordinator assigns a smoke-script slice |
| Workflow reconnaissance | Compare current read routes, copied frontend callers, and deferred approve/reject/start/cancel/SSE behavior | Safe endpoints, deferred writes, proposed no-write smoke | Read-only |
| Frontend/browser smoke | Browser-test already routed pages and record blocking requests or console errors | URL, action steps, observed API calls, screenshots if useful | No code edits unless coordinator assigns a frontend-only patch |
| Docs cleanup | Improve task notes, API docs, handoff, and plan tables for completed or approved slices | Narrow doc patch | Allowed only for assigned files |

## Serial Tracks

These must stay under single coordinator control:

- `route/app.php` route additions.
- Shared status files: `PLANS.md`, `IMPLEMENT.md`, `STATUS.md`.
- Shared planning files: `docs/tasks/api-gap-map.md`, `docs/tasks/refactor-progress-dashboard.md`, `docs/tasks/problem-optimization-log.md`.
- Shared preflight scripts: `scripts/project-preflight.ps1`, `scripts/project-progress.ps1`.
- Any customer, sale-project, finance, inventory, workflow, scheduler, provider, cloud-storage, auth-token, RBAC, or production-data write behavior.

## Deferred Until Final Or Dedicated Plan

- Real Email sending.
- Real SMS sending.
- Provider credential reads.
- External delivery calls.
- Job scheduler execution.
- Cloud storage engines and physical file cleanup.
- Workflow approve/reject/start/cancel runtime.
- Finance, inventory, stock, payment, refund, and sale-project state side effects.
- Final online data sync.

Real Email is scheduled before real SMS in the final provider phase.

## Worker Prompt Template

Use this for read-only parallel investigation:

```text
Work in F:\AI\projects\testJava\OA-ThinkPHP. Read-only reconnaissance only: do not edit files, do not commit, do not run destructive commands, and do not print secrets. Treat F:\AI\projects\testJava\OA as read-only Java reference. Start with .\scripts\project-progress.ps1 -Lean if orientation is needed. Investigate only <scope>. Return concise findings, exact endpoints/files, risk level, and a recommended smallest next slice. Keep real Email/SMS/provider, workflow write, finance/inventory, job execution, cloud storage, and final data sync deferred.
```

Use this for an assigned implementation worker only after the coordinator grants a disjoint write scope:

```text
Work in F:\AI\projects\testJava\OA-ThinkPHP. You are not alone in the codebase; do not revert or overwrite unrelated changes. Edit only <owned files/modules>. Do not touch F:\AI\projects\testJava\OA. Do not print secrets. Do not commit. Keep the scope to <task>. Run the assigned checks and report changed files, checks, residual risk, and any skipped verification.
```

## Current Parallel Queue

| Priority | Track | Status | Coordinator Action After Result |
| --- | --- | --- | --- |
| 1 | Selector/read smoke scouting for `biz/org`, `biz/user`, `biz/position`, `biz/dict` | Completed | Directory alias smoke selected; `/biz/org/page` `size` compatibility fixed and smoke-covered |
| 2 | Remaining read-only/detail-consumer scouting | Completed | Next safe candidates: follow-up detail consumers, sale-project billing nested reads, product/package relation reads |
| 3 | Workflow read/write boundary reconnaissance | Completed | No-write workflow HTTP smoke added; approve/reject/start/cancel/SSE remain deferred |
| 4 | Frontend/browser upload smoke | Pending | Run only after a concrete browser target is selected |
| 5 | Cloud storage cleanup/provider plan | Deferred | Keep out of active work until provider config plan is approved |

## Current Recommended Queue

1. Add sale-project billing nested read smoke, explicitly excluding invoicing complete, stock, settlement, and file cleanup.
2. Add sale-project product/package relation read smoke, excluding product info and relation mark writes.
3. Run targeted browser smoke only after selecting a concrete visible page and forbidden request pattern.
4. Revisit workflow query-list pagination or filtering only as a dedicated performance/compatibility slice.

## Completion Rule

A parallel task is not complete until the coordinator has:

1. Reviewed the result.
2. Decided whether it changes the next execution order.
3. Integrated any approved code or doc change.
4. Run risk-appropriate checks.
5. Committed the verified slice when appropriate.
