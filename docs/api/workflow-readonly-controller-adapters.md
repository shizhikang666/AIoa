# Workflow Read-Only Controller Adapters

## Purpose

Add thin ThinkPHP controller adapters for the safest workflow query endpoints.

## Added Controllers

- `app\controller\biz\BaseWorkflowController`
- `app\controller\biz\TaskController`
- `app\controller\biz\ProcessController`

## Planned Endpoints

| Java Endpoint | Method | Controller Adapter |
| --- | --- | --- |
| `/biz/task/count` | GET | `TaskController::count` |
| `/biz/task/list` | GET | `TaskController::list` |
| `/biz/task/page` | GET | `TaskController::page` |
| `/biz/task/history/page` | GET | `TaskController::historyPage` |
| `/biz/process/page` | GET | `ProcessController::page` |
| `/biz/process/detail` | GET | `ProcessController::detail` |
| `/biz/process/variable` | POST | `ProcessController::variable` |

## Dependencies After Final Merge

These controllers depend on:

- auth-agent `app\support\ApiResponse`
- auth-agent middleware payload key `auth_payload`
- workflow-agent `WorkflowQueryService`
- workflow-agent `WorkflowVariableService`
- db-agent `Act*` model classes used by workflow-agent services

Final merge order already places `refactor/db`, `refactor/auth`, and `refactor/workflow` before `refactor/api`.

## Routes Not Registered Yet

This phase intentionally does not modify `route/app.php`.

Route registration is documented in:

- `docs/tasks/public-file-change-request.md`

## Excluded Endpoints

- `/biz/task/approve`
- `/biz/task/reject`
- `/biz/process/cancel`
- all `/biz/process/*/start` endpoints
- process delegate side effects
- SSE notifications
- file upload/download
