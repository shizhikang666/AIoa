# Dev File Metadata Read-Only Compatibility

## Scope

This slice adds compatibility for Java `DevFileController` metadata query endpoints and the public local-file download route used by the copied Vue frontend.

## Java Reference

- Java project, read-only: `F:\AI\projects\testJava\OA`
- Controller: `DevFileController`
- Service: `DevFileServiceImpl`
- SQL table: `dev_file`
- Frontend API: `snowy-admin-web/src/api/dev/fileApi.js`

## Added Routes

Protected metadata routes:

- `GET /dev/file/page`
- `GET /dev/file/list`
- `GET /dev/file/detail`

Public download route:

- `GET /dev/file/download?id=<id>`

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
- Local-file metadata rewrites `downloadPath` to `/api/dev/file/download?id=<id>` so copied frontend anchors, images, and preview components do not keep using the imported old Java domain.
- Non-local engine rows keep their stored `DOWNLOAD_PATH`.
- `download` follows Java behavior: it is intentionally public, supports `LOCAL` engine rows only, reads `STORAGE_PATH`, sends the stored `NAME` as an attachment filename, and returns Java-compatible JSON errors for missing rows, non-local engines, or missing local files.

## Deliberate Exclusions

- Upload routes are not implemented.
- `POST /dev/file/delete` is not implemented.
- No database schema or Java source files are changed.

## Later Work

Upload and delete need a dedicated storage plan covering local filesystem roots, cloud engine credentials, access control, file-size limits, audit logging, and safe path handling. Current download compatibility follows Java by reading existing local paths into memory before returning them.
