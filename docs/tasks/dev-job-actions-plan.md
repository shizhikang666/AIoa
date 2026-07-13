# Dev Job Action Status Plan

Date: 2026-06-18

## Scope

- Replace `/dev/job/stopJob`, `/dev/job/runJob`, and `/dev/job/runJobNow` controlled-deferred responses with narrow Java-compatible job-status maintenance.
- Accept Java-style `{ id }` request bodies.
- Validate the active `dev_job` row before any status update.
- Match Java status guards:
  - `stopJob` rejects jobs already marked `STOPPED`.
  - `runJob` rejects jobs already marked `RUNNING`.
  - `runJobNow` turns a stopped job into `RUNNING` before returning success.
- Keep real scheduler registration, scheduler removal, immediate task-class execution, Java bean validation, provider calls, cache invalidation, Java source changes, schema changes, `.env` changes, production data operations, and commits deferred.

## Java Reference

- Java controller: `DevJobController`
- Java service: `DevJobServiceImpl`
- Java parameter: `DevJobIdParam`
- Java enum: `DevJobStatusEnum`

Java calls Hutool `CronUtil` and Spring task beans for real scheduler behavior. The ThinkPHP slice intentionally covers only the database status compatibility that can be verified safely without a PHP scheduler runtime.

## Verification Plan

- `php -l app\controller\dev\JobController.php`
- `php -l app\service\dev\JobService.php`
- `php -l route\app.php`
- PowerShell syntax check for `scripts\dev-job-write-http-smoke.ps1`
- `php think route:list | Select-String -Pattern 'dev/job/(stopJob|runJob|runJobNow|add|edit|delete|page|detail|getActionClass)'`
- `Select-String -Path scripts\frontend-deferred-write-wrapper-smoke.ps1 -Pattern '/dev/job/(stopJob|runJob|runJobNow)'` should return no rows.
- `.\scripts\dev-job-write-http-smoke.ps1`
- `.\scripts\frontend-deferred-write-wrapper-smoke.ps1`
- `.\scripts\frontend-api-route-gap-smoke.ps1 -FailOnReadMissing`
- `.\scripts\frontend-api-method-smoke.ps1 -ShowDeferred`
