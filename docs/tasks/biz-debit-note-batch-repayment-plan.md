# Debit Note Batch Repayment Plan

## Scope

Replace the controlled-deferred `/biz/bizdebitnote/batchRepayment/edit` wrapper with the narrow Java-compatible batch repayment flow used by the copied debit-note quick-settlement form.

Java reference:

- `BizDebitNoteController.batchRepayment`
- `BizDebitNoteServiceImpl.batchRepayment`
- `SettlementAccountServiceImpl.SettlementAccountCorrectIncome`
- `BizPaymentRecordAddEventHandler` loan-repayment callback to `BizDebitNoteServiceImpl.correctAmount`

## Behavior

- Accept `accountId`, `payer`, `payerTime`, optional `remark`, and `items[]` with `id` and positive `amount`.
- Validate the target debit notes in the current tenant and apply the same write-scope checks used by debit-note mark-success.
- Reject duplicate debit-note ids, missing notes, missing accounts, invalid dates, nonpositive amounts, and over-settlement.
- For each item, call the existing settlement-account quick-income creation path with fixed `settlementCategory = LoanRepayment` and `objectId = debit note id`.
- Update `biz_debit_note.SETTLEMENT_AMOUNT` and `PLAY_STATUS` in the same transaction.
- Mark the debit note `AlreadySettled` when the new settlement amount equals the debit-note amount; otherwise keep it `Unsettled`.
- Return generated payment and statement ids for smoke verification.

## Guardrails

- Keep debit-note add/edit/delete/history-add deferred.
- Do not implement Java event bus, workflow hooks, broader finance delete behavior, collection-receipt settlement, provider calls, schema changes, Java source changes, frontend source changes, `.env` changes, production data operations, or Git push behavior.
- DB-backed smokes that insert finance rows must run serially with other finance write smokes.

## Verification

- `php -l app\controller\biz\DebitNoteController.php`
- `php -l app\service\biz\DebitNoteService.php`
- `php -l app\service\biz\SettlementAccountService.php`
- PowerShell parse check for `scripts\biz-debit-note-batch-repayment-http-smoke.ps1`
- `php think route:list | Select-String -Pattern 'biz/bizdebitnote/(batchRepayment/edit|mark/success/edit|page|list|detail|add|edit|history/add|delete)'`
- `.\scripts\biz-debit-note-batch-repayment-http-smoke.ps1`
- `.\scripts\settlement-account-payment-add-http-smoke.ps1`
- `.\scripts\finance-read-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
