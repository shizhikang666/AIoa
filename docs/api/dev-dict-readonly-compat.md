# Dev Dict Compatibility

Date: 2026-05-29

Updated: 2026-06-08

Agent: merge-agent, api-agent

## Scope

This document records compatibility for old Java OA dictionary APIs:

- `/dev/dict/page`
- `/dev/dict/list`
- `/dev/dict/tree`
- `/dev/dict/detail`
- `/dev/dict/add`
- `/dev/dict/edit`
- `/dev/dict/delete`

All routes are protected by `AuthMiddleware`.

## Java Reference

- `DevDictController`
- `DevDictServiceImpl`

The old frontend loads `/dev/dict/tree` after login and stores the response as `DICT_TYPE_TREE_DATA`. Many pages then call `tool.dictList(...)` or `tool.dictListByPath(...)` against that cached data.

## Behavior

- `tree` returns nodes with `id`, `parentId`, `dictLabel`, `dictValue`, `name`, `label`, `value`, `weight`, and `children`.
- `page`, `list`, and `tree` support `category`, `parentId`, and `searchKey` filters.
- Tenant visibility follows the Java read pattern for page/tree compatibility: system dictionaries with `CATEGORY = FRM` plus rows for the current token tenant.
- Pagination supports `current`, `page`, `pageNo`, `size`, `limit`, and `pageSize`.
- Write routes are currently limited to `CATEGORY = BIZ`, which covers the copied business-dictionary maintenance page that posts through `/dev/dict/add`, `/dev/dict/edit`, and `/dev/dict/delete`.
- `add` requires `dictLabel`, `dictValue`, and numeric `sortCode`; it defaults empty `parentId` to `0`, writes current tenant/audit fields, and stores optional `viewState`, `editState`, and `extJson`.
- `edit` supports business maintenance fields including `parentId`, `dictLabel`, `dictValue`, `sortCode`, `viewState`, `editState`, and `extJson`.
- `delete` soft-deletes selected BIZ rows and active BIZ descendants by setting `DELETE_FLAG = DELETED`.
- Write routes require an admin-compatible role or the matching route permission code in the token payload.
- Parent changes reject self-parenting and moving a node under one of its active descendants.
- Delete recursion and the final update are tenant-bound to the selected BIZ rows.

## Deferred

The following Java endpoints remain intentionally deferred:

- translation cache refresh behavior
- cross-tenant dictionary administration policy
- FRM/system dictionary write management

No Java source, database schema, seed data, Composer files, `.env`, or public config files were changed.

## 2026-06-15 HTTP Smoke Coverage

`scripts/dev-read-http-smoke.ps1` now covers authenticated dictionary reads for:

- `GET /dev/dict/page`
- `GET /dev/dict/list`
- `GET /dev/dict/tree`
- `GET /dev/dict/detail` when a visible dictionary sample exists

The smoke asserts Java-style paging keys plus tree `name`/`label`/`value` aliases. It intentionally does not call add, edit, delete, FRM/system dictionary writes, translation cache refresh, or data-change behavior.
