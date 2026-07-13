# Sales Project Field Change Log Compatibility

Updated: 2026-06-18

Agent: api-agent / frontend-agent

## Scope

This document tracks ThinkPHP compatibility for sale-project field change log browsing and base log-row writes.

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Routes

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/biz/salesprojectfieldchangelog/page` | Page sale-project field change log rows. |
| GET | `/biz/salesprojectfieldchangelog/detail` | Read one field change log row by id. |
| POST | `/biz/salesprojectfieldchangelog/add` | Add one sale-project field change log row. |
| POST | `/biz/salesprojectfieldchangelog/edit` | Edit one sale-project field change log row. |
| POST | `/biz/salesprojectfieldchangelog/delete` | Logically delete one or more sale-project field change log rows. |

All routes are protected by `AuthMiddleware`.

## Java Compatibility Notes

- Java `SalesProjectFieldChangeLogController` exposes `page`, `add`, `edit`, `delete`, and `detail`; these endpoint paths are now routed.
- Java `SalesProjectFieldChangeLogServiceImpl.page` defaults sorting to `ID` ascending.
- The ThinkPHP service filters logical deletes and applies tenant filtering when the token payload or request contains a tenant id.
- Rows include the original change fields plus project and creator display aliases for copied sale-project detail/history views.
- `sales_project_field_change_log.OBJECT_ID` and `biz_sale_project.ID` use different collations in the imported SQL, so the read join uses an explicit collation without changing the database schema.
- Add requires `objectId`, `fieldName`, `fieldLabel`, `beforeValue`, `afterValue`, and `changeReason`.
- Edit requires the same fields plus `id`.
- Delete accepts Java-style array bodies, `idList`, `ids`, or a single `id`.
- ThinkPHP uses `DELETE_FLAG = DELETED` for delete safety instead of physically removing imported rows.
- `/biz/saleproject/amount/edit` now generates one `INIT_PRICE` change log row as part of its focused Java-compatible amount maintenance.

## Deferred

The following remain intentionally deferred:

- sale-project change-log generation writes
- sale-project non-amount change writes
- workflow, finance, and audit side effects
- Java source changes
- database schema changes
