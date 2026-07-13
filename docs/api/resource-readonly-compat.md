# System Resource Read-Only Compatibility

Date: 2026-05-29

Agent: merge-agent

## Scope

This slice adds read-only compatibility for old Java OA system resource APIs:

- `/sys/module/page`
- `/sys/module/detail`
- `/sys/menu/page`
- `/sys/menu/tree`
- `/sys/menu/detail`
- `/sys/menu/moduleSelector`
- `/sys/menu/menuTreeSelector`
- `/sys/button/page`
- `/sys/button/detail`

All routes are protected by `AuthMiddleware`.

## Java Reference

- `SysModuleController`
- `SysMenuController`
- `SysButtonController`
- `SysModuleServiceImpl`
- `SysMenuServiceImpl`
- `SysButtonServiceImpl`

The Java module/menu/button APIs all read from `sys_resource` and separate records with `CATEGORY`:

- `MODULE`
- `MENU`
- `BUTTON`

## Behavior

- Pagination supports `current`, `page`, `pageNo`, `size`, `limit`, and `pageSize`.
- Search filters by `TITLE` with `searchKey` or `title`.
- Menu page supports `module` filtering.
- Button page supports `parentId` filtering.
- Menu tree includes parent menu rows when searching, matching the Java service behavior that fills parent menu nodes.
- Responses use lower camel case fields for old frontend compatibility while preserving original column meaning.

## Deferred

The following Java endpoints remain intentionally deferred:

- relation/grant mutation behavior

Module add/edit/delete is now covered by `docs/api/sys-module-write-compat.md`.
Menu add/edit/changeModule/delete is now covered by `docs/api/sys-menu-write-compat.md`.
Button add/edit/delete is now covered by `docs/api/sys-button-write-compat.md`.
Field add/edit/delete is now covered by `docs/api/sys-field-write-compat.md`.

No Java source, database schema, seed data, Composer files, `.env`, or public config files were changed.

## 2026-06-15 HTTP Smoke Coverage

`scripts/resource-read-http-smoke.ps1` now covers authenticated system resource reads for:

- `GET /sys/module/page`
- `GET /sys/module/detail` when a module row exists
- `GET /sys/menu/page`
- `GET /sys/menu/tree`
- `GET /sys/menu/detail` when a menu row exists
- `GET /sys/menu/moduleSelector`
- `GET /sys/menu/menuTreeSelector`
- `GET /sys/button/page`
- `GET /sys/button/detail` when a button row exists
- `GET /sys/field/page`
- `GET /sys/field/tree`
- `GET /sys/field/detail` when a field row exists
- `GET /sys/field/MenuTreeSelector`

The smoke asserts Java-style paging keys, resource row aliases, and selector `label`/`value` fields. It intentionally does not call module/menu/button/field add, edit, delete, change-module, role grant, cache invalidation, or data-change behavior.
