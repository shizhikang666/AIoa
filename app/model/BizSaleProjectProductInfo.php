<?php

namespace app\model;

/**
 * Sales project product package/version info model.
 *
 * Java entity: vip.xiaonuo.biz.modular.saleprojectproductinfo.entity.BizSaleProjectProductInfo
 * Table: biz_sale_project_product_info
 *
 * Relation notes:
 * - PRODUCT_ID and TARGET_ID are product identifiers.
 * - CONTENT_TEXT and version fields store package/version details.
 *
 * @property string $ID
 * @property string $PRODUCT_ID
 * @property string $TARGET_ID
 * @property string|null $CONTENT_TEXT
 * @property string|null $REMARK
 * @property string|null $ALIAS
 * @property string|null $VERSION_TYPE
 * @property string|null $VERSION_REMARK
 * @property string|null $ABBREVIATION
 * @property string|null $HARDWARE
 * @property string|null $OLD_CODE
 * @property string|null $EXT_JSON
 * @property string $TENANT_ID
 */
class BizSaleProjectProductInfo extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_sale_project_product_info';
}
