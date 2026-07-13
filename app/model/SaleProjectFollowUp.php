<?php

namespace app\model;

/**
 * Sales project follow-up model.
 *
 * Java entity: vip.xiaonuo.biz.modular.followup.entity.SaleProjectFollowUp
 * Table: sale_project_follow_up
 *
 * Relation notes:
 * - PROJECT_ID points to biz_sale_project.ID.
 * - CREATE_USER points to sys_user.ID through audit columns.
 *
 * @property string $ID
 * @property string $PROJECT_ID
 * @property string $FOLLOW_UP_TIME
 * @property string|null $CATEGORY
 * @property string $CONTENT
 * @property string|null $EXT_JSON
 * @property string $TENANT_ID
 */
class SaleProjectFollowUp extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'sale_project_follow_up';
}
