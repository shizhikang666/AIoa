# Team Project Task User Read-Only Compatibility

Date: 2026-06-04

Agent: api-agent / frontend-agent

## Scope

This slice adds read-only ThinkPHP compatibility for team-project task user rows.

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Added Routes

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/biz/bizteamprojecttaskuser/page` | Page team-project task user rows. |
| GET | `/biz/bizteamprojecttaskuser/detail` | Read one team-project task user row by id. |

Both routes are protected by `AuthMiddleware`.

## Java Compatibility Notes

- Java `BizTeamProjectTaskUserController` exposes `page`, `add`, `edit`, `delete`, and `detail`; this slice only opens the read-only `page` and `detail` endpoints.
- Java `BizTeamProjectTaskUserServiceImpl.page` defaults sorting to `ID` ascending.
- The ThinkPHP service keeps the existing team-project read boundary: records are limited to projects where the current user is a project member.
- Rows include `headName` and `avatar` aliases, matching Java translation annotations for `USER_ID`.

## Deferred

The following remain intentionally deferred:

- `/biz/bizteamprojecttaskuser/add`
- `/biz/bizteamprojecttaskuser/edit`
- `/biz/bizteamprojecttaskuser/delete`
- task assignment writes
- task status/progress writes
- notifications and side effects
- Java source changes
- database schema changes
