# Gen Metadata Compatibility

## Scope

This document tracks authenticated compatibility for saved generator metadata and field configuration endpoints.

## Java Reference

- Java project, read-only: `F:\AI\projects\testJava\OA`
- Controllers: `GenBasicController`, `GenConfigController`
- Services: `GenBasicServiceImpl`, `GenConfigServiceImpl`
- SQL tables: `gen_basic`, `gen_config`
- Frontend APIs:
  - `snowy-admin-web/src/api/gen/genBasicApi.js`
  - `snowy-admin-web/src/api/gen/genConfigApi.js`

## Added Routes

Protected routes:

- `GET /gen/basic/page`
- `GET /gen/basic/detail`
- `GET /gen/basic/previewGen`
- `GET /gen/basic/execGenZip`
- `GET /gen/basic/tables`
- `GET /gen/basic/tableColumns`
- `GET /gen/basic/mobileModuleSelector`
- `POST /gen/basic/add`
- `POST /gen/basic/edit`
- `POST /gen/basic/delete`
- `POST /gen/basic/execGenPro`
- `GET /gen/config/list`
- `GET /gen/config/detail`
- `POST /gen/config/add`
- `POST /gen/config/edit`
- `POST /gen/config/delete`
- `POST /gen/config/editBatch`

## Response Shape

`/gen/basic/page` returns:

- `records`
- `total`
- `page`
- `current`
- `limit`
- `size`
- `pages`

`gen_basic` rows return Java-style camelCase fields such as:

- `id`
- `dbTable`
- `dbTableKey`
- `pluginName`
- `moduleName`
- `tablePrefix`
- `generateType`
- `module`
- `menuPid`
- `mobileModule`
- `functionName`
- `busName`
- `className`
- `formLayout`
- `gridWhether`
- `packageName`
- `authorName`
- `sortCode`

`gen_config` rows return Java-style camelCase fields such as:

- `id`
- `basicId`
- `isTableKey`
- `fieldName`
- `fieldRemark`
- `fieldType`
- `fieldJavaType`
- `effectType`
- `dictTypeCode`
- `whetherTable`
- `whetherRetract`
- `whetherAddUpdate`
- `whetherRequired`
- `queryWhether`
- `queryType`
- `sortCode`

## Write Compatibility

`POST /gen/basic/add`, `/edit`, and `/delete` now implement narrow generator basic metadata maintenance.

Basic add validates the copied generator form fields, verifies the target database table and primary-key column through `information_schema`, inserts one `gen_basic` row, and creates default `gen_config` rows for the selected table. Basic edit updates only generator basic metadata; when the target table changes it rebuilds active config rows, and when only the key changes it refreshes `IS_TABLE_KEY` flags. Basic delete validates the full id batch, then logically deletes the selected basic rows and their active config rows in one transaction.

`POST /gen/config/edit` and `/delete` implement narrow generator field-config metadata maintenance. Edit requires an active `gen_config` id and the Java `GenConfigEditParam` fields, updates only the same whitelisted metadata columns as editBatch, ignores client-supplied audit/delete fields, and returns `data = null`. Delete accepts Java-style `[{ id }]`, `idList`, `ids`, or a single `id`, validates the whole batch before writing, logically marks active config rows as `DELETED`, does not mutate the parent `gen_basic` row, and returns `data = null`.

`POST /gen/config/editBatch` accepts the copied frontend's Java-style JSON array body and updates active `gen_config` rows.

Each item supports:

- `id`
- `basicId`
- `isTableKey`
- `fieldName`
- `fieldRemark`
- `fieldType`
- `fieldJavaType`
- `effectType`
- `dictTypeCode`
- `whetherTable`
- `whetherRetract`
- `whetherAddUpdate`
- `whetherRequired`
- `queryWhether`
- `queryType`
- `sortCode`

The implementation validates the full batch before writing, rejects deleted or missing rows, updates only the Java edit-parameter column whitelist, ignores client-supplied audit/delete fields, writes update audit metadata from the bearer-token payload when available, and returns `data = null`.

## Preview Compatibility

`GET /gen/basic/previewGen` returns Java-compatible preview buckets:

