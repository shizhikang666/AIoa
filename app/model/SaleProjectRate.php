<?php

namespace app\model;

/**
 * Sales project rating model.
 *
 * Java entity: vip.xiaonuo.biz.modular.projectrate.entity.SaleProjectRate
 * Table: sale_project_rate
 *
 * Relation notes:
 * - PROJECT_ID points to biz_sale_project.ID.
 * - CREATE_USER points to sys_user.ID through audit columns.
 *
 * @property string $ID
 * @property string $PROJECT_ID
 * @property string|float|null $RATE_AMOUNT
 * @property string $CONTENT
 * @property string $SUBJECT
 * @property string|null $EXT_JSON
 * @property string $TENANT_ID
 */
class SaleProjectRate extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'sale_project_rate';
}
