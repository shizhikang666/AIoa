# User Directory Route Map

## Purpose

Map Java user, organization, and position read-only endpoints to planned ThinkPHP controller adapters after `refactor/user` is merged.

## Dependencies

Merge order dependency:

1. `refactor/db`
2. `refactor/auth`
3. `refactor/user`
4. `refactor/api`

api-agent should not implement these adapters until the user-agent read-only services exist in the integration branch.

## Planned Read-Only Routes

| Java Endpoint | Method | Planned ThinkPHP Adapter | Owner |
| --- | --- | --- | --- |
| `/sys/org/tree` | GET | `app\controller\sys\OrgController::tree` | api-agent adapter, user-agent service |
| `/sys/org/orgTreeSelector` | GET | `app\controller\sys\OrgController::treeSelector` | api-agent adapter, user-agent service |
| `/sys/org/detail` | GET | `app\controller\sys\OrgController::detail` | api-agent adapter, user-agent service |
| `/sys/position/page` | GET | `app\controller\sys\PositionController::page` | api-agent adapter, user-agent service |
| `/sys/position/detail` | GET | `app\controller\sys\PositionController::detail` | api-agent adapter, user-agent service |
| `/sys/position/positionSelector` | GET | `app\controller\sys\PositionController::selector` | api-agent adapter, user-agent service |
| `/sys/user/page` | GET | `app\controller\sys\UserController::page` | api-agent adapter, user-agent service |
| `/sys/user/detail` | GET | `app\controller\sys\UserController::detail` | api-agent adapter, user-agent service |
| `/sys/userCenter/loginOrgTree` | GET | `app\controller\sys\UserCenterController::loginOrgTree` | api-agent adapter, user-agent service |
| `/sys/userCenter/loginPositionInfo` | GET | `app\controller\sys\UserCenterController::loginPositionInfo` | api-agent adapter, user-agent service |
| `/sys/userCenter/getUserListByIdList` | POST | `app\controller\sys\UserCenterController::getUserListByIdList` | api-agent adapter, user-agent service |
| `/sys/userCenter/getPositionListByIdList` | POST | `app\controller\sys\UserCenterController::getPositionListByIdList` | api-agent adapter, user-agent service |

## Excluded From This Batch

- `/sys/userCenter/loginMenu`: already owned by auth-agent.
- user add/edit/delete/enable/disable/reset password.
- user role/resource/permission grants.
- import/export endpoints.
- avatar/signature uploads.
- process config endpoints.

## Response Contract

Use the shared API response convention from auth-agent after merge. During frontend compatibility work, decide whether responses include both `message` and `msg`.

## Route Registration

`route/app.php` is locked. See `docs/tasks/public-file-change-request.md`.
