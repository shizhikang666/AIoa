# user-agent Java Map

## Controllers

### `SysUserController`

- `GET /sys/user/page`
- `POST /sys/user/add`
- `POST /sys/user/edit`
- `POST /sys/user/delete`
- `GET /sys/user/detail`
- `POST /sys/user/disableUser`
- `POST /sys/user/enableUser`
- `POST /sys/user/resetPassword`
- `GET /sys/user/ownRole`
- `POST /sys/user/grantRole`
- `GET /sys/user/ownResource`
- `POST /sys/user/grantResource`
- `GET /sys/user/ownPermission`
- `POST /sys/user/grantPermission`
- `GET /sys/user/downloadImportUserTemplate`
- `POST /sys/user/import`
- `GET /sys/user/export`
- `GET /sys/user/exportUserInfo`
- `GET /sys/user/orgTreeSelector`
- `GET /sys/user/orgListSelector`
- `GET /sys/user/positionSelector`
- `GET /sys/user/roleSelector`
- `GET /sys/user/userSelector`

### `SysUserCenterController`

- password recovery and password update
- avatar and signature update
- login organization tree
- login position info
- profile and workbench updates
- unread message page/detail
- ID-list lookup helpers
- process config helpers

Note: `GET /sys/userCenter/loginMenu` is implemented by auth-agent as an RBAC menu compatibility route.

### `SysOrgController`

- `GET /sys/org/page`
- `GET /sys/org/tree`
- `POST /sys/org/add`
- `POST /sys/org/edit`
- `POST /sys/org/delete`
- `GET /sys/org/detail`
- `GET /sys/org/orgTreeSelector`
- `GET /sys/org/userSelector`

### `SysPositionController`

- `GET /sys/position/page`
- `POST /sys/position/add`
- `POST /sys/position/edit`
- `POST /sys/position/delete`
- `GET /sys/position/detail`
- `GET /sys/position/orgTreeSelector`
- `GET /sys/position/positionSelector`

## Service Inputs

- `SysUserServiceImpl`
- `SysOrgServiceImpl`
- `SysPositionServiceImpl`
- `SysDataChangeListener`
- `SysUserApiProvider`, `SysOrgApiProvider`, `SysPositionApiProvider`
