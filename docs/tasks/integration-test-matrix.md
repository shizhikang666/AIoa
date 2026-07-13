# Integration Test Matrix

## Purpose

Define what merge-agent and test-agent should verify after module branches are merged into `refactor/thinkphp-main`.

## Baseline Checks After Each Merge

Run after each merge step:

```powershell
composer dump-autoload
php think
php think route:list
Get-ChildItem -Recurse app,config,route -Include *.php | ForEach-Object { php -l $_.FullName }
git status --short --branch
```

Run `php think test` only if the command becomes available.

## Merge Order Checks

1. Merge `refactor/db`
   - Verify all `app/model/*.php` files lint.
   - Verify `Act*`, `Sys*`, and business support models autoload.
2. Merge `refactor/auth`
   - Verify auth routes appear in `php think route:list`.
   - Verify API responses include `code`, `message`, `msg`, and `data`.
3. Merge `refactor/user`
   - Verify user-agent services autoload after db models exist.
   - Smoke-check `TreeBuilder` with a small in-memory tree.
4. Merge `refactor/workflow`
   - Verify workflow services autoload after `Act*` models exist.
   - Verify workflow variable normalization can run with sample rows.
5. Merge `refactor/api`
   - Verify user directory and workflow controller classes autoload.
   - Verify route registration only matches approved public-file requests.
6. Merge `refactor/test`
   - Verify this matrix and baseline docs are present.
7. Merge `refactor/docs`
   - Verify final merge checklist and data sync reminder are present.

## Auth Contract Checks

Expected routes:

- `GET /auth/b/getPicCaptcha`
- `POST /auth/b/doLogin`
- `POST /auth/b/doLoginByPhone`
- `GET /auth/b/doLogout`
- `GET /auth/b/getLoginUser`
- `POST /auth/b/safe/password`
- `GET /sys/userCenter/loginMenu`

Expected response keys:

- `code`
- `message`
- `msg`
- `data`

## User Directory Contract Checks

Only run after route registration is approved and merged.

- `GET /sys/org/tree`
- `GET /sys/org/orgTreeSelector`
- `GET /sys/org/detail`
- `GET /sys/position/page`
- `GET /sys/position/detail`
- `GET /sys/position/positionSelector`
- `GET /sys/user/page`
- `GET /sys/user/detail`
- `GET /sys/userCenter/loginOrgTree`
- `GET /sys/userCenter/loginPositionInfo`
- `POST /sys/userCenter/getUserListByIdList`
- `POST /sys/userCenter/getPositionListByIdList`

Do not expect write/import/export/upload routes in this batch.

## Workflow Read-Only Contract Checks

Only run after route registration is approved and merged.

- `GET /biz/task/count`
- `GET /biz/task/list`
- `GET /biz/task/page`
- `GET /biz/task/history/page`
- `GET /biz/process/page`
- `GET /biz/process/detail`
- `POST /biz/process/variable`

Do not expect approve, reject, cancel, or process start routes in this batch.

## Stop Conditions During Testing

- `php think route:list` fails.
- PHP lint fails.
- Controller/service class autoload fails after expected merge order.
- A route appears that was not approved.
- A write/mutation endpoint is accidentally registered in a read-only batch.
- Database/cache runtime failure cannot be separated from missing environment configuration.

## Final Reminder

After the complete ThinkPHP OA system is working, remind the user that online realtime production data synchronization still needs a dedicated plan and implementation.
