<?php

namespace app\model;

/**
 * Sales project field change log model.
 *
 * Java entity: vip.xiaonuo.biz.modular.salesprojectfieldchangelog.entity.SalesProjectFieldChangeLog
 * Table: sales_project_field_change_log
 *
 * Relation notes:
 * - OBJECT_ID points to biz_sale_project.ID.
 * - Values are stored as strings for audit/history display.
 *
 * @property string $ID
 * @property string $OBJECT_ID
 * @property string $FIELD_NAME
 * @property string|null $FIELD_LABEL
 * @property string|null $BEFORE_VALUE
 * @property string|null $AFTER_VALUE
 * @property string|null $CHANGE_REASON
 * @property string $TENANT_ID
 */
class SalesProjectFieldChangeLog extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'sales_project_field_change_log';
}
