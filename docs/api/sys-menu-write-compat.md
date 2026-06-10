# System Menu Write Compatibility

Date: 2026-06-10

Agent: main control agent with read-only sub-agent review

## Scope

This slice adds protected Java-compatible write routes for copied system resource menu pages:

- `POST /sys/menu/add`
- `POST /sys/menu/edit`
- `POST /sys/menu/changeModule`
- `POST /sys/menu/delete`

The existing read routes remain documented in `docs/api/resource-readonly-compat.md`.

## Java Reference

- `SysMenuController`
- `SysMenuServiceImpl`
- `SysMenuAddParam`
- `SysMenuEditParam`
- `SysMenuChangeModuleParam`
- `snowy-admin-web/src/api/sys/resource/menuApi.js`

## Behavior

- `add` and `edit` accept JSON objects. `delete` accepts Java-style array bodies such as `[{ "id": "..." }]`.
- `add` requires `parentId`, `title`, `menuType`, `module`, `path`, and `sortCode`.
- `edit` requires the same fields plus `id`.
- Stored rows use `sys_resource.CATEGORY = MENU`.
- Ordinary menu add does not generate `CODE`.
- `menuType` allows `CATALOG`, `MENU`, `IFRAME`, and `LINK`.
- `MENU` rows require `name` and `component`.
- `IFRAME` and `LINK` rows store `component = null`; empty `name` is replaced with a random numeric string.
- `CATALOG` rows store `name = null` and `component = null`.
- Active sibling menu `TITLE` values must be unique under the same `PARENT_ID`.
- Non-root menus require an existing parent menu, and the parent `MODULE` must match the submitted `module`.
- `edit` rejects parent changes to self or to any descendant menu.
- `changeModule` is allowed only for root menus and updates only the current menu plus active descendant `MENU` rows.
- `delete` logically deletes target active menus plus descendant active menu/button rows.
- `delete` removes whole `sys_relation` rows where `CATEGORY = SYS_ROLE_HAS_RESOURCE` and `TARGET_ID` is in the deleted menu/button tree.

## Verification

Verified on 2026-06-10 against the user-provided local MySQL/Redis runtime:

```powershell
.\scripts\test-agent-db-smoke.ps1
.\scripts\test-agent-smoke.ps1 -SkipComposer
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysMenuHttpSmoke
```

The DB and HTTP smokes cover add, tree lookup, duplicate-title rejection, parent/module mismatch rejection, edit, `IFRAME` field normalization, self/descendant parent rejection, root-only `changeModule`, descendant module propagation, logical delete of menu/button tree, role-resource relation cleanup, and temporary row cleanup.

## Deferred

- System field write compatibility remains deferred.
- System role/resource grant mutations remain deferred.
- Java `CommonDataChangeEventCenter` cache/event behavior is not implemented yet.
- No Java source, database schema, seed data, Composer files, `.env`, frontend source, or public config files were changed.
