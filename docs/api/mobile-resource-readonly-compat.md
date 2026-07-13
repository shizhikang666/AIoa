# Mobile Resource Read-Only Compatibility

Date: 2026-05-29

Agent: merge-agent

## Scope

This slice adds read-only compatibility for old Java OA mobile resource APIs:

- `/mobile/module/page`
- `/mobile/module/detail`
- `/mobile/menu/tree`
- `/mobile/menu/detail`
- `/mobile/menu/moduleSelector`
- `/mobile/menu/menuTreeSelector`
- `/mobile/button/page`
- `/mobile/button/detail`

All routes are protected by `AuthMiddleware`.

## Java Reference

- `MobileModuleController`
- `MobileMenuController`
- `MobileButtonController`
- `MobileModuleServiceImpl`
- `MobileMenuServiceImpl`
- `MobileButtonServiceImpl`

The Java mobile module/menu/button APIs all read from `mobile_resource` and separate records with `CATEGORY`:

- `MODULE`
- `MENU`
- `BUTTON`

## Behavior

- Pagination supports `current`, `page`, `pageNo`, `size`, `limit`, and `pageSize`.
- Search filters by `TITLE` with `searchKey` or `title`.
- Mobile button page supports `parentId` filtering.
- Mobile menu tree uses descending `SORT_CODE`, matching Java `MobileMenuServiceImpl.tree`.
- Selectors return lower camel case fields plus `label`, `value`, and `name` for frontend compatibility.

## Deferred

The following Java endpoints remain intentionally deferred:

- mobile role/mobile menu grant mutations

Mobile module add/edit/delete are now covered separately in `docs/api/mobile-module-write-compat.md`.
Mobile menu add/edit/changeModule/delete are now covered separately in `docs/api/mobile-menu-write-compat.md`.
Mobile button add/edit/delete are now covered separately in `docs/api/mobile-button-write-compat.md`.

No Java source, database schema, seed data, Composer files, `.env`, or public config files were changed.

## 2026-06-15 HTTP Smoke Coverage

`scripts/resource-read-http-smoke.ps1` now covers authenticated mobile resource reads for:

- `GET /mobile/module/page`
- `GET /mobile/module/detail` when a module row exists
- `GET /mobile/menu/tree`
- `GET /mobile/menu/detail` when a menu row exists
- `GET /mobile/menu/moduleSelector`
- `GET /mobile/menu/menuTreeSelector`
- `GET /mobile/button/page`
- `GET /mobile/button/detail` when a button row exists

The smoke asserts Java-style paging keys, mobile resource row aliases, selector `label`/`value`, and copied-frontend `name` aliases. It intentionally does not call mobile module/menu/button add, edit, delete, change-module, mobile role grants, cache invalidation, or data-change behavior.
