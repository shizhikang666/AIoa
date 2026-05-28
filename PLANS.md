# PLANS.md

## Completed Plan: db-agent Phase 2 - Business Table Model Plan

Status: completed on 2026-05-28 after implementation and baseline checks.

### 1. Current Goal

Continue db-agent work after the foundation database mapping commit. The next db-agent slice should analyze and generate passive ThinkPHP Models for high-dependency OA business tables that later workflow-agent and api-agent will need.

This phase must not implement controller, service, route, auth, user, workflow, or frontend business logic.

### 2. Involved Modules

- db-agent only
- Java source analysis only under `F:\AI\projects\testJava\OA`
- ThinkPHP write target only under `F:\AI\projects\testJava\OA-db`

### 3. Involved Files

Expected Java analysis inputs:

- Primary SQL reference: `F:\AI\projects\testJava\OA\oa2026.sql`
- Historical SQL snapshot for comparison: `F:\AI\projects\testJava\OA\snowy-web-app\src\main\resources\_sql\2026\oa2026.sql`
- Java `biz` entity and mapper files under the Java OA project
- Existing db-agent docs under `F:\AI\projects\testJava\OA-db\docs\database`

Expected ThinkPHP outputs:

- `F:\AI\projects\testJava\OA-db\docs\database\biz-table-map.md`
- `F:\AI\projects\testJava\OA-db\docs\database\biz-model-plan.md`
- New passive model files under `F:\AI\projects\testJava\OA-db\app\model`

Candidate high-priority tables:

- `biz_cc_records`
- `biz_file_relation`
- `biz_leave_application`
- `biz_payment_record`
- `biz_expenditure_record`
- `biz_purchase_order`
- `biz_purchase_order_item`
- `biz_sale_project`
- `biz_sale_project_product_item`
- `biz_team_project`
- `biz_team_project_task`
- `customer`
- `supplier`
- `warehouses`
- `inventory`

### 4. Risks

- Business tables are numerous; this phase must stay small and prioritize dependency-heavy tables.
- Java entity names may not map one-to-one to table names.
- Some relations are stored in JSON or generic relation tables instead of SQL foreign keys.
- Generating too many models in one commit could violate the small-step rule.
- The updated root SQL may contain data needed for compatibility checks. Use it as read-only input only.
- Online realtime data sync is a final-stage requirement and must not be implemented until the system is complete and a confirmed migration/sync plan exists.

### 5. Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

`php think test` should be run only if a test command exists in the current ThinkPHP console.

### 6. Acceptance Criteria

- Java source project remains read-only.
- Only db-agent worktree is modified.
- No public locked files are modified.
- No database field is deleted, renamed, or altered.
- Added Models are passive database mapping classes only.
- New docs explain table purpose, fields, and relation notes for later agents.
- Tests above pass.
- Commit message includes `db-agent`.
- Single commit touches fewer than 30 files.

### 7. Forbidden Scope

- Do not modify `composer.json`, `composer.lock`, `config/app.php`, `config/database.php`, `route/app.php`, `.env`, `.env.example`, or `app/common.php`.
- Do not modify Java source files.
- Do not modify `F:\AI\projects\testJava\OA\oa2026.sql`; it is read-only reference input.
- Do not generate controllers, services, middleware, routes, API handlers, frontend code, or workflow runtime logic.
- Do not push remote branches unless explicitly requested.

## Final-Stage Reminder

Before project completion, remind the user that production/online realtime data must be synced into the final ThinkPHP OA project. Details are tracked in `docs/tasks/final-data-sync-reminder.md`.

## Completed Plan: db-agent Phase 3 - Sales Support Table Coverage

Status: completed on 2026-05-28 after implementation and baseline checks.

### Current Goal

Continue database coverage by analyzing sales project support tables that depend on the Phase 2 sales, customer, warehouse, and inventory foundations. Generate documentation and passive ThinkPHP Models only.

### Candidate Inputs

- Primary SQL reference: `F:\AI\projects\testJava\OA\oa2026.sql`
- Java entity and mapper files under `F:\AI\projects\testJava\OA`
- Current db-agent Models under `F:\AI\projects\testJava\OA-db\app\model`

### Candidate Scope

- Product master and product relation tables needed by sales order items.
- Sales project delivery, invoicing, reissue, follow-up, rating, field change, and return tables.
- Documentation updates under `docs/database`.
- Passive Model classes under `app/model` only.

Candidate tables:

- `biz_product`
- `product_relation`
- `biz_sale_project_invoice`
- `biz_sale_project_invoice_item`
- `biz_sale_project_invoicing`
- `biz_sale_project_product_info`
- `biz_sale_project_reissue_order`
- `sale_project_product_item_relation`
- `sale_project_follow_up`
- `customer_follow_up`
- `sale_project_rate`
- `sales_project_field_change_log`
- `return_order`
- `return_order_item`
- `delivery_record`

### Risks

- Java `ProductRelation` declares `PRODUCT_RELATION`, while the SQL dump contains `product_relation`; use the SQL physical table name for ThinkPHP.
- Some Java fields are translation-only with `@TableField(exist = false)` and must not become database columns.
- Follow-up, rating, and return tables reference users, organizations, customers, warehouses, and projects; only document those relations in this phase.

### Forbidden Scope

- Do not modify Java source files.
- Do not modify public locked files.
- Do not implement controller, service, route, auth, user, workflow, or frontend logic.
- Do not start production data synchronization.

### Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

### Acceptance Criteria

- Java source project remains read-only.
- Only db-agent worktree is modified.
- Added Models are passive database mapping classes only.
- No public locked files are modified.
- New docs identify field groups and relation notes for later agents.
- Tests above pass.
- Commit message includes `db-agent`.

## Completed Plan: db-agent Phase 4 - Finance Settlement Table Coverage

Status: completed on 2026-05-28 after implementation and baseline checks.

### Current Goal

Continue database coverage for finance and settlement support tables that connect payment/expenditure records to settlement accounts and account statements.

### Candidate Scope

- Finance and settlement: `biz_collection_receipt`, `biz_debit_note`, `settlement_account`, `settlement_account_statement`.
- Documentation updates under `docs/database`.
- Passive Model classes under `app/model` only.

### Deferred Scope

- Collaboration around team projects: `biz_team_project_comment`, `biz_team_project_comment_reply`, `biz_team_project_task_comment`, `biz_team_project_task_category`, `biz_team_project_user`, `biz_team_project_task_user`.
- Other support records only if a later db-agent slice needs them.

### Risks

- `settlement_account.org` is lower-case in SQL and must remain unchanged.
- `biz_collection_receipt` and `biz_debit_note` depend on payment/expenditure record ids already modeled in Phase 2.
- Settlement account statements store process identifiers, but workflow behavior is out of scope for db-agent.

### Forbidden Scope

- Do not modify Java source files.
- Do not modify public locked files.
- Do not implement controller, service, route, auth, user, workflow, or frontend logic.
- Do not start production data synchronization.

### Test Commands

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

## Next Plan Candidate: db-agent Phase 5 - Team Collaboration Support Tables

Status: not started.

### Current Goal

Add passive database coverage for team project comments, task comments, task categories, and team/task user relation tables.

### Candidate Scope

- `biz_team_project_comment`
- `biz_team_project_comment_reply`
- `biz_team_project_task_comment`
- `biz_team_project_task_category`
- `biz_team_project_user`
- `biz_team_project_task_user`

### Forbidden Scope

- Do not modify Java source files.
- Do not modify public locked files.
- Do not implement team collaboration controller, service, route, workflow, or frontend behavior.
