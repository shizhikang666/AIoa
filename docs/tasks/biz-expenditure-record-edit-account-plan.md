# Biz Expenditure Record Account-Switch Plan

Date: 2026-06-17

Agent: merge-agent / api-agent / test-agent / docs-agent

## Goal

Replace the controlled-deferred `/biz/bizexpenditurerecord/edit/account` wrapper with the Java-compatible expenditure-record settlement-account switch.

## Scope

- `POST /biz/bizexpenditurerecord/edit/account`
- `BizExpenditureRecordEditAccountParam` compatibility: `id`, `currentTargetId`, `targetId`
- tenant and write-scope guard for the expenditure record
- tenant and write-scope guard for both settlement accounts
- linked `settlement_account_statement.ACCOUNT_ID` sync by the expenditure record `SERIAL_ID`
- `biz_expenditure_record.TARGET_ID` update
- settlement-account balance transfer using the stored expenditure-record `AMOUNT`
- transactional rollback when the record, linked statement, current account, or target account is missing or mismatched

## Deliberate Exclusions

- No expenditure-record add or delete behavior.
- No client-submitted amount, object, process, serial, category, user, org, audit, or delete-flag edits.
- No expenditure-record `ORG` rewrite; the Java account-switch method updates `TARGET_ID` only.
- No new settlement-account statements.
- No payment/expenditure creation, workflow, notification, data-change event, provider, import/export, schema, Java source, `.env`, Composer, npm, frontend source, production data, or Git push changes.

## Acceptance

- Authenticated valid account switch returns `code=200`.
- No-token account switch returns `code=401`.
- Missing `targetId` returns `code=400`.
- Same current/target account returns `code=400`.
- Mismatched `currentTargetId` returns `code=400` and leaves balances/links unchanged.
- Missing linked statement returns `code=404` and leaves balances/links unchanged.
- Valid switch adds the stored expenditure amount back to the current account and subtracts it from the target account.
- Expenditure record and linked statement point to the new target account after a valid switch.
- Client-submitted spoofed amount/object/serial/org fields are ignored.
- Representative finance table counts stay stable after setup.

## Verification

```powershell
php -l app\controller\biz\ExpenditureRecordController.php
php -l app\service\biz\ExpenditureRecordService.php
php think route:list | Select-String -Pattern 'biz/bizexpenditurerecord/(edit|edit/account|add|delete|page|detail)'
.\scripts\biz-expenditure-record-edit-account-http-smoke.ps1
.\scripts\biz-expenditure-record-edit-http-smoke.ps1
.\scripts\finance-read-http-smoke.ps1
.\scripts\frontend-deferred-write-wrapper-smoke.ps1
.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing
.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred
git diff --check
```
