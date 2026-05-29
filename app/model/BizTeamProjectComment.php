<?php

namespace app\model;

/**
 * Team project comment model.
 *
 * Java entity: vip.xiaonuo.biz.modular.bizteamprojectcomment.entity.BizTeamProjectComment
 * Table: biz_team_project_comment
 *
 * Relation notes:
 * - TEAM_PROJECT_ID points to biz_team_project.ID.
 * - CREATE_USER points to sys_user.ID through audit columns.
 * - Replies live in biz_team_project_comment_reply.
 *
 * @property string $ID
 * @property string $TEAM_PROJECT_ID
 * @property string|null $STATUS
 * @property string|null $STATUS_COLOR
 * @property string|null $CONTENT_TEXT
 * @property string|null $EXT_JSON
 * @property string $TENANT_ID
 */
class BizTeamProjectComment extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_team_project_comment';
}
