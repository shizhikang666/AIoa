<?php

namespace app\model;

/**
 * User workflow configuration model.
 *
 * Java entity: vip.xiaonuo.sys.modular.userprocessconfig.entity.SysUserProcessConfig
 * Table: sys_user_process_config
 *
 * Relation notes:
 * - CONFIG_JSON stores process names and sys_user.ID lists.
 * - TENANT_ID points to tenants.Tenant_ID.
 *
 * @property string $ID 主键
 * @property string|null $CONFIG_JSON 配置json
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 * @property string $TENANT_ID 租户id
 * @property int $VERSION 版本号乐观锁标记
 */
class SysUserProcessConfig extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'sys_user_process_config';
}

