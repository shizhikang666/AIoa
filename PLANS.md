# PLANS.md

## Active Plan: db-agent Phase 2 - Business Table Model Plan

Status: pending user confirmation before implementation.

### 1. Current Goal

Continue db-agent work after the foundation database mapping commit. The next db-agent slice should analyze and generate passive ThinkPHP Models for high-dependency OA business tables that later workflow-agent and api-agent will need.

This phase must not implement controller, service, route, auth, user, workflow, or frontend business logic.

### 2. Involved Modules

- db-agent only
- Java source analysis only under `F:\AI\projects\testJava\OA`
- ThinkPHP write target only under `F:\AI\projects\testJava\OA-db`

### 3. Involved Files

Expected Java analysis inputs:

- `F:\AI\projects\testJava\OA\snowy-web-app\src\main\resources\_sql\2026\oa2026.sql`
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
- Do not generate controllers, services, middleware, routes, API handlers, frontend code, or workflow runtime logic.
- Do not push remote branches unless explicitly requested.

