<?php

namespace app\model;

/**
 * Team project task comment/log model.
 *
 * Java entity: vip.xiaonuo.biz.modular.bizteamprojecttaskcomment.entity.BizTeamProjectTaskComment
 * Table: biz_team_project_task_comment
 *
 * Relation notes:
 * - TEAM_PROJECT_ID points to biz_team_project.ID.
 * - TEAM_PROJECT_TASK_ID points to biz_team_project_task.ID.
 * - CATEGORY marks whether the row is a log or comment.
 *
 * @property string $ID
 * @property string|null $TEAM_PROJECT_TASK_ID
 * @property string $TEAM_PROJECT_ID
 * @property string|null $CONTENT_TEXT
 * @property string|null $CATEGORY
 * @property string|null $EXT_JSON
 * @property string $TENANT_ID
 */
class BizTeamProjectTaskComment extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_team_project_task_comment';
}
