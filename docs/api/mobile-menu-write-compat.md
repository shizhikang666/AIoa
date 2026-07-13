# Mobile Menu Write Compatibility

Date: 2026-06-09

Agent: main control agent with read-only sub-agent review

## Scope

This slice adds protected Java-compatible write routes for copied mobile menu resource pages:

- `POST /mobile/menu/add`
- `POST /mobile/menu/edit`
- `POST /mobile/menu/changeModule`
- `POST /mobile/menu/delete`

The existing read routes remain documented in `docs/api/mobile-resource-readonly-compat.md`.

## Java Reference

- `MobileMenuController`
- `MobileMenuServiceImpl`
- `snowy-admin-web/src/api/mobile/resource/menuApi.js`

## Request Shape

`add`, `edit`, and `changeModule` accept JSON objects. `delete` accepts a Java-style JSON array such as:

```json
[
  { "id": "1780000000000000000" }
]
```

`add` and `edit` require:

- `parentId`
- `title`
- `category`
- `module`
- `menuType`
- `path`
- `icon`
- `color`
- `regType`
- `status`

`sortCode` is optional and must be numeric when present. `edit` also requires `id`.

## Behavior

- Rows are written to `mobile_resource` with `CATEGORY = MENU`.
- Client `category` is required for Java request compatibility, but the stored category is forced to `MENU`.
- Menu writes do not generate `CODE` and do not process `EXT_JSON`.
- Active sibling menu `TITLE` values must be unique under the same `PARENT_ID`.
- Non-root menus require an existing parent mobile menu, and the parent `MODULE` must match the submitted `module`.
- `edit` rejects parent changes to self or to any descendant menu.
- `changeModule` is allowed only for root menus and updates only the current menu plus active descendant `MENU` rows.
- `changeModule` does not validate that the target module exists, does not update buttons, does not update relations, and does not change tenant ownership.
- `delete` logically deletes the target active menu rows plus active descendant menu rows.
- `delete` removes whole `sys_relation` rows where `CATEGORY = SYS_ROLE_HAS_MOBILE_MENU` and `TARGET_ID` is in the deleted menu tree.
- `delete` does not logically delete mobile button rows.

## Verification

Verified on 2026-06-09 against the user-provided local MySQL/Redis runtime:

```powershell
.\scripts\test-agent-db-smoke.ps1
.\scripts\test-agent-smoke.ps1 -SkipComposer
.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -MobileMenuHttpSmoke
```

The DB and HTTP smokes cover add, duplicate-title rejection, child parent/module validation, edit, self/descendant parent rejection, root-only `changeModule`, descendant module propagation, logical delete, mobile-menu role relation cleanup, and button-row preservation.

## Deferred

- Mobile role/mobile menu grant mutations remain deferred.
- Mobile resource data-change events, notification push, and cache invalidation hooks remain deferred.
- No Java source, database schema, seed data, Composer files, `.env`, frontend source, or public config files were changed.
