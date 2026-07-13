<?php

namespace app\model;

/**
 * Purchase order item model.
 *
 * Java entity: vip.xiaonuo.biz.modular.bizpurchaseorder.entity.BizPurchaseOrderItem
 * Table: biz_purchase_order_item
 *
 * Relation notes:
 * - PURCHASE_ORDER_ID points to biz_purchase_order.ID.
 * - PRODUCT_ID points to the product table.
 *
 * @property string $ID 主键
 * @property string $PURCHASE_ORDER_ID 主单编号
 * @property string $STORAGE_STATUS 入库状态
 * @property string $PRODUCT_ID 产品编号
 * @property string|float $AMOUNT 金额
 * @property int $NUMBER 采购数量
 * @property string|float $UNIT_AMOUNT 单价
 * @property string|float $DISCOUNT_RATE 优惠率
 * @property string|null $REMARK 备注
 * @property string|null $EXT_JSON 扩展信息,存放产品json
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 * @property string $TENANT_ID 租户id
 * @property int $VERSION 版本号乐观锁标记
 * @property string|float $FREIGHT_SHARE_AMOUNT 运费分摊金额
 * @property string|float $UNIT_COST_WITH_FREIGHT 含运费单位成本
 */
class BizPurchaseOrderItem extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_purchase_order_item';
}

