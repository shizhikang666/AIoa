# System Module Write Compatibility

Date: 2026-06-09

Agent: main control agent with worker review

## Scope

This slice adds Java-compatible write behavior for copied system resource module pages:

- `POST /sys/module/add`
- `POST /sys/module/edit`
- `POST /sys/module/delete`

The existing read endpoints remain:

- `GET /sys/module/page`
- `GET /sys/module/detail`

All routes are protected by `AuthMiddleware`.

## Java Reference

- `SysModuleController`
- `SysModuleServiceImpl`
- `SysModuleAddParam`
- `SysModuleEditParam`
- `SysModuleIdParam`

Java source stays read-only under `F:\AI\projects\testJava\OA`.

## Behavior

- `add` accepts `title`, `icon`, `color`, `sortCode`, and optional `extJson`.
- `edit` accepts the same fields plus required `id`.
- New and edited rows are stored in `sys_resource` with `CATEGORY = MODULE`.
- Module `TITLE` is unique among active `MODULE` rows.
- New module `CODE` is generated as a random 10-character resource code, matching Java's generated-code behavior.
- Audit fields use the current auth payload user id when available.
- `delete` accepts Java-style array bodies such as `[{ "id": "..." }]`, plus local compatibility forms `idList`, `ids`, `id`, and `moduleIds`.
- Built-in modules with codes `system` and `tenant` are rejected for deletion.
- Deletion is logical: `DELETE_FLAG = DELETED`.
- Deletion also logically deletes active menu/button/field resources under the module and physically removes role-resource relations whose `TARGET_ID` belongs to the deleted module/menu/button/field id set.

## Verification

- `php think route:list` lists `POST /sys/module/add`, `POST /sys/module/edit`, and `POST /sys/module/delete`.
- `scripts/test-agent-db-smoke.ps1` includes `ResourceService module write compatibility`.
- `scripts/test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysModuleHttpSmoke` covers authenticated HTTP add, page lookup, duplicate-title rejection, edit, delete, child menu logical delete, and role-resource relation cleanup.
- `scripts/test-agent-db-smoke.ps1` also verifies module delete cascades to child field resources and removes direct role-resource relation rows targeting deleted fields.

## Deferred

- Menu write compatibility is covered by `docs/api/sys-menu-write-compat.md`; field write compatibility is covered by `docs/api/sys-field-write-compat.md`.
- Java `CommonDataChangeEventCenter` cache/event behavior is not implemented yet.
- No Java source, frontend source, database schema, Composer files, `.env`, or public config files were changed.
