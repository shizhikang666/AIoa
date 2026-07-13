<?php

namespace app\model;

/**
 * Inventory model.
 *
 * Java entity: vip.xiaonuo.biz.modular.inventory.entity.Inventory
 * Table: inventory
 *
 * Relation notes:
 * - WAREHOUSES_ID points to warehouses.ID.
 * - PRODUCT_ID points to the product table.
 * - SQL defines a unique index on PRODUCT_ID and WAREHOUSES_ID.
 *
 * @property string $ID 主键
 * @property string $WAREHOUSES_ID 仓库id
 * @property string $PRODUCT_ID 产品Id
 * @property string|float $CURRENT_COUNT 当前库存
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 * @property string $TENANT_ID 租户id
 * @property int $VERSION 版本号乐观锁标记
 */
class Inventory extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'inventory';
}

