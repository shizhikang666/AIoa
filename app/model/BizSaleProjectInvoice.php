<?php

namespace app\model;

/**
 * Sales project delivery invoice master model.
 *
 * Java entity: vip.xiaonuo.biz.modular.saleprojectinvoice.entity.BizSaleProjectInvoice
 * Table: biz_sale_project_invoice
 *
 * Relation notes:
 * - PROJECT_ID points to biz_sale_project.ID.
 * - PROCESS_ID stores the related workflow/process id.
 * - Child rows live in biz_sale_project_invoice_item.
 *
 * @property string $ID
 * @property string $PROJECT_ID
 * @property string $PROCESS_ID
 * @property string $CONSIGNEE
 * @property string $LOGISTICS_CATEGORY
 * @property string $PHONE
 * @property string $LOGISTICS_ID
 * @property string|float|null $FREIGHT
 * @property string|null $FREIGHT_TIME
 * @property string $FREIGHT_CATEGORY
 * @property string $UNIT
 * @property string $ADDRESS
 * @property string|null $REMARK
 * @property string|null $EXT_JSON
 * @property string $OPERATOR
 * @property string $TENANT_ID
 */
class BizSaleProjectInvoice extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_sale_project_invoice';
}
