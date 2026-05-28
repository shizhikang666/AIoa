# Finance Settlement Table Map

Agent: db-agent

Source SQL:

- `F:\AI\projects\testJava\OA\oa2026.sql`

Java source scope:

- `snowy-plugin-biz` finance and settlement account entity/mapper packages.

## Scope

This phase adds passive coverage for finance settlement tables. It only records database structure and relationships. It does not implement payment, accounting, approval, workflow, controller, service, route, or API behavior.

## Tables

| Table | Java entity | Purpose |
| --- | --- | --- |
| `biz_collection_receipt` | `BizCollectionReceipt` | Collection receipt linked to an income/payment record. |
| `biz_debit_note` | `BizDebitNote` | Debit or advance payment note linked to an expenditure record. |
| `settlement_account` | `SettlementAccount` | Settlement account master data and balances. |
| `settlement_account_statement` | `SettlementAccountStatement` | Account transaction statement records. |

## Relation Notes

- `biz_collection_receipt.PAYMENT_RECORD_ID` points to `biz_payment_record.ID`.
- `biz_debit_note.EXPENDITURE_RECORD_ID` points to `biz_expenditure_record.ID`.
- `settlement_account_statement.ACCOUNT_ID` points to `settlement_account.ID`.
- `settlement_account_statement.PROCESS_ID` stores the related process/business operation id.
- `settlement_account.org` is lower-case in SQL and must remain unchanged.
- Java translation-only fields such as account name, organization name, and payer time are not physical columns when annotated with `@TableField(exist = false)`.

## Deferred Finance Work

- Controller/API behavior for payment, collection, debit, and account statement modules belongs to api-agent.
- Approval behavior belongs to workflow-agent.
- Production data synchronization remains a final-stage task and is not started here.
