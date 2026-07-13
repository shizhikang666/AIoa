<?php

namespace app\model;

/**
 * Sales project delivery invoice item model.
 *
 * Java entity: vip.xiaonuo.biz.modular.saleprojectinvoice.entity.BizSaleProjectInvoiceItem
 * Table: biz_sale_project_invoice_item
 *
 * Relation notes:
 * - INVOICE_ID points to biz_sale_project_invoice.ID.
 * - PROJECT_PRODUCT_ITEM_ID points to biz_sale_project_product_item.ID.
 * - WAREHOUSES_ID points to warehouses.ID.
 *
 * @property string $ID
 * @property string $INVOICE_ID
 * @property string $PROJECT_PRODUCT_ITEM_ID
 * @property string $WAREHOUSES_ID
 * @property string|float|null $AMOUNT
 * @property string $REMARK
 * @property string|null $EXT_JSON
 * @property string $TENANT_ID
 */
class BizSaleProjectInvoiceItem extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_sale_project_invoice_item';
}
