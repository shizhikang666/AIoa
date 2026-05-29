<?php

namespace app\model;

/**
 * Warehouse delivery record model.
 *
 * Java entity: vip.xiaonuo.biz.modular.warehouses.entity.DeliveryRecord
 * Table: delivery_record
 *
 * Relation notes:
 * - WAREHOUSES_ID points to warehouses.ID.
 * - PRODUCT_ID points to biz_product.ID.
 * - PROCESS_ID stores the related workflow/process id.
 * - OPERATOR points to sys_user.ID.
 * - OBJECT_ID stores the related business document id.
 *
 * @property string $ID
 * @property string $WAREHOUSES_ID
 * @property string $PROCESS_ID
 * @property string $PRODUCT_ID
 * @property string|float|null $AMOUNT
 * @property string $CATEGORY
 * @property string $PROCESS_CATEGORY
 * @property string $OPERATOR
 * @property string $REMARK
 * @property string|null $DELIVERY_TIME
 * @property string|null $OBJECT_ID
 * @property string|null $EXT_JSON
 * @property string $TENANT_ID
 */
class DeliveryRecord extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'delivery_record';
}
