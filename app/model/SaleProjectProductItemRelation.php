<?php

namespace app\model;

/**
 * Sales project product item relation model.
 *
 * Java entity: vip.xiaonuo.biz.modular.saleprojectproductitemrelation.entity.SaleProjectProductItemRelation
 * Table: sale_project_product_item_relation
 *
 * Relation notes:
 * - OBJECT_ID points to biz_sale_project_product_item.ID.
 * - TARGET_ID points to a product id.
 *
 * @property string $ID
 * @property string|null $OBJECT_ID
 * @property string|null $TARGET_ID
 * @property string $MARK
 * @property string|float|null $NUMBER
 * @property string|null $REMARK
 * @property string|null $EXT_JSON
 * @property string $TENANT_ID
 */
class SaleProjectProductItemRelation extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'sale_project_product_item_relation';
}
