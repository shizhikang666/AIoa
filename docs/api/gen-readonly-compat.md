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
- `GET /gen/basic/tables`
- `GET /gen/basic/tableColumns`
- `GET /gen/basic/mobileModuleSelector`
- `GET /gen/config/list`
- `GET /gen/config/detail`
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

This ThinkPHP implementation renders safe preview strings from `gen_basic` and `gen_config` metadata. It does not execute Java Beetl templates, write project files, create ZIP archives, or run generator output.

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

- No `/gen/basic/add` route is implemented.
- No `/gen/basic/edit` route is implemented.
- No `/gen/basic/delete` route is implemented.
- No `/gen/config/edit` route is implemented.
- No `/gen/config/delete` route is implemented.
- No `/gen/basic/execGenZip` route is implemented.
- No `/gen/basic/execGenPro` route is implemented.
- No file writing, ZIP generation, direct project generation, or Java source modification is performed.

## Later Work

Generator writes, executable code generation, and direct project output need a dedicated approval and safety design, including schema allow-listing, output path restrictions, permission checks, audit logging, and a clear policy for generated ThinkPHP code ownership.
