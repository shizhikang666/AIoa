# Biz Dict Edit Compatibility

Date: 2026-06-06

Agent: api-agent

## Scope

This slice adds the copied frontend business dictionary edit endpoint:

- `POST /biz/dict/edit`

It does not add business dictionary add/delete routes and does not change system dictionary management behavior.

## Route

| Method | Path | Controller |
| --- | --- | --- |
| POST | `/biz/dict/edit` | `dev.DictController/edit` |

The route is registered in the existing `/biz/dict` group and remains protected by `AuthMiddleware`.

## Behavior

- Accepts copied frontend form payloads through form POST, raw JSON, or request parameters.
- Requires `id`, `dictLabel`, and numeric `sortCode`.
- Allows optional `parentId` and `extJson`.
- Edits only active `dev_dict` rows where `CATEGORY = BIZ`.
- Preserves existing `CATEGORY`, `DICT_VALUE`, `TENANT_ID`, `CREATE_TIME`, and `CREATE_USER`.
- Updates `PARENT_ID`, `DICT_LABEL`, `SORT_CODE`, optional `EXT_JSON`, `UPDATE_TIME`, and `UPDATE_USER`.
- Blocks same-parent duplicate business dictionary labels.
- Rejects missing, deleted, non-business, or tenant-incompatible dictionary rows.

## Deferred

- `/biz/dict/add`
- `/biz/dict/delete`
- system dictionary writes under `/dev/dict`
- dictionary cache invalidation parity with Java `DictionaryTransService`
- frontend source changes
- Java source changes
- database schema changes
