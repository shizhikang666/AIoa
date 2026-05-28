# IMPLEMENT.md

## db-agent Implementation Flow

Every db-agent phase must follow this order:

1. Read `AGENTS.md`, `PLANS.md`, `IMPLEMENT.md`, and `STATUS.md`.
2. Check `git status --short --branch`.
3. Analyze Java SQL/entity/mapper files from `F:\AI\projects\testJava\OA` as read-only input.
4. Analyze existing ThinkPHP files in `F:\AI\projects\testJava\OA-db`.
5. Write the smallest safe change set.
6. Avoid public locked files unless a change request is written and confirmed.
7. Run required tests:

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem app\model -Filter *.php | ForEach-Object { php -l $_.FullName }
```

8. Run `git status --short --branch`.
9. Run `git add .`.
10. Run `git commit -m "db-agent: <clear summary>"`.
11. Append completion status to `STATUS.md`.
12. Report completed content, modified files, test results, current issues, and next plan.

## Public File Change Request Rule

The following files are locked for db-agent by default:

- `composer.json`
- `composer.lock`
- `config/app.php`
- `config/database.php`
- `route/app.php`
- `.env`
- `.env.example`
- `app/common.php`

If a future db-agent phase needs one of these files, create:

`F:\AI\projects\testJava\OA-db\docs\tasks\public-file-change-request.md`

Then wait for confirmation before editing the locked file.

## Model Generation Rule

Foundation Models created by db-agent must:

- preserve physical table names
- preserve database column spelling and casing
- use comments to record Java entity/source table relations
- avoid controller/service logic
- avoid query behavior that belongs to auth-agent, user-agent, workflow-agent, or api-agent

