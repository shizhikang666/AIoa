# Dev Config Safe Compatibility

## Scope

This slice adds query compatibility, low-risk `BIZ_DEFINE` maintenance compatibility, and narrow batch value maintenance for Java `DevConfigController`.

Because `dev_config` stores default passwords, access keys, SecretKey values, email credentials, SMS credentials, and third-party client secrets, this ThinkPHP slice masks sensitive values by default.

## Java Reference

- Java project, read-only: `F:\AI\projects\testJava\OA`
- Controller: `DevConfigController`
- Service: `DevConfigServiceImpl`
- SQL table: `dev_config`
- Frontend API: `snowy-admin-web/src/api/dev/configApi.js`

## Added Routes

Public route:

- `GET /dev/config/sysBaseList`

Protected routes:

- `GET /dev/config/page`
- `GET /dev/config/list`
- `GET /dev/config/detail`
- `POST /dev/config/add`
- `POST /dev/config/edit`
- `POST /dev/config/editBatch`
- `POST /dev/config/delete`

## Response Shape

Rows return:

- `id`
- `configKey`
- `configValue`
- `category`
- `remark`
- `sortCode`
- `extJson`
- `sensitive`
- `createTime`
- `createUser`
- `updateTime`
- `updateUser`

Page responses return:

- `records`
- `total`
- `page`
- `current`
- `limit`
- `size`
- `pages`

Write responses for `add`, `edit`, `editBatch`, and `delete` return Java-compatible success envelopes with `data = null`.

## Sensitive Value Policy

`configValue` is returned as `******` when `configKey` contains one of:

- `PASSWORD`
- `SECRET`
- `TOKEN`
- `PRIVATE`
- `ACCESS_KEY`
- `APP_KEY`

The row also includes `sensitive: true`.

When editing or batch-editing a sensitive key, submitting the mask value `******` preserves the existing stored `configValue` instead of overwriting the secret with the mask.

## Endpoint Notes

- `sysBaseList` returns only `SYS_BASE` records and excludes `SNOWY_SYS_DEFAULT_PASSWORD`, matching Java behavior.
- `page` returns only `BIZ_DEFINE` records, matching Java behavior.
- `list` supports optional `category` and `configKey` filters.
- `detail` is read-only and masks sensitive values.
- `add` creates only `BIZ_DEFINE` records and rejects duplicate active `configKey` values.
- `edit` allows only active `BIZ_DEFINE` records; `SYS_BASE` edit is rejected.
- `editBatch` accepts a non-empty array of `{ configKey, configValue }`, validates the whole batch before writing, updates only existing active rows' `CONFIG_VALUE`, `UPDATE_TIME`, and `UPDATE_USER`, preserves sensitive values when the submitted value is `******`, and rejects duplicate or missing keys.
- `delete` accepts Java-style array payloads such as `[{ "id": "..." }]`, allows only active `BIZ_DEFINE` records, rejects malformed mixed payloads before any write, and marks rows with `DELETE_FLAG = DELETED`.

## Deliberate Exclusions

- `editBatch` does not create rows, delete rows, send provider test messages, or perform file storage operations.
- No Redis config cache mutation is performed; current ThinkPHP reads query `dev_config` directly.
- No database schema or Java source files are changed.

## Later Work

Provider send/test behavior, cache invalidation hooks, and any need to unmask sensitive values should be handled in separate plans. Unmasking sensitive values should require explicit confirmation and should preferably be avoided.

## 2026-06-15 HTTP Smoke Coverage

`scripts/dev-read-http-smoke.ps1` now covers authenticated config metadata reads for:

- `GET /dev/config/page`
- `GET /dev/config/list`
- `GET /dev/config/detail` when a visible `BIZ_DEFINE` sample exists

The smoke asserts Java-style paging keys, masked sensitive value fields, and the `sensitive` flag. It intentionally does not call add, edit, delete, provider send routes, Redis cache mutation, or external services.

## 2026-06-16 EditBatch HTTP Smoke Coverage

`scripts/dev-config-edit-batch-http-smoke.ps1` covers protected batch value updates for temporary `CODEX_DEV_CONFIG_BATCH_*` rows:

- no-token rejection;
- empty list and missing-value rejection;
- successful multi-row batch update across `SYS_BASE`, provider-style, and `BIZ_DEFINE` categories;
- sensitive detail masking;
- sensitive raw value preservation when `******` is submitted;
- duplicate-key rejection before writes;
- mixed missing-key rollback;
- temporary-row cleanup.
