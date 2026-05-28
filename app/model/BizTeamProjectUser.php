<?php

namespace app\model;

/**
 * Team project user relation model.
 *
 * Java entity: vip.xiaonuo.biz.modular.bizteamprojectuser.entity.BizTeamProjectUser
 * Table: biz_team_project_user
 *
 * Relation notes:
 * - TEAM_PROJECT_ID points to biz_team_project.ID.
 * - USER_ID points to sys_user.ID.
 * - ROLE_TYPE stores team role marker; permission interpretation is business-layer work.
 *
 * @property string $ID
 * @property string $TEAM_PROJECT_ID
 * @property string|null $USER_ID
 * @property string|null $ROLE_TYPE
 * @property string $TENANT_ID
 */
class BizTeamProjectUser extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_team_project_user';
}
