# Dev Config EditBatch Write Plan

Date: 2026-06-16

## Scope

Replace only the copied frontend `/dev/config/editBatch` controlled-deferred wrapper with narrow `dev_config` value maintenance.

This endpoint is used by copied configuration forms for system base, file, email, SMS, third-party login, and other provider settings. It must remain a configuration-row update only.

## Java References

- Java controller: `F:\AI\projects\testJava\OA\snowy-plugin\fudi-plugin-tenants\src\main\java\vip\xiaonuo\tenant\modular\config\controller\DevConfigController.java`
- Java service: `F:\AI\projects\testJava\OA\snowy-plugin\fudi-plugin-tenants\src\main\java\vip\xiaonuo\tenant\modular\config\service\impl\DevConfigServiceImpl.java`
- Java param: `F:\AI\projects\testJava\OA\snowy-plugin\fudi-plugin-tenants\src\main\java\vip\xiaonuo\tenant\modular\config\param\DevConfigBatchParam.java`
- Frontend API: `snowy-admin-web/src/api/dev/configApi.js`
- ThinkPHP target: `app/controller/dev/ConfigController.php`, `app/service/dev/ConfigService.php`

## Planned Behavior

- Accept a non-empty JSON array of `{ configKey, configValue }` objects.
- Reject missing, blank, unknown, deleted, or duplicate `configKey` values before any write.
- Reject blank `configValue`, matching Java `@NotBlank`.
- Update only `CONFIG_VALUE`, `UPDATE_TIME`, and `UPDATE_USER` on existing active `dev_config` rows.
- Keep all rows in a single transaction so mixed invalid batches do not partially update.
- Preserve existing sensitive values when the submitted value is the displayed mask `******`.
- Return Java-compatible success envelopes with `data = null`.

## Deliberate Exclusions

- No new configuration rows.
- No physical delete or logical delete.
- No provider send/test behavior.
- No file storage provider mutation beyond updating existing config values.
- No Redis/cache mutation is added in this PHP slice because the current ThinkPHP config reads query `dev_config` directly.
- No Java source, database schema, Composer/npm, or frontend source changes.

## Verification

- `php -l app\controller\dev\ConfigController.php`
- `php -l app\service\dev\ConfigService.php`
- `php think route:list | Select-String -Pattern 'dev/config/(editBatch|page|list|detail)'`
- `.\scripts\dev-config-edit-batch-http-smoke.ps1`
- `.\scripts\dev-read-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`

The write smoke creates temporary `CODEX_DEV_CONFIG_BATCH_*` rows, verifies successful batch updates, sensitive mask preservation, duplicate-key rejection, mixed missing-key rollback, no-token rejection, and physical cleanup of only those temporary rows.
