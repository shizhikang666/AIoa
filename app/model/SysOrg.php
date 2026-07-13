<?php

namespace app\model;

/**
 * System organization model.
 *
 * Java entity: vip.xiaonuo.sys.modular.org.entity.SysOrg
 * Table: sys_org
 *
 * Relation notes:
 * - PARENT_ID points to sys_org.ID and uses 0 for root companies in seed data.
 * - DIRECTOR_ID points to sys_user.ID.
 * - TENANT_ID points to tenants.Tenant_ID.
 *
 * @property string $ID 主键
 * @property string|null $PARENT_ID 父id
 * @property string|null $DIRECTOR_ID 主管ID
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
class SysOrg extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'sys_org';
}

