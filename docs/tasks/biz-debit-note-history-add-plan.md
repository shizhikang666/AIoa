# Biz Debit Note History Add Plan

Date: 2026-06-17

## Scope

Replace only `POST /biz/bizdebitnote/history/add` with the Java-compatible historical debit-note creation behavior used by the copied debit-note form.

Java behavior checked:

- `BizDebitNoteController.historyAdd`
- `BizDebitNoteServiceImpl.add(BizDebitNoteHistoryAddParam)`
- `BizDebitNoteHistoryAddParam`

Frontend behavior checked:

- `snowy-admin-web/src/api/biz/bizDebitNoteApi.js`
- `snowy-admin-web/src/views/biz/bizdebitnote/addForm.vue`

## Intended Behavior

The endpoint accepts:

- `accountId`
- `amount`
- `historyAmount`
- `createTime`
- `remark`

The ThinkPHP implementation:

- validates required fields and money bounds;
- uses the selected settlement account only to derive organization and tenant context;
- inserts one `biz_debit_note` row with no `EXPENDITURE_RECORD_ID`;
- sets `SETTLEMENT_AMOUNT = HISTORY_AMOUNT`;
- sets `PLAY_STATUS = AlreadySettled` only when `historyAmount == amount`, otherwise `Unsettled`;
- rejects `historyAmount > amount`;
- does not create payment records, expenditure records, or settlement-account statements;
- does not change any settlement-account balance.

## Files

- `app/controller/biz/DebitNoteController.php`
- `app/service/biz/DebitNoteService.php`
- `scripts/biz-debit-note-history-add-http-smoke.ps1`
- `scripts/frontend-deferred-write-wrapper-smoke.ps1`
- `scripts/project-progress.ps1`
- `docs/api/biz-debit-note-readonly-compat.md`
- `docs/api/frontend-controlled-deferred-write-wrappers.md`
- `docs/tasks/api-gap-map.md`
- `docs/tasks/refactor-progress-dashboard.md`
- `docs/tasks/frontend-adaptation-notes.md`
- `docs/tasks/new-conversation-bootstrap.md`
- `docs/tasks/public-file-change-request.md`
- `PLANS.md`
- `IMPLEMENT.md`
- `STATUS.md`

## Acceptance

- Valid authenticated history add returns `code=200` with the generated debit-note id.
- No-token history add returns `code=401`.
- Missing `accountId`, zero `amount`, negative `historyAmount`, invalid `createTime`, over-settlement, and missing settlement-account cases fail before writing a debit-note row.
- Failed cases leave settlement-account balance/version and finance table counts unchanged.
- Successful history add inserts one active `biz_debit_note` row with the expected amount, history amount, settlement amount, status, org, tenant, create time, and no expenditure-record link.
- Payment, statement, expenditure, collection, and settlement-account counts do not change on success.
- Existing batch-repayment, settlement-account payment, finance-read, deferred-wrapper, frontend route-gap, and API-method smokes keep passing with `/biz/bizdebitnote/history/add` removed from the deferred list.

## Out Of Scope

- Debit-note add/edit/delete.
- Payment or expenditure creation.
- Settlement-account statement creation.
- Settlement-account balance mutation.
- Java event bus, workflow hooks, and data-change events.
- Java source, schema, `.env`, Composer/npm/frontend source, production data, and Git push changes.
