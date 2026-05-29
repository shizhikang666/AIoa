# PLANS.md

## Active Plan: docs-agent Phase 1 - Parallel Refactor Documentation

Status: completed locally, pending commit.

Date: 2026-05-28

## Current Goal

Start docs-agent phase 1 and document the current multi-agent parallel refactor status, final merge rules, and post-launch realtime data sync reminder.

## Module

docs-agent only.

## Files In Scope

- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`
- `docs/tasks/parallel-agent-status.md`
- `docs/tasks/final-merge-checklist.md`
- `docs/tasks/post-launch-data-sync-reminder.md`

## Risks

- Worktrees may drift if Agents do not commit and report status before merge.
- Public files such as `route/app.php` may create conflicts if multiple Agents change them without a request record.
- The final project can become fragmented if worktrees are treated as final deliverables.
- Post-launch realtime data sync can be forgotten if it is not kept as an explicit release task.

## Test Commands

```powershell
git status --short --branch
composer dump-autoload
php think
php think route:list
```

## Acceptance Criteria

- docs-agent rules files exist in `F:\AI\projects\testJava\OA-docs`.
- Parallel Agent status is documented.
- Final merge checklist is documented.
- Post-launch realtime data sync reminder is documented.
- Java source project remains read-only.
- No business code or locked public files are modified.
- Changes are committed with message `docs-agent: update parallel refactor docs`.

## Forbidden Scope

- Do not modify `F:\AI\projects\testJava\OA`.
- Do not modify business code.
- Do not modify locked public files.
- Do not push to remote in this phase.

## Active Plan: docs-agent Phase 2 - Autonomous Execution Rules

Status: in progress.

Date: 2026-05-29

## Current Goal

Document how the main control Agent can keep moving autonomously while preserving safety boundaries for a long-running multi-agent refactor.

## Files In Scope

- `PLANS.md`
- `STATUS.md`
- `docs/tasks/autonomous-execution-rules.md`
- `docs/tasks/parallel-agent-status.md`

## Risks

- Over-broad authorization could allow unsafe edits to Java source, production data, locked config files, or database schema.
- Under-specified authorization will keep causing avoidable pauses for safe recurring commands.
- Merge and route registration still need explicit scope rules because they touch integration behavior.

## Test Commands

```powershell
composer dump-autoload
php think
php think route:list
git status --short --branch
```

## Acceptance Criteria

- Autonomous execution rules include allowed actions, stop conditions, and a copyable user authorization statement.
- Branch push/sync status is documented.
- No business code or locked public files are modified.
