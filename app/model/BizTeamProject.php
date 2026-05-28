<?php

namespace app\model;

/**
 * Team project model.
 *
 * Java entity: vip.xiaonuo.biz.modular.bizteamproject.entity.BizTeamProject
 * Table: biz_team_project
 *
 * Relation notes:
 * - USER points to sys_user.ID.
 * - ORG points to sys_org.ID.
 *
 * @property string $ID 主键
 * @property string $NAME 项目名称
 * @property string|null $DESCRIPTION 项目描述
 * @property string|null $PROJECT_STATUS 项目状态
 * @property string|null $COMPLETION_TIME 完成结束时间
 * @property string|null $USER 所属用户
 * @property string|null $ORG 所属部门
 * @property int $VERSION 版本号乐观锁标记
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 * @property string $TENANT_ID 租户id
 */
class BizTeamProject extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_team_project';
}

