# Post-Launch Data Sync Reminder

Last updated: 2026-05-28

## Reminder

After the ThinkPHP OA refactor is complete and the final merged project is ready, remind the user to plan realtime online data synchronization.

The user explicitly requested:

- Use `F:\AI\projects\testJava\OA\oa2026.sql` as the updated SQL reference during refactor.
- Data from the online system may be needed when appropriate.
- After the project is completed, online realtime data must be synchronized into the new project.
- Do not forget this task.

## Current Phase Boundary

Do not implement online data sync during the current module refactor phase.

This document is a reminder and planning anchor only.

## Future Scope

Before launch, create a dedicated data migration and synchronization plan that covers:

- source online database connection inventory
- target ThinkPHP database connection inventory
- table-by-table sync strategy
- full import versus incremental sync
- primary key and unique key conflict handling
- tenant data boundaries
- file attachment migration
- Redis/session cutover policy
- rollback plan
- downtime or dual-write window
- verification queries
- final user acceptance check

## Suggested Future Agent

Create a dedicated `data-sync-agent` or assign this task to `db-agent` after all module branches are merged.

The future Agent must not run production sync commands without explicit user approval.

## Completion Reminder Text

When the full ThinkPHP OA project is complete, tell the user:

`Project refactor is complete. Please start the online realtime data synchronization plan before production cutover. The SQL reference used during development was F:\AI\projects\testJava\OA\oa2026.sql.`
