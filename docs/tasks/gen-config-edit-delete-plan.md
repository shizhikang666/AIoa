# Gen Config Edit/Delete Plan

Date: 2026-06-16

## Scope

Replace only Java-compatible `/gen/config/edit` and `/gen/config/delete` controlled-deferred wrappers with safe generator field-configuration metadata maintenance.

Keep `/gen/config/add` controlled-deferred. The read Java `GenConfigController` exposes `list`, `detail`, `edit`, `delete`, and `editBatch`; it does not expose `/gen/config/add`.

## Java Reference

- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-gen\src\main\java\vip\xiaonuo\gen\modular\config\controller\GenConfigController.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-gen\src\main\java\vip\xiaonuo\gen\modular\config\service\impl\GenConfigServiceImpl.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-gen\src\main\java\vip\xiaonuo\gen\modular\config\param\GenConfigEditParam.java`
- `F:\AI\projects\testJava\OA\snowy-plugin\snowy-plugin-gen\src\main\java\vip\xiaonuo\gen\modular\config\param\GenConfigIdParam.java`

## Planned Behavior

`POST /gen/config/edit`:

- require an active `gen_config` id;
- use the same Java edit-parameter whitelist as existing `/gen/config/editBatch`;
- validate the row exists before writing;
- update audit metadata from the bearer-token payload when available;
- return `data = null`.

`POST /gen/config/delete`:

- accept Java-style `[{ id }]`, `idList`, `ids`, or a single `id`;
- validate the full id batch before any write;
- logically delete the selected active `gen_config` rows in one transaction;
- return `data = null`.

## Deliberate Exclusions

- No `/gen/config/add` behavior.
- No `gen_basic` mutation.
- No generator preview, ZIP, or direct project generation changes.
- No generated source writes, menu/role/resource generation, database schema changes, Composer/npm changes, Java source changes, or commits.

## Verification

- `php -l app\controller\gen\ConfigController.php`
- `php -l app\service\gen\ConfigService.php`
- `php think route:list | Select-String -Pattern 'gen/config/(add|edit|delete|editBatch|list|detail)'`
- `.\scripts\gen-config-write-http-smoke.ps1`
- `.\scripts\gen-read-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `git diff --check`
