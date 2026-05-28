<?php

namespace app\model;

/**
 * Team project task user relation model.
 *
 * Java entity: vip.xiaonuo.biz.modular.bizteamprojecttaskuser.entity.BizTeamProjectTaskUser
 * Table: biz_team_project_task_user
 *
 * Relation notes:
 * - TEAM_PROJECT_ID points to biz_team_project.ID.
 * - TEAM_PROJECT_TASK_ID points to biz_team_project_task.ID.
 * - USER_ID points to sys_user.ID.
 * - ROLE_TYPE stores task role marker.
 *
 * @property string $ID
 * @property string $USER_ID
 * @property string $TEAM_PROJECT_ID
 * @property string|null $TEAM_PROJECT_TASK_ID
 * @property string|null $ROLE_TYPE
 * @property string|null $EXT_JSON
 * @property string $TENANT_ID
 */
class BizTeamProjectTaskUser extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_team_project_task_user';
}
