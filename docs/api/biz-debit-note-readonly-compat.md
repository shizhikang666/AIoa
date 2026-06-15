# Biz Debit Note Compatibility

## Scope

This document tracks ThinkPHP compatibility endpoints for the old Java debit-note APIs used by the Vue OA frontend.

Java source analyzed:

- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizdebitnote/controller/BizDebitNoteController.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizdebitnote/service/impl/BizDebitNoteServiceImpl.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizdebitnote/param/BizDebitNotePageParam.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizdebitnote/entity/BizDebitNote.java`
- `F:\AI\projects\testJava\OA\oa2026.sql`

Frontend source analyzed:

- `snowy-admin-web/src/api/biz/bizDebitNoteApi.js`

## Added Routes

All routes are protected by `AuthMiddleware`.

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/biz/bizdebitnote/page` | Paginated debit-note list. |
| GET | `/biz/bizdebitnote/list` | Non-paginated debit-note list. |
| GET | `/biz/bizdebitnote/detail` | Read-only detail lookup. |
| POST | `/biz/bizdebitnote/mark/success/edit` | Marks one debit note as settled. |

## Write Compatibility

`POST /biz/bizdebitnote/mark/success/edit` accepts a Java-compatible body containing `id`.

The ThinkPHP implementation intentionally matches Java `BizDebitNoteServiceImpl.markSettlement(String id)` as a single-table update:

- validates that `id` is present;
- checks the target debit note in the current tenant;
- allows admin-compatible users, matching scoped organization access, or the original creator;
- sets `biz_debit_note.PLAY_STATUS` to `AlreadySettled`;
- refreshes `UPDATE_TIME` and `UPDATE_USER`;
- increments `VERSION` to match the Java entity's optimistic-lock field behavior.

It does not update `AMOUNT`, `SETTLEMENT_AMOUNT`, `HISTORY_AMOUNT`, `EXPENDITURE_RECORD_ID`, settlement account balances, settlement account statements, payment records, or expenditure records.

## Explicitly Deferred Routes

These Java/frontend routes are not implemented in this slice because they mutate payment records, settlement accounts, or history data beyond the single Java mark-settlement update:

- `POST /biz/bizdebitnote/history/add`
- `POST /biz/bizdebitnote/batchRepayment/edit`
- `POST /biz/bizdebitnote/add`
- `POST /biz/bizdebitnote/edit`
- `POST /biz/bizdebitnote/delete`

## Query Compatibility

Supported query parameters:

- `current`, `size`, `page`, `limit`, `pageNo`, `pageSize`
- `sortField`, `sortOrder`
- `id`
- `expenditureRecordId`
- `playStatus`
- `startCreateTime`, `endCreateTime`
- `remark`
- `accountName`
- `category`, `settlementCategory`
- `org`, `orgId`
- `amount`
- `searchKey`
- `tenantId`

The service reads `biz_debit_note` and enriches rows through:

- `biz_expenditure_record` by `EXPENDITURE_RECORD_ID`
- `settlement_account` by `biz_expenditure_record.TARGET_ID`
- `sys_org` by `biz_debit_note.ORG`

## Response Shape

Rows return frontend-friendly camelCase fields:

- `id`
- `expenditureRecordId`
- `remark`
- `playStatus`
- `amount`
- `settlementAmount`
- `historyAmount`
- `deleteFlag`
- `createTime`
- `createUser`
- `updateTime`
- `updateUser`
- `tenantId`
- `version`
- `org`
- `orgName`
- `payerTime`
- `accountId`
- `accountName`
- `accountNumber`
- `settlementCategory`
- `category`
- `payer`
- `bankName`
- `bankAccount`

## Notes

- Java page/list conditionally joins expenditure records for account/category filters. This ThinkPHP read query always uses left joins for display enrichment.
- Java history-add and batch-repayment flows can change payment records, settlement-account data, and debit-note settlement amounts. They still need a later transactional write design before implementation.
- This slice does not modify Java source, database schema, Composer files, `.env`, or any account/statement/payment side-effect endpoint.

## 2026-06-15 HTTP Smoke Coverage

`scripts/finance-read-http-smoke.ps1` now verifies authenticated debit-note read payloads against the local backend:

- `GET /biz/bizdebitnote/page`
- `GET /biz/bizdebitnote/list`
- `GET /biz/bizdebitnote/detail` when a visible page row exists

The smoke checks Java-style paging keys and stable frontend-visible fields such as `expenditureRecordId`, `playStatus`, `amount`, `settlementAmount`, `historyAmount`, `version`, `accountName`, `accountNumber`, `settlementCategory`, `category`, and `orgName`. It does not call mark-success, history-add, batch-repayment, add, edit, delete, settlement-account, statement, payment, expenditure, workflow, provider, or finance mutation behavior.
