# Biz Draft Read-Only Compatibility

Date: 2026-06-04

Agent: api-agent

## Scope

This slice adds read-only ThinkPHP compatibility for the copied sale-project draft detail endpoint.

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Added Route

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/biz/bizdraft/detail` | Read one sale-project draft by target sale-project id. |

The route is protected by `AuthMiddleware`.

## Java Compatibility Notes

- Java `BizDraftServiceImpl.detail` queries `biz_draft` by `TARGET_ID`, not by draft row `ID`.
- This ThinkPHP read service preserves that behavior and applies tenant filtering when the token payload contains a tenant id.
- The response keeps the raw `extJson` string so the copied frontend can parse the saved form and file list.

## Deferred

The following remain intentionally deferred:

- `/biz/bizdraft/saleproject/add`
- draft save/update behavior
- sale-project add/edit workflow side effects
- file upload/storage writes
- Java source changes
- database schema changes
