# Biz Settlement Account Compatibility

## Scope

This note tracks protected ThinkPHP routes compatible with the Java settlement-account controller and the existing Vue API module.

## Routes

- `GET /biz/settlementaccount/page`
- `GET /biz/settlementaccount/list`
- `POST /biz/settlementaccount/add`
- `POST /biz/settlementaccount/edit`
- `POST /biz/settlementaccount/edit/status`
- `POST /biz/settlementaccount/delete`
- `POST /biz/settlementaccount/expenses/add`
- `POST /biz/settlementaccount/payment/add`
- `POST /biz/settlementaccount/transfer/add`
- `GET /biz/settlementaccount/detail`
- `GET /biz/settlementaccount/queryName`

## Java References

- `vip.xiaonuo.biz.modular.settlementaccount.controller.SettlementAccountController`
- `vip.xiaonuo.biz.modular.settlementaccount.service.impl.SettlementAccountServiceImpl`
- `vip.xiaonuo.biz.modular.settlementaccount.entity.SettlementAccount`
- `vip.xiaonuo.biz.modular.settlementaccount.param.SettlementAccountPageParam`
- `vip.xiaonuo.biz.modular.settlementaccount.param.SettlementAccountQueryParam`
- `vip.xiaonuo.biz.modular.settlementaccount.param.SettlementAccountCorrectParam`
- `vip.xiaonuo.biz.modular.settlementaccount.param.SettlementAccountTransferParam`
- `vip.xiaonuo.biz.modular.bizexpenditurerecord.param.BizExpenditureRecordAddParam`
- `vip.xiaonuo.biz.modular.bizpaymentrecord.param.BizPaymentRecordAddParam`

## Tables

- `settlement_account`
- `settlement_account_statement`
- `biz_expenditure_record`
- `biz_payment_record`
- `sys_org`

## Supported Filters

- `current`, `size`, `page`, `limit`, `pageNo`, `pageSize`
- `sortField`, `sortOrder`
- `id`
- `accountName`
- `name`
- `accountNumber`
- `accountStatus`
- `searchKey`
- `orgId`
- `tenantId`

## Response Notes

- `page` returns `records`, `total`, `current`, `size`, `pages`.
- `list` returns only enabled accounts, matching Java `queryEnableList`.
- `detail` returns a single account row.
- `queryName` returns the account name string.
- SQL column `org` is intentionally preserved as the source organization field and enriched with `orgName` from `sys_org`.

## Base Write Compatibility

The active frontend account form and status switch use:

- `POST /biz/settlementaccount/add`
- `POST /biz/settlementaccount/edit`
- `POST /biz/settlementaccount/edit/status`

Behavior:

- `add` requires `accountName` and `accountNumber`, validates `accountStatus` as `ENABLE` or `DISABLED`, writes `INITIAL_AMOUNT`, initializes `CURRENT_AMOUNT` to the same value, and fills `org` from the current token/user context when the frontend does not send it.
- `edit` updates only Java-compatible master-data fields: `ACCOUNT_NAME`, `ACCOUNT_STATUS`, `SORT_CODE`, and lower-case `org`.
- `edit/status` updates only `ACCOUNT_STATUS`.
- `delete` marks selected active accounts as `DELETE_FLAG = DELETED` after full-batch validation.
- Duplicate active account names are rejected within the same tenant.
- Writes use tenant, audit, and data-scope guards consistent with the existing ThinkPHP master-data write slices.
- No account balance statement, income record, expenditure record, transfer, or archive behavior is triggered by these base maintenance routes.

## Delete Compatibility

`POST /biz/settlementaccount/delete` now implements protected logical deletion for unused settlement accounts.

Behavior:

