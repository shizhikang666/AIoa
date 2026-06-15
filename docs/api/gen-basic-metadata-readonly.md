# Gen Basic Metadata Read-Only Compatibility

Date: 2026-06-05

Agent: api-agent / frontend-agent

## Scope

This document records the read-only ThinkPHP compatibility endpoints for copied generator basic form metadata.

Java reference:

- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-gen\src\main\java\vip\xiaonuo\gen\modular\basic\controller\GenBasicController.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-gen\src\main\java\vip\xiaonuo\gen\modular\basic\service\impl\GenBasicServiceImpl.java`

Frontend reference:

- `snowy-admin-web/src/api/gen/genBasicApi.js`
- `snowy-admin-web/src/views/gen/basic.vue`

## Endpoints

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/gen/basic/tables` | List current database base tables for the generator form. |
| GET | `/gen/basic/tableColumns` | List columns for one table. |

Both routes are protected by `AuthMiddleware`.

## Response Shapes

`/gen/basic/tables` returns:

```json
[
  {
    "tableName": "sys_user",
    "tableRemark": "sys_user"
  }
]
```

`/gen/basic/tableColumns?tableName=sys_user` returns:

```json
[
  {
    "columnName": "ID",
    "typeName": "VARCHAR",
    "columnRemark": "ID"
  }
]
```

## Compatibility Notes

- Table and column metadata is read from MySQL `information_schema`.
- `ACT_` workflow engine tables are excluded, matching the Java `tables()` behavior.
- Column names and SQL type names are upper-cased to match the Java JDBC metadata mapping.
- Empty table or column comments fall back to the table or column name.
- `/gen/basic/tableColumns` requires `tableName`; missing values return a 400-style API failure through the shared controller guard.
- `scripts/gen-read-http-smoke.ps1` now covers these metadata reads through authenticated HTTP without invoking generator writes or ZIP download.

## Deferred

- `/gen/basic/add`, `/edit`, and `/delete` remain deferred.
- `/gen/basic/previewGen` is now covered as a safe metadata-only preview route.
- `/gen/basic/execGenZip` is now covered as a protected temporary ZIP download that reuses preview output, writes no project files, and deletes its temporary archive after reading it.
- `/gen/basic/execGenPro` remains deferred because it writes generated code into project directories and creates menu/role side effects in Java.
- Generator templates, direct project code generation, database schema changes, Java source changes, Composer files, `.env`, and frontend source are unchanged.
