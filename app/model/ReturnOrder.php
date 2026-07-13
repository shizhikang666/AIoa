<?php

namespace app\model;

/**
 * Return order master model.
 *
 * Java entity: vip.xiaonuo.biz.modular.returnorder.entity.ReturnOrder
 * Table: return_order
 *
 * Relation notes:
 * - PROJECT_ID points to biz_sale_project.ID.
 * - WAREHOUSES_ID points to warehouses.ID.
 * - PROCESS_ID stores the related workflow/process id.
 * - USER points to sys_user.ID.
 * - ORG points to sys_org.ID.
 * - Child rows live in return_order_item.
 *
 * @property string $ID
 * @property string $PROJECT_ID
 * @property string|float|null $AMOUNT
 * @property string|null $STATE
 * @property string|null $PROCESS_ID
 * @property string|null $REMARK
 * @property string|null $WAREHOUSES_ID
 * @property string|null $LOGISTICS_CATEGORY
 * @property string|null $LOGISTICS_ID
 * @property string|null $USER
 * @property string|null $ORG
 * @property string|null $EXT_JSON
 * @property string $TENANT_ID
 */
class ReturnOrder extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'return_order';
}
