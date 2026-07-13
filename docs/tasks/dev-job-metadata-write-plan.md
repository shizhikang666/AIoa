# Dev Job Metadata Write Plan

Date: 2026-06-16

Agent: api-agent / test-agent

## Scope

Replace only the copied frontend `/dev/job/add` and `/dev/job/edit` controlled-deferred wrappers with safe `dev_job` metadata maintenance.

The Java source project remains read-only:

`F:\AI\projects\testJava\OA`

## Java Reference

- `DevJobController.add`
- `DevJobController.edit`
- `DevJobServiceImpl.add`
- `DevJobServiceImpl.edit`
- `DevJobAddParam`
- `DevJobEditParam`
- `dev_job`

Java validates required fields, category, cron expression, action class, duplicate `ACTION_CLASS + CRON_EXPRESSION`, and prevents editing running jobs.

## Implemented Behavior

`POST /dev/job/add` now:

- requires `name`, `category`, `actionClass`, `cronExpression`, and `sortCode`;
- validates category against `FRM` and `BIZ`;
- validates cron expression shape for Java-style six/seven-field cron text;
- accepts only action classes already exposed through the ThinkPHP `getActionClass` compatibility list;
- rejects duplicate active `ACTION_CLASS + CRON_EXPRESSION`;
- inserts a new active `dev_job` row with generated `CODE`, `JOB_STATUS = STOPPED`, `DELETE_FLAG = NOT_DELETE`, and create audit fields.

`POST /dev/job/edit` now:

- requires the same fields plus `id`;
- rejects missing or deleted rows;
- rejects rows currently marked `RUNNING`;
- validates category, cron expression, action class, and duplicate active `ACTION_CLASS + CRON_EXPRESSION` excluding the edited row;
- updates only job metadata fields plus update audit fields;
- preserves `CODE`, `JOB_STATUS`, delete state, and create audit fields.

## Deliberate Exclusions

- No ThinkPHP scheduler is started, stopped, or registered.
- No job class is executed.
- `stopJob`, `runJob`, and `runJobNow` still return controlled `code = 400` deferred responses.
- No Java source files, database schema, provider calls, notifications, cache invalidation hooks, or data-change events are changed.

## Rollback And Cleanup

The write HTTP smoke creates only temporary rows whose names start with `CODEX_JOB_`, then physically removes those temporary rows in `finally`.

The production route behavior uses normal metadata add/edit and does not perform broad cleanup.

## Verification

- `php -l app\controller\dev\JobController.php`
- `php -l app\service\dev\JobService.php`
- `php think route:list | Select-String -Pattern 'dev/job/(add|edit|stopJob|runJob|runJobNow|delete|page|detail|getActionClass)'`
- `.\scripts\dev-job-write-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\dev-read-http-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
- `git diff --check`
