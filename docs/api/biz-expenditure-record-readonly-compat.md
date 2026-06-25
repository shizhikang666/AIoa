# Biz Expenditure Record Compatibility

## Scope

This document tracks protected ThinkPHP routes compatible with the Java expenditure-record controller and the existing Vue API module.

## Routes

- `GET /biz/bizexpenditurerecord/page`
- `GET /biz/bizexpenditurerecord/listDetails`
- `GET /biz/bizexpenditurerecord/list`
- `GET /biz/bizexpenditurerecord/detail`
- `POST /biz/bizexpenditurerecord/edit`
- `POST /biz/bizexpenditurerecord/edit/account`

## Java References

- `vip.xiaonuo.biz.modular.bizexpenditurerecord.controller.BizExpenditureRecordController`
- `vip.xiaonuo.biz.modular.bizexpenditurerecord.service.impl.BizExpenditureRecordServiceImpl`
- `vip.xiaonuo.biz.modular.bizexpenditurerecord.entity.BizExpenditureRecord`
- `vip.xiaonuo.biz.modular.bizexpenditurerecord.param.BizExpenditureRecordPageParam`
- `vip.xiaonuo.biz.modular.bizexpenditurerecord.param.BizExpenditureRecordQueryParam`
- `vip.xiaonuo.biz.modular.bizexpenditurerecord.param.BizExpenditureRecordEditParam`
- `vip.xiaonuo.biz.modular.bizexpenditurerecord.param.BizExpenditureRecordEditAccountParam`
- `vip.xiaonuo.biz.modular.settlementaccountstatement.service.impl.SettlementAccountStatementServiceImpl`

## Tables

- `biz_expenditure_record`
- `settlement_account`
- `settlement_account_statement`
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
- `payer`
- `bankName`
- `bankAccount`
- `remark`
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
- `listDetails` returns plain rows using page-style filters.
- `list` returns plain rows using query-style filters.
- `detail` returns a single read row.
- Rows include settlement-account display fields `accountName` and `accountNumber`, plus `orgName` from `sys_org`.
- `edit` returns the edited expenditure record id and linked statement id/counts after a narrow correction.

## Edit Behavior

`POST /biz/bizexpenditurerecord/edit` accepts:

- required `id`
- optional `payerTime`
- optional `settlementCategory`

The endpoint is intentionally narrow:

- validates tenant/write visibility before updating;
- rejects object-linked expenditure records;
- rejects category changes when the current category is `ReturnAndRefund`, `GOODS_EXPENDITURE`, or `repayment`;
- rejects target categories `CUSTOMER_REBATE`, `ReturnAndRefund`, `repayment`, and `TravelExpenses`;
- updates only `biz_expenditure_record.PAYER_TIME`, `SETTLEMENT_CATEGORY`, `UPDATE_TIME`, and `UPDATE_USER`;
- syncs only `settlement_account_statement.PAYER_TIME`, `UPDATE_TIME`, and `UPDATE_USER` when `payerTime` is supplied;
- rolls back if the linked statement is missing.

The linked statement category, account id, amount, object/process ids, target id, tenant, delete flag, and settlement/account balances are not changed by this route.

## Account Switch

`POST /biz/bizexpenditurerecord/edit/account` accepts Java-style `id`, `currentTargetId`, and `targetId`.

- Rejects missing ids, same current/target account, mismatched expenditure-record current account, missing linked statement, missing accounts, tenant mismatch, or write-scope mismatch.
- Runs in a transaction.
- Uses the stored `biz_expenditure_record.AMOUNT`; client-submitted amount/object/serial/org/process/category fields are ignored.
- Adds the stored amount back to the current settlement account `CURRENT_AMOUNT`.
- Subtracts the stored amount from the target settlement account `CURRENT_AMOUNT`.
- Updates `biz_expenditure_record.TARGET_ID`, `UPDATE_TIME`, and `UPDATE_USER`.
- Leaves `biz_expenditure_record.ORG` unchanged, matching the Java account-switch method's `TARGET_ID`-only record update.
- Updates the linked `settlement_account_statement.ACCOUNT_ID`, `UPDATE_TIME`, and `UPDATE_USER`.
- Does not create additional statements or expenditure rows.

## Explicit Exclusions

- No `/biz/bizexpenditurerecord/add` route was added.
- No `/biz/bizexpenditurerecord/delete` route was added.
- No expenditure-record add/delete, new statement creation, statement category mutation, workflow/data-change event, database schema change, Java source change, `.env`, Composer file, npm file, frontend source, production data, Git push, or public config change was added.

## Verification

- `composer dump-autoload`
- `php think`
- `php think route:list`
- PHP syntax lint
- Token smoke tests for page, listDetails, list, detail, and no-token 401.
- `scripts/biz-expenditure-record-edit-http-smoke.ps1`
- `scripts/biz-expenditure-record-edit-account-http-smoke.ps1`

## 2026-06-15 HTTP Smoke Coverage

`scripts/finance-read-http-smoke.ps1` now verifies authenticated expenditure-record read payloads against the local backend:

- `GET /biz/bizexpenditurerecord/page`
- `GET /biz/bizexpenditurerecord/listDetails`
- `GET /biz/bizexpenditurerecord/list`
- `GET /biz/bizexpenditurerecord/detail` when a visible page row exists

The smoke checks Java-style paging keys and stable frontend-visible finance fields such as `objectId`, `targetId`, `accountName`, `accountNumber`, `serialId`, `processId`, `settlementCategory`, `payerTime`, `amount`, and `orgName`. It does not call add/edit/delete, account edit routes, settlement-account transfers, statements, provider actions, workflow, or any finance mutation.

## 2026-06-16 Edit Smoke Coverage

`scripts/biz-expenditure-record-edit-http-smoke.ps1` verifies the narrow edit route against temporary local rows:

- no-token edit returns `code=401`;
- missing `id` returns `code=400`;
- valid edit changes `payerTime` and `settlementCategory`;
- detail readback exposes the changed values;
- linked statement `payerTime` is synced;
- protected target categories are rejected;
- object-linked records are rejected;
- missing linked statements return `code=404` and roll back the expenditure row;
- client-spoofed object, target, serial, process, amount, tenant, delete, user, org, account, and statement fields are preserved.

## 2026-06-17 Account-Switch Smoke Coverage

`scripts/biz-expenditure-record-edit-account-http-smoke.ps1` inserts temporary current/target settlement accounts, an expenditure record, and a linked statement, then verifies:

- no-token account switch returns `code=401`;
- missing `targetId`, same-account switch, and mismatched `currentTargetId` return `code=400`;
- missing linked statement returns `code=404` and leaves account balances and record links unchanged;
- valid switch returns `code=200`;
- detail readback exposes the new `targetId` and preserves the expenditure record `org`;
- current account balance is increased and target account balance is decreased by the stored expenditure amount only;
- the linked statement `ACCOUNT_ID` is switched to the target account;
- client-spoofed amount/object/serial/org fields are ignored;
- payment, statement, account, expenditure, receipt, and debit-note row counts stay unchanged after setup.
