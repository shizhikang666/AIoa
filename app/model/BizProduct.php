<?php

namespace app\model;

/**
 * Product master model.
 *
 * Java entity: vip.xiaonuo.biz.modular.bizproduct.entity.BizProduct
 * Table: biz_product
 *
 * Relation notes:
 * - ORG points to sys_org.ID.
 * - Used by sale project product items, warehouse delivery records, and product relations.
 * - status is lower-case in SQL and must remain unchanged.
 *
 * @property string $ID
 * @property string $PRODUCT_NAME
 * @property string $PRODUCT_CATEGORY
 * @property string|float|null $SAFETY_STOCK
 * @property string|float|null $PURCHASE_PRICE
 * @property string|float|null $SALE_PRICE
 * @property string|float|null $MIN_PRICE
 * @property string $CATEGORY
 * @property string|null $SPECS
 * @property string|null $ORG
 * @property string|null $COVER_IMAGE
 * @property string|null $RECONCILIATION_TYPE
 * @property string|float|null $RECONCILIATION_AMOUNT
 * @property string $status
 * @property string $TENANT_ID
 */
class BizProduct extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_product';
}
