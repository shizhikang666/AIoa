# Settlement Account Delete Plan

## Scope

Replace `POST /biz/settlementaccount/delete` controlled-deferred behavior with protected settlement-account logical delete maintenance.

## Java Reference

- Java `SettlementAccountController.delete` is present but commented out in the copied source snapshot.
- Java `SettlementAccountServiceImpl.delete` delegates to `removeByIds(...)`.
- The ThinkPHP implementation intentionally uses logical delete to match the existing active-row filtering and to avoid destroying finance/account history.

## ThinkPHP Behavior

- Accept Java/copied-frontend delete payloads:
  - `[{ "id": "..." }]`
  - `{ "idList": [...] }`
  - `{ "ids": [...] }`
  - `{ "id": "..." }`
- Validate every selected active account through existing tenant and write-scope guards before updating any row.
- Reject the whole batch if any id is missing or not visible.
- Reject active referenced accounts when any selected account appears in:
  - `settlement_account_statement.ACCOUNT_ID`
  - `biz_payment_record.TARGET_ID`
  - `biz_payment_record.OBJECT_ID`
  - `biz_expenditure_record.TARGET_ID`
  - `biz_expenditure_record.OBJECT_ID`
- Mark selected rows with `DELETE_FLAG = DELETED` and refresh update audit fields.

## Non-Goals

- No physical deletion through the HTTP route.
- No settlement-account balance mutation.
- No statement/payment/expenditure creation, deletion, or mutation.
- No workflow hooks, data-change events, notifications, Java source changes, schema changes, `.env` edits, production data operations, or commits.

## Verification

- `php -l app\controller\biz\SettlementAccountController.php`
- `php -l app\service\biz\SettlementAccountService.php`
- PowerShell parser checks for:
  - `scripts\settlement-account-delete-http-smoke.ps1`
  - `scripts\frontend-deferred-write-wrapper-smoke.ps1`
  - `scripts\project-preflight.ps1`
- `.\scripts\settlement-account-delete-http-smoke.ps1`
- `.\scripts\settlement-account-payment-add-http-smoke.ps1`
- `.\scripts\settlement-account-expenses-add-http-smoke.ps1`
- `.\scripts\settlement-account-transfer-add-http-smoke.ps1`
- `.\scripts\settlement-account-read-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `git diff --check`
