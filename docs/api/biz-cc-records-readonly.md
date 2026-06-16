# Biz CC Records Compatibility

Date: 2026-06-05

Agent: api-agent / frontend-agent / workflow-agent

## Scope

This document records ThinkPHP compatibility for Java workflow copy/CC record endpoints used by the copied Vue page `biz/biztask/copytask`. Reads remain user-scoped, and add/edit/delete are now supported as narrow `biz_cc_records` row maintenance.

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Added Routes

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/biz/ccrecords/page` | Read paged copy/CC records for the current login user. |
| POST | `/biz/ccrecords/add` | Add one current-user copy/CC record. |
| POST | `/biz/ccrecords/edit` | Edit one current-user copy/CC record. |
| POST | `/biz/ccrecords/delete` | Logically delete current user's copy/CC records. |
| GET | `/biz/ccrecords/detail` | Read one copy/CC record for the current login user. |

All routes are protected by `AuthMiddleware`.

## Java Compatibility Notes

- Java `BizCcRecordsServiceImpl.page` filters rows by `USER = StpUtil.getLoginId()`.
- This ThinkPHP read service also filters by the current bearer token user id.
- Tenant filtering is applied when the token payload contains a tenant id.
- Supported filters: `title`, `searchKey`, `processId`, `promoterId`, `instanceId`, `category`, `startCreateTime`, and `endCreateTime`.
- Returned rows include `promoterName` and `userName` display aliases for copied frontend tables.
- `instanceId` is preserved so the copied process-detail drawer can open existing workflow detail reads.
- `/biz/ccrecords/add` forces `USER` to the current token user, defaults `promoterId` to the token user when omitted, writes tenant/audit fields from the bearer-token payload, and ignores client-supplied `user` or `deleteFlag`.
- `/biz/ccrecords/edit` only updates the current token user's active row in the current tenant and preserves `USER`, `CREATE_TIME`, `CREATE_USER`, `TENANT_ID`, and `DELETE_FLAG`.
- `/biz/ccrecords/delete` accepts Java-style `[{id: ...}]`, `idList`, `ids`, or single `id` payloads.
- Delete preserves Java's current-user guard by requiring `USER` to equal the token user id; token tenant id is also enforced when present.
- Delete uses `DELETE_FLAG = DELETED` instead of physical removal so imported data remains traceable during refactor testing.

## Deferred

The following real behavior remains intentionally deferred:

- workflow copy-user delegate writes
- approval/reject/start/cancel workflow writes
- file relation binding
- notifications and data-change events
- Java source changes
- database schema changes
