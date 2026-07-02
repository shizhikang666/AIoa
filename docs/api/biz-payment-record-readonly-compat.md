# Biz Payment Record Compatibility

## Scope

This slice covers protected payment-record read routes, narrow Java-compatible payer-time correction and settlement-account switch routes, and the 2026-06-26 product-approved direct manual add/delete behavior.

## Routes

- `GET /biz/bizpaymentrecord/page`
- `GET /biz/bizpaymentrecord/listdetails`
- `GET /biz/bizpaymentrecord/list`
- `GET /biz/bizpaymentrecord/detail`
- `POST /biz/bizpaymentrecord/add`
- `POST /biz/bizpaymentrecord/edit`
- `POST /biz/bizpaymentrecord/edit/account`
- `POST /biz/bizpaymentrecord/delete`

## Java References

- `vip.xiaonuo.biz.modular.bizpaymentrecord.controller.BizPaymentRecordController`
- `vip.xiaonuo.biz.modular.bizpaymentrecord.service.impl.BizPaymentRecordServiceImpl`
- `vip.xiaonuo.biz.modular.bizpaymentrecord.entity.BizPaymentRecord`
- `vip.xiaonuo.biz.modular.bizpaymentrecord.param.BizPaymentRecordPageParam`
- `vip.xiaonuo.biz.modular.bizpaymentrecord.param.BizPaymentRecordQueryParam`
- `vip.xiaonuo.biz.modular.bizpaymentrecord.param.BizPaymentRecordEditParam`
- `vip.xiaonuo.biz.modular.bizpaymentrecord.param.BizPaymentRecordEditAccountParam`

## Tables

- `biz_payment_record`
- `settlement_account_statement`
- `settlement_account`
- `sys_org`

## Supported Filters

- `current`, `size`, `page`, `limit`, `pageNo`, `pageSize`
- `sortField`, `sortOrder`
- `id`
- `objectId`
- `objectIds`
- `targetId`
- `serialId`
- `processId`
- `settlementCategory`
- `startPayerTime`, `endPayerTime`
- `payerStartTime`, `payerEndTime`
- `startCreateTime`, `endCreateTime`
- `amount`
- `accountName`
- `orgId`
- `searchKey`
- `tenantId`

## Response Notes

- `page` returns `records`, `total`, `current`, `size`, `pages`.
- `listdetails` returns plain read rows using page-style filters.
- `list` returns plain read rows using query-style filters.
- `detail` is added for old frontend compatibility; the analyzed Java controller does not expose a detail route, but the Java service has a `detail` method and the Vue wrapper calls it.
- Rows include settlement-account display fields `accountName` and `accountNumber`, plus `orgName` from `sys_org`.

## Payer-Time Correction

`POST /biz/bizpaymentrecord/edit` accepts Java-style `id` and `payerTime`.

- Updates only `biz_payment_record.PAYER_TIME`, `UPDATE_TIME`, and `UPDATE_USER`.
- Syncs the linked `settlement_account_statement.PAYER_TIME`, `UPDATE_TIME`, and `UPDATE_USER` by the payment record `SERIAL_ID`.
- Runs in a transaction and rejects missing payment records, missing `payerTime`, invalid time text, missing statements, tenant mismatch, or write-scope mismatch.
- Ignores client-submitted amount, account, object, process, category, user, organization, audit, and delete fields.
- Does not update settlement account balances or create income/payment records.

## Account Switch

`POST /biz/bizpaymentrecord/edit/account` accepts Java-style `id`, `currentTargetId`, and `targetId`.

- Rejects missing ids, same current/target account, mismatched payment-record current account, missing linked statement, missing accounts, tenant mismatch, or write-scope mismatch.
- Runs in a transaction.
- Uses the stored `biz_payment_record.AMOUNT`; client-submitted amount/object/serial/org/process/category fields are ignored.
- Subtracts the stored amount from the current settlement account `CURRENT_AMOUNT`.
- Adds the stored amount to the target settlement account `CURRENT_AMOUNT`.
- Updates `biz_payment_record.TARGET_ID`, `ORG`, `UPDATE_TIME`, and `UPDATE_USER`.
- Updates the linked `settlement_account_statement.ACCOUNT_ID`, `UPDATE_TIME`, and `UPDATE_USER`.
- Does not create additional statements or payment rows.

## Direct Add/Delete

`POST /biz/bizpaymentrecord/add` delegates to settlement-account quick income creation, creating one settlement statement, one payment record, and increasing the target account balance.

`POST /biz/bizpaymentrecord/delete` is bounded product behavior rather than Java-public route parity. It only deletes guarded manual `Process_sys` payment rows, rejects transfer rows, validates the linked income statement account/type/process/amount, logically deletes the payment record and statement, and subtracts the stored amount from the settlement account balance.

## Explicit Exclusions

- No workflow-owned, transfer-owned, refund-owned, or other linked payment rows can be directly deleted.
- No workflow, data-change event, database schema change, Java source change, `.env`, Composer file, npm file, frontend source, production data, Git push, or public config change was added.

## Verification

- `composer dump-autoload`
- `php think`
- `php think route:list`
- PHP syntax lint
- Token smoke tests for page, listdetails, list, detail, and no-token 401.
- `scripts/biz-payment-record-edit-http-smoke.ps1`
- `scripts/biz-payment-record-edit-account-http-smoke.ps1`
- PHP syntax lint for `PaymentRecordController` and `PaymentRecordService`
- `php think route:list` payment-record route check

## 2026-06-15 HTTP Smoke Coverage

`scripts/finance-read-http-smoke.ps1` now verifies authenticated payment-record read payloads against the local backend:

- `GET /biz/bizpaymentrecord/page`
- `GET /biz/bizpaymentrecord/listdetails`
- `GET /biz/bizpaymentrecord/list`
- `GET /biz/bizpaymentrecord/detail` when a visible page row exists

The smoke checks Java-style paging keys and stable frontend-visible finance fields such as `objectId`, `targetId`, `accountName`, `accountNumber`, `serialId`, `processId`, `settlementCategory`, `payerTime`, `amount`, and `orgName`. It does not call payment edit/account routes, settlement-account transfers, statements, provider actions, workflow, or any finance mutation.

## 2026-06-16 Payer-Time Edit Smoke Coverage

`scripts/biz-payment-record-edit-http-smoke.ps1` inserts temporary payment-record and account-statement rows, then verifies:

- no-token edit returns `code=401`;
- missing `payerTime` returns `code=400`;
- valid edit returns `code=200`;
- detail readback exposes the new `payerTime`;
- the linked statement `PAYER_TIME` is updated in the same transaction;
- client-spoofed amount/account/object/process/category/user/org fields are ignored;
- a missing linked statement returns `code=404` and leaves the payment row unchanged;
- payment, statement, account, expenditure, receipt, and debit-note row counts stay unchanged after setup.

## 2026-06-17 Account-Switch Smoke Coverage

`scripts/biz-payment-record-edit-account-http-smoke.ps1` inserts temporary current/target settlement accounts, a payment record, and a linked statement, then verifies:

- no-token account switch returns `code=401`;
- missing `targetId`, same-account switch, and mismatched `currentTargetId` return `code=400`;
- missing linked statement returns `code=404` and leaves account balances and record links unchanged;
- valid switch returns `code=200`;
- detail readback exposes the new `targetId` and target-account `org`;
- current account balance is decreased and target account balance is increased by the stored payment amount only;
- the linked statement `ACCOUNT_ID` is switched to the target account;
- client-spoofed amount/object/serial/org fields are ignored;
- payment, statement, account, expenditure, receipt, and debit-note row counts stay unchanged after setup.
