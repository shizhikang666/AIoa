# Dev File Metadata Read-Only Compatibility

## Scope

This slice adds authenticated, read-only compatibility for Java `DevFileController` metadata query endpoints.

## Java Reference

- Java project, read-only: `F:\AI\projects\testJava\OA`
- Controller: `DevFileController`
- Service: `DevFileServiceImpl`
- SQL table: `dev_file`
- Frontend API: `snowy-admin-web/src/api/dev/fileApi.js`

## Added Routes

Protected routes:

- `GET /dev/file/page`
- `GET /dev/file/list`
- `GET /dev/file/detail`

## Response Shape

Rows return:

- `id`
- `engine`
- `bucket`
- `name`
- `suffix`
- `sizeKb`
- `sizeInfo`
- `objName`
- `storagePath`
- `downloadPath`
- `thumbnail`
- `extJson`
- `tenantId`
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

## Supported Filters

- `id`
- `engine`
- `suffix`
- `searchKey`
- `current`, `page`, or `pageNo`
- `size`, `limit`, or `pageSize`
- `sortField`
- `sortOrder`

Supported sort fields are:

- `id`
- `engine`
- `bucket`
- `name`
- `suffix`
- `sizeKb`
- `createTime`
- `updateTime`

## Endpoint Notes

- `page` returns paginated metadata and includes stored thumbnails for compatibility with the existing file management table.
- `list` returns at most 200 lightweight metadata rows and omits thumbnail payloads to avoid returning large base64 data sets.
- `detail` returns the full stored metadata row for one file id.

## Deliberate Exclusions

- Upload routes are not implemented.
- `GET /dev/file/download` is not implemented in this slice.
- `POST /dev/file/delete` is not implemented.
- No local filesystem file content is read.
- No database schema or Java source files are changed.

## Later Work

Upload and download need a dedicated storage plan covering local filesystem roots, cloud engine credentials, access control, file-size limits, audit logging, and safe path handling.
