# Scheduler And Queue Policy

Date: 2026-06-26

Owner: api-agent/test-agent

## Current Decision

Scheduler and queue workers are disabled for the current ThinkPHP deployment slice.

The deployment readiness checks may inspect scheduler and queue signals, but they must not start workers, execute jobs, call `dev/job` run endpoints, mutate job status, or connect to production services.

## Current Inventory

- `config/console.php` exists and currently registers no ThinkPHP console commands.
- `composer.json` does not require known queue/worker packages such as `topthink/think-queue`.
- `app/controller/dev/JobController.php`, `app/service/dev/JobService.php`, and `route/app.php` expose dev-job compatibility metadata and control endpoints.
- Dev-job control endpoints are treated as deferred write/runtime controls, not as an approved production scheduler.

## Production Policy

- Do not enable crontab, Supervisor, systemd timers, queue workers, or long-running PHP workers until there is a module-specific execution plan.
- Keep `dev/job` run, stop, and run-now behavior behind normal authentication/authorization and out of deployment smoke automation.
- Before enabling any worker, document the command, working directory, runtime user, restart policy, log path, concurrency, timeout, retry behavior, failure alerting, rollback method, and data side effects.
- Run backup readiness before enabling any job that can write business data.
- Verify worker logs during staging smoke before production traffic.

## Readiness Command

```powershell
.\scripts\deployment-readiness.ps1 -CheckSchedulerPolicy
.\scripts\project-progress.ps1 -CheckDeploy -CheckSchedulerPolicy -Lean
```

Linux equivalent:

```bash
bash scripts/deployment-readiness.sh --check-scheduler-policy
```

## Deferred

- Implementing a real scheduler loop.
- Starting queue workers or background daemons.
- Running existing dev-job endpoints from readiness checks.
- Choosing Supervisor/systemd/crontab process management.
- Adding provider sends, cloud cleanup, imports, exports, or workflow/business write jobs.
