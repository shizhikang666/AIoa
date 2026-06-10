# Sys Field Write Compatibility

Date: 2026-06-10

Agent: main coordinator / api-agent / test-agent

## Scope

This slice opens the copied system field resource maintenance endpoints used by:

- `snowy-admin-web/src/api/sys/resource/fieldApi.js`
- `snowy-admin-web/src/views/sys/resource/field/form.vue`
- `snowy-admin-web/src/views/sys/resource/field/index.vue`

The Java reference project has no `SysFieldController`, `SysFieldService`, `SysField*Param`, or `FIELD` resource enum entry. Compatibility is therefore based on the copied Vue request shape plus the existing ThinkPHP `sys_resource.CATEGORY = FIELD` read model.

## Routes

| Method | Path | Controller |
| --- | --- | --- |
| POST | `/sys/field/add` | `sys.FieldController/add` |
| POST | `/sys/field/edit` | `sys.FieldController/edit` |
| POST | `/sys/field/delete` | `sys.FieldController/delete` |

All routes remain protected by `AuthMiddleware`.

## Request Shape

`add` accepts the copied frontend form body:

```json
{
  "category": "FIELD",
  "parentId": "menu-id",
  "title": "Field title",
  "code": "FIELD_CODE",
  "sortCode": 99
}
```

`edit` accepts the same fields plus `id`.

`delete` accepts Java-style array payloads:

```json
[
  { "id": "field-id" }
]
```

Compatibility aliases `idList`, `ids`, `id`, and `fieldIds` are also accepted for delete.

## Behavior

- Rows are written to `sys_resource`.
- Storage category is forced to `FIELD`; client `category` is not trusted.
- Add and edit require `parentId`, `title`, `code`, and numeric `sortCode`.
- `parentId` must reference an active `MENU` row, matching the copied field drawer owner.
- Active fields reject duplicate `code` under the same `parentId`.
- `extJson` is preserved when submitted and normalized using the existing resource JSON helper.
- Audit fields are set from the auth payload when available.
- Delete logically marks active field rows as `DELETE_FLAG = DELETED`.
- Delete tolerates mixed existing and missing ids.
- Delete removes direct `SYS_ROLE_HAS_RESOURCE` rows whose `TARGET_ID` is the deleted field id.

## Intentional Limits

- No Java source, database schema, frontend source, Composer files, `.env`, or public config files are changed.
- No Java data-change event publishing or cache invalidation hook is emulated.
- No `buttonInfo` cleanup is applied to fields, because Java role resources only define menu ids and button info in the inspected implementation.
- No module-level field validation is applied because copied field forms do not submit `module`.

## Verification

- `php -l app/service/sys/ResourceService.php`
- `php -l app/controller/sys/FieldController.php`
- `php -l route/app.php`
- `php think route:list | Select-String -Pattern "sys/field/(add|edit|delete)"`
- `.\scripts\test-agent-db-smoke.ps1`
- `.\scripts\test-agent-smoke.ps1 -SkipComposer`
- `.\scripts\test-agent-smoke.ps1 -SkipComposer -BackendBaseUrl http://127.0.0.1:82 -SysFieldHttpSmoke`
