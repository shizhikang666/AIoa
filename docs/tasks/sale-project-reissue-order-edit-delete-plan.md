# Sale Project Reissue Order Edit/Delete Plan

Date: 2026-06-25

## Scope

- Add protected `POST /biz/saleprojectreissueorder/edit` and `/delete`.
- Keep direct edit/delete limited to direct reissue orders. Reissue orders whose `PROCESS_ID` exists in `act_hi_procinst` remain protected for the workflow-owned `Process_project_reissue_product` path.
- Allow edit to replace the direct reissue order `PROCESS_ID`, `AMOUNT`, `REMARK`, and submitted `productList` in one transaction.
- Allow delete to logically delete direct reissue orders, linked `REISSUE_ORDER` product items, and child `sale_project_product_item_relation` rows in one transaction.
- Recalculate sale-project totals, return/refund totals, payment state, and project state after edit/delete.

## Write Guards

- Require authenticated route access and existing sale-project data scope through the joined project row.
- Reject `FOLLOW` projects.
- Reject duplicate active `PROCESS_ID` values.
- Reject direct add/edit/delete when the submitted or existing process id is workflow-owned by checking `act_hi_procinst.PROC_INST_ID_`.
- Reject product item replacement/deletion when linked reissue product items are delivered, no longer `WAIT_DELIVER`, not `REISSUE_ORDER`, or referenced by active invoice/return rows.

## Side Effects

- Covered:
  - `biz_sale_project_reissue_order` master update/logical delete.
  - `biz_sale_project_product_item` replacement/logical delete for linked reissue items.
  - `sale_project_product_item_relation` replacement/logical delete for linked child rows.
  - `biz_sale_project` total/status correction.
- Deferred:
  - Delivery invoice rows, invoice item rows, inventory rows, delivery records, settlement statements, payment records, expenditure records, workflow runtime mutation, notifications, file cleanup, Java data-change events, Java source changes, schema changes, `.env`, production data operations, and commits.

## Verification

- `php -l app\controller\biz\SaleProjectReissueOrderController.php`
- `php -l app\service\biz\SaleProjectService.php`
- `php -l route\app.php`
- PowerShell parser checks for `scripts\sale-project-reissue-order-add-http-smoke.ps1`, `scripts\project-preflight.ps1`, and `scripts\project-progress.ps1`
- `php think route:list | Select-String -Pattern "saleprojectreissueorder/(add|edit|delete)"`
- `php think route:list` concrete route count: 587
- `.\scripts\sale-project-reissue-order-add-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`
- `.\scripts\workflow-project-reissue-approve-http-smoke.ps1 -BackendBaseUrl http://127.0.0.1:82`
- Frontend route/method/deferred-wrapper smokes
- `.\scripts\project-progress.ps1 -Lean`
- `git diff --check`
