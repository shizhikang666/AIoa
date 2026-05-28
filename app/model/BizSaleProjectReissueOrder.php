<?php

namespace app\model;

/**
 * Sales project reissue order model.
 *
 * Java entity: vip.xiaonuo.biz.modular.saleprojectreissueorder.entity.BizSaleProjectReissueOrder
 * Table: biz_sale_project_reissue_order
 *
 * Relation notes:
 * - PROJECT_ID points to biz_sale_project.ID.
 * - PROCESS_ID stores the related workflow/process id.
 *
 * @property string $ID
 * @property string $PROJECT_ID
 * @property string|float|null $AMOUNT
 * @property string $PROCESS_ID
 * @property string|null $REMARK
 * @property string $TENANT_ID
 */
class BizSaleProjectReissueOrder extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_sale_project_reissue_order';
}
