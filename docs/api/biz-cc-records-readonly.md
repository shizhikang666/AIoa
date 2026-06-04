# Biz CC Records Read-Only Compatibility

Date: 2026-06-04

Agent: api-agent / workflow-agent

## Scope

This slice adds read-only ThinkPHP compatibility for Java workflow copy/CC record endpoints used by the copied Vue page `biz/biztask/copytask`.

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Added Routes

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/biz/ccrecords/page` | Read paged copy/CC records for the current login user. |
| GET | `/biz/ccrecords/detail` | Read one copy/CC record for the current login user. |

All routes are protected by `AuthMiddleware`.

## Java Compatibility Notes

- Java `BizCcRecordsServiceImpl.page` filters rows by `USER = StpUtil.getLoginId()`.
- This ThinkPHP read service also filters by the current bearer token user id.
- Tenant filtering is applied when the token payload contains a tenant id.
- Supported filters: `title`, `searchKey`, `processId`, `promoterId`, `instanceId`, `category`, `startCreateTime`, and `endCreateTime`.
- Returned rows include `promoterName` and `userName` display aliases for copied frontend tables.
- `instanceId` is preserved so the copied process-detail drawer can open existing workflow detail reads.

## Deferred

The following remain intentionally deferred:

- `/biz/ccrecords/add`
- `/biz/ccrecords/edit`
- `/biz/ccrecords/delete`
- workflow copy-user delegate writes
- approval/reject/start/cancel workflow writes
- Java source changes
- database schema changes