- `genBasicCodeSqlResultList`
- `genBasicCodeFrontendResultList`
- `genBasicCodeBackendResultList`
- `genBasicCodeMobileResultList`

Each bucket item includes:

- `codeFileName`
- `codeFileWithPathName`
- `codeFileContent`

`genBasicCodeMobileResultList` is `null` when the saved generator basic row has no `mobileModule`, matching the copied preview modal's mobile-tab guard.

This ThinkPHP implementation renders safe preview strings from `gen_basic` and `gen_config` metadata. It does not execute Java Beetl templates, write project files, or run direct project generator output.

## ZIP Download Compatibility

`GET /gen/basic/execGenZip` reuses the same preview buckets and returns an authenticated blob download for copied frontend code-generation ZIP buttons.

The ZIP layout follows Java's `genTempFolder()` grouping:

- SQL files are stored under their preview path, such as `sql/Mysql.sql`.
- Frontend files are stored under `frontend/`.
- Backend files are stored under `backend/`.
- Mobile files are stored under `mobile/` when `genBasicCodeMobileResultList` is present.

The implementation creates a temporary ZIP outside the project tree, reads it into the response, and deletes the temporary file before returning. It performs no database writes, no Java source writes, no frontend source writes, and no menu/role generation side effects.

Focused smoke on 2026-06-12 verified:

- Service output is a valid ZIP (`PK`) containing SQL, frontend, and backend preview entries.
- Authenticated HTTP download returns `Content-Type: application/octet-stream;charset=UTF-8` and Java-style encoded `Content-Disposition`.
- Missing generator id returns 404 through the service.
- No-token HTTP access returns business `code=401`.
- `gen_basic` and `gen_config` row counts stay unchanged.

Focused read HTTP smoke on 2026-06-15 verifies:

- `GET /gen/basic/page`, `/detail`, `/tables`, `/tableColumns`, `/mobileModuleSelector`, and `/previewGen`.
- `GET /gen/config/list` and `/detail` when a saved `gen_basic` sample has config rows.
- The smoke deliberately skips `/gen/basic/execGenZip`, `/gen/config/editBatch`, generator writes, downloads, direct project generation, and source/schema mutations.

Focused write HTTP smoke on 2026-06-16 verifies:

- no-token rejection for `/gen/basic/add`;
- add/detail/config-list readback with default generated `gen_config` rows;
- missing table and missing primary-key rejection;
- same-table primary-key edit refreshing config key flags;
- table-change edit rebuilding active config rows;
- failed mixed delete rollback;
- logical delete hiding the basic row and active config rows;
- physical cleanup of temporary `CODEX_GEN_*` smoke rows.

Focused config write HTTP smoke on 2026-06-16 verifies:

- no-token rejection for `/gen/config/edit`;
- `/gen/config/add` remains controlled-deferred because Java `GenConfigController` has no add route;
- missing required edit field and missing-id rejection;
- edit readback for field metadata, boolean normalization, sort order, and ignored audit/delete fields;
- failed mixed delete rollback;
- logical delete hiding through detail/list reads while preserving the parent `gen_basic` row;
- physical cleanup of temporary `CODEX_GENCFG_*` smoke rows.

## Supported Filters

`/gen/basic/page` supports:

- `id`
- `dbTable`
- `moduleName`
- `functionName`
- `className`
- `searchKey`
- `current`, `page`, or `pageNo`
- `size`, `limit`, or `pageSize`
- `sortField`
- `sortOrder`

`/gen/config/list` supports:

- `basicId`
- `fieldName`
- `sortField`
- `sortOrder`

## Deliberate Exclusions

- `/gen/basic/execGenPro` and `/gen/config/add` return controlled `code = 400` deferred responses.
- Direct generator project writes and generator config add remain deferred.
- No direct project generation, menu/role generation, Java source modification, ThinkPHP source modification, database schema change, Composer change, or `.env` mutation is performed.

## Later Work

Generator writes, direct project output, and full Java Beetl template parity need a dedicated approval and safety design, including schema allow-listing, output path restrictions, permission checks, audit logging, and a clear policy for generated ThinkPHP code ownership.
