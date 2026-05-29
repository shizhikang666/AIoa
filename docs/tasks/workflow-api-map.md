# Workflow API Map

## First Read-Only Batch

| Java Endpoint | Method | ThinkPHP Direction | Notes |
| --- | --- | --- | --- |
| `/biz/task/count` | GET | task count for current user | Requires auth-agent current user |
| `/biz/task/list` | GET | pending task list for current user | Filter by `ASSIGNEE_` |
| `/biz/task/page` | GET | pending task page | Use `act_ru_task` |
| `/biz/task/history/page` | GET | completed task page | Use `act_hi_taskinst` |
| `/biz/process/page` | GET | started process page | Use `act_hi_procinst.START_USER_ID_` |
| `/biz/process/detail` | GET | process detail/timeline | Use historic activities and variables |
| `/biz/process/variable` | POST | process variables | Normalize runtime/history values |

## Second Config Batch

| Java Endpoint | Method | ThinkPHP Direction | Notes |
| --- | --- | --- | --- |
| `/biz/userprocessconfig/detail` | GET | current user's workflow config | Uses `sys_user_process_config` |
| `/biz/userprocessconfig/save` | POST | save workflow config | Needs validation and auth context |
| `/sys/userCenter/process/config` | POST | frontend user-center compatibility | Existing frontend API wrapper calls this path |
| `/sys/userCenter/process/config/edit` | POST | frontend user-center compatibility | Coordinate with user-agent |

## Deferred Runtime Mutation Batch

| Java Endpoint | Method | Reason Deferred |
| --- | --- | --- |
| `/biz/task/approve` | POST | Needs side-effect dispatch and variable validation |
| `/biz/task/reject` | POST | Needs task state/comment semantics |
| `/biz/process/cancel` | POST | Needs runtime/historic consistency |
| `/biz/process/*/start` | POST | Each start route has domain-specific validation and side effects |
| `/biz/process/leave/edit` | POST | Needs editable task/form state support |

## Route File Boundary

`route/app.php` is locked. workflow-agent must write a public file change request before any workflow routes are registered.

## Response Contract

Use shared API response conventions after auth-agent and frontend-agent agree whether frontend-compatible `msg` is also returned alongside `message`.
