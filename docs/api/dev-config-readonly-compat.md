# Dev Config Safe Compatibility

## Scope

This slice adds query compatibility plus low-risk `BIZ_DEFINE` maintenance compatibility for Java `DevConfigController`.

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

Write responses for `add`, `edit`, and `delete` return Java-compatible success envelopes with `data = null`.

## Sensitive Value Policy

`configValue` is returned as `******` when `configKey` contains one of:

- `PASSWORD`
- `SECRET`
- `TOKEN`
- `PRIVATE`
- `ACCESS_KEY`
- `APP_KEY`

The row also includes `sensitive: true`.

When editing a sensitive key, submitting the mask value `******` preserves the existing stored `configValue` instead of overwriting the secret with the mask.

## Endpoint Notes

- `sysBaseList` returns only `SYS_BASE` records and excludes `SNOWY_SYS_DEFAULT_PASSWORD`, matching Java behavior.
- `page` returns only `BIZ_DEFINE` records, matching Java behavior.
- `list` supports optional `category` and `configKey` filters.
- `detail` is read-only and masks sensitive values.
- `add` creates only `BIZ_DEFINE` records and rejects duplicate active `configKey` values.
- `edit` allows only active `BIZ_DEFINE` records; `SYS_BASE` edit is rejected.
- `delete` accepts Java-style array payloads such as `[{ "id": "..." }]`, allows only active `BIZ_DEFINE` records, rejects malformed mixed payloads before any write, and marks rows with `DELETE_FLAG = DELETED`.

## Deliberate Exclusions

- `POST /dev/config/editBatch` is not implemented.
- `SYS_BASE` writes remain closed; provider/system configuration batch updates need a separate cache and secret-handling plan.
- No Redis config cache mutation is performed.
- No database schema or Java source files are changed.

## Later Work

`editBatch` needs a separate permission, cache, and audit plan. Unmasking sensitive values should require explicit confirmation and should preferably be avoided.

## 2026-06-15 HTTP Smoke Coverage

`scripts/dev-read-http-smoke.ps1` now covers authenticated config metadata reads for:

- `GET /dev/config/page`
- `GET /dev/config/list`
- `GET /dev/config/detail` when a visible `BIZ_DEFINE` sample exists

The smoke asserts Java-style paging keys, masked sensitive value fields, and the `sensitive` flag. It intentionally does not call add, edit, delete, `editBatch`, `SYS_BASE` writes, provider config mutation, Redis cache mutation, Email/SMS provider routes, or external services.
