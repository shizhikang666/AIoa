# Gen Metadata Read-Only Compatibility

## Scope

This slice adds authenticated, read-only compatibility for saved generator metadata and field configuration endpoints.

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
- `GET /gen/basic/mobileModuleSelector`
- `GET /gen/config/list`
- `GET /gen/config/detail`

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
- No `/gen/config/editBatch` route is implemented.
- No `/gen/basic/tables` route is implemented.
- No `/gen/basic/tableColumns` route is implemented.
- No `/gen/basic/execGenZip` route is implemented.
- No `/gen/basic/execGenPro` route is implemented.
- No `/gen/basic/previewGen` route is implemented.
- No database schema scanning, code template rendering, file writing, ZIP generation, or Java source modification is performed.

## Later Work

Generator writes, code preview, code generation, and database table/column scanning need a dedicated approval and safety design, including schema allow-listing, output path restrictions, permission checks, audit logging, and a clear policy for generated ThinkPHP code ownership.
