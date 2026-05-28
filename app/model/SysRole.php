<?php

namespace app\model;

/**
 * System role model.
 *
 * Java entity: vip.xiaonuo.sys.modular.role.entity.SysRole
 * Table: sys_role
 *
 * Relation notes:
 * - ORG_ID optionally scopes a role to sys_org.ID.
 * - TENANT_ID points to tenants.Tenant_ID.
 * - User-role and role-resource grants are stored in sys_relation.
 *
 * @property string $ID 主键
 * @property string|null $ORG_ID 组织id
 * @property string|null $NAME 名称
 * @property string|null $CODE 编码
 * @property string|null $CATEGORY 分类
 * @property int|null $SORT_CODE 排序码
 * @property string|null $EXT_JSON 扩展信息
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 * @property string $TENANT_ID 租户id
 */
class SysRole extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'sys_role';
}

