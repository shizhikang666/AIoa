<?php

namespace app\model;

/**
 * Team project task category model.
 *
 * Java entity: vip.xiaonuo.biz.modular.bizteamprojecttaskcategory.entity.BizTeamProjectTaskCategory
 * Table: biz_team_project_task_category
 *
 * Relation notes:
 * - TEAM_PROJECT_ID points to biz_team_project.ID.
 * - SORT_CODE controls category display order.
 *
 * @property string $ID
 * @property string $TEAM_PROJECT_ID
 * @property string|null $TITLE
 * @property string|null $EXT_JSON
 * @property int|null $SORT_CODE
 * @property string $TENANT_ID
 */
class BizTeamProjectTaskCategory extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_team_project_task_category';
}
