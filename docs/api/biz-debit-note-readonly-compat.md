# Biz Debit Note Read-Only Compatibility

## Scope

This slice adds read-only ThinkPHP compatibility endpoints for the old Java debit-note APIs used by the Vue OA frontend.

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

## Explicitly Deferred Routes

These Java/frontend routes are not implemented in this slice because they mutate debit-note settlement state, payment records, settlement accounts, or history data:

- `POST /biz/bizdebitnote/history/add`
- `POST /biz/bizdebitnote/mark/success/edit`
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
- Java history-add, mark-success, and batch-repayment flows change balances and settlement status. They need a later transactional write design before implementation.
- This slice does not modify Java source, database schema, Composer files, `.env`, or any write endpoint.
