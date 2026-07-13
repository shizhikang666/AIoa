# Final Data Sync Reminder

## Requirement

Before the Java OA to ThinkPHP OA refactor is considered complete, remind the user to handle online realtime data synchronization into the final merged ThinkPHP project.

## Current SQL Reference

- Preferred SQL source for db-agent analysis: `F:\AI\projects\testJava\OA\oa2026.sql`
- File observed on 2026-05-28 15:43 +08:00:
  - Size: `389857132` bytes
  - Last write time: `2026-05-14 12:07:41`

This file is read-only reference input. Do not edit it from any Agent.

## Timing

Do not implement online data sync during early module refactor phases.

Handle this after:

- db-agent, auth-agent, user-agent, api-agent, workflow-agent, frontend-agent, and test-agent work has been merged
- the final project at `F:\AI\projects\testJava\OA-ThinkPHP` can run
- `composer install`, `php think`, and `php think route:list` pass
- database model and API compatibility checks are complete

## Final Sync Plan To Prepare Later

The final sync stage should include:

- source database identification and connection method
- target ThinkPHP database identification
- backup before sync
- dry-run or staging sync
- table-by-table compatibility check
- ID, tenant, relation, and workflow data validation
- rollback plan
- verification queries after sync
- final user confirmation before touching online data

## Reminder Text

At project completion, tell the user:

`项目已经接近完成。你之前要求不要忘记线上实时数据同步，现在需要确认线上数据库来源、同步窗口、备份方式和验证方案，然后再把线上实时数据同步到最终 ThinkPHP OA 项目。`
