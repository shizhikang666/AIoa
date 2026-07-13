# user-agent Phase 2 Services

## Goal

Add read-only user, organization, and position service classes without adding routes or controllers.

## Added Services

- `app/service/user/TreeBuilder.php`
- `app/service/user/OrgService.php`
- `app/service/user/PositionService.php`
- `app/service/user/UserDirectoryService.php`

## Java Compatibility

The service names map to read-only portions of:

- `SysOrgServiceImpl.tree`
- `SysOrgServiceImpl.orgTreeSelector`
- `SysOrgServiceImpl.detail`
- `SysPositionServiceImpl.page`
- `SysPositionServiceImpl.positionSelector`
- `SysUserServiceImpl.page`
- `SysUserServiceImpl.detail`
- `SysUserServiceImpl.loginOrgTree`
- `SysUserServiceImpl.loginPositionInfo`
- `SysUserServiceImpl.getUserListByIdList`
- `SysUserServiceImpl.getPositionListByIdList`

## Merge Dependency

These services reference db-agent model classes:

- `app\model\SysUser`
- `app\model\SysOrg`
- `app\model\SysPosition`

The final merge order already puts `refactor/db` before `refactor/user`, so these classes should exist in `refactor/thinkphp-main` before user-agent code is tested as a merged feature.

## Deliberately Deferred

- `route/app.php` registration.
- Controller adapters.
- Add, edit, delete, enable, disable, reset password.
- Role/resource/permission grants.
- Import/export.
- Avatar and signature upload.
- User process configuration edits.

## Next Step

api-agent or merge-agent should add route/controller integration only after the route change request is approved.