- Accepts Java/copied-frontend delete payloads: array rows with `id`, `idList`, `ids`, or a single `id`.
- Validates every selected id as an active visible/writable settlement account before updating any row.
- Rejects the whole batch when any account is missing, out of scope, or already deleted.
- Rejects active accounts referenced by `settlement_account_statement.ACCOUNT_ID`, `biz_payment_record.TARGET_ID`, `biz_payment_record.OBJECT_ID`, `biz_expenditure_record.TARGET_ID`, or `biz_expenditure_record.OBJECT_ID`.
- Updates only `settlement_account.DELETE_FLAG`, `UPDATE_TIME`, and `UPDATE_USER`.
- Does not mutate balances, statements, payment records, expenditure records, collection receipts, debit notes, workflows, notifications, or Java data-change events.

## Quick Payment/Income Add Compatibility

`POST /biz/settlementaccount/payment/add` now implements the Java quick-income path used by the settlement-account income form.

Behavior:

- Requires `targetId`, `settlementCategory`, `payer`, `payerTime`, and positive `amount`.
- Accepts optional `objectId`, `bankName`, `bankAccount`, and `remark`.
- Joins array-style `settlementCategory` values with `/` for cascader-compatible payloads.
- Locks the target settlement account, records `BEFORE_AMOUNT`, adds the submitted amount, writes `AFTER_AMOUNT`, and updates `settlement_account.CURRENT_AMOUNT` in the same transaction.
- Creates one `settlement_account_statement` row with `SETTLEMENT_TYPE = INCOME`, `PROCESS_ID = Process_sys`, and `PROCESS_CATEGORY = Process_sys`.
- Creates one linked `biz_payment_record` row with `SERIAL_ID` pointing at the statement, `USER` from the current token, and `ORG` from the settlement account.
- Does not emit Java data-change events and does not start workflow behavior.

## Quick Expenses Add Compatibility

`POST /biz/settlementaccount/expenses/add` now implements the Java quick-expense path used by the settlement-account expense form.

Behavior:

- Requires `targetId`, `settlementCategory`, `payer`, `payerTime`, and positive `amount`.
- Accepts optional `objectId`, `bankName`, `bankAccount`, and `remark`.
- Locks the target settlement account, records `BEFORE_AMOUNT`, subtracts the submitted amount, writes `AFTER_AMOUNT`, and updates `settlement_account.CURRENT_AMOUNT` in the same transaction.
- Creates one `settlement_account_statement` row with `SETTLEMENT_TYPE = EXPEND`, `PROCESS_ID = Process_sys`, and `PROCESS_CATEGORY = Process_sys`.
- Creates one linked `biz_expenditure_record` row with `SERIAL_ID` pointing at the statement, `USER` from the current token, and `ORG` from the settlement account.
- Does not emit Java data-change events, does not start workflow behavior, and does not update collection-receipt settlement state.

## Transfer Add Compatibility

`POST /biz/settlementaccount/transfer/add` now implements the Java account-transfer path used by the settlement-account transfer form.

Behavior:

- Requires `expensesAccountId`, `revenueAccountId`, `payerTime`, and positive `amount`.
- Rejects same-account transfers.
- Accepts optional `remark`.
- Locks both settlement accounts in stable id order, subtracts the submitted amount from the expense account, adds it to the income account, and updates both `CURRENT_AMOUNT` values in one transaction.
- Uses fixed settlement category `dealings`, matching Java `SettlementCategoryEnum.dealings`.
- Creates one expense-side `settlement_account_statement` row with `SETTLEMENT_TYPE = EXPEND`, `PROCESS_ID = Process_sys`, and `PROCESS_CATEGORY = Process_sys`.
- Creates one income-side `settlement_account_statement` row with `SETTLEMENT_TYPE = INCOME`, `PROCESS_ID = Process_sys`, and `PROCESS_CATEGORY = Process_sys`.
- Creates one linked `biz_expenditure_record` for the expense account and one linked `biz_payment_record` for the income account. The opposite account is written as `OBJECT_ID`, `PAYER`, and `BANK_ACCOUNT`, matching Java `getSettlementAccountCorrectParam`.
- Does not emit Java data-change events, does not start workflow behavior, and does not update collection-receipt or debit-note settlement state.

## Verification

