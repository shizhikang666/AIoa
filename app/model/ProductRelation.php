<?php

namespace app\model;

/**
 * Product relation model.
 *
 * Java entity: vip.xiaonuo.biz.modular.relation.entity.ProductRelation
 * Java TableName: PRODUCT_RELATION
 * SQL table: product_relation
 *
 * Relation notes:
 * - OBJECT_ID and TARGET_ID are generic relation endpoints.
 * - CATEGORY defines relation type.
 * - Use SQL physical table name because the updated dump contains lower-case product_relation.
 *
 * @property string $ID
 * @property string|null $OBJECT_ID
 * @property string|null $TARGET_ID
 * @property string|null $CATEGORY
 * @property string|null $EXT_JSON
 * @property string $TENANT_ID
 */
class ProductRelation extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'product_relation';
}
