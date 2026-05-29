# Dev Config Safe Read-Only Compatibility

## Scope

This slice adds read-only compatibility for Java `DevConfigController` query endpoints.

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

## Sensitive Value Policy

`configValue` is returned as `******` when `configKey` contains one of:

- `PASSWORD`
- `SECRET`
- `TOKEN`
- `PRIVATE`
- `ACCESS_KEY`
- `APP_KEY`

The row also includes `sensitive: true`.

## Endpoint Notes

- `sysBaseList` returns only `SYS_BASE` records and excludes `SNOWY_SYS_DEFAULT_PASSWORD`, matching Java behavior.
- `page` returns only `BIZ_DEFINE` records, matching Java behavior.
- `list` supports optional `category` and `configKey` filters.
- `detail` is read-only and masks sensitive values.

## Deliberate Exclusions

- `POST /dev/config/add` is not implemented.
- `POST /dev/config/edit` is not implemented.
- `POST /dev/config/delete` is not implemented.
- `POST /dev/config/editBatch` is not implemented.
- No Redis config cache mutation is performed.
- No database schema or Java source files are changed.

## Later Work

Write endpoints need a separate permission and audit plan. Unmasking sensitive values should require explicit confirmation and should preferably be avoided; unchanged sensitive values can be handled with a "keep existing secret" write contract later.
