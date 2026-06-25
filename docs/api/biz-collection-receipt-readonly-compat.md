# Biz Collection Receipt Compatibility

## Scope

This document tracks ThinkPHP compatibility endpoints for the old Java collection-receipt APIs used by the Vue OA frontend.

Java source analyzed:

- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizcollectionreceipt/controller/BizCollectionReceiptController.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizcollectionreceipt/service/impl/BizCollectionReceiptServiceImpl.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizcollectionreceipt/param/BizCollectionReceiptPageParam.java`
- `snowy-plugin/snowy-plugin-biz/src/main/java/vip/xiaonuo/biz/modular/bizcollectionreceipt/entity/BizCollectionReceipt.java`
- `F:\AI\projects\testJava\OA\oa2026.sql`

Frontend source analyzed:

- `snowy-admin-web/src/api/biz/bizCollectionReceiptApi.js`

## Added Routes

All routes are protected by `AuthMiddleware`.

| Method | Path | Notes |
| --- | --- | --- |
| GET | `/biz/bizcollectionreceipt/page` | Paginated collection-receipt list. |
| GET | `/biz/bizcollectionreceipt/list` | Non-paginated collection-receipt list. |
| GET | `/biz/bizcollectionreceipt/detail` | Read-only detail lookup for old frontend compatibility. |
| POST | `/biz/bizcollectionreceipt/mark/success/edit` | Marks one collection receipt as settled. |
| POST | `/biz/bizcollectionreceipt/batchExpenditure/edit` | Creates repayment expenditure rows for selected collection receipts. |

## Write Compatibility

`POST /biz/bizcollectionreceipt/mark/success/edit` accepts a Java-compatible body containing `id`.

The ThinkPHP implementation intentionally matches Java `BizCollectionReceiptServiceImpl.markSettlement(String id)` as a single-table update:

- validates that `id` is present;
- checks the target receipt in the current tenant;
- allows admin-compatible users, matching scoped organization access through the linked payment record, or the original creator;
- sets `biz_collection_receipt.PLAY_STATUS` to `AlreadySettled`;
- refreshes `UPDATE_TIME` and `UPDATE_USER`;
- increments `VERSION` to match the Java entity's optimistic-lock field behavior.

It does not update `AMOUNT`, `SETTLEMENT_AMOUNT`, `PAYMENT_RECORD_ID`, settlement account balances, settlement account statements, payment records, or expenditure records.

`POST /biz/bizcollectionreceipt/batchExpenditure/edit` accepts the copied frontend batch quick-settlement body:

- `accountId`
- `payer`
- `payerTime`
- optional `remark`
- `items[]` with `id` and positive `amount`

The ThinkPHP implementation follows Java `BizCollectionReceiptServiceImpl.batchExpenditure` plus the repayment-side event effect that Java normally triggers through `ExpenditureRecordAddEventHandler`:

- validates duplicate, missing, invalid, and over-settlement items before writing;
- locks the selected active collection receipts for the current tenant;
- creates one `biz_expenditure_record` and one `settlement_account_statement` per item through the settlement-account quick-expense path;
- uses fixed `SETTLEMENT_CATEGORY = repayment` and `OBJECT_ID = collection receipt id`;
- decrements the target settlement account balance;
- updates `biz_collection_receipt.SETTLEMENT_AMOUNT`;
- sets `PLAY_STATUS = AlreadySettled` when the receipt is fully settled, otherwise `Unsettled`;
- increments `VERSION`.

## Explicitly Deferred Routes

These Java/frontend routes remain deferred:

- `POST /biz/bizcollectionreceipt/add`
- `POST /biz/bizcollectionreceipt/edit`
- `POST /biz/bizcollectionreceipt/delete`

## Query Compatibility

Supported query parameters:

- `current`, `size`, `page`, `limit`, `pageNo`, `pageSize`
- `sortField`, `sortOrder`
- `id`
- `paymentRecordId`
- `playStatus`
- `remark`
- `accountName`
- `searchKey`
- `tenantId`

The service reads `biz_collection_receipt` and enriches rows through:

- `biz_payment_record` by `PAYMENT_RECORD_ID`
- `settlement_account` by `biz_payment_record.TARGET_ID`
- `sys_org` by `biz_payment_record.ORG`

## Response Shape

Rows return frontend-friendly camelCase fields:

- `id`
- `paymentRecordId`
- `remark`
- `playStatus`
- `amount`
- `settlementAmount`
- `deleteFlag`
- `createTime`
- `createUser`
- `updateTime`
- `updateUser`
- `tenantId`
- `version`
- `payerTime`
- `accountId`
- `accountName`
- `accountNumber`
- `settlementCategory`
- `payer`
- `bankName`
- `bankAccount`
- `org`
- `orgName`

## Notes

- The Java controller comments out add, edit, delete, and detail mappings. The old frontend still has a `detail` wrapper, so ThinkPHP exposes only a protected read-only detail endpoint.
- Java's batch-expenditure flow creates expenditure data and account-side settlement records. ThinkPHP implements this as a narrow transaction and explicitly applies the collection-receipt settlement correction that Java would normally trigger through its event handler.
- This compatibility work does not modify Java source, database schema, Composer files, or `.env`; the only collection-receipt finance side-effect route currently implemented here is the narrow batch-expenditure flow described above.

## 2026-06-15 HTTP Smoke Coverage

`scripts/finance-read-http-smoke.ps1` now verifies authenticated collection-receipt read payloads against the local backend:

- `GET /biz/bizcollectionreceipt/page`
- `GET /biz/bizcollectionreceipt/list`
- `GET /biz/bizcollectionreceipt/detail` when a visible page row exists

The smoke checks Java-style paging keys and stable frontend-visible fields such as `paymentRecordId`, `playStatus`, `amount`, `settlementAmount`, `version`, `accountName`, `accountNumber`, `settlementCategory`, and `orgName`. It does not call mark-success, batch-expenditure, add, edit, delete, settlement-account, statement, payment, expenditure, workflow, provider, or finance mutation behavior.

## 2026-06-17 Batch Expenditure Smoke Coverage

`scripts/biz-collection-receipt-batch-expenditure-http-smoke.ps1` verifies authenticated quick-settlement behavior against the local backend:

- no-token rejection;
- missing `accountId`, empty items, zero amount, over amount, missing receipt, and missing account rollback;
- generated `biz_expenditure_record` detail readback;
- generated `settlement_account_statement` values;
- target settlement-account balance decrease;
- `biz_collection_receipt.SETTLEMENT_AMOUNT`, `PLAY_STATUS`, and `VERSION` update;
- unrelated payment/account/collection/debit counts stay stable except for the expected expenditure and statement rows.