- `php -l app/service/biz/SettlementAccountService.php`
- `php -l app/controller/biz/SettlementAccountController.php`
- `php -l route/app.php`
- `php think route:list`
- Token smoke tests for page, list, detail, queryName, and no-token 401.
- DB smoke added a temporary account, verified `CURRENT_AMOUNT` equals `INITIAL_AMOUNT` and `org` comes from the current context, edited name/status/org, toggled status, checked duplicate-name, invalid-status, and non-admin rejection, then removed the temporary row.
- `scripts/settlement-account-expenses-add-http-smoke.ps1`
- `scripts/settlement-account-payment-add-http-smoke.ps1`
- `scripts/settlement-account-transfer-add-http-smoke.ps1`
- `scripts/settlement-account-delete-http-smoke.ps1`

## 2026-06-15 HTTP Smoke Coverage

`scripts/settlement-account-read-http-smoke.ps1` now verifies authenticated settlement-account read payloads against the local backend:

- `GET /biz/settlementaccount/page`
- `GET /biz/settlementaccount/list`
- `GET /biz/settlementaccount/detail` when a visible page row exists
- `GET /biz/settlementaccount/queryName` when a visible page row exists

The smoke checks Java-style paging keys and stable frontend-visible fields such as `accountName`, `accountNumber`, `initialAmount`, `currentAmount`, `accountStatus`, `orgName`, `archiveAmount`, `archiveTime`, and decoded `ext`. It does not call add, edit, edit/status, delete, expenses, payment, transfer, balance mutation, statement writes, workflow, provider, or data-change behavior.

## 2026-06-17 Payment Add HTTP Smoke Coverage

`scripts/settlement-account-payment-add-http-smoke.ps1` now verifies authenticated quick-income creation against the local backend:

- no-token requests return `code = 401`;
- missing `targetId`, zero `amount`, and missing account failures do not change balances or counts;
- a valid request creates one income statement and one payment record, increments the account balance, preserves tenant/org/user links, and is readable through `/biz/bizpaymentrecord/detail`;
- unrelated expenditure, collection-receipt, and debit-note row counts stay unchanged.

## 2026-06-17 Expenses Add HTTP Smoke Coverage

`scripts/settlement-account-expenses-add-http-smoke.ps1` now verifies authenticated quick-expense creation against the local backend:

- no-token requests return `code = 401`;
- missing `targetId`, zero `amount`, and missing account failures do not change balances or counts;
- a valid request creates one expense statement and one expenditure record, decrements the account balance, preserves tenant/org/user links, and is readable through `/biz/bizexpenditurerecord/detail`;
- unrelated payment, collection-receipt, and debit-note row counts stay unchanged.

## 2026-06-17 Transfer Add HTTP Smoke Coverage

`scripts/settlement-account-transfer-add-http-smoke.ps1` now verifies authenticated settlement-account transfer creation against the local backend:

- no-token requests return `code = 401`;
- missing expense account, missing income account, zero amount, same-account, and missing-account failures do not change balances or counts;
- a valid request creates one expense statement, one income statement, one expenditure record, and one payment record;
- the expense account balance decreases and the income account balance increases by the submitted amount;
- transfer records use fixed `dealings` category, preserve tenant/org/user links, and are readable through `/biz/bizexpenditurerecord/detail` and `/biz/bizpaymentrecord/detail`;
- unrelated collection-receipt and debit-note row counts stay unchanged.

## 2026-06-22 Delete HTTP Smoke Coverage

`scripts/settlement-account-delete-http-smoke.ps1` now verifies authenticated protected logical deletion against the local backend:

- no-token requests return `code = 401`;
- missing id and missing-account failures do not delete any temporary account;
- accounts referenced by active settlement statements or payment-record `OBJECT_ID` rows are rejected;
- mixed valid/missing batches roll back without changing the valid account;
- a valid unused account is marked `DELETE_FLAG = DELETED`, disappears from `detail`, and does not change settlement statement, payment, expenditure, collection-receipt, debit-note, or account row counts.
