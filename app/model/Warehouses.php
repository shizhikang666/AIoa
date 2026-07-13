<?php

namespace app\model;

/**
 * Warehouse model.
 *
 * Java entity: vip.xiaonuo.biz.modular.warehouses.entity.Warehouses
 * Table: warehouses
 *
 * Relation notes:
 * - USER points to sys_user.ID as warehouse owner.
 * - ORG points to sys_org.ID.
 *
 * @property string $ID 主键
 * @property string $NAME 仓库名称
 * @property string $CODE 仓库编码
 * @property string|null $ADDRESS 仓库地址
 * @property int|null $SORT_CODE 排序码
 * @property string|null $USER 仓库负责人
 * @property string|null $EXT_JSON 扩展信息
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 * @property string $TENANT_ID 租户id
 * @property string|null $ORG 所属组织组织
 */
class Warehouses extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'warehouses';
}

