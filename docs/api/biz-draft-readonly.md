# Biz Draft Compatibility

Date: 2026-06-12

Agent: api-agent / merge-agent

## Scope

This document maps ThinkPHP compatibility for the copied sale-project draft endpoints.

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Added Route

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/biz/bizdraft/detail` | Read one sale-project draft by target sale-project id. |
| POST | `/biz/bizdraft/saleproject/add` | Save or overwrite one sale-project draft by target sale-project id. |

The route is protected by `AuthMiddleware`.

## Java Compatibility Notes

- Java `BizDraftServiceImpl.detail` queries `biz_draft` by `TARGET_ID`, not by draft row `ID`.
- This ThinkPHP read service preserves that behavior and applies tenant filtering when the token payload contains a tenant id.
- The response keeps the raw `extJson` string so the copied frontend can parse the saved form and file list.
- Java `BizDraftServiceImpl.addOrEditSaleProjectDraft` queries by `TARGET_ID`, creates a draft with `CATEGORY = SALE_PROJECT_INIT` when none exists, and otherwise updates only `EXT_JSON`.
- The ThinkPHP save route keeps the same behavior within the current tenant and does not modify sale-project, workflow, or file-storage tables.

## Deferred

The following remain intentionally deferred:

- sale-project add/edit workflow side effects
- file upload/storage writes
- Java source changes
- database schema changes

## Test Notes

Focused DB smoke on 2026-06-12 verified:

- save creates a `biz_draft` row with `CATEGORY = SALE_PROJECT_INIT` and `DELETE_FLAG = NOT_DELETE`
- saving the same `targetId` updates the existing active row instead of creating a duplicate
- `detail` returns the updated raw `extJson`
- missing `targetId` fails with `400`
- `biz_sale_project` row count stays unchanged
- temporary smoke drafts are physically cleaned up
