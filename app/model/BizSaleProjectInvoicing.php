<?php

namespace app\model;

/**
 * Sales project tax invoicing model.
 *
 * Java entity: vip.xiaonuo.biz.modular.saleprojectinvoicing.entity.BizSaleProjectInvoicing
 * Table: biz_sale_project_invoicing
 *
 * Relation notes:
 * - PROJECT_ID points to biz_sale_project.ID.
 * - PROCESS_ID stores the related workflow/process id.
 *
 * @property string $ID
 * @property string $PROJECT_ID
 * @property string|float|null $AMOUNT
 * @property string|null $INVOICING_STATE
 * @property string|null $INVOICING_CATEGORY
 * @property string|null $PROCESS_ID
 * @property string|null $REMARK
 * @property string|null $COMPANY_NAME
 * @property string|null $CUSTOMER_COMPANY
 * @property string|null $UNIT
 * @property string|null $PHONE
 * @property string|null $TAXPAYER
 * @property string|null $CORPORATE_ACCOUNT
 * @property string|null $BANK_NAME
 * @property string|null $UNIT_ADDRESS
 * @property string|null $UNIT_PHONE
 * @property string|null $HARVEST_ADDRESS
 * @property string|null $EXT_JSON
 * @property string $TENANT_ID
 */
class BizSaleProjectInvoicing extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_sale_project_invoicing';
}
