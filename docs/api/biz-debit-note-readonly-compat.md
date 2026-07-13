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
| POST | `/biz/bizdebitnote/add` | Creates one standalone debit-note row linked to an active expenditure record. |
| POST | `/biz/bizdebitnote/edit` | Updates debit-note amount, settlement amount, and remark with settled/history guards. |
| POST | `/biz/bizdebitnote/mark/success/edit` | Marks one debit note as settled. |
| POST | `/biz/bizdebitnote/batchRepayment/edit` | Creates loan-repayment payment rows for selected debit notes. |
| POST | `/biz/bizdebitnote/history/add` | Creates one historical debit note from a selected settlement account. |
| POST | `/biz/bizdebitnote/delete` | Logically deletes debit notes that have no settlement or history amount. |

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

`POST /biz/bizdebitnote/batchRepayment/edit` accepts the copied frontend batch repayment body:

- `accountId`
- `payer`
- `payerTime`
- optional `remark`
- `items[]` with `id` and positive `amount`

The ThinkPHP implementation follows Java `BizDebitNoteServiceImpl.batchRepayment` plus the loan-repayment event effect that Java normally triggers through `BizPaymentRecordAddEventHandler`:

- validates duplicate, missing, invalid, and over-settlement items before writing;
- locks the selected active debit notes for the current tenant;
- creates one `biz_payment_record` and one `settlement_account_statement` per item through the settlement-account quick-income path;
- uses fixed `SETTLEMENT_CATEGORY = LoanRepayment` and `OBJECT_ID = debit note id`;
- increments the target settlement account balance;
- updates `biz_debit_note.SETTLEMENT_AMOUNT`;
- sets `PLAY_STATUS = AlreadySettled` when the debit note is fully settled, otherwise `Unsettled`;
- increments `VERSION`.

`POST /biz/bizdebitnote/history/add` accepts the copied frontend historical debit-note body:

- `accountId`
- `amount`
- `historyAmount`
- `createTime`
- `remark`

The ThinkPHP implementation follows Java `BizDebitNoteServiceImpl.add(BizDebitNoteHistoryAddParam)` as a narrow debit-note insert:

- validates required fields, positive `amount`, nonnegative `historyAmount`, valid `createTime`, and `historyAmount <= amount`;
- reads the selected settlement account for tenant and organization context;
- inserts one `biz_debit_note` row with `EXPENDITURE_RECORD_ID = null`;
- sets `HISTORY_AMOUNT` and `SETTLEMENT_AMOUNT` to the submitted `historyAmount`;
- sets `PLAY_STATUS = AlreadySettled` when `historyAmount == amount`, otherwise `Unsettled`;
- does not create payment records, expenditure records, or settlement-account statements;
- does not change settlement-account balances.

`POST /biz/bizdebitnote/add` accepts:

- `expenditureRecordId`
- `amount`
- optional `settlementAmount`
- optional `remark`

`POST /biz/bizdebitnote/edit` accepts:

- `id`
- optional `amount`
- optional `settlementAmount`
- optional `remark`

`POST /biz/bizdebitnote/delete` accepts Java-style `[{ id }]`, `idList`, `ids`, or `id` payloads.

Direct debit-note CRUD is intentionally bounded product behavior opened on 2026-06-26:

- active expenditure records are locked and checked against tenant/data-scope/create-user permissions;
- an expenditure record can be bound to only one active debit note;
- debit-note amount cannot exceed the linked expenditure-record amount;
- `settlementAmount` must be nonnegative and cannot exceed `amount`;
- `add` derives organization from the linked expenditure record's target settlement account, matching Java service behavior;
- settled or historical rows can only edit `remark`;
- delete is logical (`DELETE_FLAG = DELETED`) and rejects rows with positive `SETTLEMENT_AMOUNT`, positive `HISTORY_AMOUNT`, or `PLAY_STATUS = AlreadySettled`;
- direct CRUD does not create payment records, expenditure records, settlement-account statements, or settlement-account balance changes.

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
- Java batch-repayment can change payment records, settlement-account data, and debit-note settlement amounts. ThinkPHP implements batch repayment as a narrow transaction and explicitly applies the debit-note settlement correction that Java would normally trigger through its event handler.
- Java history-add creates a debit-note row with historical settlement amount. ThinkPHP implements it as a debit-note-only insert and intentionally does not create payment/expenditure/statement rows or account-balance changes.
- Java controller CRUD routes are commented out. Direct add/edit/delete is now explicit ThinkPHP product behavior, not Java-public route parity.
- This compatibility work does not modify Java source, database schema, Composer files, or `.env`.

## 2026-06-15 HTTP Smoke Coverage

`scripts/finance-read-http-smoke.ps1` now verifies authenticated debit-note read payloads against the local backend:

- `GET /biz/bizdebitnote/page`
- `GET /biz/bizdebitnote/list`
- `GET /biz/bizdebitnote/detail` when a visible page row exists

The smoke checks Java-style paging keys and stable frontend-visible fields such as `expenditureRecordId`, `playStatus`, `amount`, `settlementAmount`, `historyAmount`, `version`, `accountName`, `accountNumber`, `settlementCategory`, `category`, and `orgName`. It does not call mark-success, history-add, batch-repayment, add, edit, delete, settlement-account, statement, payment, expenditure, workflow, provider, or finance mutation behavior.

## 2026-06-17 Batch Repayment Smoke Coverage

`scripts/biz-debit-note-batch-repayment-http-smoke.ps1` verifies authenticated quick-repayment behavior against the local backend:

- no-token rejection;
- missing `accountId`, empty items, zero amount, over amount, missing note, and missing account rollback;
- generated `biz_payment_record` detail readback;
- generated `settlement_account_statement` values;
- target settlement-account balance increase;
- `biz_debit_note.SETTLEMENT_AMOUNT`, `PLAY_STATUS`, and `VERSION` update;
- unrelated account/expenditure/collection/debit counts stay stable except for the expected payment and statement rows.

## 2026-06-17 History Add Smoke Coverage

`scripts/biz-debit-note-history-add-http-smoke.ps1` verifies authenticated historical debit-note creation against the local backend:

- no-token rejection;
- missing `accountId`, zero amount, negative `historyAmount`, invalid `createTime`, over amount, and missing account rollback;
- debit-note detail readback;
- `biz_debit_note.EXPENDITURE_RECORD_ID`, `AMOUNT`, `HISTORY_AMOUNT`, `SETTLEMENT_AMOUNT`, `PLAY_STATUS`, `ORG`, `TENANT_ID`, `DELETE_FLAG`, `VERSION`, and `CREATE_TIME` values;
- settlement-account balance/version preservation;
- payment, statement, account, expenditure, collection, and debit counts stay stable except for the expected single debit-note row.

## 2026-06-26 Direct CRUD Coverage

Focused verification currently covers PHP syntax and route registration for direct debit-note CRUD:

- `POST /biz/bizdebitnote/add`
- `POST /biz/bizdebitnote/edit`
- `POST /biz/bizdebitnote/delete`

DB-backed HTTP smoke is pending local runtime availability.
