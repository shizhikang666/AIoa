<?php

namespace app\model;

/**
 * Sales project product item model.
 *
 * Java entity: vip.xiaonuo.biz.modular.saleprojectproductitem.entity.BizSaleProjectProductItem
 * Table: biz_sale_project_product_item
 *
 * Relation notes:
 * - PROJECT_ID points to biz_sale_project.ID.
 * - PRODUCT_ID points to the product table.
 *
 * @property string $ID 主键
 * @property string $PROJECT_ID 项目编号
 * @property string $PRODUCT_ID 产品编号
 * @property string $CATEGORY 类别
 * @property string $STATE 状态
 * @property string|float $NUMBER 数量
 * @property string|float $DELIVERY 已发货数量
 * @property string|float $UNIT_PRICE 单价
 * @property string|float $DISCOUNT_RATE 优惠率
 * @property string|float $PRICE 金额
 * @property string|null $REMARK 备注
 * @property string|null $EXT_JSON 扩展信息,存放产品json
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 * @property string $TENANT_ID 租户id
 * @property int $VERSION 版本号乐观锁标记
 * @property string $PROJECT_REISSUE_ORDER_ID 补发单编号
 * @property string|null $MARK 标记
 */
class BizSaleProjectProductItem extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_sale_project_product_item';
}

