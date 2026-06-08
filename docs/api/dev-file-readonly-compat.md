# Dev File Compatibility

## Scope

This slice adds compatibility for Java `DevFileController` metadata query endpoints, LOCAL/dynamic upload routes, and the public local-file download route used by the copied Vue frontend.

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

Protected LOCAL/dynamic upload routes:

- `POST /dev/file/uploadDynamicReturnId`
- `POST /dev/file/uploadDynamicReturnUrl`
- `POST /dev/file/uploadDynamicReturnFile`
- `POST /dev/file/uploadLocalReturnId`
- `POST /dev/file/uploadLocalReturnUrl`
- `POST /dev/file/uploadLocalReturnFile`

Protected cloud upload stubs:

- `POST /dev/file/uploadAliyunReturnId`
- `POST /dev/file/uploadAliyunReturnUrl`
- `POST /dev/file/uploadAliyunReturnFile`
- `POST /dev/file/uploadTencentReturnId`
- `POST /dev/file/uploadTencentReturnUrl`
- `POST /dev/file/uploadTencentReturnFile`
- `POST /dev/file/uploadMinioReturnId`
- `POST /dev/file/uploadMinioReturnUrl`
- `POST /dev/file/uploadMinioReturnFile`

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
- LOCAL uploads store files under `runtime/upload/dev_file/defaultBucketName/yyyy/M/d/`, write `dev_file.STORAGE_PATH` as an absolute path, and write `DOWNLOAD_PATH` as `/api/dev/file/download?id=<id>`.
- Dynamic upload routes read `SNOWY_SYS_DEFAULT_FILE_ENGINE`; this compatibility slice requires the default engine to stay `LOCAL`. If it is changed to Aliyun, Tencent, or Minio before real provider storage is implemented, dynamic uploads return `code = 501`.
- `upload*ReturnId`, `upload*ReturnUrl`, and `upload*ReturnFile` return Java-compatible `data` values: string id, string download path, or a camelCase file row.
- Uploads reject empty files, files larger than 50 MB, and executable/script-style extensions such as `.php`, `.exe`, `.bat`, `.cmd`, `.ps1`, `.sh`, `.js`, and `.vbs`.
- Aliyun, Tencent, and Minio routes are registered but return `code = 501` until real provider credentials and storage clients are implemented.
- `download` follows Java behavior: it is intentionally public, supports `LOCAL` engine rows only, reads `STORAGE_PATH`, sends the stored `NAME` as an attachment filename, and returns Java-compatible JSON errors for missing rows, non-local engines, or missing local files.

## Deliberate Exclusions

- `POST /dev/file/delete` is not implemented.
- Cloud provider uploads are not implemented; their routes return unsupported responses instead of pretending to store files locally.
- Image thumbnail generation is not implemented for new uploads.
- Download path root whitelisting for historical imported `STORAGE_PATH` values is deferred until a migration/whitelist plan is approved.
- `/biz/bizfilerelation/add` is not part of this slice, so business attachment upload screens may still need a relation-write slice after file storage succeeds.
- No database schema or Java source files are changed.

## Later Work

Delete, cloud provider uploads, thumbnail generation, and business attachment relation writes need dedicated plans covering provider credentials, access control, file-size limits, audit logging, cleanup, and safe path handling. Current LOCAL download compatibility follows Java by reading existing local paths into memory before returning them.
