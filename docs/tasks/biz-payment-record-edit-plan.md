# Biz Payment Record Payer-Time Edit Plan

Date: 2026-06-16

Agent: merge-agent / api-agent / test-agent / docs-agent

## Goal

Replace the controlled-deferred `/biz/bizpaymentrecord/edit` wrapper with the narrow Java-compatible payer-time correction behavior.

## Scope

- `POST /biz/bizpaymentrecord/edit`
- `BizPaymentRecordEditParam` compatibility: `id`, `payerTime`
- `biz_payment_record.PAYER_TIME` update
- linked `settlement_account_statement.PAYER_TIME` sync by `SERIAL_ID`
- tenant and write-scope guard
- transaction rollback when the payment record or linked statement is missing

## Deliberate Exclusions

- No payment-record add or delete behavior.
- No payment-record account switch behavior.
- No amount, account, object, process, settlement-category, user, org, audit spoofing, or delete-flag edits from the client payload.
- No settlement-account balance mutation.
- No workflow, notification, data-change event, provider, import/export, schema, Java source, `.env`, Composer, or public config changes.

## Acceptance

- Authenticated valid edit returns `code=200`.
- No-token edit returns `code=401`.
- Missing `payerTime` returns `code=400`.
- Missing linked statement returns `code=404` and leaves the payment row unchanged.
- Detail readback returns the new `payerTime`.
- Payment and statement timestamps are updated together.
- Representative finance table counts stay stable after setup.

## Verification

```powershell
php -l app\controller\biz\PaymentRecordController.php
php -l app\service\biz\PaymentRecordService.php
php think route:list | Select-String -Pattern 'biz/bizpaymentrecord/(edit|edit/account|add|delete|page|detail)'
.\scripts\biz-payment-record-edit-http-smoke.ps1
.\scripts\frontend-deferred-write-wrapper-smoke.ps1
.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing
.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred
git diff --check
```
