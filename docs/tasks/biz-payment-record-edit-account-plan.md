# Biz Payment Record Account-Switch Plan

Date: 2026-06-17

Agent: merge-agent / api-agent / test-agent / docs-agent

## Goal

Replace the controlled-deferred `/biz/bizpaymentrecord/edit/account` wrapper with the Java-compatible payment-record settlement-account switch.

## Scope

- `POST /biz/bizpaymentrecord/edit/account`
- `BizPaymentRecordEditAccountParam` compatibility: `id`, `currentTargetId`, `targetId`
- tenant and write-scope guard for the payment record
- tenant and write-scope guard for both settlement accounts
- linked `settlement_account_statement.ACCOUNT_ID` sync by the payment record `SERIAL_ID`
- `biz_payment_record.TARGET_ID` and `ORG` update from the target account
- settlement-account balance transfer using the stored payment-record `AMOUNT`
- transactional rollback when the record, linked statement, current account, or target account is missing or mismatched

## Deliberate Exclusions

- No payment-record add or delete behavior.
- No client-submitted amount, object, process, serial, category, user, org, audit, or delete-flag edits.
- No new settlement-account statements.
- No payment-record creation, workflow, notification, data-change event, provider, import/export, schema, Java source, `.env`, Composer, npm, frontend source, production data, or Git push changes.

## Acceptance

- Authenticated valid account switch returns `code=200`.
- No-token account switch returns `code=401`.
- Missing `targetId` returns `code=400`.
- Same current/target account returns `code=400`.
- Mismatched `currentTargetId` returns `code=400` and leaves balances/links unchanged.
- Missing linked statement returns `code=404` and leaves balances/links unchanged.
- Valid switch subtracts the stored payment amount from the current account and adds it to the target account.
- Payment record and linked statement point to the new target account after a valid switch.
- Client-submitted spoofed amount/object/serial/org fields are ignored.
- Representative finance table counts stay stable after setup.

## Verification

```powershell
php -l app\controller\biz\PaymentRecordController.php
php -l app\service\biz\PaymentRecordService.php
php think route:list | Select-String -Pattern 'biz/bizpaymentrecord/(edit|edit/account|add|delete|page|detail)'
.\scripts\biz-payment-record-edit-account-http-smoke.ps1
.\scripts\biz-payment-record-edit-http-smoke.ps1
.\scripts\finance-read-http-smoke.ps1
.\scripts\frontend-deferred-write-wrapper-smoke.ps1
.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing
.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred
git diff --check
```
