# User Directory Controller Adapters

## Purpose

Add thin ThinkPHP controller adapters for read-only user, organization, position, and user-center directory endpoints.

## Added Controllers

- `app\controller\sys\BaseSysController`
- `app\controller\sys\OrgController`
- `app\controller\sys\PositionController`
- `app\controller\sys\UserController`
- `app\controller\sys\UserCenterController`

## Dependencies After Final Merge

These controllers depend on:

- auth-agent `app\support\ApiResponse`
- auth-agent middleware payload key `auth_payload`
- user-agent `OrgService`
- user-agent `PositionService`
- user-agent `UserDirectoryService`
- db-agent model classes used by those services

Final merge order already places `refactor/db`, `refactor/auth`, and `refactor/user` before `refactor/api`.

## Routes Not Registered Yet

This phase intentionally does not modify `route/app.php`.

Route registration remains documented in:

- `docs/tasks/public-file-change-request.md`
- `docs/api/user-directory-route-map.md`

## Excluded Endpoints

- `/sys/userCenter/loginMenu`, owned by auth-agent.
- write endpoints for user/org/position.
- role/resource/permission grant endpoints.
- import/export.
- upload endpoints.
- workflow endpoints.
