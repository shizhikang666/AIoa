# Collection Receipt Batch Expenditure Plan

## Scope

Replace the controlled-deferred `/biz/bizcollectionreceipt/batchExpenditure/edit` wrapper with the narrow Java-compatible batch expenditure flow used by the copied collection-receipt quick-settlement form.

Java reference:

- `BizCollectionReceiptController.batchExpenditure`
- `BizCollectionReceiptServiceImpl.batchExpenditure`
- `SettlementAccountServiceImpl.SettlementAccountCorrectExpenses`
- `ExpenditureRecordAddEventHandler` repayment callback to `BizCollectionReceiptServiceImpl.correctAmount`

## Behavior

- Accept `accountId`, `payer`, `payerTime`, optional `remark`, and `items[]` with `id` and positive `amount`.
- Validate the target receipts in the current tenant and apply the same write-scope checks used by collection-receipt mark-success.
- Reject duplicate receipt ids, missing receipts, missing accounts, invalid dates, nonpositive amounts, and over-settlement.
- For each item, call the existing settlement-account quick-expense creation path with fixed `settlementCategory = repayment` and `objectId = receipt id`.
- Update `biz_collection_receipt.SETTLEMENT_AMOUNT` and `PLAY_STATUS` in the same transaction.
- Mark the receipt `AlreadySettled` when the new settlement amount equals the receipt amount; otherwise keep it `Unsettled`.
- Return generated expenditure and statement ids for smoke verification.

## Guardrails

- Keep collection-receipt add/edit/delete deferred.
- Do not implement Java event bus, workflow hooks, broader finance delete behavior, provider calls, schema changes, Java source changes, frontend source changes, `.env` changes, production data operations, or Git push behavior.
- DB-backed smokes that insert finance rows must run serially with other finance write smokes.

## Verification

- `php -l app\controller\biz\CollectionReceiptController.php`
- `php -l app\service\biz\CollectionReceiptService.php`
- `php -l app\service\biz\SettlementAccountService.php`
- PowerShell parse check for `scripts\biz-collection-receipt-batch-expenditure-http-smoke.ps1`
- `php think route:list | Select-String -Pattern 'biz/bizcollectionreceipt/(batchExpenditure/edit|mark/success/edit|page|list|detail|add|edit|delete)'`
- `.\scripts\biz-collection-receipt-batch-expenditure-http-smoke.ps1`
- `.\scripts\settlement-account-expenses-add-http-smoke.ps1`
- `.\scripts\finance-read-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
