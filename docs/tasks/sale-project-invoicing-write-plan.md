# Sale Project Invoicing Write Plan

Date: 2026-06-18

## Scope

Implement the copied frontend sale-project invoicing row-maintenance actions:

- `POST /biz/saleprojectinvoicing/add`
- `POST /biz/saleprojectinvoicing/edit`
- `POST /biz/saleprojectinvoicing/delete`

The existing reads and complete marker stay in the same service:

- `GET /biz/saleprojectinvoicing/page`
- `GET /biz/saleprojectinvoicing/customer`
- `GET /biz/saleprojectinvoicing/detail`
- `POST /biz/saleprojectinvoicing/complete`

## Java And Frontend References

- Java controller exposes page/customer/detail/complete. Java service also has `add()` for workflow/project startup paths.
- Copied Vue page exposes add and delete controls and has a form capable of submitting edit-shaped data.
- Frontend form fields: `projectId`, `amount`, `invoicingCategory`, `processId`, `remark`, `companyName`, `customerCompany`, `unit`, `phone`, `taxpayer`, `corporateAccount`, `bankName`, `unitAddress`, `unitPhone`, and `harvestAddress`.
- Java add param requires amount > 0, ticket category, company/customer/unit/tax/bank/address fields, and process id.
- Java edit param requires id, project id, ticket category, process id, company/customer/unit/phone/tax/bank/address, and harvest address.

## Data Model

Primary table:

- `biz_sale_project_invoicing`

Related read and permission table:

- `biz_sale_project`

The write block must not mutate delivery invoice, invoice item, payment, expenditure, settlement statement, delivery, return, rating, workflow, product item, inventory, or file tables.

## Implementation Rules

- Add validates the target active sale project through tenant/data-scope guards and inserts one invoice application row.
- Add always writes `INVOICING_STATE = INVOICING_STATE_WAIT`, matching Java service behavior.
- Edit validates the existing row and target project through tenant/data-scope guards, updates only invoice application fields, and optionally accepts `INVOICING_STATE_WAIT` or `INVOICING_STATE_COMPLETE`.
- Delete validates all selected active rows before any write, then logically deletes with `DELETE_FLAG = DELETED`.
- All writes run in transactions and refresh audit update fields.
- Ticket categories are restricted to `SpecialTicket` and `GeneralTicket`.
- States are restricted to `INVOICING_STATE_WAIT` and `INVOICING_STATE_COMPLETE`.

## Verification

Run:

```powershell
php -l app\controller\biz\SaleProjectInvoicingController.php
php -l app\service\biz\SaleProjectBillingService.php
.\scripts\sale-project-invoicing-write-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
.\scripts\frontend-deferred-write-wrapper-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
.\scripts\frontend-api-method-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
.\scripts\business-read-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82
```

The focused smoke covers no-token rejection, validation errors, missing project, add/page/customer/detail readback, invalid edit rollback, valid edit, complete, mixed delete rollback, valid logical delete, owning project preservation, and related side-effect table count stability.
