# System Button Write Compatibility

Date: 2026-06-09

Agent: main control agent with worker review

## Scope

This slice adds Java-compatible write behavior for copied system resource button pages:

- `POST /sys/button/add`
- `POST /sys/button/edit`
- `POST /sys/button/delete`

The existing read endpoints remain:

- `GET /sys/button/page`
- `GET /sys/button/detail`

All routes are protected by `AuthMiddleware`.

## Java Reference

- `SysButtonController`
- `SysButtonServiceImpl`
- `SysButtonAddParam`
- `SysButtonEditParam`
- `SysButtonIdParam`

Java source stays read-only under `F:\AI\projects\testJava\OA`.

## Behavior

- `add` accepts `parentId`, `title`, `code`, `sortCode`, and optional `extJson`.
- `edit` accepts the same fields plus required `id`.
- New and edited rows are stored in `sys_resource` with `CATEGORY = BUTTON`.
- Button `CODE` is unique among active `BUTTON` rows.
- Audit fields use the current auth payload user id when available.
- `delete` accepts Java-style array bodies such as `[{ "id": "..." }]`, plus local compatibility forms `idList`, `ids`, `id`, and `buttonIds`.
- Deletion is logical: `DELETE_FLAG = DELETED`.
- Deletion also removes deleted button ids from role-resource relation JSON:
  - table: `sys_relation`
  - `CATEGORY = SYS_ROLE_HAS_RESOURCE`
  - `TARGET_ID` in the deleted buttons' parent menu ids
  - `EXT_JSON.buttonInfo` is filtered and written back

## Verification

- `php think route:list` lists `POST /sys/button/add`, `POST /sys/button/edit`, and `POST /sys/button/delete`.
- `scripts/test-agent-db-smoke.ps1` includes `ResourceService button write compatibility`.
- `scripts/test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysButtonHttpSmoke` covers authenticated HTTP add, page lookup, duplicate-code rejection, edit, delete, and `buttonInfo` cleanup.

## Deferred

- Module and menu write compatibility are covered separately; field write compatibility remains deferred.
- Java `CommonDataChangeEventCenter` cache/event behavior is not implemented yet.
- No Java source, frontend source, database schema, Composer files, `.env`, or public config files were changed.
