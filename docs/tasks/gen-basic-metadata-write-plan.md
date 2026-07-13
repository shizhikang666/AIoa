# Gen Basic Metadata Write Plan

Date: 2026-06-16

## Scope

Replace only the copied frontend `/gen/basic/add`, `/gen/basic/edit`, and `/gen/basic/delete` controlled-deferred wrappers with safe generator metadata maintenance.

Keep `/gen/basic/execGenPro` controlled-deferred because it writes generated code into project directories and can create menu/role side effects in Java.

Keep `/gen/config/add`, `/gen/config/edit`, and `/gen/config/delete` controlled-deferred. The copied field configuration grid already saves through the implemented `/gen/config/editBatch` endpoint.

## Java Reference

- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-gen\src\main\java\vip\xiaonuo\gen\modular\basic\controller\GenBasicController.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-gen\src\main\java\vip\xiaonuo\gen\modular\basic\service\impl\GenBasicServiceImpl.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-gen\src\main\java\vip\xiaonuo\gen\modular\basic\param\GenBasicAddParam.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-gen\src\main\java\vip\xiaonuo\gen\modular\basic\param\GenBasicEditParam.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-gen\src\main\java\vip\xiaonuo\gen\modular\config\param\GenConfigAddParam.java`

## Planned Behavior

`POST /gen/basic/add`:

- validate the Java form fields used by the copied generator form;
- reject missing target tables, `ACT_` workflow tables, and missing primary-key columns;
- insert one `gen_basic` row with audit metadata;
- create default `gen_config` rows from current `information_schema.COLUMNS`;
- return the inserted generator basic row.

`POST /gen/basic/edit`:

- require an active `gen_basic` id;
- update only generator basic metadata fields;
- preserve create audit and delete state;
- if the table changes, logically delete old config rows and rebuild config rows for the new table;
- if only the primary key changes, update the `IS_TABLE_KEY` flags without overwriting field display/edit configuration.

`POST /gen/basic/delete`:

- accept Java-style `[{ id }]`, `idList`, or `ids` payloads;
- validate the full batch before writing;
- logically delete the selected `gen_basic` rows and their active `gen_config` rows in one transaction.

## Deliberate Exclusions

- No direct project generation.
- No generator output written to Java, ThinkPHP, frontend, or mobile source directories.
- No route/menu/role/resource generation.
- No database schema changes.
- No Composer/npm dependency changes.
- No Java source changes.
- No `gen/config/add`, `/edit`, or `/delete` behavior beyond their existing controlled-deferred wrappers.

## Verification

- `php -l app\controller\gen\BasicController.php`
- `php -l app\service\gen\BasicService.php`
- `php think route:list | Select-String -Pattern 'gen/basic/(add|edit|delete|execGenPro|page|detail|tables|tableColumns)'`
- `.\scripts\gen-basic-write-http-smoke.ps1`
- `.\scripts\gen-read-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `git diff --check`
