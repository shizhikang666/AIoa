# Sales Project Field Change Log Read-Only Compatibility

Date: 2026-06-04

Agent: api-agent / frontend-agent

## Scope

This slice adds read-only ThinkPHP compatibility for sale-project field change log browsing.

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Added Routes

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/biz/salesprojectfieldchangelog/page` | Page sale-project field change log rows. |
| GET | `/biz/salesprojectfieldchangelog/detail` | Read one field change log row by id. |

Both routes are protected by `AuthMiddleware`.

## Java Compatibility Notes

- Java `SalesProjectFieldChangeLogController` exposes `page`, `add`, `edit`, `delete`, and `detail`; this slice only opens the read-only `page` and `detail` endpoints.
- Java `SalesProjectFieldChangeLogServiceImpl.page` defaults sorting to `ID` ascending.
- The ThinkPHP service filters logical deletes and applies tenant filtering when the token payload or request contains a tenant id.
- Rows include the original change fields plus project and creator display aliases for copied sale-project detail/history views.
- `sales_project_field_change_log.OBJECT_ID` and `biz_sale_project.ID` use different collations in the imported SQL, so the read join uses an explicit collation without changing the database schema.

## Deferred

The following remain intentionally deferred:

- `/biz/salesprojectfieldchangelog/add`
- `/biz/salesprojectfieldchangelog/edit`
- `/biz/salesprojectfieldchangelog/delete`
- sale-project change-log generation writes
- sale-project amount/change writes
- workflow, finance, and audit side effects
- Java source changes
- database schema changes
