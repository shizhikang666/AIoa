# Position Write Compatibility

Date: 2026-06-06

Agent: user-agent / frontend-agent

## Scope

This slice supports copied system and business position maintenance pages:

- `snowy-admin-web/src/api/sys/positionApi.js`
- `snowy-admin-web/src/api/biz/bizPositionApi.js`
- `snowy-admin-web/src/views/sys/position/index.vue`
- `snowy-admin-web/src/views/sys/position/form.vue`
- `snowy-admin-web/src/views/biz/position/index.vue`
- `snowy-admin-web/src/views/biz/position/form.vue`

The Java source project remains read-only at `F:\AI\projects\testJava\OA`.

## Implemented Routes

| Method | Path | Target |
| --- | --- | --- |
| POST | `/sys/position/add` | `sys.PositionController/add` |
| POST | `/sys/position/edit` | `sys.PositionController/edit` |
| POST | `/sys/position/delete` | `sys.PositionController/delete` |
| POST | `/biz/position/add` | `sys.PositionController/bizAdd` |
| POST | `/biz/position/edit` | `sys.PositionController/bizEdit` |
| POST | `/biz/position/delete` | `sys.PositionController/bizDelete` |

All routes are protected by `AuthMiddleware`.

## Request Compatibility

Add and edit accept copied frontend camelCase fields:

- `orgId`
- `name`
- `category`
- `sortCode`
- `extJson`

Delete accepts copied frontend batch payloads such as:

```json
[{ "id": "..." }]
```

It also accepts common `id`, `ids`, `idList`, and `positionIds` payload forms.

## Behavior

- Add validates active organization, category values `HIGH`, `MIDDLE`, and `LOW`, same-organization duplicate names, tenant compatibility, and base route/button permission.
- Edit validates the active position, active organization, category, same-organization duplicate names, tenant compatibility, and base route/button permission.
- Delete blocks positions referenced by active users through direct `POSITION_ID`.
- Delete blocks positions referenced by active users through `POSITION_JSON[*].positionId`.
- Delete uses logical `sys_position.DELETE_FLAG = DELETED` instead of Java's physical removal to keep the staged refactor safer on imported data.
- Business routes add Java-compatible organization data-scope checks.

## Deferred

- Java data-change event publishing
- Java physical delete behavior
- User import/export
- Route-permission middleware
- Frontend source changes
- Database schema changes
- Workflow, finance, stock, and unrelated business side effects

## Verification

- `php -l app\controller\sys\PositionController.php`
- `php -l app\service\user\PositionService.php`
- `php -l route\app.php`
- `php think route:list`
- Direct service smoke creates, edits, logically deletes, and physically cleans only temporary smoke positions.
- No-token HTTP smoke for all six POST routes returns business `code=401`.
