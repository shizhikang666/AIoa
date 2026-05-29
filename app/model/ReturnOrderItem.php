<?php

namespace app\model;

/**
 * Return order item model.
 *
 * Java entity: vip.xiaonuo.biz.modular.returnorderitem.entity.ReturnOrderItem
 * Table: return_order_item
 *
 * Relation notes:
 * - RETURN_ORDER_ID points to return_order.ID.
 * - PROJECT_PRODUCT_ITEM_ID points to biz_sale_project_product_item.ID.
 *
 * @property string $ID
 * @property string $RETURN_ORDER_ID
 * @property string $PROJECT_PRODUCT_ITEM_ID
 * @property string|float|null $AMOUNT
 * @property string $TENANT_ID
 */
class ReturnOrderItem extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'return_order_item';
}
