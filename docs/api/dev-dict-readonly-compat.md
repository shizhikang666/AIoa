# Dev Dict Read-Only Compatibility

Date: 2026-05-29

Agent: merge-agent

## Scope

This slice adds read-only compatibility for old Java OA dictionary APIs:

- `/dev/dict/page`
- `/dev/dict/list`
- `/dev/dict/tree`
- `/dev/dict/detail`

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

## Deferred

The following Java endpoints remain intentionally deferred:

- dictionary add/edit/delete
- translation cache refresh behavior
- cross-tenant dictionary administration policy

No Java source, database schema, seed data, Composer files, `.env`, or public config files were changed.
