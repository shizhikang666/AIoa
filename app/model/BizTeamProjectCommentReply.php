<?php

namespace app\model;

/**
 * Team project comment reply model.
 *
 * Java entity: vip.xiaonuo.biz.modular.bizteamprojectcommentreply.entity.BizTeamProjectCommentReply
 * Table: biz_team_project_comment_reply
 *
 * Relation notes:
 * - TARGET_ID points to biz_team_project_comment.ID.
 * - CREATE_USER points to sys_user.ID through audit columns.
 *
 * @property string $ID
 * @property string $TARGET_ID
 * @property string|null $CONTENT_TEXT
 * @property string|null $EXT_JSON
 * @property string $TENANT_ID
 */
class BizTeamProjectCommentReply extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_team_project_comment_reply';
}
